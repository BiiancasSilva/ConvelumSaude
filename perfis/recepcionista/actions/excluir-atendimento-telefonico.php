<?php
session_start();
require_once __DIR__ . '/../../../conexao.php';

if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'recepcionista') {
    header('Location: ../../../auth/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../atendimento-telefonico.php');
    exit();
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!isset($_SESSION['csrf_atendimento_telefonico']) || !is_string($csrfToken)
    || !hash_equals($_SESSION['csrf_atendimento_telefonico'], $csrfToken)) {
    header('Location: ../atendimento-telefonico.php?erro=' . urlencode('A sessão do formulário expirou. Atualize a página e tente novamente.'));
    exit();
}

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
if ($id === false || $id < 1) {
    header('Location: ../atendimento-telefonico.php?erro=' . urlencode('Registro de atendimento inválido.'));
    exit();
}

try {
    $stmt = $pdo->prepare('DELETE FROM atendimento_telefonico WHERE id = ?');
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) {
        header('Location: ../atendimento-telefonico.php?erro=' . urlencode('O atendimento não foi encontrado.'));
        exit();
    }

    header('Location: ../atendimento-telefonico.php?sucesso=excluido');
    exit();
} catch (PDOException $e) {
    error_log('Falha ao excluir atendimento telefônico: ' . $e->getMessage());
    header('Location: ../atendimento-telefonico.php?erro=' . urlencode('Não foi possível excluir o atendimento. Tente novamente.'));
    exit();
}
