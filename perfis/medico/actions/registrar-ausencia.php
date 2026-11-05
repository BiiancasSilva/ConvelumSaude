<?php
session_start();
require_once '../../../conexao.php';

if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'medico') {
    header('Location: ../../../auth/login.php');
    exit();
}

function voltarAusenciaMedico(string $erro = ''): void
{
    header('Location: ../agenda-configurar.php' . ($erro !== '' ? '?erro=' . rawurlencode($erro) : ''));
    exit();
}

function dataAgendaValida(string $valor): ?DateTimeImmutable
{
    $data = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
    return $data && $data->format('Y-m-d') === $valor ? $data : null;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    voltarAusenciaMedico();
}
if (!isset($_POST['csrf_token'], $_SESSION['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
    voltarAusenciaMedico('csrf');
}

$medicoId = filter_var($_SESSION['usuario_id'], FILTER_VALIDATE_INT);
$tipoRaw = (string)($_POST['tipo'] ?? '');
$tipos = ['ferias' => 'Férias', 'licenca' => 'Licença', 'ausencia' => 'Ausência temporária'];
$dataInicioRaw = (string)($_POST['data_inicio'] ?? '');
$dataFimRaw = (string)($_POST['data_fim'] ?? '');
$motivo = trim((string)($_POST['motivo'] ?? ''));
$dataInicio = dataAgendaValida($dataInicioRaw);
$dataFim = dataAgendaValida($dataFimRaw);
$hoje = new DateTimeImmutable('today');

if (!$medicoId || !isset($tipos[$tipoRaw]) || !$dataInicio || !$dataFim
    || $dataInicio < $hoje || $dataFim < $dataInicio || mb_strlen($motivo) > 255) {
    voltarAusenciaMedico('datas');
}

try {
    $pdo->beginTransaction();
    $stmtMedico = $pdo->prepare('SELECT id, nome FROM medico WHERE id = ? FOR UPDATE');
    $stmtMedico->execute([$medicoId]);
    $medico = $stmtMedico->fetch(PDO::FETCH_ASSOC);
    if (!$medico) {
        throw new DomainException('Médico não encontrado.');
    }

    $inicioSql = $dataInicio->format('Y-m-d') . ' 00:00:00';
    $fimSql = $dataFim->format('Y-m-d') . ' 23:59:59';
    $stmtConsulta = $pdo->prepare("
        SELECT COUNT(*) FROM consultas
        WHERE id_medico = ? AND status <> 'cancelada'
          AND data_hora BETWEEN ? AND ?
    ");
    $stmtConsulta->execute([$medicoId, $inicioSql, $fimSql]);
    if ((int)$stmtConsulta->fetchColumn() > 0) {
        throw new DomainException('Há consultas agendadas dentro desse período.');
    }

    $stmtEncaixe = $pdo->prepare("
        SELECT COUNT(*) FROM encaixes
        WHERE medico = ? AND status <> 'cancelada'
          AND data_encaixe BETWEEN ? AND ?
    ");
    $stmtEncaixe->execute([
        (string)$medico['nome'],
        $dataInicio->format('Y-m-d'),
        $dataFim->format('Y-m-d'),
    ]);
    if ((int)$stmtEncaixe->fetchColumn() > 0) {
        throw new DomainException('Há encaixes agendados dentro desse período.');
    }

    $stmtInsert = $pdo->prepare("
        INSERT INTO medico_ausencias (id_medico, tipo, data_inicio, data_fim, motivo)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmtInsert->execute([
        $medicoId,
        $tipos[$tipoRaw],
        $dataInicio->format('Y-m-d'),
        $dataFim->format('Y-m-d'),
        $motivo !== '' ? $motivo : null,
    ]);
    $pdo->commit();

    header('Location: ../agenda-configurar.php?sucesso=ausencia');
    exit();
} catch (DomainException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    voltarAusenciaMedico('ausencia-conflito');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Erro ao registrar ausência do médico: ' . $e->getMessage());
    voltarAusenciaMedico('falha');
}
