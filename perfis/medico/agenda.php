<?php
session_start();
require_once '../../conexao.php';

if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'medico') {
    header('Location: ../../auth/login.php');
    exit();
}

$medicoId = (int)$_SESSION['usuario_id'];
$hoje = new DateTimeImmutable('today');
$dataParam = isset($_GET['data']) && is_string($_GET['data']) ? trim($_GET['data']) : 'hoje';
$dataAtualObj = null;

if ($dataParam === 'hoje') {
    $dataAtualObj = $hoje;
} elseif (in_array($dataParam, ['anterior', 'proximo'], true)) {
    $base = isset($_SESSION['agenda_data_atual']) && is_string($_SESSION['agenda_data_atual'])
        ? DateTimeImmutable::createFromFormat('!Y-m-d', $_SESSION['agenda_data_atual'])
        : false;
    if (!$base || $base->format('Y-m-d') !== $_SESSION['agenda_data_atual']) {
        $base = $hoje;
    }
    $dataAtualObj = $base->modify($dataParam === 'anterior' ? '-1 day' : '+1 day');
} else {
    foreach (['!Y-m-d', '!d/m/Y'] as $formato) {
        $dataCandidata = DateTimeImmutable::createFromFormat($formato, $dataParam);
        $errosData = DateTimeImmutable::getLastErrors();
        if ($dataCandidata
            && ($errosData === false || ($errosData['warning_count'] === 0 && $errosData['error_count'] === 0))
            && $dataCandidata->format(substr($formato, 1)) === $dataParam) {
            $dataAtualObj = $dataCandidata;
            break;
        }
    }
    $dataAtualObj = $dataAtualObj ?: $hoje;
}

$dataSelecionadaStr = $dataAtualObj->format('Y-m-d');
$_SESSION['agenda_data_atual'] = $dataSelecionadaStr;
$inicioDia = $dataAtualObj->setTime(0, 0);
$fimDia = $inicioDia->modify('+1 day');
$inicioMes = $dataAtualObj->modify('first day of this month')->setTime(0, 0);
$fimMes = $inicioMes->modify('+1 month');
$dataAnterior = $dataAtualObj->modify('-1 day')->format('Y-m-d');
$dataProxima = $dataAtualObj->modify('+1 day')->format('Y-m-d');

$diasSemanaPt = [
    'Sunday' => 'Domingo', 'Monday' => 'Segunda-feira', 'Tuesday' => 'Terça-feira',
    'Wednesday' => 'Quarta-feira', 'Thursday' => 'Quinta-feira', 'Friday' => 'Sexta-feira', 'Saturday' => 'Sábado'
];
$mesesPt = [
    'January' => 'janeiro', 'February' => 'fevereiro', 'March' => 'março', 'April' => 'abril',
    'May' => 'maio', 'June' => 'junho', 'July' => 'julho', 'August' => 'agosto',
    'September' => 'setembro', 'October' => 'outubro', 'November' => 'novembro', 'December' => 'dezembro'
];
$tituloDataFormatado = $diasSemanaPt[$dataAtualObj->format('l')] . ', '
    . $dataAtualObj->format('d') . ' de ' . $mesesPt[$dataAtualObj->format('F')];
$diaSelecionado = (int)$dataAtualObj->format('d');
$mesSelecionado = (int)$dataAtualObj->format('m') - 1;
$anoSelecionado = (int)$dataAtualObj->format('Y');

$horariosAgenda = [];
$diasComConsulta = [];
$erroAgenda = false;
$medico = null;
$diaTrabalho = false;
$ausencia = null;
$duracao = 30;
$intervalo = 0;
$horaInicioMedico = '08:00:00';
$horaFimMedico = '18:00:00';

try {
    $stmtMedico = $pdo->prepare("
        SELECT nome, dias_trabalho, hora_inicio, hora_fim,
               COALESCE(duracao_consulta, 30) AS duracao_consulta,
               COALESCE(intervalo_consulta, 0) AS intervalo_consulta
        FROM medico
        WHERE id = ? AND status = 'Ativo'
        LIMIT 1
    ");
    $stmtMedico->execute([$medicoId]);
    $medico = $stmtMedico->fetch(PDO::FETCH_ASSOC);
    if (!$medico) {
        header('Location: painel.php?erro=medico');
        exit();
    }

    $diasPermitidos = array_map('trim', explode(',', strtolower((string)$medico['dias_trabalho'])));
    $diaSemanaChave = [1 => 'seg', 2 => 'ter', 3 => 'qua', 4 => 'qui', 5 => 'sex', 6 => 'sab', 7 => 'dom'];
    $diaTrabalho = in_array($diaSemanaChave[(int)$dataAtualObj->format('N')], $diasPermitidos, true);
    $duracao = max(1, (int)$medico['duracao_consulta']);
    $intervalo = max(0, (int)$medico['intervalo_consulta']);
    $horaInicioMedico = (string)$medico['hora_inicio'];
    $horaFimMedico = (string)$medico['hora_fim'];
    $fimReservaMinutos = $duracao + $intervalo;

    $stmtConsultas = $pdo->prepare("
        SELECT c.id, c.data_hora, p.nome AS paciente_nome,
               c.tipo_atendimento, c.status, c.is_encaixe
        FROM consultas c
        INNER JOIN paciente p ON p.id = c.id_paciente
        WHERE c.id_medico = ?
          AND c.data_hora >= ? AND c.data_hora < ?
          AND c.status <> 'cancelada'
        ORDER BY c.data_hora ASC
    ");
    $stmtConsultas->execute([$medicoId, $inicioDia->format('Y-m-d H:i:s'), $fimDia->format('Y-m-d H:i:s')]);
    $consultasBanco = $stmtConsultas->fetchAll(PDO::FETCH_ASSOC);
    $encaixesConvertidos = [];

    foreach ($consultasBanco as $row) {
        $inicioConsulta = new DateTimeImmutable($row['data_hora']);
        $fimConsulta = $inicioConsulta->modify('+' . $fimReservaMinutos . ' minutes');
        $isEncaixe = (int)$row['is_encaixe'] === 1;
        if ($isEncaixe) {
            $encaixesConvertidos[$inicioConsulta->format('H:i') . '|' . strtolower(trim($row['paciente_nome']))] = true;
        }
        $status = (string)$row['status'];
        $tipoAtendimento = strtolower((string)$row['tipo_atendimento']);
        $cssStatus = $isEncaixe
            ? 'encaixe'
            : ($tipoAtendimento === 'teleconsulta'
                ? 'tele'
                : ($status === 'realizada' ? 'confirmada' : $status));
        $rotulo = $isEncaixe
            ? 'Encaixe'
            : ($tipoAtendimento === 'teleconsulta'
                ? 'Teleconsulta' . ($status === 'pendente' ? ' — Pendente' : '')
                : ([
                'pendente' => 'Pendente',
                'confirmada' => 'Confirmada',
                'realizada' => 'Realizada',
            ][$status] ?? 'Agendada'));
        $horariosAgenda[] = [
            'id' => (int)$row['id'],
            'inicio' => $inicioConsulta,
            'fim' => $fimConsulta,
            'hora' => $inicioConsulta->format('H:i'),
            'hora_fim' => $fimConsulta->format('H:i'),
            'paciente' => (string)$row['paciente_nome'],
            'status' => $cssStatus,
            'rotulo' => $rotulo,
            'detalhe' => $status === 'realizada' ? 'Atendimento realizado' : '',
            'tipo' => 'consulta',
        ];
    }

    $stmtEncaixes = $pdo->prepare("
        SELECT paciente, horario, tipo_atendimento, motivo, status
        FROM encaixes
        WHERE medico = ? AND data_encaixe = ? AND status <> 'cancelada'
        ORDER BY horario ASC
    ");
    $stmtEncaixes->execute([(string)$medico['nome'], $dataSelecionadaStr]);
    foreach ($stmtEncaixes->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $horaEncaixe = substr((string)$row['horario'], 0, 5);
        $inicioEncaixe = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $dataSelecionadaStr . ' ' . $horaEncaixe);
        if (!$inicioEncaixe || $inicioEncaixe->format('H:i') !== $horaEncaixe) {
            continue;
        }
        $chaveEncaixe = $horaEncaixe . '|' . strtolower(trim((string)$row['paciente']));
        if (isset($encaixesConvertidos[$chaveEncaixe])) {
            continue;
        }
        $horariosAgenda[] = [
            'id' => null,
            'inicio' => $inicioEncaixe,
            'fim' => $inicioEncaixe->modify('+' . $fimReservaMinutos . ' minutes'),
            'hora' => $horaEncaixe,
            'hora_fim' => $inicioEncaixe->modify('+' . $fimReservaMinutos . ' minutes')->format('H:i'),
            'paciente' => (string)$row['paciente'],
            'status' => 'encaixe',
            'rotulo' => 'Encaixe ' . ((string)$row['status'] === 'pendente' ? 'pendente' : 'confirmado'),
            'detalhe' => 'Encaixe cadastrado pela recepção — sem vínculo com uma consulta/prontuário.',
            'tipo' => 'encaixe_separado',
        ];
    }

    $stmtAusencia = $pdo->prepare("
        SELECT tipo, motivo, data_inicio, data_fim
        FROM medico_ausencias
        WHERE id_medico = ? AND ? BETWEEN data_inicio AND data_fim
        ORDER BY data_inicio
    ");
    $stmtAusencia->execute([$medicoId, $dataSelecionadaStr]);
    $ausencias = $stmtAusencia->fetchAll(PDO::FETCH_ASSOC);
    if ($ausencias) {
        $ausencia = $ausencias[0];
        $horariosAgenda[] = [
            'id' => null,
            'inicio' => $inicioDia,
            'fim' => $fimDia,
            'hora' => 'Dia todo',
            'hora_fim' => '',
            'paciente' => trim((string)$ausencia['tipo'] . ((string)$ausencia['motivo'] !== '' ? ' — ' . $ausencia['motivo'] : '')),
            'status' => 'bloqueado',
            'rotulo' => 'Ausência',
            'detalhe' => count($ausencias) > 1 ? 'Há mais de um registro de ausência nesta data.' : '',
            'tipo' => 'ausencia',
        ];
    }

    $stmtBloqueios = $pdo->prepare("
        SELECT hora_inicio, hora_fim, motivo
        FROM medico_bloqueios
        WHERE id_medico = ? AND data_bloqueio = ?
        ORDER BY hora_inicio
    ");
    $stmtBloqueios->execute([$medicoId, $dataSelecionadaStr]);
    foreach ($stmtBloqueios->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $inicioBloqueio = new DateTimeImmutable($dataSelecionadaStr . ' ' . $row['hora_inicio']);
        $fimBloqueio = new DateTimeImmutable($dataSelecionadaStr . ' ' . $row['hora_fim']);
        $horariosAgenda[] = [
            'id' => null,
            'inicio' => $inicioBloqueio,
            'fim' => $fimBloqueio,
            'hora' => $inicioBloqueio->format('H:i'),
            'hora_fim' => $fimBloqueio->format('H:i'),
            'paciente' => trim((string)$row['motivo']) ?: 'Bloqueio de agenda',
            'status' => 'bloqueado',
            'rotulo' => 'Bloqueado',
            'detalhe' => '',
            'tipo' => 'bloqueio',
        ];
    }

    $stmtBloqueiosLegados = $pdo->prepare("
        SELECT data_inicio, data_fim, motivo
        FROM bloqueio_agenda
        WHERE id_medico = ? AND data_inicio < ? AND data_fim > ?
        ORDER BY data_inicio
    ");
    $stmtBloqueiosLegados->execute([
        $medicoId,
        $fimDia->format('Y-m-d H:i:s'),
        $inicioDia->format('Y-m-d H:i:s'),
    ]);
    foreach ($stmtBloqueiosLegados->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $inicioBloqueio = new DateTimeImmutable($row['data_inicio']);
        $fimBloqueio = new DateTimeImmutable($row['data_fim']);
        $inicioExibicao = max($inicioBloqueio, $inicioDia);
        $fimExibicao = min($fimBloqueio, $fimDia);
        $horariosAgenda[] = [
            'id' => null,
            'inicio' => $inicioBloqueio,
            'fim' => $fimBloqueio,
            'hora' => $inicioExibicao->format('H:i'),
            'hora_fim' => $fimExibicao->format('H:i'),
            'paciente' => trim((string)$row['motivo']) ?: 'Bloqueio de agenda',
            'status' => 'bloqueado',
            'rotulo' => 'Bloqueado',
            'detalhe' => '',
            'tipo' => 'bloqueio',
        ];
    }

    $primeiroDia = $inicioMes->format('Y-m-d H:i:s');
    $primeiroDiaProximoMes = $fimMes->format('Y-m-d H:i:s');
    $stmtDias = $pdo->prepare("
        SELECT DISTINCT DAY(data_hora) AS dia
        FROM consultas
        WHERE id_medico = ? AND status <> 'cancelada'
          AND data_hora >= ? AND data_hora < ?
        UNION
        SELECT DISTINCT DAY(data_encaixe) AS dia
        FROM encaixes
        WHERE medico = ? AND status <> 'cancelada'
          AND data_encaixe >= DATE(?) AND data_encaixe < DATE(?)
    ");
    $stmtDias->execute([
        $medicoId,
        $primeiroDia,
        $primeiroDiaProximoMes,
        (string)$medico['nome'],
        $primeiroDia,
        $primeiroDiaProximoMes,
    ]);
    $diasComConsulta = array_map('intval', $stmtDias->fetchAll(PDO::FETCH_COLUMN));

    if ($diaTrabalho && !$ausencia) {
        $horaInicioDia = new DateTimeImmutable($dataSelecionadaStr . ' ' . $horaInicioMedico);
        $horaFimDia = new DateTimeImmutable($dataSelecionadaStr . ' ' . $horaFimMedico);
        $passo = $duracao + $intervalo;
        if ($passo > 0) {
            for ($slot = $horaInicioDia; $slot->modify('+' . $duracao . ' minutes') <= $horaFimDia; $slot = $slot->modify('+' . $passo . ' minutes')) {
                $fimSlot = $slot->modify('+' . $fimReservaMinutos . ' minutes');
                $ocupado = false;
                foreach ($horariosAgenda as $item) {
                    if (in_array($item['tipo'], ['consulta', 'encaixe_separado', 'bloqueio', 'ausencia'], true)
                        && $item['inicio'] < $fimSlot && $item['fim'] > $slot) {
                        $ocupado = true;
                        break;
                    }
                }
                if (!$ocupado) {
                    $horariosAgenda[] = [
                        'id' => null,
                        'inicio' => $slot,
                        'fim' => $fimSlot,
                        'hora' => $slot->format('H:i'),
                        'hora_fim' => $fimSlot->format('H:i'),
                        'paciente' => 'Horário disponível',
                        'status' => 'livre',
                        'rotulo' => 'Livre',
                        'detalhe' => '',
                        'tipo' => 'livre',
                    ];
                }
            }
        }
    }

    if (!$diaTrabalho) {
        $horariosAgenda[] = [
            'id' => null, 'inicio' => $inicioDia, 'fim' => $fimDia,
            'hora' => 'Dia todo', 'hora_fim' => '', 'paciente' => 'Não há expediente cadastrado para este dia.',
            'status' => 'bloqueado', 'rotulo' => 'Sem expediente', 'detalhe' => '', 'tipo' => 'sem_expediente',
        ];
    }

    usort($horariosAgenda, static function (array $a, array $b): int {
        $comparacao = $a['inicio'] <=> $b['inicio'];
        if ($comparacao !== 0) {
            return $comparacao;
        }
        return ($a['status'] === 'livre' ? 1 : 0) <=> ($b['status'] === 'livre' ? 1 : 0);
    });
} catch (Throwable $e) {
    error_log('Erro ao carregar agenda diária do médico: ' . $e->getMessage());
    $horariosAgenda = [];
    $diasComConsulta = [];
    $erroAgenda = true;
}

$rotulosStatus = [
    'confirmada' => 'Confirmada',
    'pendente' => 'Pendente',
    'realizada' => 'Realizada',
    'encaixe' => 'Encaixe',
    'tele' => 'Teleconsulta',
    'bloqueado' => 'Bloqueado',
    'livre' => 'Livre',
];
$iconesStatus = [
    'confirmada' => 'bi-hospital',
    'pendente' => 'bi-hourglass-split',
    'realizada' => 'bi-check-circle',
    'encaixe' => 'bi-person-plus',
    'tele' => 'bi-camera-video',
    'bloqueado' => 'bi-slash-circle',
    'livre' => 'bi-plus-circle',
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minha Agenda - Convelum Saúde</title>

    <link href="../../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/responsivo.css">
</head>
<body>

<!-- ========================= TOPO DO PAINEL ========================= -->
<div class="topo-painel">
    <div class="topo-painel-marca">
        <a href="../../index.php">
        <img src="../../imgs/logofrase.png" alt="Convelum Saúde">
        </a>
    </div>
    <div class="topo-painel-perfil">
        <a href="painel.php" class="topo-painel-sair">
            <i class="bi bi-arrow-left"></i> Voltar ao painel
        </a>
    </div>
</div>

<!-- ========================= CABEÇALHO DA AGENDA ========================= -->
<div id="painel-boasvindas">
    <div>
        <span id="subtitulo-painel">MINHA AGENDA</span>
        <h1 id="titulo-painel"><?php echo htmlspecialchars(ucfirst($tituloDataFormatado)); ?></h1>
        <p id="texto-painel">Consultas, encaixes, teleconsultas e horários livres do dia.</p>
    </div>
    <a href="agenda-configurar.php" class="botao-acao-principal">
        <i class="bi bi-gear"></i> Configurar agenda
    </a>
</div>

<!-- ========================= ALTERNADOR LISTA / CALENDÁRIO ========================= -->
<div class="alternador-visualizacao">
    <button type="button" class="botao-visualizacao ativo" id="botaoVisualizacaoLista" onclick="mostrarVisualizacao('lista')">
        <i class="bi bi-list-ul"></i> Lista
    </button>
    <button type="button" class="botao-visualizacao" id="botaoVisualizacaoCalendario" onclick="mostrarVisualizacao('calendario')">
        <i class="bi bi-calendar3"></i> Calendário
    </button>
</div>

<!-- ========================= VISÃO DE CALENDÁRIO ========================= -->
<div class="calendario-mes" id="visaoCalendario" style="display:none;">
    <div class="calendario-cabecalho">
        <button type="button" class="calendario-nav-btn" onclick="mudarMes(-1)">
            <i class="bi bi-chevron-left"></i>
        </button>
        <span class="calendario-mes-nome" id="calendarioMesNome"></span>
        <button type="button" class="calendario-nav-btn" onclick="mudarMes(1)">
            <i class="bi bi-chevron-right"></i>
        </button>
    </div>

    <div class="calendario-dias-semana">
        <span>D</span><span>S</span><span>T</span><span>Q</span><span>Q</span><span>S</span><span>S</span>
    </div>

    <div class="calendario-grid" id="calendarioGrid"></div>

    <div class="legenda-agenda calendario-legenda">
        <span class="legenda-item"><span class="legenda-cor cor-confirmada"></span> Tem consulta</span>
        <span class="legenda-item"><span class="ponto-hoje-legenda"></span> Hoje</span>
    </div>
</div>

<!-- ========================= VISÃO DE LISTA ========================= -->
<div id="visaoLista">

<!-- ========================= NAVEGAÇÃO DE DATA ========================= -->
<div class="navegacao-data">
    <a href="agenda.php?data=<?php echo rawurlencode($dataAnterior); ?>" class="navegacao-data-seta">
        <i class="bi bi-chevron-left"></i> Dia anterior
    </a>
    <a href="agenda.php?data=hoje" class="navegacao-data-hoje">Hoje</a>
    <a href="agenda.php?data=<?php echo rawurlencode($dataProxima); ?>" class="navegacao-data-seta">
        Próximo dia <i class="bi bi-chevron-right"></i>
    </a>
</div>

<!-- ========================= LEGENDA ========================= -->
<div class="legenda-agenda">
    <span class="legenda-item"><span class="legenda-cor cor-confirmada"></span> Confirmada</span>
    <span class="legenda-item"><span class="legenda-cor cor-encaixe"></span> Pendente</span>
    <span class="legenda-item"><span class="legenda-cor cor-tele"></span> Teleconsulta</span>
    <span class="legenda-item"><span class="legenda-cor cor-encaixe"></span> Encaixe</span>
    <span class="legenda-item"><span class="legenda-cor cor-bloqueado"></span> Bloqueado / ausência</span>
    <span class="legenda-item"><span class="legenda-cor cor-livre"></span> Livre</span>
</div>

<?php if ($erroAgenda): ?>
    <div class="alert alert-danger mx-3" role="alert">
        Não foi possível carregar a agenda. Atualize a página ou tente novamente mais tarde.
    </div>
<?php endif; ?>

<!-- ========================= GRADE DE HORÁRIOS ========================= -->
<div class="secao-lista-painel">
    <div class="lista-itens-painel">
        <?php if (!$erroAgenda && empty($horariosAgenda)): ?>
            <p class="text-center text-muted py-4">Nenhum atendimento ou horário disponível para esta data.</p>
        <?php endif; ?>
        <?php foreach ($horariosAgenda as $item): ?>
            <div class="linha-item-painel status-<?php echo htmlspecialchars($item['status'], ENT_QUOTES, 'UTF-8'); ?>">

                <span class="item-painel-data">
                    <?php echo htmlspecialchars($item['hora'], ENT_QUOTES, 'UTF-8'); ?>
                    <?php if ($item['hora_fim'] !== ''): ?>–<?php echo htmlspecialchars($item['hora_fim'], ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>
                </span>

                <span class="item-painel-tag">
                    <i class="bi <?php echo $iconesStatus[$item['status']] ?? 'bi-calendar'; ?>"></i>
                    <?php echo htmlspecialchars($item['rotulo'] ?? ($rotulosStatus[$item['status']] ?? 'Outro'), ENT_QUOTES, 'UTF-8'); ?>
                </span>

                <span class="item-painel-principal">
                    <?php echo htmlspecialchars($item['paciente'] ?: '—', ENT_QUOTES, 'UTF-8'); ?>
                    <?php if (!empty($item['detalhe'])): ?>
                        <span class="item-painel-submedico"><?php echo htmlspecialchars($item['detalhe'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                </span>

                <?php if ($item['tipo'] === 'consulta' && $item['id']): ?>
                    <a href="atendimento.php?consulta=<?php echo $item['id']; ?>" class="item-painel-botao">
                        Atender
                    </a>
                <?php endif; ?>

            </div>
        <?php endforeach; ?>
    </div>
</div>

</div>
<!-- fecha #visaoLista -->

<script>
// ===== Alternador Lista / Calendário =====
function mostrarVisualizacao(tipo) {
    const lista = document.getElementById('visaoLista');
    const calendario = document.getElementById('visaoCalendario');
    const botaoLista = document.getElementById('botaoVisualizacaoLista');
    const botaoCalendario = document.getElementById('botaoVisualizacaoCalendario');

    if (tipo === 'lista') {
        lista.style.display = 'block';
        calendario.style.display = 'none';
        botaoLista.classList.add('ativo');
        botaoCalendario.classList.remove('ativo');
    } else {
        lista.style.display = 'none';
        calendario.style.display = 'block';
        botaoLista.classList.remove('ativo');
        botaoCalendario.classList.add('ativo');
    }
}

// ===== Calendário do mês =====
const diasComConsulta = <?php echo json_encode($diasComConsulta, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const diaSelecionado = <?php echo json_encode($diaSelecionado); ?>;

const nomesMeses = [
    'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
    'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'
];

let mesAtual = <?php echo $mesSelecionado; ?>;
let anoAtual = <?php echo $anoSelecionado; ?>;
const hoje = new Date();

function mudarMes(direcao) {
    mesAtual += direcao;
    if (mesAtual < 0) { mesAtual = 11; anoAtual--; }
    if (mesAtual > 11) { mesAtual = 0; anoAtual++; }
    const primeiroDia = anoAtual + '-' + String(mesAtual + 1).padStart(2, '0') + '-01';
    window.location.href = 'agenda.php?data=' + encodeURIComponent(primeiroDia);
}

function renderizarCalendario() {
    const grid = document.getElementById('calendarioGrid');
    const nomeMes = document.getElementById('calendarioMesNome');
    nomeMes.textContent = nomesMeses[mesAtual] + ' de ' + anoAtual;

    grid.innerHTML = '';

    const primeiroDiaSemana = new Date(anoAtual, mesAtual, 1).getDay();
    const totalDiasMes = new Date(anoAtual, mesAtual + 1, 0).getDate();

    // Espaços vazios antes do dia 1
    for (let i = 0; i < primeiroDiaSemana; i++) {
        const vazio = document.createElement('div');
        vazio.className = 'calendario-dia outro-mes';
        grid.appendChild(vazio);
    }

    for (let dia = 1; dia <= totalDiasMes; dia++) {
        const celula = document.createElement('div');
        celula.className = 'calendario-dia';

        const ehHoje = dia === hoje.getDate() && mesAtual === hoje.getMonth() && anoAtual === hoje.getFullYear();
        const ehSelecionado = dia === diaSelecionado
            && mesAtual === <?php echo $mesSelecionado; ?>
            && anoAtual === <?php echo $anoSelecionado; ?>;

        if (ehHoje) celula.classList.add('hoje');
        if (ehSelecionado) celula.classList.add('selecionado');

        const numero = document.createElement('span');
        numero.textContent = dia;
        celula.appendChild(numero);

        if (diasComConsulta.includes(dia)) {
            const ponto = document.createElement('span');
            ponto.className = 'ponto-ocupado';
            celula.appendChild(ponto);
        }

        celula.addEventListener('click', function () {
            const dataFormatada = anoAtual + '-' + String(mesAtual + 1).padStart(2, '0') + '-' + String(dia).padStart(2, '0');
            window.location.href = 'agenda.php?data=' + encodeURIComponent(dataFormatada);
        });

        grid.appendChild(celula);
    }
}

renderizarCalendario();
</script>

</body>
</html>