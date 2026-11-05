<?php
session_start();
require_once '../../../conexao.php';
header('Content-Type: application/json; charset=utf-8');

function responderMedicos(array $dados, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    exit();
}

if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'recepcionista') {
    responderMedicos(['sucesso' => false, 'erro' => 'Sessão inválida.', 'medicos' => []], 403);
}

$especialidadeId = filter_var($_GET['especialidade_id'] ?? '', FILTER_VALIDATE_INT);
$pacienteId = filter_var($_GET['paciente_id'] ?? '', FILTER_VALIDATE_INT);
$data = (string)($_GET['data'] ?? '');
$horario = (string)($_GET['horario'] ?? '');
$inicio = null;
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) && preg_match('/^\d{2}:\d{2}$/', $horario)) {
    $inicio = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $data . ' ' . $horario);
    if (!$inicio || $inicio->format('Y-m-d H:i') !== $data . ' ' . $horario) {
        $inicio = null;
    }
}
if (!$especialidadeId || !$pacienteId || !$inicio || $inicio <= new DateTimeImmutable()) {
    responderMedicos(['sucesso' => false, 'erro' => 'Informe paciente, especialidade e horário futuro válidos.', 'medicos' => []], 400);
}

try {
    $stmt = $pdo->prepare("
        SELECT m.id, m.uid, m.nome, m.dias_trabalho, m.hora_inicio, m.hora_fim,
               COALESCE(m.duracao_consulta, 30) AS duracao_consulta,
               COALESCE(m.intervalo_consulta, 0) AS intervalo_consulta
        FROM medico m
        INNER JOIN medico_especialidades me ON me.id_medico = m.id
        INNER JOIN especialidade e ON e.id = me.id_especialidade
        WHERE me.id_especialidade = ? AND m.status = 'Ativo' AND e.status = 'Ativo'
        ORDER BY m.nome
    ");
    $stmt->execute([(int)$especialidadeId]);
    $candidatos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $disponiveis = [];
    $diasSemana = [1 => 'seg', 2 => 'ter', 3 => 'qua', 4 => 'qui', 5 => 'sex', 6 => 'sab', 7 => 'dom'];
    $diaSemana = $diasSemana[(int)$inicio->format('N')];
    $inicioSql = $inicio->format('Y-m-d H:i:s');

    foreach ($candidatos as $medico) {
        $duracao = max(1, (int)$medico['duracao_consulta']);
        $intervalo = max(0, (int)$medico['intervalo_consulta']);
        $fimAtendimento = $inicio->modify('+' . $duracao . ' minutes');
        if (!in_array($diaSemana, array_map('trim', explode(',', (string)$medico['dias_trabalho'])), true)
            || $inicio->format('H:i:s') < (string)$medico['hora_inicio']
            || $fimAtendimento->format('H:i:s') > (string)$medico['hora_fim']) {
            continue;
        }

        $fimReserva = $inicio->modify('+' . ($duracao + $intervalo) . ' minutes')->format('Y-m-d H:i:s');
        $medicoId = (int)$medico['id'];

        $stmtAusencia = $pdo->prepare("
            SELECT COUNT(*) FROM medico_ausencias
            WHERE id_medico = ? AND data_inicio <= ? AND data_fim >= ?
        ");
        $stmtAusencia->execute([$medicoId, $data, $data]);
        if ((int)$stmtAusencia->fetchColumn() > 0) {
            continue;
        }

        $stmtBloqueioAgenda = $pdo->prepare("
            SELECT COUNT(*) FROM bloqueio_agenda
            WHERE id_medico = ? AND data_inicio < ? AND data_fim > ?
        ");
        $stmtBloqueioAgenda->execute([$medicoId, $fimReserva, $inicioSql]);
        if ((int)$stmtBloqueioAgenda->fetchColumn() > 0) {
            continue;
        }

        $stmtBloqueioHorario = $pdo->prepare("
            SELECT COUNT(*) FROM medico_bloqueios
            WHERE id_medico = ? AND data_bloqueio = ?
              AND hora_inicio < ? AND hora_fim > ?
        ");
        $stmtBloqueioHorario->execute([
            $medicoId,
            $data,
            $inicio->modify('+' . ($duracao + $intervalo) . ' minutes')->format('H:i:s'),
            $inicio->format('H:i:s'),
        ]);
        if ((int)$stmtBloqueioHorario->fetchColumn() > 0) {
            continue;
        }

        $stmtBloqueioCalendario = $pdo->prepare("
            SELECT COUNT(*) FROM bloqueios
            WHERE (tipo_alvo = 'geral' OR (tipo_alvo = 'medico' AND alvo_id = ?))
              AND CONCAT(data_inicio, ' ', COALESCE(hora_inicio, '00:00:00')) < ?
              AND CONCAT(data_fim, ' ', COALESCE(hora_fim, '23:59:59')) > ?
        ");
        $stmtBloqueioCalendario->execute([(string)$medico['uid'], $fimReserva, $inicioSql]);
        if ((int)$stmtBloqueioCalendario->fetchColumn() > 0) {
            continue;
        }

        $stmtConflito = $pdo->prepare("
            SELECT COUNT(*)
            FROM consultas c
            INNER JOIN medico m2 ON m2.id = c.id_medico
            WHERE c.id_medico = ? AND c.status <> 'cancelada'
              AND c.data_hora < ?
              AND DATE_ADD(c.data_hora, INTERVAL
                  (COALESCE(m2.duracao_consulta, 30) + COALESCE(m2.intervalo_consulta, 0)) MINUTE) > ?
        ");
        $stmtConflito->execute([$medicoId, $fimReserva, $inicioSql]);
        if ((int)$stmtConflito->fetchColumn() > 0) {
            continue;
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
        $stmtConflitoPaciente->execute([(int)$pacienteId, $fimReserva, $inicioSql]);
        if ((int)$stmtConflitoPaciente->fetchColumn() > 0) {
            continue;
        }

        $disponiveis[] = ['id' => $medicoId, 'nome' => $medico['nome']];
    }

    responderMedicos(['sucesso' => true, 'medicos' => $disponiveis]);
} catch (Throwable $e) {
    error_log('Erro ao consultar médicos disponíveis para a recepção: ' . $e->getMessage());
    responderMedicos(['sucesso' => false, 'erro' => 'Erro ao buscar médicos disponíveis.', 'medicos' => []], 500);
}
