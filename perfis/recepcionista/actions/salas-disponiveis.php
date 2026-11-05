<?php
session_start();
require_once '../../../conexao.php';
header('Content-Type: application/json; charset=utf-8');

function responderSalas(array $dados, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    exit();
}

if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'recepcionista') {
    responderSalas(['sucesso' => false, 'erro' => 'Sessão inválida.', 'salas' => []], 403);
}

$medicoId = filter_var($_GET['medico_id'] ?? '', FILTER_VALIDATE_INT);
$data = (string)($_GET['data'] ?? '');
$horario = (string)($_GET['horario'] ?? '');
$inicio = null;
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) && preg_match('/^\d{2}:\d{2}$/', $horario)) {
    $inicio = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $data . ' ' . $horario);
    if (!$inicio || $inicio->format('Y-m-d H:i') !== $data . ' ' . $horario) {
        $inicio = null;
    }
}
if (!$medicoId || !$inicio || $inicio <= new DateTimeImmutable()) {
    responderSalas(['sucesso' => false, 'erro' => 'Informe um médico e horário futuro válidos.', 'salas' => []], 400);
}

try {
    $stmtMedico = $pdo->prepare("
        SELECT COALESCE(duracao_consulta, 30) AS duracao_consulta,
               COALESCE(intervalo_consulta, 0) AS intervalo_consulta
        FROM medico WHERE id = ? AND status = 'Ativo'
    ");
    $stmtMedico->execute([(int)$medicoId]);
    $medico = $stmtMedico->fetch(PDO::FETCH_ASSOC);
    if (!$medico) {
        responderSalas(['sucesso' => false, 'erro' => 'Médico não encontrado ou inativo.', 'salas' => []], 404);
    }

    $duracao = max(1, (int)$medico['duracao_consulta']);
    $intervalo = max(0, (int)$medico['intervalo_consulta']);
    $fimReserva = $inicio->modify('+' . ($duracao + $intervalo) . ' minutes')->format('Y-m-d H:i:s');
    $inicioSql = $inicio->format('Y-m-d H:i:s');

    $stmtSalas = $pdo->prepare("
        SELECT s.id, s.nome, s.id_medico, mf.nome AS medico_fixo_nome
        FROM salas s
        LEFT JOIN medico mf ON mf.id = s.id_medico
        WHERE LOWER(s.status) NOT IN ('bloqueada', 'inativo')
          AND (s.id_medico IS NULL OR s.id_medico = ?)
        ORDER BY s.nome
    ");
    $stmtSalas->execute([(int)$medicoId]);
    $candidatas = $stmtSalas->fetchAll(PDO::FETCH_ASSOC);
    $disponiveis = [];

    foreach ($candidatas as $sala) {
        $salaId = (int)$sala['id'];
        $stmtConflito = $pdo->prepare("
            SELECT COUNT(*)
            FROM consultas c
            INNER JOIN medico m2 ON m2.id = c.id_medico
            WHERE c.id_sala = ? AND c.status <> 'cancelada'
              AND c.data_hora < ?
              AND DATE_ADD(c.data_hora, INTERVAL
                  (COALESCE(m2.duracao_consulta, 30) + COALESCE(m2.intervalo_consulta, 0)) MINUTE) > ?
        ");
        $stmtConflito->execute([$salaId, $fimReserva, $inicioSql]);
        if ((int)$stmtConflito->fetchColumn() > 0) {
            continue;
        }

        $stmtBloqueio = $pdo->prepare("
            SELECT COUNT(*) FROM bloqueio_agenda
            WHERE id_sala = ? AND data_inicio < ? AND data_fim > ?
        ");
        $stmtBloqueio->execute([$salaId, $fimReserva, $inicioSql]);
        if ((int)$stmtBloqueio->fetchColumn() > 0) {
            continue;
        }

        $stmtBloqueioClinica = $pdo->prepare("
            SELECT COUNT(*) FROM bloqueios
            WHERE tipo_alvo = 'geral'
              AND CONCAT(data_inicio, ' ', COALESCE(hora_inicio, '00:00:00')) < ?
              AND CONCAT(data_fim, ' ', COALESCE(hora_fim, '23:59:59')) > ?
        ");
        $stmtBloqueioClinica->execute([$fimReserva, $inicioSql]);
        if ((int)$stmtBloqueioClinica->fetchColumn() > 0) {
            continue;
        }

        $disponiveis[] = [
            'id' => $salaId,
            'nome' => $sala['nome'],
            'fixa' => !empty($sala['id_medico']),
            'medico_fixo_nome' => $sala['medico_fixo_nome'],
        ];
    }

    responderSalas(['sucesso' => true, 'salas' => $disponiveis]);
} catch (Throwable $e) {
    error_log('Erro ao consultar salas disponíveis para a recepção: ' . $e->getMessage());
    responderSalas(['sucesso' => false, 'erro' => 'Erro ao buscar salas disponíveis.', 'salas' => []], 500);
}
