<?php
// gestor/actions/salvar-usuario.php
session_start();
require_once '../../../conexao.php'; // Ajuste o caminho conforme sua estrutura
require_once 'validacoes.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_perfil'] !== 'gestor') {
    header("Location: ../../../auth/login.php");
    exit();
}

// Fotos antigas de recepcionistas e gestores continuam na pasta legada.
define('PASTA_PERFIS', dirname(__DIR__, 4) . '/perfis');
define('PASTA_UPLOADS', dirname(__DIR__, 3) . '/uploads');

// Fotos novas de médicos vão para uploads/medicos. Os demais perfis mantêm
// o local legado para evitar alterar o comportamento fora do cadastro médico.
$pastasFotoPorPerfil = [
    'medico'        => 'medicos',
    'recepcionista' => 'recepcionista',
    'gestor'        => 'gestor',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $perfil            = $_POST['perfil'] ?? '';
    $nome              = trim($_POST['nome'] ?? '');
    $cpf               = trim($_POST['cpf'] ?? ''); // usado como "número do documento" pra qualquer perfil
    $dataInicio        = $_POST['data_inicio'] ?? date('Y-m-d');
    $email             = strtolower(trim($_POST['email'] ?? ''));
    $telefone          = trim($_POST['telefone'] ?? '');
    $celular           = trim($_POST['celular'] ?? '');
    $crm               = trim($_POST['crm'] ?? '');
    $especialidadesStr = $_POST['especialidades'] ?? '';

    // Campos específicos de paciente
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

    // Perfis aceitos (evita perfil inválido/injetado no <select>)
    $perfisValidos = ['medico', 'recepcionista', 'gestor', 'paciente'];
    if (!in_array($perfil, $perfisValidos, true)) {
        header("Location: ../cadastrar-usuario.php?erro=" . urlencode('Perfil inválido.'));
        exit();
    }

    // Tipos de documento aceitos (evita valor inválido/injetado no <select>)
    $tiposDocValidos = ['CPF', 'RG', 'Passaporte', 'Certidao', 'Outro'];
    if (!in_array($tipoDoc, $tiposDocValidos, true)) {
        $tipoDoc = 'CPF';
    }
    if (!in_array($tipoDocResp, $tiposDocValidos, true)) {
        $tipoDocResp = 'CPF';
    }
    // Médico, recepcionista e gestor sempre usam CPF
    if ($perfil !== 'paciente') {
        $tipoDoc = 'CPF';
    }

    // Campos obrigatórios em qualquer perfil
    if (empty($nome) || empty($email) || empty($cpf)) {
        header("Location: ../cadastrar-usuario.php?erro=" . urlencode('Preencha todos os campos obrigatórios.') . "&perfil=$perfil");
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: ../cadastrar-usuario.php?erro=" . urlencode('E-mail inválido.') . "&perfil=$perfil");
        exit();
    }

    // Validação de CPF só se roda quando o documento é de fato um CPF
    // (médico/recepcionista/gestor sempre; paciente só quando tipo_doc = 'CPF')
    if ($tipoDoc === 'CPF' && !validarCpf($cpf)) {
        header("Location: ../cadastrar-usuario.php?erro=" . urlencode('CPF inválido.') . "&perfil=$perfil");
        exit();
    }

    // Validações específicas do paciente
    if ($perfil === 'paciente') {
        if (empty($dataNasc)) {
            header("Location: ../cadastrar-usuario.php?erro=" . urlencode('Informe a data de nascimento do paciente.') . "&perfil=paciente");
            exit();
        }
        if ($menorIdade === 'Y' && empty($nomeResp)) {
            header("Location: ../cadastrar-usuario.php?erro=" . urlencode('Informe o nome do responsável (paciente menor de idade).') . "&perfil=paciente");
            exit();
        }
        // Valida CPF do responsável só se o documento dele também for CPF
        if ($menorIdade === 'Y' && $tipoDocResp === 'CPF' && $numDocResp !== '' && !validarCpf($numDocResp)) {
            header("Location: ../cadastrar-usuario.php?erro=" . urlencode('CPF do responsável inválido.') . "&perfil=paciente");
            exit();
        }
    } else {
        // Data de início só existe pra médico/recepcionista/gestor
        if (empty($dataInicio)) {
            $dataInicio = date('Y-m-d');
        }
    }

    // Campos extras obrigatórios só pro médico
    $idsEsp = array_filter(array_map('intval', explode(',', $especialidadesStr)));
    if ($perfil === 'medico') {
        if (empty($crm)) {
            header("Location: ../cadastrar-usuario.php?erro=" . urlencode('Informe o CRM do médico.') . "&perfil=medico");
            exit();
        }
        if (empty($idsEsp)) {
            header("Location: ../cadastrar-usuario.php?erro=" . urlencode('Selecione ao menos uma especialidade.') . "&perfil=medico");
            exit();
        }
    }

    // ================= VALIDAÇÃO DA FOTO =================
    // Obrigatória só pro médico. Opcional pra recepcionista e gestor. Paciente não usa foto aqui.
    $temFoto = $perfil !== 'paciente'
        && isset($_FILES['foto'])
        && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE;

    if ($perfil === 'medico' && !$temFoto) {
        header("Location: ../cadastrar-usuario.php?erro=" . urlencode('Envie a foto do médico.') . "&perfil=medico");
        exit();
    }

    $extensaoFoto = null;

    if ($temFoto) {
        if ($_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
            header("Location: ../cadastrar-usuario.php?erro=" . urlencode('Erro ao enviar a foto. Tente novamente.') . "&perfil=$perfil");
            exit();
        }

        $tamanhoMaximo = 5 * 1024 * 1024; // 5 MB
        if ($_FILES['foto']['size'] > $tamanhoMaximo) {
            header("Location: ../cadastrar-usuario.php?erro=" . urlencode('A foto deve ter no máximo 5MB.') . "&perfil=$perfil");
            exit();
        }

        $extensaoFoto = extensaoFotoValida($_FILES['foto']);
        if ($extensaoFoto === null) {
            header("Location: ../cadastrar-usuario.php?erro=" . urlencode('Formato de foto inválido. Use JPG, PNG ou WEBP.') . "&perfil=$perfil");
            exit();
        }
    }

    // Bloqueia e-mail/documento já usados
    // Obs: a checagem de duplicidade de documento compara só o número. Se dois pacientes
    // tiverem documentos de tipos diferentes mas com o mesmo número por coincidência,
    // isso ainda vai barrar o segundo cadastro — cenário raro, mas vale saber.
    try {
        $stmtDup = $pdo->prepare("
            SELECT email FROM medico WHERE email = :email OR cpf = :doc
            UNION ALL
            SELECT email FROM recepcionistas WHERE email = :email OR cpf = :doc
            UNION ALL
            SELECT email FROM gestores WHERE email = :email OR cpf = :doc
            UNION ALL
            SELECT email FROM paciente WHERE email = :email OR num_doc = :doc
            LIMIT 1
        ");
        $stmtDup->execute(['email' => $email, 'doc' => $cpf]);
        if ($stmtDup->fetch()) {
            header("Location: ../cadastrar-usuario.php?erro=" . urlencode('Já existe um usuário com este e-mail ou documento.') . "&perfil=$perfil");
            exit();
        }
    } catch (PDOException $e) {
        header("Location: ../cadastrar-usuario.php?erro=" . urlencode('Erro ao verificar duplicidade.') . "&perfil=$perfil");
        exit();
    }

    // Gera o UID sequencial no padrão MED001/REC001/GEST001/PAC001
    $prefixos  = ['medico' => 'MED', 'recepcionista' => 'REC', 'gestor' => 'GEST', 'paciente' => 'PAC'];
    $tabelas   = ['medico' => 'medico', 'recepcionista' => 'recepcionistas', 'gestor' => 'gestores', 'paciente' => 'paciente'];
    $proximoId = ((int)$pdo->query("SELECT id FROM {$tabelas[$perfil]} ORDER BY id DESC LIMIT 1")->fetchColumn()) + 1;
    $uid = $prefixos[$perfil] . str_pad($proximoId, 3, '0', STR_PAD_LEFT);

    // ================= SALVA A FOTO NO DISCO (usa o UID como nome do arquivo) =================
    // Só move o arquivo de verdade depois de passar por todas as validações acima,
    // pra não deixar arquivo órfão na pasta se o cadastro for barrado antes disso.
    $fotoRelativa   = null; // caminho salvo no banco (relativo à pasta "perfis")
    $caminhoFotoAbs = null; // caminho absoluto, usado só se precisar desfazer em caso de erro no banco

    if ($temFoto) {
        $subpasta     = $pastasFotoPorPerfil[$perfil];
        $pastaDestino = $perfil === 'medico'
            ? PASTA_UPLOADS . '/' . $subpasta
            : PASTA_PERFIS . '/' . $subpasta;

        if (!is_dir($pastaDestino) && !mkdir($pastaDestino, 0755, true)) {
            header("Location: ../cadastrar-usuario.php?erro=" . urlencode('Não foi possível preparar a pasta de fotos.') . "&perfil=$perfil");
            exit();
        }

        $nomeArquivo    = $uid . '.' . $extensaoFoto;
        $caminhoFotoAbs = $pastaDestino . '/' . $nomeArquivo;

        if (!move_uploaded_file($_FILES['foto']['tmp_name'], $caminhoFotoAbs)) {
            header("Location: ../cadastrar-usuario.php?erro=" . urlencode('Não foi possível salvar a foto.') . "&perfil=$perfil");
            exit();
        }

        $fotoRelativa = $subpasta . '/' . $nomeArquivo;
    }

    // Senha temporária padrão — o usuário troca no primeiro acesso (primeiro_acesso = 1)
    $senhaTemporaria = password_hash('123456', PASSWORD_DEFAULT);

    try {
        $pdo->beginTransaction();

        if ($perfil === 'medico') {
            $stmt = $pdo->prepare("
                INSERT INTO medico (nome, cpf, email, telefone, celular, crm, data_inicio, uid, senha, foto, primeiro_acesso)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([$nome, $cpf, $email, $telefone, $celular, $crm, $dataInicio, $uid, $senhaTemporaria, $fotoRelativa]);
            $idMedico = $pdo->lastInsertId();

            if (!empty($idsEsp)) {
                $stmtEsp = $pdo->prepare("INSERT INTO medico_especialidades (id_medico, id_especialidade) VALUES (?, ?)");
                foreach ($idsEsp as $idEsp) {
                    $stmtEsp->execute([$idMedico, $idEsp]);
                }
            }

        } elseif ($perfil === 'recepcionista') {
            $stmt = $pdo->prepare("
                INSERT INTO recepcionistas (nome, cpf, email, telefone, celular, data_inicio, uid, senha, foto, primeiro_acesso)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([$nome, $cpf, $email, $telefone, $celular, $dataInicio, $uid, $senhaTemporaria, $fotoRelativa]);

        } elseif ($perfil === 'gestor') {
            // gestores não tem coluna data_inicio no banco — não inclua aqui
            $stmt = $pdo->prepare("
                INSERT INTO gestores (nome, cpf, email, telefone, celular, uid, senha, foto, status, primeiro_acesso)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Ativo', 1)
            ");
            $stmt->execute([
                $nome,
                $cpf,
                $email,
                $telefone !== '' ? $telefone : null,
                $celular !== '' ? $celular : null,
                $uid,
                $senhaTemporaria,
                $fotoRelativa,
            ]);

        } elseif ($perfil === 'paciente') {
            $stmt = $pdo->prepare("
                INSERT INTO paciente
                    (uid, nome, menor_idade, tipo_documento, num_doc, data_nasc, telefone, celular, email, senha,
                     nome_resp, data_nasc_resp, tipo_documento_resp, num_doc_resp, telefone_resp, celular_resp, email_resp, primeiro_acesso)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                     ?, ?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([
                $uid,
                $nome,
                $menorIdade,
                $tipoDoc,
                $cpf,
                $dataNasc,
                $telefone,
                $celular,
                $email,
                $senhaTemporaria,
                $menorIdade === 'Y' ? $nomeResp : null,
                ($menorIdade === 'Y' && $dataNascResp !== '') ? $dataNascResp : null,
                $menorIdade === 'Y' ? $tipoDocResp : null,
                $menorIdade === 'Y' ? ($numDocResp !== '' ? $numDocResp : null) : null,
                $menorIdade === 'Y' ? ($telefoneResp !== '' ? $telefoneResp : null) : null,
                $menorIdade === 'Y' ? ($celularResp !== '' ? $celularResp : null) : null,
                $menorIdade === 'Y' ? ($emailResp !== '' ? $emailResp : null) : null,
            ]);
        }

        $pdo->commit();
        header("Location: ../cadastrar-usuario.php?status=sucesso");
        exit();

    } catch (PDOException $e) {
        $pdo->rollBack();

        // Desfaz o arquivo de foto já salvo em disco, já que o cadastro não foi concluído
        if ($caminhoFotoAbs !== null && file_exists($caminhoFotoAbs)) {
            @unlink($caminhoFotoAbs);
        }

        header("Location: ../cadastrar-usuario.php?erro=" . urlencode('Não foi possível cadastrar. Tente novamente.'));
        exit();
    }

} else {
    header("Location: ../cadastrar-usuario.php");
    exit();
}