<?php
session_start();
require_once '../../../conexao.php';

function voltarExame(int $consultaId, string $erro): void
{
    header('Location: ../exame.php?id_consulta=' . $consultaId . '&erro=' . rawurlencode($erro));
    exit();
}

if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'medico') {
    header('Location: ../../../auth/login.php');
    exit();
}

$idMedico = (int)$_SESSION['usuario_id'];
$idConsulta = filter_input(INPUT_POST, 'id_consulta', FILTER_VALIDATE_INT);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    voltarExame((int)$idConsulta, 'metodo');
}
if (!isset($_POST['csrf_token'], $_SESSION['csrf_token'])
    || !hash_equals((string)$_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
    voltarExame((int)$idConsulta, 'csrf');
}
if (!$idConsulta || $idConsulta < 1) {
    voltarExame(0, 'dados');
}

$listaExames = json_decode((string)($_POST['exames'] ?? ''), true);
$observacoes = trim((string)($_POST['observacoes'] ?? ''));
$urgenciasPermitidas = ['Rotina', 'Urgente'];
$preparosPermitidos = ['Nenhum', 'Jejum de 8h', 'Jejum de 12h', 'Outro'];

if (!is_array($listaExames) || count($listaExames) < 1 || count($listaExames) > 50
    || strlen($observacoes) > 10000) {
    voltarExame($idConsulta, 'dados');
}

$examesValidos = [];
foreach ($listaExames as $exame) {
    if (!is_array($exame)) {
        voltarExame($idConsulta, 'dados');
    }
    $nome = trim((string)($exame['nome'] ?? ''));
    $urgencia = (string)($exame['urgencia'] ?? '');
    $preparo = (string)($exame['preparo'] ?? '');
    $justificativa = trim((string)($exame['justificativa'] ?? ''));
    if ($nome === '' || strlen($nome) > 255
        || !in_array($urgencia, $urgenciasPermitidas, true)
        || !in_array($preparo, $preparosPermitidos, true)
        || strlen($justificativa) > 255) {
        voltarExame($idConsulta, 'dados');
    }
    $examesValidos[] = [
        'nome' => $nome,
        'urgencia' => $urgencia,
        'preparo' => $preparo,
        'justificativa' => $justificativa,
    ];
}

try {
    $pdo->beginTransaction();
    $stmtConsulta = $pdo->prepare('
        SELECT id_paciente
        FROM consultas
        WHERE id = ? AND id_medico = ?
        LIMIT 1
        FOR UPDATE
    ');
    $stmtConsulta->execute([$idConsulta, $idMedico]);
    $idPaciente = $stmtConsulta->fetchColumn();
    if (!$idPaciente) {
        $pdo->rollBack();
        voltarExame($idConsulta, 'consulta');
    }

    $stmt = $pdo->prepare("
        INSERT INTO exames
            (id_paciente, id_medico, nome_exame, urgencia, preparo, justificativa, observacoes, data_exame, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), 'pendente')
    ");
    foreach ($examesValidos as $exame) {
        $stmt->execute([
            (int)$idPaciente,
            $idMedico,
            $exame['nome'],
            $exame['urgencia'],
            $exame['preparo'],
            $exame['justificativa'] !== '' ? $exame['justificativa'] : null,
            $observacoes !== '' ? $observacoes : null,
        ]);
    }
    $pdo->commit();
    header('Location: ../exame.php?id_consulta=' . $idConsulta . '&sucesso=1');
    exit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Erro ao solicitar exame: ' . $e->getMessage());
    voltarExame($idConsulta, 'falha');
}
