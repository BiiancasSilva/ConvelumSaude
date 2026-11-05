<?php
session_start();
require_once '../../../conexao.php';

if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'recepcionista') {
    header('Location: ../../../auth/login.php');
    exit();
}

function voltarAgendamento(string $mensagem): void
{
    header('Location: ../agendar-consulta.php?erro=' . rawurlencode($mensagem));
    exit();
}

function dataHoraEstrita(string $data, string $hora): ?DateTimeImmutable
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) || !preg_match('/^\d{2}:\d{2}$/', $hora)) {
        return null;
    }
    $valor = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $data . ' ' . $hora);
    return $valor && $valor->format('Y-m-d H:i') === $data . ' ' . $hora ? $valor : null;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../agendar-consulta.php');
    exit();
}
if (!isset($_POST['csrf_token'], $_SESSION['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
    voltarAgendamento('Sua sessão expirou. Atualize a página e tente novamente.');
}

$pacienteId = filter_var($_POST['paciente_id'] ?? '', FILTER_VALIDATE_INT);
$medicoId = filter_var($_POST['medico_id'] ?? '', FILTER_VALIDATE_INT);
$especialidadeId = filter_var($_POST['especialidade_id'] ?? '', FILTER_VALIDATE_INT);
$salaId = filter_var($_POST['sala_id'] ?? '', FILTER_VALIDATE_INT);
$convenioId = filter_var($_POST['convenio_id'] ?? '', FILTER_VALIDATE_INT);
$planoId = filter_var($_POST['plano_id'] ?? '', FILTER_VALIDATE_INT);
$data = trim((string)($_POST['data'] ?? ''));
$horario = trim((string)($_POST['horario'] ?? ''));
$tipoAtendimento = (string)($_POST['tipo_atendimento'] ?? '');
$status = (string)($_POST['status'] ?? '');
$possuiConvenio = (string)($_POST['possui_convenio'] ?? '');

$pacienteId = $pacienteId ? (int)$pacienteId : 0;
$medicoId = $medicoId ? (int)$medicoId : 0;
$especialidadeId = $especialidadeId ? (int)$especialidadeId : 0;
$salaId = $salaId ? (int)$salaId : null;
$convenioId = $convenioId ? (int)$convenioId : null;
$planoId = $planoId ? (int)$planoId : null;
$inicio = dataHoraEstrita($data, $horario);

if (!$pacienteId || !$medicoId || !$especialidadeId || !$inicio
    || !in_array($tipoAtendimento, ['Presencial', 'Teleconsulta'], true)
    || !in_array($status, ['pendente', 'confirmada'], true)
    || !in_array($possuiConvenio, ['0', '1'], true)) {
    voltarAgendamento('Confira paciente, tipo, especialidade, data, horário e convênio.');
}
if ($inicio <= new DateTimeImmutable()) {
    voltarAgendamento('Escolha uma data e horário futuros.');
}
if ($tipoAtendimento === 'Presencial' && !$salaId) {
    voltarAgendamento('Escolha uma sala para a consulta presencial.');
}
if ($tipoAtendimento === 'Teleconsulta') {
    $salaId = null;
}
if ($possuiConvenio === '0') {
    $convenioId = null;
    $planoId = null;
} elseif (!$convenioId) {
    voltarAgendamento('Selecione o convênio do paciente.');
}

try {
    $pdo->beginTransaction();

    $stmtPaciente = $pdo->prepare("SELECT id FROM paciente WHERE id = ? AND status = 'Ativo' FOR UPDATE");
    $stmtPaciente->execute([$pacienteId]);
    if (!$stmtPaciente->fetchColumn()) {
        throw new DomainException('O paciente selecionado não está ativo ou não existe.');
    }

    $stmtMedico = $pdo->prepare("
        SELECT m.id, m.uid, m.dias_trabalho, m.hora_inicio, m.hora_fim,
               COALESCE(m.duracao_consulta, 30) AS duracao_consulta,
               COALESCE(m.intervalo_consulta, 0) AS intervalo_consulta
        FROM medico m
        INNER JOIN medico_especialidades me ON me.id_medico = m.id
        INNER JOIN especialidade e ON e.id = me.id_especialidade
        WHERE m.id = ? AND me.id_especialidade = ?
          AND m.status = 'Ativo' AND e.status = 'Ativo'
        FOR UPDATE
    ");
    $stmtMedico->execute([$medicoId, $especialidadeId]);
    $medico = $stmtMedico->fetch(PDO::FETCH_ASSOC);
    if (!$medico) {
        throw new DomainException('O médico não está ativo ou não atende essa especialidade.');
    }

    $diasSemana = [1 => 'seg', 2 => 'ter', 3 => 'qua', 4 => 'qui', 5 => 'sex', 6 => 'sab', 7 => 'dom'];
    $diaSemana = $diasSemana[(int)$inicio->format('N')];
    $diasTrabalho = array_map('trim', explode(',', (string)$medico['dias_trabalho']));
    $duracao = max(1, (int)$medico['duracao_consulta']);
    $intervalo = max(0, (int)$medico['intervalo_consulta']);
    $fimConsulta = $inicio->modify('+' . $duracao . ' minutes');
    if (!in_array($diaSemana, $diasTrabalho, true)
        || $inicio->format('H:i:s') < (string)$medico['hora_inicio']
        || $fimConsulta->format('H:i:s') > (string)$medico['hora_fim']) {
        throw new DomainException('O médico não atende nesse dia ou horário.');
    }

    $fimReserva = $inicio->modify('+' . ($duracao + $intervalo) . ' minutes');
    $inicioSql = $inicio->format('Y-m-d H:i:s');
    $fimReservaSql = $fimReserva->format('Y-m-d H:i:s');

    $stmtAusencia = $pdo->prepare("
        SELECT COUNT(*) FROM medico_ausencias
        WHERE id_medico = ? AND data_inicio <= ? AND data_fim >= ?
    ");
    $stmtAusencia->execute([$medicoId, $data, $data]);
    if ((int)$stmtAusencia->fetchColumn() > 0) {
        throw new DomainException('O médico está ausente nessa data.');
    }

    $stmtBloqueioAgendaMedico = $pdo->prepare("
        SELECT COUNT(*) FROM bloqueio_agenda
        WHERE id_medico = ? AND data_inicio < ? AND data_fim > ?
    ");
    $stmtBloqueioAgendaMedico->execute([$medicoId, $fimReservaSql, $inicioSql]);
    if ((int)$stmtBloqueioAgendaMedico->fetchColumn() > 0) {
        throw new DomainException('O horário escolhido está bloqueado para esse médico.');
    }

    $stmtBloqueioHorario = $pdo->prepare("
        SELECT COUNT(*) FROM medico_bloqueios
        WHERE id_medico = ? AND data_bloqueio = ?
          AND hora_inicio < ? AND hora_fim > ?
    ");
    $stmtBloqueioHorario->execute([
        $medicoId,
        $data,
        $fimReserva->format('H:i:s'),
        $inicio->format('H:i:s'),
    ]);
    if ((int)$stmtBloqueioHorario->fetchColumn() > 0) {
        throw new DomainException('O horário escolhido está bloqueado para esse médico.');
    }

    $stmtBloqueioCalendario = $pdo->prepare("
        SELECT COUNT(*) FROM bloqueios
        WHERE (tipo_alvo = 'geral' OR (tipo_alvo = 'medico' AND alvo_id = ?))
          AND CONCAT(data_inicio, ' ', COALESCE(hora_inicio, '00:00:00')) < ?
          AND CONCAT(data_fim, ' ', COALESCE(hora_fim, '23:59:59')) > ?
    ");
    $stmtBloqueioCalendario->execute([(string)$medico['uid'], $fimReservaSql, $inicioSql]);
    if ((int)$stmtBloqueioCalendario->fetchColumn() > 0) {
        throw new DomainException('A clínica ou o médico está bloqueado nesse horário.');
    }

    $stmtConflitoMedico = $pdo->prepare("
        SELECT COUNT(*)
        FROM consultas c
        INNER JOIN medico m2 ON m2.id = c.id_medico
        WHERE c.id_medico = ? AND c.status <> 'cancelada'
          AND c.data_hora < ?
          AND DATE_ADD(c.data_hora, INTERVAL
              (COALESCE(m2.duracao_consulta, 30) + COALESCE(m2.intervalo_consulta, 0)) MINUTE) > ?
    ");
    $stmtConflitoMedico->execute([$medicoId, $fimReservaSql, $inicioSql]);
    if ((int)$stmtConflitoMedico->fetchColumn() > 0) {
        throw new DomainException('Esse médico acabou de ficar indisponível nesse horário. Escolha outro.');
    }

    $stmtConflitoPaciente = $pdo->prepare("
        SELECT COUNT(*)
        FROM consultas c
        INNER JOIN medico m2 ON m2.id = c.id_medico
        WHERE c.id_paciente = ? AND c.status <> 'cancelada'
          AND c.data_hora < ?
          AND DATE_ADD(c.data_hora, INTERVAL
              (COALESCE(m2.duracao_consulta, 30) + COALESCE(m2.intervalo_consulta, 0)) MINUTE) > ?
    ");
    $stmtConflitoPaciente->execute([$pacienteId, $fimReservaSql, $inicioSql]);
    if ((int)$stmtConflitoPaciente->fetchColumn() > 0) {
        throw new DomainException('O paciente já possui outra consulta nesse horário.');
    }

    if ($possuiConvenio === '1') {
        $stmtConvenio = $pdo->prepare("SELECT id FROM convenios WHERE id = ? AND status = 'Ativo'");
        $stmtConvenio->execute([$convenioId]);
        if (!$stmtConvenio->fetchColumn()) {
            throw new DomainException('O convênio selecionado não está ativo.');
        }
        $stmtPlanos = $pdo->prepare("SELECT id FROM convenio_planos WHERE id_convenio = ?");
        $stmtPlanos->execute([$convenioId]);
        $planosValidos = array_map('intval', $stmtPlanos->fetchAll(PDO::FETCH_COLUMN));
        if ($planosValidos && (!$planoId || !in_array($planoId, $planosValidos, true))) {
            throw new DomainException('Selecione um plano válido para esse convênio.');
        }
        if (!$planosValidos && $planoId !== null) {
            throw new DomainException('O convênio selecionado não possui esse plano.');
        }
    }

    if ($tipoAtendimento === 'Presencial') {
        $stmtSala = $pdo->prepare("
            SELECT id FROM salas
            WHERE id = ? AND LOWER(status) NOT IN ('bloqueada', 'inativo')
              AND (id_medico IS NULL OR id_medico = ?)
            FOR UPDATE
        ");
        $stmtSala->execute([$salaId, $medicoId]);
        if (!$stmtSala->fetchColumn()) {
            throw new DomainException('A sala selecionada não está disponível para esse médico.');
        }

        $stmtConflitoSala = $pdo->prepare("
            SELECT COUNT(*)
            FROM consultas c
            INNER JOIN medico m2 ON m2.id = c.id_medico
            WHERE c.id_sala = ? AND c.status <> 'cancelada'
              AND c.data_hora < ?
              AND DATE_ADD(c.data_hora, INTERVAL
                  (COALESCE(m2.duracao_consulta, 30) + COALESCE(m2.intervalo_consulta, 0)) MINUTE) > ?
        ");
        $stmtConflitoSala->execute([$salaId, $fimReservaSql, $inicioSql]);
        if ((int)$stmtConflitoSala->fetchColumn() > 0) {
            throw new DomainException('A sala escolhida acabou de ser ocupada. Selecione outra sala.');
        }

        $stmtBloqueioSala = $pdo->prepare("
            SELECT COUNT(*) FROM bloqueio_agenda
            WHERE id_sala = ? AND data_inicio < ? AND data_fim > ?
        ");
        $stmtBloqueioSala->execute([$salaId, $fimReservaSql, $inicioSql]);
        if ((int)$stmtBloqueioSala->fetchColumn() > 0) {
            throw new DomainException('A sala escolhida está bloqueada nesse horário.');
        }

        $stmtSalaCalendario = $pdo->prepare("
            SELECT COUNT(*) FROM bloqueios
            WHERE tipo_alvo = 'geral'
              AND CONCAT(data_inicio, ' ', COALESCE(hora_inicio, '00:00:00')) < ?
              AND CONCAT(data_fim, ' ', COALESCE(hora_fim, '23:59:59')) > ?
        ");
        $stmtSalaCalendario->execute([$fimReservaSql, $inicioSql]);
        if ((int)$stmtSalaCalendario->fetchColumn() > 0) {
            throw new DomainException('A clínica está bloqueada nesse horário.');
        }
    }

    $stmtInsert = $pdo->prepare("
        INSERT INTO consultas (
            id_paciente, id_medico, id_especialidade, id_sala, data_hora,
            tipo_atendimento, agendado_por, status, id_convenio, id_convenios, id_plano
        ) VALUES (?, ?, ?, ?, ?, ?, 'Recepcionista', ?, ?, ?, ?)
    ");
    $stmtInsert->execute([
        $pacienteId,
        $medicoId,
        $especialidadeId,
        $salaId,
        $inicioSql,
        $tipoAtendimento,
        $status,
        $convenioId,
        $convenioId,
        $planoId,
    ]);

    $pdo->commit();
    header('Location: ../painel.php?agendado=sucesso');
    exit();
} catch (DomainException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    voltarAgendamento($e->getMessage());
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Erro ao salvar agendamento pela recepção: ' . $e->getMessage());
    voltarAgendamento('Não foi possível salvar o agendamento. Confira a disponibilidade e tente novamente.');
}
