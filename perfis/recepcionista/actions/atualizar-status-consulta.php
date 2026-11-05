<?php
session_start();
require_once __DIR__ . '/../../../conexao.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'recepcionista') {
    http_response_code(403);
    echo json_encode(['sucesso' => false, 'erro' => 'Sessão inválida.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['sucesso' => false, 'erro' => 'Método não permitido.']);
    exit();
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!isset($_SESSION['csrf_consultas']) || !is_string($csrfToken)
    || !hash_equals($_SESSION['csrf_consultas'], $csrfToken)) {
    http_response_code(403);
    echo json_encode(['sucesso' => false, 'erro' => 'A sessão expirou. Atualize a página e tente novamente.']);
    exit();
}

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
$status = isset($_POST['status']) && is_string($_POST['status']) ? $_POST['status'] : '';
$statusPermitidos = ['confirmada', 'cancelada'];

if ($id === false || $id < 1 || !in_array($status, $statusPermitidos, true)) {
    http_response_code(400);
    echo json_encode(['sucesso' => false, 'erro' => 'Dados inválidos.']);
    exit();
}

try {
    // A condição pelo status impede que solicitações concorrentes sobrescrevam
    // uma confirmação ou cancelamento já processado.
    $stmt = $pdo->prepare("
        UPDATE consultas
        SET status = ?
        WHERE id = ? AND status = 'pendente'
    ");
    $stmt->execute([$status, $id]);

    if ($stmt->rowCount() !== 1) {
        $stmtCheck = $pdo->prepare('SELECT status FROM consultas WHERE id = ? LIMIT 1');
        $stmtCheck->execute([$id]);
        $statusAtual = $stmtCheck->fetchColumn();

        if ($statusAtual === false) {
            http_response_code(404);
            echo json_encode(['sucesso' => false, 'erro' => 'Consulta não encontrada.']);
        } else {
            http_response_code(409);
            echo json_encode(['sucesso' => false, 'erro' => 'Essa consulta já não está mais pendente. Atualize a página.']);
        }
        exit();
    }

    echo json_encode(['sucesso' => true, 'status' => $status]);
} catch (PDOException $e) {
    error_log('Falha ao atualizar status da consulta: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => 'Erro ao atualizar no banco de dados.']);
}
