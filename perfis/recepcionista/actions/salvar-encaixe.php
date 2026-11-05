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

function voltarComErro(string $mensagem): void
{
    global $pdo;
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: ../inserir-encaixe.php?erro=' . urlencode($mensagem));
    exit();
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!is_string($csrfToken) || empty($_SESSION['csrf_encaixes']) || !hash_equals($_SESSION['csrf_encaixes'], $csrfToken)) {
    voltarComErro('Sua sessão expirou. Atualize a página e tente novamente.');
}

$paciente = isset($_POST['paciente']) && is_string($_POST['paciente']) ? trim($_POST['paciente']) : '';
$rawMedico = $_POST['medico'] ?? null;
$idMedico = is_string($rawMedico)
    ? filter_var($rawMedico, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
$data = isset($_POST['data']) && is_string($_POST['data']) ? $_POST['data'] : '';
$horario = isset($_POST['horario']) && is_string($_POST['horario']) ? $_POST['horario'] : '';
$tipo = isset($_POST['tipo']) && is_string($_POST['tipo']) ? $_POST['tipo'] : '';
$motivo = isset($_POST['motivo']) && is_string($_POST['motivo']) ? trim($_POST['motivo']) : '';

if ($paciente === '' || strlen($paciente) > 200 || !$idMedico || $data === '' || $horario === '') {
    voltarComErro('Preencha o paciente, médico, data e horário corretamente.');
}
if (!in_array($tipo, ['presencial', 'tele'], true)) {
    voltarComErro('Selecione um tipo de atendimento válido.');
}
if (strlen($motivo) > 5000) {
    voltarComErro('O motivo informado é muito longo.');
}

$inicioDt = DateTime::createFromFormat('!Y-m-d H:i', $data . ' ' . $horario);
$dateErrors = DateTime::getLastErrors();
if (
    !$inicioDt ||
    ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0)) ||
    $inicioDt->format('Y-m-d H:i') !== $data . ' ' . $horario ||
    !preg_match('/^\d{2}:\d{2}$/', $horario) ||
    $data < date('Y-m-d') ||
    ($data === date('Y-m-d') && $inicioDt < new DateTime())
) {
    voltarComErro('Data ou horário inválido ou já passado.');
}

try {
    $stmtMed = $pdo->prepare("
        SELECT id, nome, dias_trabalho, hora_inicio, hora_fim, duracao_consulta, intervalo_consulta
        FROM medico
        WHERE id = ?
        LIMIT 1
    ");
    $stmtMed->execute([$idMedico]);
    $medico = $stmtMed->fetch(PDO::FETCH_ASSOC);
    if (!$medico) {
        voltarComErro('Médico não encontrado. Selecione um médico cadastrado.');
    }

    $mapaDias = [1 => 'seg', 2 => 'ter', 3 => 'qua', 4 => 'qui', 5 => 'sex', 6 => 'sab', 7 => 'dom'];
    $diasTrabalho = array_map('trim', explode(',', strtolower($medico['dias_trabalho'] ?? '')));
    if (!in_array($mapaDias[(int)$inicioDt->format('N')], $diasTrabalho, true)) {
        voltarComErro('O médico não atende nesse dia da semana.');
    }

    $duracao = (int)($medico['duracao_consulta'] ?? 30);
    $intervalo = max(0, (int)($medico['intervalo_consulta'] ?? 0));
    if ($duracao <= 0) {
        $duracao = 30;
    }
    $passo = $duracao + $intervalo;
    $horaInicio = new DateTime($data . ' ' . $medico['hora_inicio']);
    $horaFim = new DateTime($data . ' ' . $medico['hora_fim']);
    $fimDt = (clone $inicioDt)->modify('+' . $duracao . ' minutes');
    $minutosDesdeInicio = ((int)$inicioDt->format('H') * 60 + (int)$inicioDt->format('i')) -
        ((int)$horaInicio->format('H') * 60 + (int)$horaInicio->format('i'));
    if (
        $minutosDesdeInicio < 0 ||
        $minutosDesdeInicio % $passo !== 0 ||
        $fimDt > $horaFim
    ) {
        voltarComErro('O horário selecionado não pertence à agenda desse médico.');
    }

    $stmtAusencia = $pdo->prepare("
        SELECT COUNT(*) FROM medico_ausencias
        WHERE id_medico = ? AND ? BETWEEN data_inicio AND data_fim
    ");
    $stmtAusencia->execute([$medico['id'], $data]);
    if ((int)$stmtAusencia->fetchColumn() > 0) {
        voltarComErro('O médico está ausente nessa data.');
    }

    $stmtBloqueio = $pdo->prepare("
        SELECT data_inicio, data_fim FROM bloqueio_agenda
        WHERE id_medico = ? AND ? BETWEEN DATE(data_inicio) AND DATE(data_fim)
    ");
    $stmtBloqueio->execute([$medico['id'], $data]);
    foreach ($stmtBloqueio->fetchAll(PDO::FETCH_ASSOC) as $bloqueio) {
        if (new DateTime($bloqueio['data_inicio']) < $fimDt && new DateTime($bloqueio['data_fim']) > $inicioDt) {
            voltarComErro('O horário selecionado está bloqueado na agenda do médico.');
        }
    }

    $stmtBloqueioHora = $pdo->prepare("
        SELECT hora_inicio, hora_fim FROM medico_bloqueios
        WHERE id_medico = ? AND data_bloqueio = ?
    ");
    $stmtBloqueioHora->execute([$medico['id'], $data]);
    foreach ($stmtBloqueioHora->fetchAll(PDO::FETCH_ASSOC) as $bloqueio) {
        $inicioBloqueio = new DateTime($data . ' ' . $bloqueio['hora_inicio']);
        $fimBloqueio = new DateTime($data . ' ' . $bloqueio['hora_fim']);
        if ($inicioBloqueio < $fimDt && $fimBloqueio > $inicioDt) {
            voltarComErro('O horário selecionado está bloqueado para esse médico.');
        }
    }

    $inicio = $inicioDt->format('Y-m-d H:i:s');
    $fim = $fimDt->format('Y-m-d H:i:s');
    $pdo->beginTransaction();

    $stmtTrava = $pdo->prepare('SELECT id FROM medico WHERE id = ? FOR UPDATE');
    $stmtTrava->execute([$medico['id']]);
    $stmtTrava->fetch();

    $stmtConflitoMedico = $pdo->prepare("
        SELECT COUNT(*) FROM consultas
        WHERE id_medico = ?
          AND status <> 'cancelada'
          AND data_hora < ?
          AND TIMESTAMPADD(MINUTE, ?, data_hora) > ?
    ");
    $stmtConflitoMedico->execute([$medico['id'], $fim, $duracao, $inicio]);
    if ((int)$stmtConflitoMedico->fetchColumn() > 0) {
        voltarComErro('Esse médico já tem uma consulta nesse horário. Escolha outro horário.');
    }

    $stmtConflitoMedico = $pdo->prepare("
        SELECT COUNT(*) FROM encaixes
        WHERE medico = ? AND status <> 'cancelada' AND data_encaixe = ?
          AND CAST(CONCAT(data_encaixe, ' ', LEFT(horario, 5), ':00') AS DATETIME) < ?
          AND TIMESTAMPADD(MINUTE, ?, CAST(CONCAT(data_encaixe, ' ', LEFT(horario, 5), ':00') AS DATETIME)) > ?
    ");
    $stmtConflitoMedico->execute([$medico['nome'], $data, $fim, $duracao, $inicio]);
    if ((int)$stmtConflitoMedico->fetchColumn() > 0) {
        voltarComErro('Já existe uma consulta ou encaixe desse médico nesse horário.');
    }

    $stmtConflitoPaciente = $pdo->prepare("
        SELECT COUNT(*) FROM consultas c
        INNER JOIN paciente p ON p.id = c.id_paciente
        INNER JOIN medico m ON m.id = c.id_medico
        WHERE p.nome = ? AND c.status <> 'cancelada'
          AND c.data_hora < ?
          AND TIMESTAMPADD(MINUTE, COALESCE(m.duracao_consulta, 30), c.data_hora) > ?
    ");
    $stmtConflitoPaciente->execute([$paciente, $fim, $inicio]);
    if ((int)$stmtConflitoPaciente->fetchColumn() > 0) {
        voltarComErro('Esse paciente já tem uma consulta marcada nesse horário.');
    }

    $stmtConflitoPaciente = $pdo->prepare("
        SELECT COUNT(*) FROM encaixes
        WHERE paciente = ? AND status <> 'cancelada' AND data_encaixe = ?
          AND CAST(CONCAT(data_encaixe, ' ', LEFT(horario, 5), ':00') AS DATETIME) < ?
          AND TIMESTAMPADD(MINUTE, ?, CAST(CONCAT(data_encaixe, ' ', LEFT(horario, 5), ':00') AS DATETIME)) > ?
    ");
    $stmtConflitoPaciente->execute([$paciente, $data, $fim, $duracao, $inicio]);
    if ((int)$stmtConflitoPaciente->fetchColumn() > 0) {
        voltarComErro('Esse paciente já tem um encaixe marcado nesse horário.');
    }

    $stmtInsert = $pdo->prepare("
        INSERT INTO encaixes (paciente, medico, data_encaixe, horario, tipo_atendimento, motivo, criado_em)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmtInsert->execute([$paciente, $medico['nome'], $data, $horario, $tipo, $motivo]);
    $pdo->commit();

    header('Location: ../inserir-encaixe.php?sucesso=1');
    exit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Falha ao inserir encaixe: ' . $e->getMessage());
    voltarComErro('Não foi possível salvar o encaixe. Verifique os dados e tente novamente.');
}
