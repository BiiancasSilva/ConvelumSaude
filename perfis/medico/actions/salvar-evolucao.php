<?php
session_start();
require_once '../../../conexao.php';

function voltarEvolucao(int $consultaId, string $erro): void
{
    header('Location: ../evolucao.php?id_consulta=' . $consultaId . '&erro=' . rawurlencode($erro));
    exit();
}

if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'medico') {
    header('Location: ../../../auth/login.php');
    exit();
}

$idMedico = (int)$_SESSION['usuario_id'];
$idConsulta = filter_input(INPUT_POST, 'id_consulta', FILTER_VALIDATE_INT);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    voltarEvolucao((int)$idConsulta, 'metodo');
}
if (!isset($_POST['csrf_token'], $_SESSION['csrf_token'])
    || !hash_equals((string)$_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
    voltarEvolucao((int)$idConsulta, 'csrf');
}
if (!$idConsulta || $idConsulta < 1) {
    voltarEvolucao(0, 'campos');
}

$queixa = trim((string)($_POST['queixa'] ?? ''));
$exameFisico = trim((string)($_POST['exame_fisico'] ?? ''));
$evolucaoClinica = trim((string)($_POST['evolucao_clinica'] ?? ''));
$diagnostico = trim((string)($_POST['diagnostico'] ?? ''));
$conduta = trim((string)($_POST['conduta'] ?? ''));
$statusForm = (string)($_POST['status'] ?? '');
$statusPermitidos = ['em_andamento', 'finalizado', 'retorno'];

if ($evolucaoClinica === '' || $diagnostico === ''
    || strlen($queixa) > 10000 || strlen($exameFisico) > 10000
    || strlen($evolucaoClinica) > 10000 || strlen($diagnostico) > 10000
    || strlen($conduta) > 10000 || !in_array($statusForm, $statusPermitidos, true)) {
    voltarEvolucao($idConsulta, 'campos');
}

try {
    $pdo->beginTransaction();
    $stmtConsulta = $pdo->prepare("
        SELECT id_paciente, status
        FROM consultas
        WHERE id = ? AND id_medico = ?
        LIMIT 1
        FOR UPDATE
    ");
    $stmtConsulta->execute([$idConsulta, $idMedico]);
    $consulta = $stmtConsulta->fetch(PDO::FETCH_ASSOC);
    if (!$consulta || $consulta['status'] === 'cancelada') {
        $pdo->rollBack();
        voltarEvolucao($idConsulta, 'consulta');
    }
    $idPaciente = (int)$consulta['id_paciente'];

    $stmtPaciente = $pdo->prepare('SELECT id FROM paciente WHERE id = ? FOR UPDATE');
    $stmtPaciente->execute([$idPaciente]);
    if (!$stmtPaciente->fetchColumn()) {
        $pdo->rollBack();
        voltarEvolucao($idConsulta, 'consulta');
    }

    $stmtProntuario = $pdo->prepare('SELECT id FROM prontuario WHERE id_paciente = ? LIMIT 1');
    $stmtProntuario->execute([$idPaciente]);
    $idProntuario = $stmtProntuario->fetchColumn();
    if (!$idProntuario) {
        $stmtCriaProntuario = $pdo->prepare('INSERT INTO prontuario (id_paciente) VALUES (?)');
        $stmtCriaProntuario->execute([$idPaciente]);
        $idProntuario = (int)$pdo->lastInsertId();
    }

    $stmtAtendimento = $pdo->prepare('
        INSERT INTO atendimento
            (id_consulta, id_prontuario, queixa_principal, exame_fisico, diagnostico, evolucao_clinica, conduta)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ');
    $stmtAtendimento->execute([
        $idConsulta,
        (int)$idProntuario,
        $queixa,
        $exameFisico !== '' ? $exameFisico : null,
        $diagnostico,
        $evolucaoClinica,
        $conduta,
    ]);

    if ($statusForm === 'finalizado') {
        $stmtStatus = $pdo->prepare("
            UPDATE consultas
            SET status = 'realizada'
            WHERE id = ? AND id_medico = ? AND status <> 'cancelada'
        ");
        $stmtStatus->execute([$idConsulta, $idMedico]);
    }

    $pdo->commit();
    header('Location: ../evolucao.php?id_consulta=' . $idConsulta . '&sucesso=1');
    exit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Erro ao salvar evolução médica: ' . $e->getMessage());
    voltarEvolucao($idConsulta, 'falha');
}
