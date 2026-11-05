<?php
session_start();
require_once '../../../conexao.php';

if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'recepcionista') {
    header('Location: ../../../auth/login.php');
    exit();
}

function voltarCadastroPaciente(string $mensagem): void
{
    header('Location: ../cadastrar-paciente.php?erro=' . rawurlencode($mensagem));
    exit();
}

class ErroUploadPaciente extends RuntimeException
{
}

function dataValidaPaciente(string $data): ?DateTimeImmutable
{
    $valor = DateTimeImmutable::createFromFormat('!Y-m-d', $data);
    return $valor && $valor->format('Y-m-d') === $data ? $valor : null;
}

function salvarDocumentoPaciente(string $campo, string $prefixo, int $limiteBytes = 5242880): ?string
{
    if (!isset($_FILES[$campo]) || $_FILES[$campo]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $arquivo = $_FILES[$campo];
    if ($arquivo['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($arquivo['tmp_name'])
        || $arquivo['size'] < 1 || $arquivo['size'] > $limiteBytes) {
        throw new ErroUploadPaciente('Cada comprovante deve ter até 5 MB e ser enviado corretamente.');
    }

    $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
    $mimesPermitidos = [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
    ];
    if (!isset($mimesPermitidos[$extensao])) {
        throw new ErroUploadPaciente('Formato de comprovante inválido. Envie PDF, JPG ou PNG.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    if (!in_array($finfo->file($arquivo['tmp_name']), $mimesPermitidos[$extensao], true)) {
        throw new ErroUploadPaciente('O conteúdo do comprovante não corresponde ao formato informado.');
    }

    $pasta = dirname(__DIR__, 3) . '/uploads/documentos';
    if (!is_dir($pasta) && !mkdir($pasta, 0755, true) && !is_dir($pasta)) {
        throw new ErroUploadPaciente('Não foi possível preparar a pasta de documentos.');
    }

    $nome = $prefixo . bin2hex(random_bytes(12)) . '.' . $extensao;
    if (!move_uploaded_file($arquivo['tmp_name'], $pasta . '/' . $nome)) {
        throw new ErroUploadPaciente('Não foi possível salvar o comprovante enviado.');
    }

    return 'uploads/documentos/' . $nome;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../cadastrar-paciente.php');
    exit();
}

if (!isset($_POST['csrf_token'], $_SESSION['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
    voltarCadastroPaciente('Sua sessão expirou. Atualize a página e tente novamente.');
}

$nome = trim((string)($_POST['nome'] ?? ''));
$tipoDocumento = trim((string)($_POST['tipo_documento'] ?? ''));
$numeroDocumento = trim((string)($_POST['numero_documento'] ?? ''));
$nascimento = trim((string)($_POST['nascimento'] ?? ''));
$telefone = trim((string)($_POST['telefone'] ?? ''));
$celular = trim((string)($_POST['celular'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$endereco = trim((string)($_POST['endereco'] ?? ''));
$observacoes = trim((string)($_POST['obs_paciente'] ?? ''));
$tipoDocumentoResponsavel = trim((string)($_POST['tipo_documento_responsavel'] ?? ''));
$idConvenio = filter_var($_POST['id_convenio'] ?? '', FILTER_VALIDATE_INT);
$idConvenio = $idConvenio ? (int)$idConvenio : null;
$planoConvenio = trim((string)($_POST['plano_convenio'] ?? ''));
$carteirinha = trim((string)($_POST['carteirinha'] ?? ''));
$validadeCarteirinha = trim((string)($_POST['validade_carteirinha'] ?? ''));
$nomeResponsavel = trim((string)($_POST['nome_responsavel'] ?? ''));
$numeroDocResponsavel = trim((string)($_POST['num_doc_responsavel'] ?? ''));
$telefoneResponsavel = trim((string)($_POST['telefone_responsavel'] ?? ''));
$celularResponsavel = trim((string)($_POST['celular_responsavel'] ?? ''));
$emailResponsavel = trim((string)($_POST['email_responsavel'] ?? ''));
$nascimentoResponsavel = trim((string)($_POST['nascimento_responsavel'] ?? ''));
$documentosSalvos = [];

$tiposDocumento = ['RG', 'CPF', 'CNH', 'Passaporte', 'Certidao', 'Outro'];
$tiposDocResponsavel = ['RG', 'CPF', 'CNH', 'Passaporte', 'Outro'];
$dataNascimento = dataValidaPaciente($nascimento);
$hoje = new DateTimeImmutable('today');
$digitosCelular = preg_replace('/\D/', '', $celular);
$digitosTelefone = preg_replace('/\D/', '', $telefone);

if ($nome === '' || mb_strlen($nome) > 200 || !in_array($tipoDocumento, $tiposDocumento, true)
    || $numeroDocumento === '' || mb_strlen($numeroDocumento) > 60 || !$dataNascimento
    || $dataNascimento > $hoje || !in_array(strlen($digitosCelular), [10, 11], true) || mb_strlen($celular) > 15
    || ($telefone !== '' && !in_array(strlen($digitosTelefone), [10, 11], true))
    || ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100))
    || mb_strlen($telefone) > 20 || mb_strlen($endereco) > 200) {
    voltarCadastroPaciente('Confira nome, documento, data de nascimento, celular e e-mail.');
}

$menor = $dataNascimento->diff($hoje)->y < 18;
if ($menor) {
    $dataNascResp = dataValidaPaciente($nascimentoResponsavel);
    $digitosCelularResponsavel = preg_replace('/\D/', '', $celularResponsavel);
    $digitosTelefoneResponsavel = preg_replace('/\D/', '', $telefoneResponsavel);
    if ($nomeResponsavel === '' || mb_strlen($nomeResponsavel) > 100
        || !in_array($tipoDocumentoResponsavel, $tiposDocResponsavel, true)
        || !$dataNascResp || $dataNascResp > $hoje || $dataNascResp->diff($hoje)->y < 18
        || !in_array(strlen($digitosCelularResponsavel), [10, 11], true) || mb_strlen($celularResponsavel) > 15
        || mb_strlen($numeroDocResponsavel) > 30 || mb_strlen($telefoneResponsavel) > 15
        || ($telefoneResponsavel !== '' && !in_array(strlen($digitosTelefoneResponsavel), [10, 11], true))
        || ($emailResponsavel !== '' && (!filter_var($emailResponsavel, FILTER_VALIDATE_EMAIL) || mb_strlen($emailResponsavel) > 100))) {
        voltarCadastroPaciente('Para menor de idade, informe os dados válidos de um responsável maior de 18 anos.');
    }
} else {
    $tipoDocumentoResponsavel = '';
    $nomeResponsavel = $numeroDocResponsavel = $telefoneResponsavel = $celularResponsavel = $emailResponsavel = $nascimentoResponsavel = '';
}

if ($idConvenio === null) {
    $planoConvenio = $carteirinha = $validadeCarteirinha = '';
} else {
    if (mb_strlen($planoConvenio) > 100 || mb_strlen($carteirinha) > 50
        || ($validadeCarteirinha !== '' && !dataValidaPaciente($validadeCarteirinha))) {
        voltarCadastroPaciente('Confira os dados do convênio e a validade da carteirinha.');
    }
}

try {
    if ($idConvenio !== null) {
        $stmtConvenio = $pdo->prepare("SELECT id FROM convenios WHERE id = ? AND status = 'Ativo'");
        $stmtConvenio->execute([$idConvenio]);
        if (!$stmtConvenio->fetchColumn()) {
            voltarCadastroPaciente('O convênio selecionado não está ativo.');
        }
    }

    $documentosSalvos['paciente'] = salvarDocumentoPaciente('doc_comprovante', 'pac_');
    if ($menor) {
        $documentosSalvos['responsavel'] = salvarDocumentoPaciente('doc_comprovante_responsavel', 'resp_');
    }

    $pdo->beginTransaction();
    $senhaTemporaria = password_hash('123456', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("
        INSERT INTO paciente (
            uid, id_convenio, plano_convenio, num_carteirinha, validade_carteirinha, status_convenio,
            nome, menor_idade, tipo_documento, num_doc, doc_comprovante, doc_comprovante_resp,
            data_nasc, telefone, celular, endereco, email, senha, nome_resp, tipo_documento_resp,
            data_nasc_resp, num_doc_resp, telefone_resp, celular_resp, email_resp, obs_paciente,
            nivel_acesso
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1
        )
    ");
    $stmt->execute([
        'TMP_' . bin2hex(random_bytes(16)),
        $idConvenio,
        $idConvenio !== null ? ($planoConvenio ?: null) : null,
        $idConvenio !== null ? ($carteirinha ?: null) : null,
        $idConvenio !== null && $validadeCarteirinha !== '' ? $validadeCarteirinha : null,
        $idConvenio !== null ? 'ativo' : 'inativo',
        $nome,
        $menor ? 'Y' : 'N',
        $tipoDocumento,
        $numeroDocumento,
        $documentosSalvos['paciente'],
        $documentosSalvos['responsavel'] ?? null,
        $nascimento,
        $telefone ?: null,
        $celular,
        $endereco ?: null,
        $email !== '' ? $email : 'sem-email+' . bin2hex(random_bytes(8)) . '@sem-email.invalid',
        $senhaTemporaria,
        $menor ? $nomeResponsavel : null,
        $menor ? $tipoDocumentoResponsavel : null,
        $menor ? $nascimentoResponsavel : null,
        $menor ? ($numeroDocResponsavel ?: null) : null,
        $menor ? ($telefoneResponsavel ?: null) : null,
        $menor ? $celularResponsavel : null,
        $menor ? ($emailResponsavel ?: null) : null,
        $observacoes ?: null,
    ]);

    $idPaciente = (int)$pdo->lastInsertId();
    $uid = 'PAC' . str_pad((string)$idPaciente, 3, '0', STR_PAD_LEFT);
    $pdo->prepare('UPDATE paciente SET uid = ? WHERE id = ?')->execute([$uid, $idPaciente]);
    $pdo->commit();

    header('Location: ../cadastrar-paciente.php?status=sucesso');
    exit();
} catch (ErroUploadPaciente $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    foreach ($documentosSalvos as $arquivo) {
        if ($arquivo !== null) {
            $caminho = dirname(__DIR__, 3) . '/' . $arquivo;
            if (is_file($caminho)) {
                unlink($caminho);
            }
        }
    }
    voltarCadastroPaciente($e->getMessage());
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    foreach ($documentosSalvos as $arquivo) {
        if ($arquivo !== null) {
            $caminho = dirname(__DIR__, 3) . '/' . $arquivo;
            if (is_file($caminho)) {
                unlink($caminho);
            }
        }
    }
    error_log('Erro ao cadastrar paciente pela recepção: ' . $e->getMessage());
    voltarCadastroPaciente('Não foi possível cadastrar o paciente. Confira os dados e tente novamente.');
}
