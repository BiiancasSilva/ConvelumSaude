<?php
session_start();
require_once '../../../conexao.php';

if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'medico') {
    header('Location: ../../../auth/login.php');
    exit();
}

function voltarRemocaoAgenda(string $erro = ''): void
{
    header('Location: ../agenda-configurar.php' . ($erro !== '' ? '?erro=' . rawurlencode($erro) : ''));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    voltarRemocaoAgenda();
}
if (!isset($_POST['csrf_token'], $_SESSION['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
    voltarRemocaoAgenda('csrf');
}

$medicoId = filter_var($_SESSION['usuario_id'], FILTER_VALIDATE_INT);
$id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
$origem = (string)($_POST['origem'] ?? '');
$tabelasPermitidas = [
    'medico_ausencias' => ['table' => 'medico_ausencias', 'success' => 'removido'],
    'medico_bloqueios' => ['table' => 'medico_bloqueios', 'success' => 'removido'],
    'bloqueio_agenda' => ['table' => 'bloqueio_agenda', 'success' => 'removido'],
];

if (!$medicoId || !$id || !isset($tabelasPermitidas[$origem])) {
    voltarRemocaoAgenda('campos');
}

try {
    $configuracao = $tabelasPermitidas[$origem];
    $stmt = $pdo->prepare('DELETE FROM ' . $configuracao['table'] . ' WHERE id = ? AND id_medico = ?');
    $stmt->execute([(int)$id, (int)$medicoId]);
    if ($stmt->rowCount() !== 1) {
        voltarRemocaoAgenda('registro');
    }

    header('Location: ../agenda-configurar.php?sucesso=' . rawurlencode($configuracao['success']));
    exit();
} catch (Throwable $e) {
    error_log('Erro ao remover registro da agenda médica: ' . $e->getMessage());
    voltarRemocaoAgenda('falha');
}
