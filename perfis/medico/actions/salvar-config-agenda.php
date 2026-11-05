<?php
session_start();
require_once '../../../conexao.php';

if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'medico') {
    header('Location: ../../../auth/login.php');
    exit();
}

function voltarAgendaMedico(string $erro = ''): void
{
    header('Location: ../agenda-configurar.php' . ($erro !== '' ? '?erro=' . rawurlencode($erro) : ''));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    voltarAgendaMedico();
}
if (!isset($_POST['csrf_token'], $_SESSION['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
    voltarAgendaMedico('csrf');
}

$medicoId = filter_var($_SESSION['usuario_id'], FILTER_VALIDATE_INT);
$diasPermitidos = ['seg', 'ter', 'qua', 'qui', 'sex', 'sab', 'dom'];
$diasPostados = array_values(array_unique(array_filter(array_map('trim', explode(',', (string)($_POST['dias'] ?? ''))))));
$horaInicio = (string)($_POST['hora_inicio'] ?? '');
$horaFim = (string)($_POST['hora_fim'] ?? '');
$duracao = filter_var($_POST['duracao'] ?? '', FILTER_VALIDATE_INT);
$intervalo = filter_var($_POST['intervalo'] ?? '', FILTER_VALIDATE_INT);
$duracoesPermitidas = [15, 20, 30, 45, 60];
$intervalosPermitidos = [0, 5, 10, 15];

if (!$medicoId || !$diasPostados || array_diff($diasPostados, $diasPermitidos)
    || !preg_match('/^\d{2}:\d{2}$/', $horaInicio)
    || !preg_match('/^\d{2}:\d{2}$/', $horaFim)
    || !in_array($duracao, $duracoesPermitidas, true)
    || !in_array($intervalo, $intervalosPermitidos, true)) {
    voltarAgendaMedico('agenda');
}

$inicio = DateTimeImmutable::createFromFormat('!H:i', $horaInicio);
$fim = DateTimeImmutable::createFromFormat('!H:i', $horaFim);
if (!$inicio || $inicio->format('H:i') !== $horaInicio
    || !$fim || $fim->format('H:i') !== $horaFim || $fim <= $inicio) {
    voltarAgendaMedico('agenda');
}

$diasPorNumero = [1 => 'seg', 2 => 'ter', 3 => 'qua', 4 => 'qui', 5 => 'sex', 6 => 'sab', 7 => 'dom'];
try {
    $pdo->beginTransaction();

    $stmtMedico = $pdo->prepare("SELECT id, nome FROM medico WHERE id = ? FOR UPDATE");
    $stmtMedico->execute([$medicoId]);
    $medico = $stmtMedico->fetch(PDO::FETCH_ASSOC);
    if (!$medico) {
        throw new DomainException('Médico não encontrado.');
    }

    $stmtConsultas = $pdo->prepare("
        SELECT data_hora FROM consultas
        WHERE id_medico = ? AND status <> 'cancelada' AND data_hora >= NOW()
        FOR UPDATE
    ");
    $stmtConsultas->execute([$medicoId]);
    $slotsAgenda = [];
    foreach ($stmtConsultas->fetchAll(PDO::FETCH_COLUMN) as $dataHora) {
        $slotsAgenda[] = new DateTimeImmutable((string)$dataHora);
    }

    $stmtEncaixes = $pdo->prepare("
        SELECT data_encaixe, horario FROM encaixes
        WHERE medico = ? AND status <> 'cancelada'
          AND STR_TO_DATE(CONCAT(data_encaixe, ' ', LEFT(horario, 5)), '%Y-%m-%d %H:%i') >= NOW()
    ");
    $stmtEncaixes->execute([(string)$medico['nome']]);
    foreach ($stmtEncaixes->fetchAll(PDO::FETCH_ASSOC) as $encaixe) {
        $dataHora = (string)$encaixe['data_encaixe'] . ' ' . substr((string)$encaixe['horario'], 0, 5);
        $slot = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $dataHora);
        if (!$slot || $slot->format('Y-m-d H:i') !== $dataHora) {
            throw new DomainException('Existe um encaixe com data ou horário inválido.');
        }
        $slotsAgenda[] = $slot;
    }
    usort($slotsAgenda, static function (DateTimeImmutable $a, DateTimeImmutable $b): int {
        return $a <=> $b;
    });

    $fimReservaAnterior = null;
    foreach ($slotsAgenda as $slot) {
        $diaConsulta = $diasPorNumero[(int)$slot->format('N')];
        $fimConsulta = $slot->modify('+' . (int)$duracao . ' minutes');
        if (!in_array($diaConsulta, $diasPostados, true)
            || $slot->format('H:i:s') < $horaInicio . ':00'
            || $fimConsulta->format('H:i:s') > $horaFim . ':00'
            || ($fimReservaAnterior !== null && $slot < $fimReservaAnterior)) {
            throw new DomainException('A alteração conflita com consulta ou encaixe futuro já agendado.');
        }
        $fimReservaAnterior = $fimConsulta->modify('+' . (int)$intervalo . ' minutes');
    }

    $stmtUpdate = $pdo->prepare("
        UPDATE medico
        SET dias_trabalho = ?, hora_inicio = ?, hora_fim = ?, duracao_consulta = ?, intervalo_consulta = ?
        WHERE id = ?
    ");
    $stmtUpdate->execute([
        implode(',', array_values(array_intersect($diasPermitidos, $diasPostados))),
        $horaInicio . ':00',
        $horaFim . ':00',
        $duracao,
        $intervalo,
        $medicoId,
    ]);

    $pdo->commit();
    header('Location: ../agenda-configurar.php?sucesso=1');
    exit();
} catch (DomainException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    voltarAgendaMedico('conflito');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Erro ao salvar expediente do médico: ' . $e->getMessage());
    voltarAgendaMedico('falha');
}
