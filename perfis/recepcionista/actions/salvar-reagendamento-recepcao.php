<?php
session_start();
require_once '../../../conexao.php';

if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'recepcionista') {
    header('Location: ../../../auth/login.php');
    exit();
}

function voltarReagendamento(int $consultaId, string $destino, string $mensagem): void
{
    global $pdo;
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($destino === 'detalhes') {
        header('Location: ../detalhes-consulta.php?id=' . $consultaId . '&msg=' . rawurlencode($mensagem));
    } else {
        header('Location: ../reagendar-consulta.php?id=' . $consultaId . '&erro=' . rawurlencode($mensagem));
    }
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../painel.php');
    exit();
}

$rawId = $_POST['consulta_id'] ?? null;
$consultaId = is_string($rawId)
    ? filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
if (!$consultaId) {
    header('Location: ../painel.php');
    exit();
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (
    !is_string($csrfToken) ||
    empty($_SESSION['csrf_consultas_detalhes']) ||
    !hash_equals($_SESSION['csrf_consultas_detalhes'], $csrfToken)
) {
    voltarReagendamento($consultaId, 'formulario', 'csrf');
}

$novaData = isset($_POST['nova_data']) && is_string($_POST['nova_data']) ? $_POST['nova_data'] : '';
$novoHorario = isset($_POST['novo_horario']) && is_string($_POST['novo_horario']) ? $_POST['novo_horario'] : '';
$dataHoraNova = DateTime::createFromFormat('!Y-m-d H:i', $novaData . ' ' . $novoHorario);
$dateErrors = DateTime::getLastErrors();
if (
    !$dataHoraNova ||
    ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0)) ||
    $dataHoraNova->format('Y-m-d H:i') !== $novaData . ' ' . $novoHorario ||
    !preg_match('/^\d{2}:\d{2}$/', $novoHorario) ||
    $dataHoraNova <= new DateTime()
) {
    voltarReagendamento($consultaId, 'formulario', 'dados_invalidos');
}

try {
    $pdo->beginTransaction();
    $stmtConsulta = $pdo->prepare("
        SELECT c.id, c.id_paciente, c.id_medico, c.id_sala, c.data_hora, c.status,
               c.tipo_atendimento, p.nome AS paciente
        FROM consultas c
        INNER JOIN paciente p ON p.id = c.id_paciente
        WHERE c.id = ?
        LIMIT 1
        FOR UPDATE
    ");
    $stmtConsulta->execute([$consultaId]);
    $consulta = $stmtConsulta->fetch(PDO::FETCH_ASSOC);
    if (
        !$consulta ||
        !in_array($consulta['status'], ['pendente', 'confirmada'], true) ||
        $consulta['data_hora'] <= date('Y-m-d H:i:s')
    ) {
        throw new DomainException('nao_reagendavel');
    }

    $stmtMedico = $pdo->prepare("
        SELECT id, nome, dias_trabalho, hora_inicio, hora_fim, duracao_consulta, intervalo_consulta, status
        FROM medico
        WHERE id = ?
        LIMIT 1
        FOR UPDATE
    ");
    $stmtMedico->execute([(int)$consulta['id_medico']]);
    $medico = $stmtMedico->fetch(PDO::FETCH_ASSOC);
    if (!$medico || $medico['status'] !== 'Ativo') {
        throw new DomainException('indisponivel');
    }

    $mapaDias = [1 => 'seg', 2 => 'ter', 3 => 'qua', 4 => 'qui', 5 => 'sex', 6 => 'sab', 7 => 'dom'];
    $diasTrabalho = array_map('trim', explode(',', strtolower($medico['dias_trabalho'] ?? '')));
    if (!in_array($mapaDias[(int)$dataHoraNova->format('N')], $diasTrabalho, true)) {
        throw new DomainException('indisponivel');
    }

    $duracao = (int)($medico['duracao_consulta'] ?? 30);
    if ($duracao <= 0) {
        $duracao = 30;
    }
    $intervalo = max(0, (int)($medico['intervalo_consulta'] ?? 0));
    $passo = $duracao + $intervalo;
    $horaInicio = new DateTime($novaData . ' ' . $medico['hora_inicio']);
    $horaFim = new DateTime($novaData . ' ' . $medico['hora_fim']);
    $inicioMinutos = (int)$dataHoraNova->format('H') * 60 + (int)$dataHoraNova->format('i');
    $horaInicioMinutos = (int)$horaInicio->format('H') * 60 + (int)$horaInicio->format('i');
    $fimNovaConsulta = (clone $dataHoraNova)->modify('+' . $duracao . ' minutes');
    if (
        $passo <= 0 ||
        $inicioMinutos < $horaInicioMinutos ||
        ($inicioMinutos - $horaInicioMinutos) % $passo !== 0 ||
        $fimNovaConsulta > $horaFim
    ) {
        throw new DomainException('indisponivel');
    }

    $stmtAusencia = $pdo->prepare("
        SELECT COUNT(*) FROM medico_ausencias
        WHERE id_medico = ? AND ? BETWEEN data_inicio AND data_fim
    ");
    $stmtAusencia->execute([(int)$medico['id'], $novaData]);
    if ((int)$stmtAusencia->fetchColumn() > 0) {
        throw new DomainException('indisponivel');
    }

    $inicioSql = $dataHoraNova->format('Y-m-d H:i:s');
    $fimSql = $fimNovaConsulta->format('Y-m-d H:i:s');
    $stmtBloqueioAgenda = $pdo->prepare("
        SELECT data_inicio, data_fim FROM bloqueio_agenda
        WHERE id_medico = ? AND ? BETWEEN DATE(data_inicio) AND DATE(data_fim)
    ");
    $stmtBloqueioAgenda->execute([(int)$medico['id'], $novaData]);
    foreach ($stmtBloqueioAgenda->fetchAll(PDO::FETCH_ASSOC) as $bloqueio) {
        if (new DateTime($bloqueio['data_inicio']) < $fimNovaConsulta && new DateTime($bloqueio['data_fim']) > $dataHoraNova) {
            throw new DomainException('indisponivel');
        }
    }

    $stmtBloqueioHora = $pdo->prepare("
        SELECT hora_inicio, hora_fim FROM medico_bloqueios
        WHERE id_medico = ? AND data_bloqueio = ?
    ");
    $stmtBloqueioHora->execute([(int)$medico['id'], $novaData]);
    foreach ($stmtBloqueioHora->fetchAll(PDO::FETCH_ASSOC) as $bloqueio) {
        $inicioBloqueio = new DateTime($novaData . ' ' . $bloqueio['hora_inicio']);
        $fimBloqueio = new DateTime($novaData . ' ' . $bloqueio['hora_fim']);
        if ($inicioBloqueio < $fimNovaConsulta && $fimBloqueio > $dataHoraNova) {
            throw new DomainException('indisponivel');
        }
    }

    $stmtMedicoConflito = $pdo->prepare("
        SELECT COUNT(*)
        FROM consultas c
        INNER JOIN medico m ON m.id = c.id_medico
        WHERE c.id_medico = ? AND c.id <> ? AND c.status <> 'cancelada'
          AND c.data_hora < ?
          AND TIMESTAMPADD(MINUTE, COALESCE(m.duracao_consulta, 30), c.data_hora) > ?
    ");
    $stmtMedicoConflito->execute([(int)$medico['id'], $consultaId, $fimSql, $inicioSql]);
    if ((int)$stmtMedicoConflito->fetchColumn() > 0) {
        throw new DomainException('indisponivel');
    }

    $stmtEncaixeMedico = $pdo->prepare("
        SELECT COUNT(*) FROM encaixes
        WHERE medico = ? AND data_encaixe = ? AND status <> 'cancelada'
          AND CAST(CONCAT(data_encaixe, ' ', LEFT(horario, 5), ':00') AS DATETIME) < ?
          AND TIMESTAMPADD(MINUTE, ?, CAST(CONCAT(data_encaixe, ' ', LEFT(horario, 5), ':00') AS DATETIME)) > ?
    ");
    $stmtEncaixeMedico->execute([$medico['nome'], $novaData, $fimSql, $duracao, $inicioSql]);
    if ((int)$stmtEncaixeMedico->fetchColumn() > 0) {
        throw new DomainException('indisponivel');
    }

    $stmtPacienteConflito = $pdo->prepare("
        SELECT COUNT(*)
        FROM consultas c
        INNER JOIN medico m ON m.id = c.id_medico
        WHERE c.id_paciente = ? AND c.id <> ? AND c.status <> 'cancelada'
          AND c.data_hora < ?
          AND TIMESTAMPADD(MINUTE, COALESCE(m.duracao_consulta, 30), c.data_hora) > ?
    ");
    $stmtPacienteConflito->execute([(int)$consulta['id_paciente'], $consultaId, $fimSql, $inicioSql]);
    if ((int)$stmtPacienteConflito->fetchColumn() > 0) {
        throw new DomainException('indisponivel');
    }

    $stmtEncaixePaciente = $pdo->prepare("
        SELECT COUNT(*) FROM encaixes
        WHERE paciente = ? AND data_encaixe = ? AND status <> 'cancelada'
          AND CAST(CONCAT(data_encaixe, ' ', LEFT(horario, 5), ':00') AS DATETIME) < ?
          AND TIMESTAMPADD(MINUTE, ?, CAST(CONCAT(data_encaixe, ' ', LEFT(horario, 5), ':00') AS DATETIME)) > ?
    ");
    $stmtEncaixePaciente->execute([$consulta['paciente'], $novaData, $fimSql, $duracao, $inicioSql]);
    if ((int)$stmtEncaixePaciente->fetchColumn() > 0) {
        throw new DomainException('indisponivel');
    }

    if ($consulta['id_sala'] !== null) {
        $stmtSala = $pdo->prepare("
            SELECT COUNT(*)
            FROM consultas c
            INNER JOIN medico m ON m.id = c.id_medico
            WHERE c.id_sala = ? AND c.id <> ? AND c.status <> 'cancelada'
              AND c.data_hora < ?
              AND TIMESTAMPADD(MINUTE, COALESCE(m.duracao_consulta, 30), c.data_hora) > ?
        ");
        $stmtSala->execute([(int)$consulta['id_sala'], $consultaId, $fimSql, $inicioSql]);
        if ((int)$stmtSala->fetchColumn() > 0) {
            throw new DomainException('indisponivel');
        }
    }

    $stmtAtualiza = $pdo->prepare("
        UPDATE consultas
        SET data_hora = ?, checkin_em = NULL
        WHERE id = ? AND status IN ('pendente', 'confirmada')
    ");
    $stmtAtualiza->execute([$inicioSql, $consultaId]);
    if ($stmtAtualiza->rowCount() !== 1) {
        throw new DomainException('nao_reagendavel');
    }

    $pdo->commit();
    voltarReagendamento($consultaId, 'detalhes', 'remarcada');
} catch (DomainException $e) {
    if ($e->getMessage() === 'nao_reagendavel') {
        voltarReagendamento($consultaId, 'formulario', 'nao_reagendavel');
    }
    voltarReagendamento($consultaId, 'formulario', 'indisponivel');
} catch (PDOException $e) {
    error_log('Falha ao reagendar consulta #' . $consultaId . ': ' . $e->getMessage());
    voltarReagendamento($consultaId, 'formulario', 'erro');
}
