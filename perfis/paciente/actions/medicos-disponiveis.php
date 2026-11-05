<?php
// perfis/paciente/actions/medicos-disponiveis.php
session_start();
require_once '../../../conexao.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_perfil'] !== 'paciente') {
    http_response_code(403);
    echo json_encode(['sucesso' => false, 'erro' => 'Sessão inválida.', 'medicos' => []]);
    exit();
}

$especialidadeId = !empty($_GET['especialidade_id']) ? (int)$_GET['especialidade_id'] : null;
$data            = $_GET['data'] ?? '';
$horario         = $_GET['horario'] ?? '';
// $tipo (presencial/tele) fica reservado pra quando o médico tiver uma coluna
// dizendo se atende teleconsulta. Por enquanto não filtra por isso.
$tipo            = $_GET['tipo'] ?? 'presencial';

if (!$especialidadeId || !$data || !$horario) {
    echo json_encode(['sucesso' => false, 'erro' => 'Dados incompletos.', 'medicos' => []]);
    exit();
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) || !preg_match('/^\d{2}:\d{2}$/', $horario)) {
    echo json_encode(['sucesso' => false, 'erro' => 'Data ou horário inválido.', 'medicos' => []]);
    exit();
}

$dataObj = DateTimeImmutable::createFromFormat('!Y-m-d', $data);
$horaObj = DateTimeImmutable::createFromFormat('!H:i', $horario);
if (!$dataObj || $dataObj->format('Y-m-d') !== $data
    || !$horaObj || $horaObj->format('H:i') !== $horario) {
    echo json_encode(['sucesso' => false, 'erro' => 'Data ou horário inválido.', 'medicos' => []]);
    exit();
}

$dataHora = $data . ' ' . $horario . ':00';

// Mapeia o dia da semana pro formato salvo em medico.dias_trabalho (seg,ter,qua,qui,sex,sab,dom)
$diasSemana = [1 => 'seg', 2 => 'ter', 3 => 'qua', 4 => 'qui', 5 => 'sex', 6 => 'sab', 7 => 'dom'];
$diaSemana  = $diasSemana[(int)$dataObj->format('N')];

try {
    // Médicos que atendem essa especialidade
    $stmt = $pdo->prepare("
        SELECT m.id, m.nome, m.dias_trabalho, m.hora_inicio, m.hora_fim,
               COALESCE(m.duracao_consulta, 30) AS duracao_consulta,
               COALESCE(m.intervalo_consulta, 0) AS intervalo_consulta
        FROM medico m
        JOIN medico_especialidades me ON me.id_medico = m.id
        WHERE me.id_especialidade = ?
          AND m.status = 'Ativo'
    ");
    $stmt->execute([$especialidadeId]);
    $medicos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $disponiveis = [];

    $stmtConflito = $pdo->prepare("
        SELECT COUNT(*) FROM consultas 
        WHERE id_medico = ? AND data_hora = ? AND status != 'cancelada'
    ");
    $stmtBloqueio = $pdo->prepare("
        SELECT COUNT(*) FROM bloqueio_agenda 
        WHERE id_medico = ? AND ? BETWEEN data_inicio AND data_fim
    ");
    $stmtAusencia = $pdo->prepare("
        SELECT COUNT(*) FROM medico_ausencias 
        WHERE id_medico = ? AND ? BETWEEN data_inicio AND data_fim
    ");
    $stmtBloqueioHora = $pdo->prepare("
        SELECT COUNT(*) FROM medico_bloqueios
        WHERE id_medico = ? AND data_bloqueio = ?
          AND hora_inicio < ? AND hora_fim > ?
    ");

    foreach ($medicos as $medico) {
        // 1. O médico trabalha nesse dia da semana?
        $diasTrabalho = array_map('trim', explode(',', $medico['dias_trabalho'] ?? ''));
        if (!in_array($diaSemana, $diasTrabalho, true)) {
            continue;
        }

        // 2. O horário está dentro do expediente dele?
        $inicioConsulta = new DateTimeImmutable($dataHora);
        $fimConsulta = $inicioConsulta->modify('+' . (int)$medico['duracao_consulta'] . ' minutes');
        if ($horario < substr($medico['hora_inicio'], 0, 5)
            || $fimConsulta->format('H:i:s') > (string)$medico['hora_fim']) {
            continue;
        }

        // 3. Já existe consulta marcada com ele nesse exato horário?
        $stmtConflito->execute([$medico['id'], $dataHora]);
        if ($stmtConflito->fetchColumn() > 0) {
            continue;
        }

        // 4. Tem bloqueio de agenda cobrindo esse horário?
        $stmtBloqueio->execute([$medico['id'], $dataHora]);
        if ($stmtBloqueio->fetchColumn() > 0) {
            continue;
        }

        // 5. Está de ausência (férias/licença) nesse dia?
        $stmtAusencia->execute([$medico['id'], $data]);
        if ($stmtAusencia->fetchColumn() > 0) {
            continue;
        }

        $fimReserva = $inicioConsulta->modify(
            '+' . ((int)$medico['duracao_consulta'] + (int)$medico['intervalo_consulta']) . ' minutes'
        );
        $stmtBloqueioHora->execute([
            (int)$medico['id'],
            $data,
            $fimReserva->format('H:i:s'),
            $inicioConsulta->format('H:i:s'),
        ]);
        if ((int)$stmtBloqueioHora->fetchColumn() > 0) {
            continue;
        }

        $disponiveis[] = [
            'id'   => $medico['id'],
            'nome' => $medico['nome'],
        ];
    }

    echo json_encode(['sucesso' => true, 'medicos' => $disponiveis]);

} catch (Throwable $e) {
    error_log('Erro ao buscar médicos disponíveis para agendamento: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => 'Erro ao buscar médicos disponíveis.', 'medicos' => []]);
}