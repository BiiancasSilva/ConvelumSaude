<?php
// gestor/relatorios.php
session_start();
require_once '../../conexao.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_perfil'] !== 'gestor') {
    header("Location: ../../auth/login.php");
    exit();
}

$diasFiltro = isset($_GET['periodo']) ? (int)$_GET['periodo'] : 30;
if (!in_array($diasFiltro, [7, 30, 90])) {
    $diasFiltro = 30;
}
$inicioPeriodo = date('Y-m-d 00:00:00', strtotime('-' . ($diasFiltro - 1) . ' days'));
$fimPeriodo = date('Y-m-d 00:00:00', strtotime('tomorrow'));
$erroRelatorios = false;

// ================= CONSULTAS AO BANCO DE DADOS =================
try {
    // Consulta o volume e as realizadas na janela selecionada até hoje.
    $stmtResumo = $pdo->prepare("
        SELECT
            COUNT(*) AS total,
            COALESCE(SUM(CASE WHEN status = 'realizada' THEN 1 ELSE 0 END), 0) AS realizadas
        FROM consultas
        WHERE data_hora >= :inicio AND data_hora < :fim
    ");
    $stmtResumo->execute(['inicio' => $inicioPeriodo, 'fim' => $fimPeriodo]);
    $resumo = $stmtResumo->fetch(PDO::FETCH_ASSOC);
    $totalAgendamentos = (int)($resumo['total'] ?? 0);
    $totalRealizados = (int)($resumo['realizadas'] ?? 0);

    // O banco não guarda o valor cobrado por consulta; trata-se de estimativa
    // pelo preço atual cadastrado para a especialidade.
    $stmtFat = $pdo->prepare("
        SELECT COALESCE(SUM(e.preco), 0)
        FROM consultas c
        INNER JOIN especialidade e ON c.id_especialidade = e.id
        WHERE c.status = 'realizada'
          AND c.data_hora >= :inicio AND c.data_hora < :fim
    ");
    $stmtFat->execute(['inicio' => $inicioPeriodo, 'fim' => $fimPeriodo]);
    $faturamentoVal = (float)$stmtFat->fetchColumn();

    $faturamentoFormatado = 'R$ ' . number_format($faturamentoVal, 2, ',', '.');
    $ticketMedioVal = ($totalRealizados > 0) ? ($faturamentoVal / $totalRealizados) : 0;
    $ticketMedioFormatado = 'R$ ' . number_format($ticketMedioVal, 2, ',', '.');

    // 2. Gráfico de Barras: Consultas por mês (Últimos 6 meses)
    $stmtMeses = $pdo->query("
        SELECT DATE_FORMAT(data_hora, '%b') AS mes, COUNT(*) AS total
        FROM consultas
        WHERE data_hora >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 5 MONTH)
          AND data_hora < '{$fimPeriodo}'
        GROUP BY YEAR(data_hora), MONTH(data_hora)
        ORDER BY YEAR(data_hora) ASC, MONTH(data_hora) ASC
    ");
    $agendamentosPorMes = $stmtMeses->fetchAll(PDO::FETCH_ASSOC);

    if (empty($agendamentosPorMes)) {
        $agendamentosPorMes = [
            ['mes' => 'Atual', 'total' => 0]
        ];
    }
    $maiorValorMes = max(array_column($agendamentosPorMes, 'total')) ?: 1;

    // 3. Gráfico de Rosca: consultas agendadas por especialidade no período.
    $stmtEsp = $pdo->prepare("
        SELECT e.nome, COUNT(c.id) AS total_consultas
        FROM consultas c
        INNER JOIN especialidade e ON c.id_especialidade = e.id
        WHERE c.data_hora >= :inicio AND c.data_hora < :fim
        GROUP BY e.id
        ORDER BY total_consultas DESC
        LIMIT 5
    ");
    $stmtEsp->execute(['inicio' => $inicioPeriodo, 'fim' => $fimPeriodo]);
    $dadosEspecialidade = $stmtEsp->fetchAll(PDO::FETCH_ASSOC);

    $coresDisponiveis = ['#16a085', '#0a1e3f', '#e0a800', '#5b9bd5', '#cccccc'];
    $porEspecialidade = [];
    $totalEspecialidades = array_sum(array_column($dadosEspecialidade, 'total_consultas')) ?: 1;

    foreach ($dadosEspecialidade as $key => $esp) {
        $porEspecialidade[] = [
            'nome'       => $esp['nome'],
            'percentual' => round(($esp['total_consultas'] / $totalEspecialidades) * 100),
            'cor'        => $coresDisponiveis[$key % count($coresDisponiveis)]
        ];
    }

    if (empty($porEspecialidade)) {
        $porEspecialidade = [
            ['nome' => 'Sem dados', 'percentual' => 100, 'cor' => '#cccccc']
        ];
    }

    $gradienteRosca = '';
    $acumulado = 0;
    foreach ($porEspecialidade as $item) {
        $inicio = $acumulado;
        $acumulado += $item['percentual'];
        $gradienteRosca .= $item['cor'] . ' ' . $inicio . '% ' . $acumulado . '%, ';
    }
    $gradienteRosca = rtrim($gradienteRosca, ', ');

    // 4. Ranking de médicos por consultas realizadas. Cancelamentos não são
    // classificados como faltas: o esquema não tem status específico para falta.
    $stmtRanking = $pdo->prepare("
        SELECT 
            m.nome,
            SUM(CASE WHEN c.status = 'realizada' THEN 1 ELSE 0 END) AS realizadas,
            SUM(CASE WHEN c.status = 'cancelada' THEN 1 ELSE 0 END) AS canceladas
        FROM consultas c
        INNER JOIN medico m ON c.id_medico = m.id
        WHERE c.data_hora >= :inicio AND c.data_hora < :fim
        GROUP BY m.id
        ORDER BY realizadas DESC, canceladas ASC, m.nome ASC
        LIMIT 5
    ");
    $stmtRanking->execute(['inicio' => $inicioPeriodo, 'fim' => $fimPeriodo]);
    $rankingMedicosRaw = $stmtRanking->fetchAll(PDO::FETCH_ASSOC);

    $rankingMedicos = [];
    foreach ($rankingMedicosRaw as $m) {
        $rankingMedicos[] = [
            'nome'         => $m['nome'],
            'realizadas'   => (int)$m['realizadas'],
            'canceladas'   => (int)$m['canceladas']
        ];
    }

} catch (PDOException $e) {
    error_log('Erro ao carregar relatórios analíticos: ' . $e->getMessage());
    $erroRelatorios       = true;
    $totalAgendamentos    = 0;
    $totalRealizados      = 0;
    $faturamentoFormatado = 'R$ 0,00';
    $ticketMedioFormatado = 'R$ 0,00';
    $agendamentosPorMes   = [['mes' => 'Atual', 'total' => 0]];
    $maiorValorMes        = 1;
    $porEspecialidade     = [['nome' => 'Sem dados', 'percentual' => 100, 'cor' => '#cccccc']];
    $gradienteRosca       = '#cccccc 0% 100%';
    $rankingMedicos       = [];
}

$kpis = [
    ['rotulo' => 'Total de agendamentos', 'valor' => $totalAgendamentos, 'icone' => 'bi-calendar2-week'],
    ['rotulo' => 'Consultas realizadas', 'valor' => $totalRealizados, 'icone' => 'bi-person-check'],
    ['rotulo' => 'Faturamento estimado', 'valor' => $faturamentoFormatado, 'icone' => 'bi-cash-stack'],
    ['rotulo' => 'Ticket médio estimado', 'valor' => $ticketMedioFormatado, 'icone' => 'bi-graph-up-arrow'],
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios Analíticos - Convelum Saúde</title>

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

<!-- ========================= CABEÇALHO ========================= -->
<div id="painel-boasvindas">
    <div>
        <span id="subtitulo-painel">DASHBOARD GERENCIAL</span>
        <h1 id="titulo-painel">Relatórios analíticos</h1>
        <p id="texto-painel">Indicadores operacionais e estimativas financeiras até hoje.</p>
    </div>
    <select class="select-perfil select-periodo-relatorio" onchange="window.location.href='relatorios.php?periodo=' + this.value">
        <option value="7" <?php echo $diasFiltro === 7 ? 'selected' : ''; ?>>Últimos 7 dias</option>
        <option value="30" <?php echo $diasFiltro === 30 ? 'selected' : ''; ?>>Últimos 30 dias</option>
        <option value="90" <?php echo $diasFiltro === 90 ? 'selected' : ''; ?>>Últimos 90 dias</option>
    </select>
</div>

<p class="text-muted mx-3">O período inclui somente consultas até hoje. Valores financeiros são estimativas pelo preço atual da especialidade, pois o banco não registra o valor cobrado em cada consulta.</p>

<?php if ($erroRelatorios): ?>
    <div class="alert alert-danger m-3" role="alert">
        Não foi possível carregar os relatórios. Verifique a conexão e a estrutura do banco de dados.
    </div>
<?php endif; ?>

<!-- ========================= KPIS ========================= -->
<div id="resumo-painel">
    <?php foreach ($kpis as $item): ?>
        <div class="card-resumo-painel">
            <div class="icone-resumo-painel">
                <i class="bi <?php echo $item['icone']; ?>"></i>
            </div>
            <div>
                <p class="valor-resumo-painel"><?php echo $item['valor']; ?></p>
                <p class="rotulo-resumo-painel"><?php echo $item['rotulo']; ?></p>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="secao-config-agenda secao-relatorios">

    <!-- ========================= GRÁFICO DE BARRAS ========================= -->
    <div class="cartao-config-agenda">
        <h2 class="titulo-cartao-config">Agendamentos por mês (inclui todos os status)</h2>

        <div class="grafico-barras">
            <?php foreach ($agendamentosPorMes as $item): ?>
                <div class="coluna-grafico-barra">
                    <span class="valor-barra"><?php echo $item['total']; ?></span>
                    <div class="barra-grafico" style="height: <?php echo round(($item['total'] / $maiorValorMes) * 100); ?>%;"></div>
                    <span class="rotulo-barra"><?php echo $item['mes']; ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ========================= GRÁFICO DE ROSCA + RANKING ========================= -->
    <div class="grade-relatorios-dupla">

        <div class="cartao-config-agenda">
            <h2 class="titulo-cartao-config">Consultas por especialidade</h2>

            <div class="grafico-rosca-area">
                <div class="grafico-rosca" style="background: conic-gradient(<?php echo $gradienteRosca; ?>);">
                    <div class="grafico-rosca-centro">
                        <span><?php echo array_sum(array_column($porEspecialidade, 'percentual')); ?>%</span>
                    </div>
                </div>

                <div class="legenda-rosca">
                    <?php foreach ($porEspecialidade as $item): ?>
                        <div class="legenda-rosca-item">
                            <span class="legenda-rosca-cor" style="background-color: <?php echo $item['cor']; ?>;"></span>
                            <span class="legenda-rosca-nome"><?php echo htmlspecialchars($item['nome']); ?></span>
                            <span class="legenda-rosca-percentual"><?php echo $item['percentual']; ?>%</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="cartao-config-agenda">
            <h2 class="titulo-cartao-config">Ranking de médicos</h2>

            <div class="lista-config-registros">
                <?php if (!empty($rankingMedicos)): ?>
                    <?php foreach ($rankingMedicos as $indice => $medico): ?>
                        <div class="registro-config registro-ranking">
                            <span class="posicao-ranking">#<?php echo $indice + 1; ?></span>
                            <div class="registro-ranking-info">
                                <span class="registro-config-tipo"><?php echo htmlspecialchars($medico['nome']); ?></span>
                                <p class="registro-medicamento-detalhes">
                                    <?php echo $medico['realizadas']; ?> realizadas · <?php echo $medico['canceladas']; ?> canceladas
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted p-3">Nenhum médico com consultas registradas neste período.</p>
                <?php endif; ?>
            </div>
        </div>

    </div>

</div>

</body>
</html>