<?php
session_start();
require_once '../../../conexao.php';

function voltarReceita(int $consultaId, string $erro): void
{
    header('Location: ../receita.php?id_consulta=' . $consultaId . '&erro=' . rawurlencode($erro));
    exit();
}

if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'medico') {
    header('Location: ../../../auth/login.php');
    exit();
}

$idMedico = (int)$_SESSION['usuario_id'];
$idConsulta = filter_input(INPUT_POST, 'id_consulta', FILTER_VALIDATE_INT);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    voltarReceita((int)$idConsulta, 'metodo');
}
if (!isset($_POST['csrf_token'], $_SESSION['csrf_token'])
    || !hash_equals((string)$_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
    voltarReceita((int)$idConsulta, 'csrf');
}
if (!$idConsulta || $idConsulta < 1) {
    voltarReceita(0, 'dados');
}

$tiposReceita = [
    'comum' => 'Receita comum',
    'controlada' => 'Receita de controle especial',
    'antimicrobiano' => 'Receita de antimicrobiano',
];
$tipo = (string)($_POST['tipo_receita'] ?? '');
$observacoes = trim((string)($_POST['observacoes'] ?? ''));
$listaMedicamentos = json_decode((string)($_POST['medicamentos'] ?? ''), true);
$viasPermitidas = ['Oral', 'Tópica', 'Intramuscular', 'Intravenosa', 'Subcutânea', 'Inalatória'];

if (!isset($tiposReceita[$tipo]) || !is_array($listaMedicamentos)
    || count($listaMedicamentos) < 1 || count($listaMedicamentos) > 50
    || strlen($observacoes) > 10000) {
    voltarReceita($idConsulta, 'dados');
}

$medicamentosValidos = [];
foreach ($listaMedicamentos as $medicamento) {
    if (!is_array($medicamento)) {
        voltarReceita($idConsulta, 'dados');
    }
    $nome = trim((string)($medicamento['nome'] ?? ''));
    $posologia = trim((string)($medicamento['posologia'] ?? ''));
    $duracao = trim((string)($medicamento['duracao'] ?? ''));
    $via = (string)($medicamento['via'] ?? '');
    if ($nome === '' || strlen($nome) > 255
        || $posologia === '' || strlen($posologia) > 255
        || $duracao === '' || strlen($duracao) > 100
        || !in_array($via, $viasPermitidas, true)) {
        voltarReceita($idConsulta, 'dados');
    }
    $medicamentosValidos[] = [
        'nome' => $nome,
        'posologia' => $posologia,
        'duracao' => $duracao,
        'via' => $via,
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
        voltarReceita($idConsulta, 'consulta');
    }

    $stmtReceita = $pdo->prepare("
        INSERT INTO receitas (id_paciente, id_medico, tipo, observacoes, data_emissao, status)
        VALUES (?, ?, ?, ?, CURDATE(), 'ativa')
    ");
    $stmtReceita->execute([
        (int)$idPaciente,
        $idMedico,
        $tiposReceita[$tipo],
        $observacoes !== '' ? $observacoes : null,
    ]);
    $idReceita = (int)$pdo->lastInsertId();

    $stmtMedicamento = $pdo->prepare('
        INSERT INTO receita_medicamentos (id_receita, nome, posologia, duracao, via)
        VALUES (?, ?, ?, ?, ?)
    ');
    foreach ($medicamentosValidos as $medicamento) {
        $stmtMedicamento->execute([
            $idReceita,
            $medicamento['nome'],
            $medicamento['posologia'],
            $medicamento['duracao'],
            $medicamento['via'],
        ]);
    }

    $pdo->commit();
    header('Location: ../receita.php?id_consulta=' . $idConsulta . '&sucesso=1');
    exit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Erro ao emitir receita médica: ' . $e->getMessage());
    voltarReceita($idConsulta, 'falha');
}
