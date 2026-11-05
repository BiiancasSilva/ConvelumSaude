<?php
session_start();
require_once '../../../conexao.php';

function voltarAtestado(int $consultaId, string $erro): void
{
    header('Location: ../atestado.php?id_consulta=' . $consultaId . '&erro=' . rawurlencode($erro));
    exit();
}

if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'medico') {
    header('Location: ../../../auth/login.php');
    exit();
}

$idMedico = (int)$_SESSION['usuario_id'];
$idConsulta = filter_input(INPUT_POST, 'id_consulta', FILTER_VALIDATE_INT);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    voltarAtestado((int)$idConsulta, 'metodo');
}
if (!isset($_POST['csrf_token'], $_SESSION['csrf_token'])
    || !hash_equals((string)$_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
    voltarAtestado((int)$idConsulta, 'csrf');
}
if (!$idConsulta || $idConsulta < 1) {
    voltarAtestado(0, 'dados');
}

$tipos = [
    'afastamento' => 'Afastamento do trabalho/estudo',
    'comparecimento' => 'Comparecimento (sem afastamento)',
    'repouso' => 'Repouso domiciliar',
    'acompanhante' => 'Acompanhante',
];
$tipo = (string)($_POST['tipo'] ?? '');
$dataInicio = trim((string)($_POST['data_inicio'] ?? ''));
$diasBrutos = trim((string)($_POST['dias'] ?? ''));
$cid = trim((string)($_POST['cid'] ?? ''));
$observacoes = trim((string)($_POST['observacoes'] ?? ''));
$dataValida = DateTimeImmutable::createFromFormat('!Y-m-d', $dataInicio);

if (!isset($tipos[$tipo]) || !$dataValida || $dataValida->format('Y-m-d') !== $dataInicio
    || strlen($cid) > 100 || strlen($observacoes) > 10000) {
    voltarAtestado($idConsulta, 'dados');
}

$dias = null;
if (in_array($tipo, ['afastamento', 'repouso'], true)) {
    $dias = filter_var($diasBrutos, FILTER_VALIDATE_INT);
    if ($dias === false || $dias < 1 || $dias > 365) {
        voltarAtestado($idConsulta, 'dados');
    }
}

if (in_array($tipo, ['afastamento', 'repouso'], true)) {
    $dataFim = $dataValida->modify('+' . ($dias - 1) . ' days');
    $periodo = $dataValida->format('d/m/Y') . ' a ' . $dataFim->format('d/m/Y') . ' (' . $dias . ' dia(s))';
} else {
    $periodo = 'A partir de ' . $dataValida->format('d/m/Y');
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
        voltarAtestado($idConsulta, 'consulta');
    }

    $stmt = $pdo->prepare('
        INSERT INTO atestados (id_paciente, id_medico, tipo, data_emissao, periodo, cid, observacoes)
        VALUES (?, ?, ?, CURDATE(), ?, ?, ?)
    ');
    $stmt->execute([
        (int)$idPaciente,
        $idMedico,
        $tipos[$tipo],
        $periodo,
        $cid !== '' ? $cid : null,
        $observacoes !== '' ? $observacoes : null,
    ]);
    $pdo->commit();
    header('Location: ../atestado.php?id_consulta=' . $idConsulta . '&sucesso=1');
    exit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Erro ao salvar atestado médico: ' . $e->getMessage());
    voltarAtestado($idConsulta, 'falha');
}
