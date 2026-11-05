<?php
session_start();
require_once __DIR__ . '/../../../conexao.php';

if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'paciente') {
    header('Location: ../../../auth/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../configuracoes.php');
    exit();
}

$pacienteId = filter_var($_SESSION['usuario_id'], FILTER_VALIDATE_INT);
if ($pacienteId === false || $pacienteId < 1) {
    header('Location: ../../../auth/login.php');
    exit();
}

$nome = trim($_POST['nome'] ?? '');
$email = trim($_POST['email'] ?? '');
$celular = trim($_POST['celular'] ?? '');
$telefone = trim($_POST['telefone'] ?? '');
$tipoDocumento = strtolower(trim($_POST['tipo_documento'] ?? ''));
$numeroDocumento = trim($_POST['numero_documento'] ?? '');

$documentosPermitidos = ['rg', 'cpf', 'cnh', 'passaporte', 'outro'];
$tamanho = static function (string $valor): int {
    $quantidade = preg_match_all('/./us', $valor, $matches);
    return $quantidade === false ? PHP_INT_MAX : $quantidade;
};
$erro = null;

if ($nome === '' || $email === '') {
    $erro = 'Nome e e-mail são obrigatórios.';
} elseif ($tamanho($nome) > 200 || $tamanho($email) > 100) {
    $erro = 'Nome ou e-mail excede o limite permitido.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $erro = 'Informe um e-mail válido.';
} elseif (!in_array($tipoDocumento, $documentosPermitidos, true)) {
    $erro = 'Selecione um tipo de documento válido.';
} elseif ($tamanho($numeroDocumento) > 60 || $tamanho($celular) > 15 || $tamanho($telefone) > 20) {
    $erro = 'Documento ou telefone excede o limite permitido.';
}

if ($erro !== null) {
    header('Location: ../configuracoes.php?erro=' . urlencode($erro));
    exit();
}

try {
    $stmtCheck = $pdo->prepare('SELECT id FROM paciente WHERE email = :email AND id <> :id LIMIT 1');
    $stmtCheck->execute([':email' => $email, ':id' => $pacienteId]);
    if ($stmtCheck->fetch()) {
        header('Location: ../configuracoes.php?erro=' . urlencode('Este e-mail já está em uso por outra conta de paciente.'));
        exit();
    }

    $stmt = $pdo->prepare(
        'UPDATE paciente
         SET nome = :nome, email = :email, celular = :celular, telefone = :telefone,
             tipo_documento = :tipo_documento, num_doc = :num_doc
         WHERE id = :id'
    );
    $stmt->execute([
        ':nome' => $nome,
        ':email' => $email,
        ':celular' => $celular,
        ':telefone' => $telefone,
        ':tipo_documento' => $tipoDocumento,
        ':num_doc' => $numeroDocumento,
        ':id' => $pacienteId
    ]);

    $_SESSION['usuario_nome'] = $nome;
    header('Location: ../configuracoes.php?sucesso=1');
    exit();
} catch (PDOException $e) {
    error_log('Falha ao salvar configurações do paciente: ' . $e->getMessage());
    header('Location: ../configuracoes.php?erro=' . urlencode('Não foi possível salvar seus dados. Tente novamente.'));
    exit();
}
