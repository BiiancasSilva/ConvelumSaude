<?php
// gestor/actions/excluir-bloqueio.php
session_start();
require_once '../../../conexao.php';
require_once 'bloqueios-helpers.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_perfil'] !== 'gestor') {
    header("Location: ../../../auth/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../configurar-bloqueios.php");
    exit();
}

if (!validarTokenCsrfBloqueio($_POST['csrf_token'] ?? null)) {
    header("Location: ../configurar-bloqueios.php?erro=" . urlencode('Sessão expirada. Recarregue a página e tente novamente.'));
    exit();
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id <= 0) {
    header("Location: ../configurar-bloqueios.php?erro=" . urlencode('Bloqueio inválido.'));
    exit();
}

try {
    $stmtDel = $pdo->prepare("DELETE FROM bloqueios WHERE id = ?");
    $stmtDel->execute([$id]);

    if ($stmtDel->rowCount() === 0) {
        header("Location: ../configurar-bloqueios.php?erro=" . urlencode('Bloqueio não encontrado.'));
        exit();
    }

    header("Location: ../configurar-bloqueios.php?sucesso=excluido");
    exit();

} catch (PDOException $e) {
    header("Location: ../configurar-bloqueios.php?erro=" . urlencode('Erro ao excluir bloqueio.'));
    exit();
}