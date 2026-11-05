<?php
session_start();
require_once '../../../conexao.php';

if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'recepcionista') {
    header('Location: ../../../auth/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../atualizar-cadastro.php');
    exit();
}

$id = isset($_POST['id']) && is_string($_POST['id'])
    ? filter_var($_POST['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
$termoBusca = isset($_POST['busca']) && is_string($_POST['busca']) ? trim($_POST['busca']) : '';
$voltarComErro = static function (string $mensagem) use ($termoBusca): void {
    $query = $termoBusca !== '' ? '&busca=' . rawurlencode($termoBusca) : '';
    header('Location: ../atualizar-cadastro.php?erro=' . rawurlencode($mensagem) . $query);
    exit();
};

$csrfToken = $_POST['csrf_token'] ?? '';
if (
    !is_string($csrfToken) ||
    empty($_SESSION['csrf_atualizar_paciente']) ||
    !hash_equals($_SESSION['csrf_atualizar_paciente'], $csrfToken)
) {
    $voltarComErro('Sua sessão expirou. Atualize a página e tente novamente.');
}
if (!$id) {
    $voltarComErro('Paciente inválido.');
}

$texto = static function (string $campo): string {
    return isset($_POST[$campo]) && is_string($_POST[$campo]) ? trim($_POST[$campo]) : '';
};
$nome = $texto('nome');
$tipoDocumento = $texto('tipo_documento');
$documento = $texto('documento');
$nascimento = $texto('nascimento');
$telefone = $texto('telefone');
$celular = $texto('celular');
$email = strtolower($texto('email'));
$endereco = $texto('endereco');
$observacoes = $texto('obs_paciente');
$menorIdade = $texto('menor_de_idade') === 'sim' ? 'Y' : 'N';
$idConvenioRaw = $texto('id_convenio');
$idConvenio = $idConvenioRaw === '' ? null : filter_var($idConvenioRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$planoConvenio = $texto('plano_convenio');
$carteirinha = $texto('carteirinha');
$validadeCarteirinha = $texto('validade_carteirinha');

$tiposDocumento = [
    'CPF' => 'CPF',
    'RG' => 'RG',
    'CNH' => 'CNH',
    'Passaporte' => 'Passaporte',
    'Certidao' => 'Certidao',
    'Outro' => 'Outro',
];
$tipoDocumentoResponsavel = $texto('tipo_documento_responsavel');
$numeroDocumentoResponsavel = $texto('num_doc_responsavel');
$nomeResponsavel = $texto('nome_responsavel');
$nascimentoResponsavel = $texto('nascimento_responsavel');
$telefoneResponsavel = $texto('telefone_responsavel');
$celularResponsavel = $texto('celular_responsavel');
$emailResponsavel = strtolower($texto('email_responsavel'));

$dateValida = static function (string $data, bool $obrigatoria = false): bool {
    if ($data === '') {
        return !$obrigatoria;
    }
    $date = DateTime::createFromFormat('!Y-m-d', $data);
    $errors = DateTime::getLastErrors();
    return $date !== false &&
        ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) &&
        $date->format('Y-m-d') === $data &&
        $data <= date('Y-m-d');
};
$idade = static function (string $data): int {
    return (new DateTimeImmutable($data))->diff(new DateTimeImmutable('today'))->y;
};

if ($nome === '' || mb_strlen($nome, 'UTF-8') > 200) {
    $voltarComErro('Informe o nome completo (até 200 caracteres).');
}
if (!isset($tiposDocumento[$tipoDocumento]) || $documento === '' || mb_strlen($documento, 'UTF-8') > 60) {
    $voltarComErro('Informe um tipo e número de documento válidos.');
}
if (!$dateValida($nascimento, true)) {
    $voltarComErro('Informe uma data de nascimento válida, que não seja futura.');
}
if ($menorIdade === 'Y' && $idade($nascimento) >= 18) {
    $voltarComErro('A data de nascimento indica que o paciente é maior de idade. Atualize o formulário.');
}
if ($menorIdade === 'N' && $idade($nascimento) < 18) {
    $voltarComErro('O paciente é menor de idade; atualize os dados do responsável.');
}
if ($celular === '' || mb_strlen($celular, 'UTF-8') > 15 || mb_strlen($telefone, 'UTF-8') > 20) {
    $voltarComErro('Informe um celular válido; os telefones não podem ultrapassar o limite do cadastro.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email, 'UTF-8') > 100) {
    $voltarComErro('Informe um e-mail válido de até 100 caracteres.');
}
if (mb_strlen($endereco, 'UTF-8') > 200 || mb_strlen($observacoes, 'UTF-8') > 65535) {
    $voltarComErro('O endereço ou as observações excedem o limite permitido.');
}

if ($menorIdade === 'Y') {
    if (
        $nomeResponsavel === '' ||
        mb_strlen($nomeResponsavel, 'UTF-8') > 100 ||
        !isset($tiposDocumento[$tipoDocumentoResponsavel]) ||
        mb_strlen($numeroDocumentoResponsavel, 'UTF-8') > 30 ||
        !$dateValida($nascimentoResponsavel, true) ||
        $idade($nascimentoResponsavel) < 18 ||
        mb_strlen($telefoneResponsavel, 'UTF-8') > 15 ||
        mb_strlen($celularResponsavel, 'UTF-8') > 15 ||
        ($emailResponsavel !== '' && (!filter_var($emailResponsavel, FILTER_VALIDATE_EMAIL) || mb_strlen($emailResponsavel, 'UTF-8') > 100))
    ) {
        $voltarComErro('Confira os dados obrigatórios do responsável maior de idade.');
    }
} else {
    $nomeResponsavel = '';
    $tipoDocumentoResponsavel = '';
    $numeroDocumentoResponsavel = '';
    $nascimentoResponsavel = '';
    $telefoneResponsavel = '';
    $celularResponsavel = '';
    $emailResponsavel = '';
}

if ($idConvenio === false) {
    $voltarComErro('Selecione um convênio válido.');
}
if ($idConvenio !== null) {
    if (
        mb_strlen($planoConvenio, 'UTF-8') > 100 ||
        mb_strlen($carteirinha, 'UTF-8') > 50 ||
        !$dateValida($validadeCarteirinha)
    ) {
        $voltarComErro('Confira o plano, o número da carteirinha e sua validade.');
    }
} else {
    $planoConvenio = '';
    $carteirinha = '';
    $validadeCarteirinha = '';
}

$uploadDir = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'comprovantes';
$arquivosNovos = [];
$arquivoAntigoPaciente = null;
$arquivoAntigoResponsavel = null;
$fileError = static function (string $campo): void {
    if (!isset($_FILES[$campo]) || !is_array($_FILES[$campo])) {
        return;
    }
    $erro = $_FILES[$campo]['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($erro !== UPLOAD_ERR_OK && $erro !== UPLOAD_ERR_NO_FILE) {
        throw new InvalidArgumentException($erro === UPLOAD_ERR_INI_SIZE || $erro === UPLOAD_ERR_FORM_SIZE
            ? 'O comprovante excede o tamanho máximo permitido.'
            : 'Não foi possível receber o comprovante enviado.');
    }
};
$processarUpload = static function (string $campo) use ($uploadDir, &$arquivosNovos, $fileError): ?string {
    $fileError($campo);
    if (!isset($_FILES[$campo]) || !is_array($_FILES[$campo]) || ($_FILES[$campo]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $arquivo = $_FILES[$campo];
    if (
        !isset($arquivo['tmp_name'], $arquivo['size']) ||
        !is_string($arquivo['tmp_name']) ||
        !is_numeric($arquivo['size']) ||
        !is_uploaded_file($arquivo['tmp_name']) ||
        (int)$arquivo['size'] <= 0 ||
        (int)$arquivo['size'] > 5 * 1024 * 1024
    ) {
        throw new InvalidArgumentException('Cada comprovante deve ter no máximo 5 MB.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($arquivo['tmp_name']);
    $extensoes = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];
    if (!isset($extensoes[$mime])) {
        throw new InvalidArgumentException('Use comprovantes PDF, JPG ou PNG.');
    }

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0750, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Não foi possível preparar a pasta de comprovantes.');
    }
    $nomeArquivo = 'paciente_' . $campo . '_' . bin2hex(random_bytes(16)) . '.' . $extensoes[$mime];
    $destino = $uploadDir . DIRECTORY_SEPARATOR . $nomeArquivo;
    if (!move_uploaded_file($arquivo['tmp_name'], $destino)) {
        throw new RuntimeException('Não foi possível salvar o comprovante enviado.');
    }
    $arquivosNovos[] = $destino;
    return $nomeArquivo;
};

try {
    $stmtAtual = $pdo->prepare('SELECT id, uid, doc_comprovante, doc_comprovante_resp, id_convenio, status_convenio FROM paciente WHERE id = ? LIMIT 1');
    $stmtAtual->execute([$id]);
    $pacienteAtual = $stmtAtual->fetch(PDO::FETCH_ASSOC);
    if (!$pacienteAtual) {
        $voltarComErro('Paciente não encontrado.');
    }

    $stmtDuplicado = $pdo->prepare('SELECT id FROM paciente WHERE email = ? AND id <> ? LIMIT 1');
    $stmtDuplicado->execute([$email, $id]);
    if ($stmtDuplicado->fetchColumn()) {
        $voltarComErro('Este e-mail já está cadastrado em outro paciente.');
    }

    if ($idConvenio !== null) {
        $stmtConvenio = $pdo->prepare("SELECT id, status FROM convenios WHERE id = ? LIMIT 1");
        $stmtConvenio->execute([$idConvenio]);
        $convenio = $stmtConvenio->fetch(PDO::FETCH_ASSOC);
        if (
            !$convenio ||
            ($convenio['status'] !== 'Ativo' && (int)$pacienteAtual['id_convenio'] !== (int)$idConvenio)
        ) {
            $voltarComErro('Selecione um convênio ativo.');
        }
    }

    $novoComprovante = $processarUpload('doc_comprovante');
    $novoComprovanteResponsavel = $menorIdade === 'Y' ? $processarUpload('doc_comprovante_resp') : null;
    $arquivoAntigoPaciente = $novoComprovante !== null ? $pacienteAtual['doc_comprovante'] : null;
    $arquivoAntigoResponsavel = ($menorIdade !== 'Y' || $novoComprovanteResponsavel !== null)
        ? $pacienteAtual['doc_comprovante_resp']
        : null;

    $pdo->beginTransaction();
    $stmt = $pdo->prepare("
        UPDATE paciente
        SET nome = ?, menor_idade = ?, tipo_documento = ?, num_doc = ?, data_nasc = ?,
            telefone = ?, celular = ?, email = ?, endereco = ?, obs_paciente = ?,
            nome_resp = ?, data_nasc_resp = ?, tipo_documento_resp = ?, num_doc_resp = ?,
            telefone_resp = ?, celular_resp = ?, email_resp = ?, doc_comprovante = ?,
            doc_comprovante_resp = ?, id_convenio = ?, plano_convenio = ?,
            num_carteirinha = ?, validade_carteirinha = ?, status_convenio = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $nome,
        $menorIdade,
        $tiposDocumento[$tipoDocumento],
        $documento,
        $nascimento,
        $telefone !== '' ? $telefone : null,
        $celular,
        $email,
        $endereco !== '' ? $endereco : null,
        $observacoes !== '' ? $observacoes : null,
        $menorIdade === 'Y' ? $nomeResponsavel : null,
        $menorIdade === 'Y' ? $nascimentoResponsavel : null,
        $menorIdade === 'Y' ? $tiposDocumento[$tipoDocumentoResponsavel] : null,
        $menorIdade === 'Y' && $numeroDocumentoResponsavel !== '' ? $numeroDocumentoResponsavel : null,
        $menorIdade === 'Y' && $telefoneResponsavel !== '' ? $telefoneResponsavel : null,
        $menorIdade === 'Y' && $celularResponsavel !== '' ? $celularResponsavel : null,
        $menorIdade === 'Y' && $emailResponsavel !== '' ? $emailResponsavel : null,
        $novoComprovante ?? $pacienteAtual['doc_comprovante'],
        $menorIdade === 'Y' ? ($novoComprovanteResponsavel ?? $pacienteAtual['doc_comprovante_resp']) : null,
        $idConvenio,
        $idConvenio !== null && $planoConvenio !== '' ? $planoConvenio : null,
        $idConvenio !== null && $carteirinha !== '' ? $carteirinha : null,
        $idConvenio !== null && $validadeCarteirinha !== '' ? $validadeCarteirinha : null,
        $idConvenio === null
            ? 'inativo'
            : ($convenio['status'] === 'Ativo' ? 'ativo' : ($pacienteAtual['status_convenio'] ?? 'inativo')),
        $id,
    ]);
    $pdo->commit();

    $apagarArquivoAntigo = static function (?string $nomeArquivo) use ($uploadDir): void {
        if (!is_string($nomeArquivo) || $nomeArquivo === '' || basename($nomeArquivo) !== $nomeArquivo) {
            return;
        }
        $caminho = $uploadDir . DIRECTORY_SEPARATOR . $nomeArquivo;
        if (is_file($caminho)) {
            unlink($caminho);
        }
    };
    if ($arquivoAntigoPaciente !== null && $novoComprovante !== null) {
        $apagarArquivoAntigo($arquivoAntigoPaciente);
    }
    if ($arquivoAntigoResponsavel !== null && ($menorIdade !== 'Y' || $novoComprovanteResponsavel !== null)) {
        $apagarArquivoAntigo($arquivoAntigoResponsavel);
    }

    header('Location: ../atualizar-cadastro.php?busca=' . rawurlencode($pacienteAtual['uid']) . '&sucesso=1');
    exit();
} catch (InvalidArgumentException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    foreach ($arquivosNovos as $arquivoNovo) {
        if (is_file($arquivoNovo)) {
            unlink($arquivoNovo);
        }
    }
    $voltarComErro($e->getMessage());
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    foreach ($arquivosNovos as $arquivoNovo) {
        if (is_file($arquivoNovo)) {
            unlink($arquivoNovo);
        }
    }
    error_log('Falha ao atualizar cadastro do paciente #' . $id . ': ' . $e->getMessage());
    $voltarComErro('Não foi possível atualizar o cadastro. Confira os dados e tente novamente.');
}
