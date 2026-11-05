<?php
session_start();
require_once '../../../conexao.php';

if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'recepcionista') {
    header('Location: ../../../auth/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../inserir-encaixe.php');
    exit();
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!is_string($csrfToken) || empty($_SESSION['csrf_encaixes']) || !hash_equals($_SESSION['csrf_encaixes'], $csrfToken)) {
    header('Location: ../inserir-encaixe.php?erro=' . urlencode('Sua sessão expirou. Atualize a página e tente novamente.'));
    exit();
}

$rawId = $_POST['id'] ?? null;
$id = is_string($rawId)
    ? filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
if (!$id) {
    header('Location: ../inserir-encaixe.php?erro=' . urlencode('Encaixe inválido.'));
    exit();
}

try {
    $stmt = $pdo->prepare('DELETE FROM encaixes WHERE id = ?');
    $stmt->execute([$id]);
    if ($stmt->rowCount() !== 1) {
        header('Location: ../inserir-encaixe.php?erro=' . urlencode('O encaixe não foi encontrado ou já foi excluído.'));
        exit();
    }

    header('Location: ../inserir-encaixe.php?excluido=1');
    exit();
} catch (PDOException $e) {
    error_log('Falha ao excluir encaixe: ' . $e->getMessage());
    header('Location: ../inserir-encaixe.php?erro=' . urlencode('Não foi possível excluir o encaixe.'));
    exit();
}
