<?php
// gestor/actions/atualizar-usuario.php
session_start();
require_once '../../../conexao.php'; // Ajuste o caminho conforme sua estrutura
require_once 'validacoes.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_perfil'] !== 'gestor') {
    header("Location: ../../../auth/login.php");
    exit();
}

// Mesmo caminho usado em salvar-usuario.php — AJUSTE JUNTO se mudar lá
define('PASTA_PERFIS', dirname(__DIR__, 4) . '/perfis');
define('PASTA_UPLOADS', dirname(__DIR__, 3) . '/uploads');

$pastasFotoPorPerfil = [
    'medico'        => 'medicos',
    'recepcionista' => 'recepcionista',
    'gestor'        => 'gestor',
];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../cadastrar-usuario.php");
    exit();
}

$id     = (int)($_POST['id'] ?? 0);
$perfil = $_POST['perfil'] ?? '';

$perfisValidos = ['medico', 'recepcionista', 'gestor', 'paciente'];
if ($id <= 0 || !in_array($perfil, $perfisValidos, true)) {
    header("Location: ../cadastrar-usuario.php?erro=" . urlencode('Usuário inválido para edição.'));
    exit();
}

$tabelas = ['medico' => 'medico', 'recepcionista' => 'recepcionistas', 'gestor' => 'gestores', 'paciente' => 'paciente'];

// Confirma que o registro realmente existe antes de seguir
try {
    $stmtAtual = $pdo->prepare("SELECT * FROM {$tabelas[$perfil]} WHERE id = ? LIMIT 1");
    $stmtAtual->execute([$id]);
    $usuarioAtual = $stmtAtual->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $usuarioAtual = false;
}

if (!$usuarioAtual) {
    header("Location: ../editar-usuario.php?id=$id&perfil=$perfil&erro=" . urlencode('Usuário não encontrado.'));
    exit();
}

$nome         = trim($_POST['nome'] ?? '');
$cpf          = trim($_POST['cpf'] ?? '');
$dataInicio   = $_POST['data_inicio'] ?? null;
$email        = strtolower(trim($_POST['email'] ?? ''));
$telefone     = trim($_POST['telefone'] ?? '');
$celular      = trim($_POST['celular'] ?? '');
$crm          = trim($_POST['crm'] ?? '');
$especialidadesStr = $_POST['especialidades'] ?? '';

$tipoDoc       = trim($_POST['tipo_doc'] ?? 'CPF');
$dataNasc      = $_POST['data_nasc'] ?? '';
$menorIdade    = ($_POST['menor_idade'] ?? 'N') === 'Y' ? 'Y' : 'N';
$nomeResp      = trim($_POST['nome_resp'] ?? '');
$tipoDocResp   = trim($_POST['tipo_doc_resp'] ?? 'CPF');
$numDocResp    = trim($_POST['num_doc_resp'] ?? '');
$dataNascResp  = $_POST['data_nasc_resp'] ?? '';
$telefoneResp  = trim($_POST['telefone_resp'] ?? '');
$celularResp   = trim($_POST['celular_resp'] ?? '');
$emailResp     = strtolower(trim($_POST['email_resp'] ?? ''));

$tiposDocValidos = ['CPF', 'RG', 'Passaporte', 'Certidao', 'Outro'];
if (!in_array($tipoDoc, $tiposDocValidos, true)) {
    $tipoDoc = 'CPF';
}
if (!in_array($tipoDocResp, $tiposDocValidos, true)) {
    $tipoDocResp = 'CPF';
}
if ($perfil !== 'paciente') {
    $tipoDoc = 'CPF';
}

$voltarComErro = function (string $mensagem) use ($id, $perfil) {
    header("Location: ../editar-usuario.php?id=$id&perfil=$perfil&erro=" . urlencode($mensagem));
    exit();
};

if (empty($nome) || empty($email) || empty($cpf)) {
    $voltarComErro('Preencha todos os campos obrigatórios.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $voltarComErro('E-mail inválido.');
}

if ($tipoDoc === 'CPF' && !validarCpf($cpf)) {
    $voltarComErro('CPF inválido.');
}

if ($perfil === 'paciente') {
    if (empty($dataNasc)) {
        $voltarComErro('Informe a data de nascimento do paciente.');
    }
    if ($menorIdade === 'Y' && empty($nomeResp)) {
        $voltarComErro('Informe o nome do responsável (paciente menor de idade).');
    }
    if ($menorIdade === 'Y' && $tipoDocResp === 'CPF' && $numDocResp !== '' && !validarCpf($numDocResp)) {
        $voltarComErro('CPF do responsável inválido.');
    }
}

$idsEsp = array_filter(array_map('intval', explode(',', $especialidadesStr)));
if ($perfil === 'medico') {
    if (empty($crm)) {
        $voltarComErro('Informe o CRM do médico.');
    }
    if (empty($idsEsp)) {
        $voltarComErro('Selecione ao menos uma especialidade.');
    }
}

// ================= DUPLICIDADE (ignorando o próprio registro) =================
$colunaDoc = $perfil === 'paciente' ? 'num_doc' : 'cpf';
try {
    foreach ($tabelas as $perfilTab => $tabela) {
        $colDocTab = $perfilTab === 'paciente' ? 'num_doc' : 'cpf';
        $sql = "SELECT id FROM `$tabela` WHERE (email = :email OR `$colDocTab` = :doc)";
        $params = ['email' => $email, 'doc' => $cpf];

        if ($perfilTab === $perfil) {
            $sql .= " AND id != :id";
            $params['id'] = $id;
        }
        $sql .= " LIMIT 1";

        $stmtDup = $pdo->prepare($sql);
        $stmtDup->execute($params);
        if ($stmtDup->fetch()) {
            $voltarComErro('Já existe outro usuário com este e-mail ou documento.');
        }
    }
} catch (PDOException $e) {
    $voltarComErro('Erro ao verificar duplicidade.');
}

// ================= FOTO (opcional na edição, mesmo pro médico) =================
$temFotoNova = $perfil !== 'paciente'
    && isset($_FILES['foto'])
    && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE;

$fotoRelativaNova = null;
$caminhoFotoAbsNova = null;
$caminhoFotoAntigaAbs = null;

if ($temFotoNova) {
    if ($_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
        $voltarComErro('Erro ao enviar a foto. Tente novamente.');
    }

    $tamanhoMaximo = 5 * 1024 * 1024; // 5 MB
    if ($_FILES['foto']['size'] > $tamanhoMaximo) {
        $voltarComErro('A foto deve ter no máximo 5MB.');
    }

    $extensaoFoto = extensaoFotoValida($_FILES['foto']);
    if ($extensaoFoto === null) {
        $voltarComErro('Formato de foto inválido. Use JPG, PNG ou WEBP.');
    }

    $subpasta     = $pastasFotoPorPerfil[$perfil];
    $pastaDestino = $perfil === 'medico'
        ? PASTA_UPLOADS . '/' . $subpasta
        : PASTA_PERFIS . '/' . $subpasta;

    if (!is_dir($pastaDestino) && !mkdir($pastaDestino, 0755, true)) {
        $voltarComErro('Não foi possível preparar a pasta de fotos.');
    }

    // Reaproveita o UID já existente no nome do arquivo, com sufixo de tempo pra não colidir com o antigo
    $nomeArquivo        = $usuarioAtual['uid'] . '-' . time() . '.' . $extensaoFoto;
    $caminhoFotoAbsNova = $pastaDestino . '/' . $nomeArquivo;

    if (!move_uploaded_file($_FILES['foto']['tmp_name'], $caminhoFotoAbsNova)) {
        $voltarComErro('Não foi possível salvar a foto.');
    }

    $fotoRelativaNova = $subpasta . '/' . $nomeArquivo;

    if (!empty($usuarioAtual['foto'])) {
        $fotoAntiga = $usuarioAtual['foto'];
        if (preg_match('#^medicos/[A-Za-z0-9_-]+\.(jpg|jpeg|png|webp)$#i', $fotoAntiga)) {
            $caminhoFotoAntigaAbs = PASTA_UPLOADS . '/' . $fotoAntiga;
        } elseif (preg_match('#^(medico|recepcionista|gestor)/[A-Za-z0-9_-]+\.(jpg|jpeg|png|webp)$#i', $fotoAntiga)) {
            $caminhoFotoAntigaAbs = PASTA_PERFIS . '/' . $fotoAntiga;
        }
    }
}

try {
    $pdo->beginTransaction();

    if ($perfil === 'medico') {
        $stmt = $pdo->prepare("
            UPDATE medico
            SET nome = ?, cpf = ?, email = ?, telefone = ?, celular = ?, crm = ?, data_inicio = ?" .
            ($temFotoNova ? ", foto = ?" : "") . "
            WHERE id = ?
        ");
        $params = [$nome, $cpf, $email, $telefone, $celular, $crm, $dataInicio ?: null];
        if ($temFotoNova) {
            $params[] = $fotoRelativaNova;
        }
        $params[] = $id;
        $stmt->execute($params);

        // Reaplica as especialidades selecionadas do zero
        $pdo->prepare("DELETE FROM medico_especialidades WHERE id_medico = ?")->execute([$id]);
        if (!empty($idsEsp)) {
            $stmtEsp = $pdo->prepare("INSERT INTO medico_especialidades (id_medico, id_especialidade) VALUES (?, ?)");
            foreach ($idsEsp as $idEsp) {
                $stmtEsp->execute([$id, $idEsp]);
            }
        }

    } elseif ($perfil === 'recepcionista') {
        $stmt = $pdo->prepare("
            UPDATE recepcionistas
            SET nome = ?, cpf = ?, email = ?, telefone = ?, celular = ?, data_inicio = ?" .
            ($temFotoNova ? ", foto = ?" : "") . "
            WHERE id = ?
        ");
        $params = [$nome, $cpf, $email, $telefone, $celular, $dataInicio ?: null];
        if ($temFotoNova) {
            $params[] = $fotoRelativaNova;
        }
        $params[] = $id;
        $stmt->execute($params);

    } elseif ($perfil === 'gestor') {
        // gestores não tem coluna data_inicio no banco — não inclua aqui
        $stmt = $pdo->prepare("
            UPDATE gestores
            SET nome = ?, cpf = ?, email = ?, telefone = ?, celular = ?" .
            ($temFotoNova ? ", foto = ?" : "") . "
            WHERE id = ?
        ");
        $params = [$nome, $cpf, $email, $telefone !== '' ? $telefone : null, $celular !== '' ? $celular : null];
        if ($temFotoNova) {
            $params[] = $fotoRelativaNova;
        }
        $params[] = $id;
        $stmt->execute($params);

    } elseif ($perfil === 'paciente') {
        $stmt = $pdo->prepare("
            UPDATE paciente
            SET nome = ?, menor_idade = ?, tipo_documento = ?, num_doc = ?, data_nasc = ?, telefone = ?, celular = ?, email = ?,
                nome_resp = ?, data_nasc_resp = ?, tipo_documento_resp = ?, num_doc_resp = ?, telefone_resp = ?, celular_resp = ?, email_resp = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $nome,
            $menorIdade,
            $tipoDoc,
            $cpf,
            $dataNasc,
            $telefone,
            $celular,
            $email,
            $menorIdade === 'Y' ? $nomeResp : null,
            ($menorIdade === 'Y' && $dataNascResp !== '') ? $dataNascResp : null,
            $menorIdade === 'Y' ? $tipoDocResp : null,
            $menorIdade === 'Y' ? ($numDocResp !== '' ? $numDocResp : null) : null,
            $menorIdade === 'Y' ? ($telefoneResp !== '' ? $telefoneResp : null) : null,
            $menorIdade === 'Y' ? ($celularResp !== '' ? $celularResp : null) : null,
            $menorIdade === 'Y' ? ($emailResp !== '' ? $emailResp : null) : null,
            $id,
        ]);
    }

    $pdo->commit();

    // Só apaga a foto antiga depois que o UPDATE foi confirmado com sucesso
    if ($caminhoFotoAntigaAbs !== null && file_exists($caminhoFotoAntigaAbs)) {
        @unlink($caminhoFotoAntigaAbs);
    }

    header("Location: ../cadastrar-usuario.php?status=sucesso&acao=edicao");
    exit();

} catch (PDOException $e) {
    $pdo->rollBack();

    // Desfaz a foto nova já salva em disco, já que a edição não foi concluída
    if ($caminhoFotoAbsNova !== null && file_exists($caminhoFotoAbsNova)) {
        @unlink($caminhoFotoAbsNova);
    }

    $voltarComErro('Não foi possível salvar as alterações. Tente novamente.');
}