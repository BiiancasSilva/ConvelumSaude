<?php
// gestor/actions/salvar-bloqueio.php
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

$tipoAlvo   = $_POST['tipo_alvo'] ?? '';
$alvoId     = $_POST['alvo_id'] ?? '0';
$motivo     = trim($_POST['motivo'] ?? '');
$dataInicio = $_POST['data_inicio'] ?? '';
$horaInicio = !empty($_POST['hora_inicio']) ? $_POST['hora_inicio'] : null;
$dataFim    = $_POST['data_fim'] ?? '';
$horaFim    = !empty($_POST['hora_fim']) ? $_POST['hora_fim'] : null;

if (empty($tipoAlvo) || empty($motivo) || empty($dataInicio) || empty($dataFim)) {
    header("Location: ../configurar-bloqueios.php?erro=" . urlencode('Preencha todos os campos obrigatórios.'));
    exit();
}

$erroPeriodo = validarPeriodoBloqueio($dataInicio, $horaInicio, $dataFim, $horaFim);
if ($erroPeriodo !== null) {
    header("Location: ../configurar-bloqueios.php?erro=" . urlencode($erroPeriodo));
    exit();
}

$nomeAlvo = resolverNomeAlvo($pdo, $tipoAlvo, $alvoId);

try {
    $stmt = $pdo->prepare("
        INSERT INTO bloqueios (tipo_alvo, alvo_id, nome_alvo, motivo, data_inicio, hora_inicio, data_fim, hora_fim)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$tipoAlvo, $alvoId, $nomeAlvo, $motivo, $dataInicio, $horaInicio, $dataFim, $horaFim]);

    header("Location: ../configurar-bloqueios.php?sucesso=cadastrado");
    exit();

} catch (PDOException $e) {
    header("Location: ../configurar-bloqueios.php?erro=" . urlencode('Não foi possível salvar o bloqueio. Tente novamente.'));
    exit();
}