<?php
// recepcionista/actions/buscar-pacientes.php
session_start();
require_once '../../../conexao.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'recepcionista') {
    http_response_code(403);
    echo json_encode(['sucesso' => false, 'erro' => 'Sessão inválida.', 'pacientes' => []]);
    exit();
}

$termo = trim($_GET['termo'] ?? '');

if (mb_strlen($termo) < 2) {
    echo json_encode(['sucesso' => true, 'pacientes' => []]);
    exit();
}

try {
    $stmt = $pdo->prepare("
        SELECT id, nome, celular, telefone, num_doc
        FROM paciente
        WHERE status = 'Ativo'
          AND (nome LIKE ? OR num_doc LIKE ? OR celular LIKE ? OR telefone LIKE ?)
        ORDER BY nome ASC
        LIMIT 10
    ");
    $like = '%' . $termo . '%';
    $stmt->execute([$like, $like, $like, $like]);
    $pacientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['sucesso' => true, 'pacientes' => $pacientes], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

} catch (PDOException $e) {
    error_log('Erro ao buscar pacientes para agendamento da recepção: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => 'Erro ao buscar pacientes.', 'pacientes' => []]);
}