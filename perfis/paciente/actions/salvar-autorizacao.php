<?php
// perfis/paciente/actions/salvar-autorizacao.php
session_start();

if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'paciente') {
    header("Location: ../../../auth/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../autorizacao.php");
    exit();
}

require_once '../../../conexao.php';

function voltarComErroAutorizacao(string $mensagem): void
{
    header('Location: ../autorizacao.php?erro=' . urlencode($mensagem));
    exit();
}

$pacienteId = (int)$_SESSION['usuario_id'];
$procedimento = trim((string)($_POST['procedimento'] ?? ''));
$medico = trim((string)($_POST['medico'] ?? ''));
$dataPedido = trim((string)($_POST['data_pedido'] ?? ''));
$justificativa = trim((string)($_POST['justificativa'] ?? ''));

if ($procedimento === '' || $medico === '') {
    voltarComErroAutorizacao('Preencha o procedimento e o médico solicitante.');
}

if ($dataPedido !== '') {
    $dataValidada = DateTimeImmutable::createFromFormat('!Y-m-d', $dataPedido);
    if (!$dataValidada || $dataValidada->format('Y-m-d') !== $dataPedido) {
        voltarComErroAutorizacao('Informe uma data válida para o pedido médico.');
    }
}

$caminhoAnexo = null;
$arquivoSalvo = null;
if (isset($_FILES['arquivo_pedido']) && $_FILES['arquivo_pedido']['error'] !== UPLOAD_ERR_NO_FILE) {
    $arquivo = $_FILES['arquivo_pedido'];
    if ($arquivo['error'] !== UPLOAD_ERR_OK || $arquivo['size'] <= 0 || $arquivo['size'] > 5 * 1024 * 1024) {
        voltarComErroAutorizacao('O anexo deve ter até 5 MB e ser enviado corretamente.');
    }

    $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
    $mimesPermitidos = [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
    ];
    if (!isset($mimesPermitidos[$extensao])) {
        voltarComErroAutorizacao('Formato de anexo inválido. Envie PDF, JPG ou PNG.');
    }

    if (!class_exists('finfo')) {
        voltarComErroAutorizacao('A validação de anexos não está disponível no servidor.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($arquivo['tmp_name']);
    if (!in_array($mime, $mimesPermitidos[$extensao], true)) {
        voltarComErroAutorizacao('O conteúdo do anexo não corresponde ao formato informado.');
    }

    $pastaDestino = dirname(__DIR__, 3) . '/uploads/autorizacoes';
    if (!is_dir($pastaDestino) && !mkdir($pastaDestino, 0755, true) && !is_dir($pastaDestino)) {
        voltarComErroAutorizacao('Não foi possível preparar a pasta dos anexos.');
    }

    $nomeArquivo = 'auth_' . $pacienteId . '_' . bin2hex(random_bytes(12)) . '.' . $extensao;
    $arquivoSalvo = $pastaDestino . '/' . $nomeArquivo;
    if (!move_uploaded_file($arquivo['tmp_name'], $arquivoSalvo)) {
        voltarComErroAutorizacao('Não foi possível salvar o anexo enviado.');
    }
    $caminhoAnexo = 'uploads/autorizacoes/' . $nomeArquivo;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO autorizacoes
            (paciente_id, procedimento, medico, data_pedido, justificativa, arquivo, status, data_solicitacao)
        VALUES
            (?, ?, ?, ?, ?, ?, 'Pendente', NOW())
    ");
    $stmt->execute([
        $pacienteId,
        $procedimento,
        $medico,
        $dataPedido !== '' ? $dataPedido : null,
        $justificativa !== '' ? $justificativa : null,
        $caminhoAnexo,
    ]);
    header('Location: ../autorizacao.php?sucesso=1');
    exit();
} catch (Throwable $e) {
    if ($arquivoSalvo !== null && is_file($arquivoSalvo)) {
        unlink($arquivoSalvo);
    }
    error_log('Erro ao salvar solicitação de autorização: ' . $e->getMessage());
    voltarComErroAutorizacao('Não foi possível registrar a solicitação. Tente novamente.');
}