<?php
session_start();
require_once '../../../conexao.php';

if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'medico') {
    header('Location: ../../../auth/login.php');
    exit();
}

function voltarBloqueioMedico(string $erro = ''): void
{
    header('Location: ../agenda-configurar.php' . ($erro !== '' ? '?erro=' . rawurlencode($erro) : ''));
    exit();
}

function dataHoraAgendaValida(string $valor): ?DateTimeImmutable
{
    $dataHora = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $valor);
    return $dataHora && $dataHora->format('Y-m-d H:i') === $valor ? $dataHora : null;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    voltarBloqueioMedico();
}
if (!isset($_POST['csrf_token'], $_SESSION['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
    voltarBloqueioMedico('csrf');
}

$medicoId = filter_var($_SESSION['usuario_id'], FILTER_VALIDATE_INT);
$data = (string)($_POST['data'] ?? '');
$horaInicio = (string)($_POST['hora_inicio'] ?? '');
$horaFim = (string)($_POST['hora_fim'] ?? '');
$motivo = trim((string)($_POST['motivo'] ?? ''));
$inicio = dataHoraAgendaValida($data . ' ' . $horaInicio);
$fim = dataHoraAgendaValida($data . ' ' . $horaFim);

if (!$medicoId || !$inicio || !$fim || $inicio < new DateTimeImmutable()
    || $fim <= $inicio || mb_strlen($motivo) > 255) {
    voltarBloqueioMedico('bloqueio');
}

try {
    $pdo->beginTransaction();
    $stmtMedico = $pdo->prepare("
        SELECT id, nome, COALESCE(duracao_consulta, 30) AS duracao_consulta,
               COALESCE(intervalo_consulta, 0) AS intervalo_consulta
        FROM medico WHERE id = ? FOR UPDATE
    ");
    $stmtMedico->execute([$medicoId]);
    $medico = $stmtMedico->fetch(PDO::FETCH_ASSOC);
    if (!$medico) {
        throw new DomainException('Médico não encontrado.');
    }

    $inicioSql = $inicio->format('Y-m-d H:i:s');
    $fimSql = $fim->format('Y-m-d H:i:s');
    $duracaoReserva = (int)$medico['duracao_consulta'] + (int)$medico['intervalo_consulta'];
    $stmtConsulta = $pdo->prepare("
        SELECT COUNT(*)
        FROM consultas c
        WHERE c.id_medico = ? AND c.status <> 'cancelada'
          AND c.data_hora < ?
          AND DATE_ADD(c.data_hora, INTERVAL ? MINUTE) > ?
    ");
    $stmtConsulta->execute([
        $medicoId,
        $fimSql,
        $duracaoReserva,
        $inicioSql,
    ]);
    if ((int)$stmtConsulta->fetchColumn() > 0) {
        throw new DomainException('Há consultas agendadas dentro do bloqueio.');
    }

    $stmtEncaixe = $pdo->prepare("
        SELECT COUNT(*)
        FROM encaixes e
        WHERE e.medico = ? AND e.data_encaixe = ? AND e.status <> 'cancelada'
          AND STR_TO_DATE(LEFT(e.horario, 5), '%H:%i') < TIME(?)
          AND ADDTIME(STR_TO_DATE(LEFT(e.horario, 5), '%H:%i'), SEC_TO_TIME(? * 60)) > TIME(?)
    ");
    $stmtEncaixe->execute([
        (string)$medico['nome'],
        $data,
        $fim->format('H:i:s'),
        $duracaoReserva,
        $inicio->format('H:i:s'),
    ]);
    if ((int)$stmtEncaixe->fetchColumn() > 0) {
        throw new DomainException('Há encaixes agendados dentro do bloqueio.');
    }

    $stmtSobreposicao = $pdo->prepare("
        SELECT COUNT(*) FROM medico_bloqueios
        WHERE id_medico = ? AND data_bloqueio = ?
          AND hora_inicio < ? AND hora_fim > ?
    ");
    $stmtSobreposicao->execute([
        $medicoId,
        $data,
        $fim->format('H:i:s'),
        $inicio->format('H:i:s'),
    ]);
    if ((int)$stmtSobreposicao->fetchColumn() > 0) {
        throw new DomainException('O período coincide com outro bloqueio cadastrado.');
    }

    $stmtInsert = $pdo->prepare("
        INSERT INTO medico_bloqueios (id_medico, data_bloqueio, hora_inicio, hora_fim, motivo)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmtInsert->execute([
        $medicoId,
        $data,
        $inicio->format('H:i:s'),
        $fim->format('H:i:s'),
        $motivo !== '' ? $motivo : null,
    ]);
    $pdo->commit();

    header('Location: ../agenda-configurar.php?sucesso=bloqueio');
    exit();
} catch (DomainException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    voltarBloqueioMedico('bloqueio-conflito');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Erro ao criar bloqueio na agenda médica: ' . $e->getMessage());
    voltarBloqueioMedico('falha');
}
