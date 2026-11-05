<?php
session_start();
require_once '../../../conexao.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'recepcionista') {
    http_response_code(403);
    echo json_encode(['erro' => 'Sessão inválida.']);
    exit();
}

$rawMedico = $_GET['id_medico'] ?? null;
$idMedico = is_string($rawMedico)
    ? filter_var($rawMedico, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
$rawConsulta = $_GET['id_consulta'] ?? null;
$idConsulta = $rawConsulta === null
    ? null
    : (is_string($rawConsulta)
        ? filter_var($rawConsulta, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
        : false);
$data = isset($_GET['data']) && is_string($_GET['data']) ? $_GET['data'] : '';
if (!$idMedico || $data === '' || ($rawConsulta !== null && !$idConsulta)) {
    http_response_code(400);
    echo json_encode(['erro' => 'Médico e data são obrigatórios.']);
    exit();
}

$dataObj = DateTime::createFromFormat('!Y-m-d', $data);
$dateErrors = DateTime::getLastErrors();
if (
    !$dataObj ||
    ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0)) ||
    $dataObj->format('Y-m-d') !== $data ||
    $data < date('Y-m-d')
) {
    http_response_code(400);
    echo json_encode(['erro' => 'Data inválida ou já passada.']);
    exit();
}

try {
    $stmt = $pdo->prepare("
        SELECT id, nome, status, dias_trabalho, hora_inicio, hora_fim, duracao_consulta, intervalo_consulta
        FROM medico
        WHERE id = ?
    ");
    $stmt->execute([$idMedico]);
    $medico = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$medico) {
        http_response_code(404);
        echo json_encode(['erro' => 'Médico não encontrado.']);
        exit();
    }
    if ($medico['status'] !== 'Ativo') {
        http_response_code(409);
        echo json_encode(['erro' => 'Esse médico não está ativo para novos horários.']);
        exit();
    }

    $mapaDias = [1 => 'seg', 2 => 'ter', 3 => 'qua', 4 => 'qui', 5 => 'sex', 6 => 'sab', 7 => 'dom'];
    $diasTrabalho = array_map('trim', explode(',', strtolower($medico['dias_trabalho'] ?? '')));
    if (!in_array($mapaDias[(int)$dataObj->format('N')], $diasTrabalho, true)) {
        echo json_encode(['horarios' => [], 'erro' => 'O médico não atende nesse dia da semana.']);
        exit();
    }

    $stmtAusencia = $pdo->prepare("
        SELECT COUNT(*) FROM medico_ausencias
        WHERE id_medico = ? AND ? BETWEEN data_inicio AND data_fim
    ");
    $stmtAusencia->execute([$idMedico, $data]);
    if ((int)$stmtAusencia->fetchColumn() > 0) {
        echo json_encode(['horarios' => [], 'erro' => 'O médico está de folga/ausente nessa data.']);
        exit();
    }

    $stmtBloqueioAgenda = $pdo->prepare("
        SELECT data_inicio, data_fim FROM bloqueio_agenda
        WHERE id_medico = ? AND ? BETWEEN DATE(data_inicio) AND DATE(data_fim)
    ");
    $stmtBloqueioAgenda->execute([$idMedico, $data]);
    $bloqueiosAgenda = $stmtBloqueioAgenda->fetchAll(PDO::FETCH_ASSOC);

    $stmtBloqueioHora = $pdo->prepare("
        SELECT hora_inicio, hora_fim FROM medico_bloqueios
        WHERE id_medico = ? AND data_bloqueio = ?
    ");
    $stmtBloqueioHora->execute([$idMedico, $data]);
    $bloqueiosHora = $stmtBloqueioHora->fetchAll(PDO::FETCH_ASSOC);

    $stmtConsultas = $pdo->prepare("
        SELECT TIME(c.data_hora) AS horario, COALESCE(m.duracao_consulta, 30) AS duracao
        FROM consultas c
        INNER JOIN medico m ON m.id = c.id_medico
        WHERE c.id_medico = ? AND DATE(c.data_hora) = ? AND c.status <> 'cancelada'
    ");
    $stmtConsultas->execute([$idMedico, $data]);
    $ocupados = $stmtConsultas->fetchAll(PDO::FETCH_ASSOC);

    $stmtEncaixes = $pdo->prepare("
        SELECT horario FROM encaixes
        WHERE medico = ? AND data_encaixe = ? AND status <> 'cancelada'
    ");
    $stmtEncaixes->execute([$medico['nome'], $data]);
    foreach ($stmtEncaixes->fetchAll(PDO::FETCH_ASSOC) as $encaixe) {
        $ocupados[] = ['horario' => $encaixe['horario'], 'duracao' => $medico['duracao_consulta']];
    }

    $horariosSalaOcupados = [];
    if ($idConsulta !== null) {
        $stmtConsultaSala = $pdo->prepare("
            SELECT id_sala FROM consultas
            WHERE id = ? AND id_medico = ?
            LIMIT 1
        ");
        $stmtConsultaSala->execute([$idConsulta, $idMedico]);
        $idSala = $stmtConsultaSala->fetchColumn();
        if ($idSala === false) {
            http_response_code(404);
            echo json_encode(['erro' => 'Consulta não encontrada para esse médico.']);
            exit();
        }
        if ($idSala !== null) {
            $stmtHorariosSala = $pdo->prepare("
                SELECT TIME(c.data_hora) AS horario, COALESCE(m.duracao_consulta, 30) AS duracao
                FROM consultas c
                INNER JOIN medico m ON m.id = c.id_medico
                WHERE c.id_sala = ? AND c.id <> ? AND DATE(c.data_hora) = ?
                  AND c.status <> 'cancelada'
            ");
            $stmtHorariosSala->execute([(int)$idSala, $idConsulta, $data]);
            $horariosSalaOcupados = $stmtHorariosSala->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    $duracao = (int)($medico['duracao_consulta'] ?? 30);
    if ($duracao <= 0) {
        $duracao = 30;
    }
    $intervalo = max(0, (int)($medico['intervalo_consulta'] ?? 0));
    $passo = $duracao + $intervalo;
    $horaAtual = DateTime::createFromFormat('!Y-m-d H:i:s', $data . ' ' . $medico['hora_inicio']);
    $horaFim = DateTime::createFromFormat('!Y-m-d H:i:s', $data . ' ' . $medico['hora_fim']);
    if (!$horaAtual || !$horaFim || $passo <= 0 || $horaAtual >= $horaFim) {
        error_log('Horários de atendimento inválidos para o médico #' . $idMedico . '.');
        http_response_code(500);
        echo json_encode(['erro' => 'A agenda do médico está configurada incorretamente.']);
        exit();
    }

    $horarios = [];
    while ($horaAtual < $horaFim) {
        $fimDoSlot = (clone $horaAtual)->modify('+' . $duracao . ' minutes');
        if ($fimDoSlot > $horaFim) {
            break;
        }

        $disponivel = !($data === date('Y-m-d') && $horaAtual < new DateTime());
        $inicioMinutos = ((int)$horaAtual->format('H') * 60) + (int)$horaAtual->format('i');
        $fimMinutos = $inicioMinutos + $duracao;

        foreach ($ocupados as $ocupado) {
            $horaOcupada = substr((string)$ocupado['horario'], 0, 5);
            if (!preg_match('/^\d{2}:\d{2}$/', $horaOcupada)) {
                continue;
            }
            [$hora, $minuto] = array_map('intval', explode(':', $horaOcupada));
            $inicioOcupado = $hora * 60 + $minuto;
            $duracaoOcupada = max(1, (int)$ocupado['duracao']);
            if ($inicioOcupado < $fimMinutos && $inicioOcupado + $duracaoOcupada > $inicioMinutos) {
                $disponivel = false;
                break;
            }
        }

        if ($disponivel) {
            foreach ($horariosSalaOcupados as $ocupado) {
                $horaOcupada = substr((string)$ocupado['horario'], 0, 5);
                if (!preg_match('/^\d{2}:\d{2}$/', $horaOcupada)) {
                    continue;
                }
                [$hora, $minuto] = array_map('intval', explode(':', $horaOcupada));
                $inicioOcupado = $hora * 60 + $minuto;
                $duracaoOcupada = max(1, (int)$ocupado['duracao']);
                if ($inicioOcupado < $fimMinutos && $inicioOcupado + $duracaoOcupada > $inicioMinutos) {
                    $disponivel = false;
                    break;
                }
            }
        }

        if ($disponivel) {
            foreach ($bloqueiosAgenda as $bloqueio) {
                $inicioBloqueio = new DateTime($bloqueio['data_inicio']);
                $fimBloqueio = new DateTime($bloqueio['data_fim']);
                if ($horaAtual < $fimBloqueio && $fimDoSlot > $inicioBloqueio) {
                    $disponivel = false;
                    break;
                }
            }
        }

        if ($disponivel) {
            foreach ($bloqueiosHora as $bloqueio) {
                $inicioBloqueio = new DateTime($data . ' ' . $bloqueio['hora_inicio']);
                $fimBloqueio = new DateTime($data . ' ' . $bloqueio['hora_fim']);
                if ($horaAtual < $fimBloqueio && $fimDoSlot > $inicioBloqueio) {
                    $disponivel = false;
                    break;
                }
            }
        }

        $horarios[] = ['hora' => $horaAtual->format('H:i'), 'disponivel' => $disponivel];
        $horaAtual->modify('+' . $passo . ' minutes');
    }

    echo json_encode(['horarios' => $horarios]);
} catch (PDOException $e) {
    error_log('Falha ao consultar disponibilidade de encaixe: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['erro' => 'Erro ao consultar disponibilidade. Tente novamente.']);
}
