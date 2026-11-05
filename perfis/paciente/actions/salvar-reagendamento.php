<?php
// perfis/paciente/actions/salvar-reagendamento.php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../../../conexao.php';

function responderReagendamento(bool $sucesso, string $mensagem = ''): void
{
    echo json_encode([
        'sucesso' => $sucesso,
        'erro' => $sucesso ? '' : $mensagem,
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'paciente') {
    http_response_code(403);
    responderReagendamento(false, 'Sessão expirada ou acesso negado.');
}

$pacienteId  = (int)$_SESSION['usuario_id'];
$consultaId  = filter_input(INPUT_POST, 'consulta_id', FILTER_VALIDATE_INT);
$novaData    = trim((string)($_POST['nova_data'] ?? ''));
$novoHorario = trim((string)($_POST['novo_horario'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$consultaId || $novaData === '' || $novoHorario === '') {
    responderReagendamento(false, 'Dados incompletos para atualizar a consulta.');
}

$dataObj = DateTimeImmutable::createFromFormat('!Y-m-d', $novaData);
$horaObj = DateTimeImmutable::createFromFormat('!H:i', $novoHorario);
if (!$dataObj || $dataObj->format('Y-m-d') !== $novaData
    || !$horaObj || $horaObj->format('H:i') !== $novoHorario) {
    responderReagendamento(false, 'Informe uma data e um horário válidos.');
}

$horariosPermitidos = ['08:00', '08:30', '09:30', '10:00', '10:30', '13:30', '14:00', '15:00', '16:30'];
if (!in_array($novoHorario, $horariosPermitidos, true)) {
    responderReagendamento(false, 'O horário selecionado não está disponível para reagendamento.');
}

$novaDataHora = $novaData . ' ' . $novoHorario . ':00';
if ($novaDataHora <= date('Y-m-d H:i:s')) {
    responderReagendamento(false, 'Escolha uma data e um horário futuros.');
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
try {
    $pdo->beginTransaction();

    $stmtConsulta = $pdo->prepare("
        SELECT c.id, c.id_medico, c.data_hora
        FROM consultas c
        WHERE c.id = ?
          AND c.id_paciente = ?
          AND c.status IN ('pendente', 'confirmada')
          AND c.data_hora > NOW()
        LIMIT 1
        FOR UPDATE
    ");
    $stmtConsulta->execute([$consultaId, $pacienteId]);
    $consulta = $stmtConsulta->fetch(PDO::FETCH_ASSOC);
    if (!$consulta) {
        throw new DomainException('A consulta não foi encontrada, já passou ou não pode mais ser reagendada.');
    }

    $stmtMedico = $pdo->prepare("
        SELECT m.dias_trabalho, m.hora_inicio, m.hora_fim,
               COALESCE(m.duracao_consulta, 30) AS duracao_consulta,
               COALESCE(m.intervalo_consulta, 0) AS intervalo_consulta
        FROM medico m
        WHERE m.id = ? AND m.status = 'Ativo'
        LIMIT 1
        FOR UPDATE
    ");
    $stmtMedico->execute([(int)$consulta['id_medico']]);
    $medico = $stmtMedico->fetch(PDO::FETCH_ASSOC);
    if (!$medico) {
        throw new DomainException('O médico desta consulta não está ativo para reagendamento.');
    }

    $diasSemana = [1 => 'seg', 2 => 'ter', 3 => 'qua', 4 => 'qui', 5 => 'sex', 6 => 'sab', 7 => 'dom'];
    $diaSemana = $diasSemana[(int)$dataObj->format('N')];
    $diasTrabalho = array_map('trim', explode(',', (string)$medico['dias_trabalho']));
    $inicioConsulta = new DateTimeImmutable($novaDataHora);
    $fimConsulta = $inicioConsulta->modify('+' . (int)$medico['duracao_consulta'] . ' minutes');
    if (!in_array($diaSemana, $diasTrabalho, true)
        || $novoHorario < substr((string)$medico['hora_inicio'], 0, 5)
        || $fimConsulta->format('H:i:s') > (string)$medico['hora_fim']) {
        throw new DomainException('O médico não atende nesse dia ou horário. Escolha outro.');
    }

    $stmtConflito = $pdo->prepare("
        SELECT COUNT(*)
        FROM consultas
        WHERE id_medico = ?
          AND data_hora = ?
          AND id <> ?
          AND status <> 'cancelada'
    ");
    $stmtConflito->execute([(int)$consulta['id_medico'], $novaDataHora, $consultaId]);
    if ((int)$stmtConflito->fetchColumn() > 0) {
        throw new DomainException('Esse horário acabou de ser reservado. Escolha outro.');
    }

    $stmtBloqueio = $pdo->prepare("
        SELECT COUNT(*)
        FROM bloqueio_agenda
        WHERE (id_medico = ? OR id_medico IS NULL)
          AND ? BETWEEN data_inicio AND data_fim
    ");
    $stmtBloqueio->execute([(int)$consulta['id_medico'], $novaDataHora]);
    if ((int)$stmtBloqueio->fetchColumn() > 0) {
        throw new DomainException('O médico está indisponível nesse horário. Escolha outro.');
    }

    $stmtAusencia = $pdo->prepare("
        SELECT COUNT(*)
        FROM medico_ausencias
        WHERE id_medico = ?
          AND ? BETWEEN data_inicio AND data_fim
    ");
    $stmtAusencia->execute([(int)$consulta['id_medico'], $novaData]);
    if ((int)$stmtAusencia->fetchColumn() > 0) {
        throw new DomainException('O médico está ausente nessa data. Escolha outro dia.');
    }

    $fimReserva = $inicioConsulta->modify(
        '+' . ((int)$medico['duracao_consulta'] + (int)$medico['intervalo_consulta']) . ' minutes'
    );
    $stmtBloqueioHora = $pdo->prepare("
        SELECT COUNT(*)
        FROM medico_bloqueios
        WHERE id_medico = ? AND data_bloqueio = ?
          AND hora_inicio < ? AND hora_fim > ?
    ");
    $stmtBloqueioHora->execute([
        (int)$consulta['id_medico'],
        $novaData,
        $fimReserva->format('H:i:s'),
        $inicioConsulta->format('H:i:s'),
    ]);
    if ((int)$stmtBloqueioHora->fetchColumn() > 0) {
        throw new DomainException('O médico está bloqueado nesse horário. Escolha outro.');
    }

    $stmtAtualiza = $pdo->prepare("
        UPDATE consultas
        SET data_hora = ?
        WHERE id = ?
          AND id_paciente = ?
          AND status IN ('pendente', 'confirmada')
    ");
    $stmtAtualiza->execute([$novaDataHora, $consultaId, $pacienteId]);
    $pdo->commit();

    responderReagendamento(true);
} catch (DomainException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    responderReagendamento(false, $e->getMessage());
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Erro ao reagendar consulta do paciente: ' . $e->getMessage());
    http_response_code(500);
    responderReagendamento(false, 'Não foi possível reagendar agora. Tente novamente.');
}