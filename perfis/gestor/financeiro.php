<?php
// gestor/financeiro.php
session_start();
require_once '../../conexao.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_perfil'] !== 'gestor') {
    header("Location: ../../auth/login.php");
    exit();
}

// Os preços são estimativas calculadas pelo preço atual da especialidade.
$erroFinanceiro = false;
$inicioMes = date('Y-m-01');
$inicioProximoMes = date('Y-m-01', strtotime('first day of next month'));

// ================= CONSULTAS AO BANCO DE DADOS =================
try {
    // O banco não registra pagamentos de consultas; este valor é uma estimativa.
    $stmtReceitas = $pdo->query("
        SELECT COALESCE(SUM(e.preco), 0)
        FROM consultas c
        INNER JOIN especialidade e ON c.id_especialidade = e.id
        WHERE c.status = 'realizada'
          AND c.data_hora >= '{$inicioMes}'
          AND c.data_hora < '{$inicioProximoMes}'
    ");
    $receitasMes = (float)$stmtReceitas->fetchColumn();

    // A tabela despesas possui data_vencimento, mas não data de pagamento.
    $stmtDespesas = $pdo->prepare("
        SELECT COALESCE(SUM(valor), 0)
        FROM despesas
        WHERE status = 'Pago'
          AND data_vencimento >= :inicio
          AND data_vencimento < :fim
    ");
    $stmtDespesas->execute(['inicio' => $inicioMes, 'fim' => $inicioProximoMes]);
    $despesasMes = (float)$stmtDespesas->fetchColumn();

    // Saldo estimado, considerando despesas pelo vencimento (não pela data de pagamento).
    $saldoMes = $receitasMes - $despesasMes;

    // Consultas futuras ou para hoje que ainda não foram realizadas/canceladas.
    $stmtPendentes = $pdo->query("
        SELECT COALESCE(SUM(e.preco), 0)
        FROM consultas c
        INNER JOIN especialidade e ON c.id_especialidade = e.id
        WHERE c.status IN ('pendente', 'confirmada')
          AND c.data_hora >= CURRENT_DATE()
    ");
    $valorPrevisto = (float)$stmtPendentes->fetchColumn();

    $kpisFinanceiro = [
        ['rotulo' => 'Faturamento estimado do mês', 'valor' => 'R$ ' . number_format($receitasMes, 2, ',', '.'), 'icone' => 'bi-arrow-down-circle', 'cor' => 'positivo'],
        ['rotulo' => 'Despesas pagas (por vencimento)', 'valor' => 'R$ ' . number_format($despesasMes, 2, ',', '.'), 'icone' => 'bi-arrow-up-circle', 'cor' => 'negativo'],
        ['rotulo' => 'Saldo estimado do mês', 'valor' => 'R$ ' . number_format($saldoMes, 2, ',', '.'), 'icone' => 'bi-wallet2', 'cor' => ($saldoMes >= 0 ? 'positivo' : 'negativo')],
        ['rotulo' => 'Consultas previstas', 'valor' => 'R$ ' . number_format($valorPrevisto, 2, ',', '.'), 'icone' => 'bi-hourglass-split', 'cor' => 'atencao'],
    ];

    // Histórico mensal por competência da consulta e vencimento da despesa.
    $stmtFluxo = $pdo->query("
        SELECT 
            DATE_FORMAT(meses.data_ref, '%b') AS mes,
            COALESCE((
                SELECT SUM(e.preco)
                FROM consultas c
                INNER JOIN especialidade e ON c.id_especialidade = e.id
                WHERE c.status = 'realizada'
                  AND c.data_hora >= DATE_FORMAT(meses.data_ref, '%Y-%m-01')
                  AND c.data_hora < DATE_ADD(DATE_FORMAT(meses.data_ref, '%Y-%m-01'), INTERVAL 1 MONTH)
            ), 0) AS receita,
            COALESCE((
                SELECT SUM(d.valor)
                FROM despesas d
                WHERE d.status = 'Pago'
                  AND d.data_vencimento >= DATE_FORMAT(meses.data_ref, '%Y-%m-01')
                  AND d.data_vencimento < DATE_ADD(DATE_FORMAT(meses.data_ref, '%Y-%m-01'), INTERVAL 1 MONTH)
            ), 0) AS despesa
        FROM (
            SELECT DATE_SUB(CURRENT_DATE(), INTERVAL 5 MONTH) AS data_ref UNION ALL
            SELECT DATE_SUB(CURRENT_DATE(), INTERVAL 4 MONTH) UNION ALL
            SELECT DATE_SUB(CURRENT_DATE(), INTERVAL 3 MONTH) UNION ALL
            SELECT DATE_SUB(CURRENT_DATE(), INTERVAL 2 MONTH) UNION ALL
            SELECT DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH) UNION ALL
            SELECT CURRENT_DATE()
        ) AS meses
    ");
    $fluxoCaixa = $stmtFluxo->fetchAll(PDO::FETCH_ASSOC);

    $todasValoresFluxo = array_map('floatval', array_merge(array_column($fluxoCaixa, 'receita'), array_column($fluxoCaixa, 'despesa')));
    $maiorValorFluxo = max($todasValoresFluxo) ?: 1;

    // Usa o convênio/plano selecionado na consulta; recorre ao convênio do paciente
    // somente em registros antigos sem vínculo na consulta.
    $stmtConvenios = $pdo->query("
        SELECT co.nome, SUM(e.preco) AS valor_total
        FROM consultas c
        INNER JOIN paciente p ON c.id_paciente = p.id
        LEFT JOIN convenio_planos cp ON cp.id = c.id_plano
        INNER JOIN convenios co
            ON co.id = COALESCE(c.id_convenios, cp.id_convenio, c.id_convenio, p.id_convenio)
        INNER JOIN especialidade e ON c.id_especialidade = e.id
        WHERE c.status = 'realizada'
          AND c.data_hora >= '{$inicioMes}'
          AND c.data_hora < '{$inicioProximoMes}'
        GROUP BY co.id, co.nome
        ORDER BY valor_total DESC
        LIMIT 5
    ");
    $faturamentoConvenios = $stmtConvenios->fetchAll(PDO::FETCH_ASSOC);

    // Estimativa por médico; o banco não possui controle de repasses/pagamentos.
    $stmtMedicos = $pdo->query("
        SELECT m.id, m.nome AS medico, SUM(e.preco) AS valor
        FROM consultas c
        INNER JOIN medico m ON c.id_medico = m.id
        INNER JOIN especialidade e ON c.id_especialidade = e.id
        WHERE c.status = 'realizada'
          AND c.data_hora >= '{$inicioMes}'
          AND c.data_hora < '{$inicioProximoMes}'
        GROUP BY m.id
        ORDER BY valor DESC
        LIMIT 5
    ");
    $valoresMedicos = $stmtMedicos->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Erro ao carregar gestão financeira: ' . $e->getMessage());
    $erroFinanceiro = true;
    $kpisFinanceiro = [
        ['rotulo' => 'Faturamento estimado do mês', 'valor' => 'Indisponível', 'icone' => 'bi-arrow-down-circle', 'cor' => 'positivo'],
        ['rotulo' => 'Despesas pagas (por vencimento)', 'valor' => 'Indisponível', 'icone' => 'bi-arrow-up-circle', 'cor' => 'negativo'],
        ['rotulo' => 'Saldo estimado do mês', 'valor' => 'Indisponível', 'icone' => 'bi-wallet2', 'cor' => 'positivo'],
        ['rotulo' => 'Consultas previstas', 'valor' => 'Indisponível', 'icone' => 'bi-hourglass-split', 'cor' => 'atencao'],
    ];
    $fluxoCaixa = [['mes' => 'Atual', 'receita' => 0, 'despesa' => 0]];
    $maiorValorFluxo = 1;
    $faturamentoConvenios = [];
    $valoresMedicos = [];
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão Financeira - Convelum Saúde</title>

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
        <span id="subtitulo-painel">MÓDULO FINANCEIRO</span>
        <h1 id="titulo-painel">Gestão financeira</h1>
        <p id="texto-painel">Estimativas de faturamento, despesas por vencimento e valores previstos.</p>
    </div>
    <a href="relatorios.php" class="botao-acao-principal">
        <i class="bi bi-file-earmark-arrow-down"></i> Ver relatórios completos
    </a>
</div>

<?php if ($erroFinanceiro): ?>
    <div class="alert alert-danger m-3" role="alert">
        Não foi possível carregar os dados financeiros. Verifique a conexão e a estrutura do banco de dados.
    </div>
<?php endif; ?>

<!-- ========================= KPIS ========================= -->
<div id="resumo-painel">
    <?php foreach ($kpisFinanceiro as $item): ?>
        <div class="card-resumo-painel">
            <div class="icone-resumo-painel icone-financeiro-<?php echo $item['cor']; ?>">
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

    <!-- ========================= FLUXO DE CAIXA ========================= -->
    <div class="cartao-config-agenda">
        <h2 class="titulo-cartao-config">Resumo financeiro mensal</h2>
        <p class="text-muted">Valores estimados por data da consulta e vencimento da despesa; o banco não registra a data efetiva de pagamento.</p>

        <div class="legenda-fluxo-caixa">
            <span class="legenda-item"><span class="legenda-cor cor-fluxo-receita"></span> Consultas realizadas (estimativa)</span>
            <span class="legenda-item"><span class="legenda-cor cor-fluxo-despesa"></span> Despesas pagas por vencimento</span>
        </div>

        <div class="grafico-barras grafico-barras-duplo">
            <?php foreach ($fluxoCaixa as $item): ?>
                <div class="coluna-grafico-barra">
                    <div class="par-barras">
                        <div class="barra-grafico barra-receita" 
                             style="height: <?php echo round(($item['receita'] / $maiorValorFluxo) * 100); ?>%;" 
                             title="Receita: R$ <?php echo number_format($item['receita'], 2, ',', '.'); ?>"></div>
                        <div class="barra-grafico barra-despesa" 
                             style="height: <?php echo round(($item['despesa'] / $maiorValorFluxo) * 100); ?>%;" 
                             title="Despesa: R$ <?php echo number_format($item['despesa'], 2, ',', '.'); ?>"></div>
                    </div>
                    <span class="rotulo-barra"><?php echo $item['mes']; ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="grade-relatorios-dupla">

        <!-- ========================= FATURAMENTO ESTIMADO POR CONVÊNIO ========================= -->
        <div class="cartao-config-agenda">
            <h2 class="titulo-cartao-config">Faturamento estimado por convênio</h2>

            <div class="lista-config-registros">
                <?php if (!empty($faturamentoConvenios)): ?>
                    <?php foreach ($faturamentoConvenios as $item): ?>
                        <div class="registro-config">
                            <div>
                                <span class="registro-config-tipo"><?php echo htmlspecialchars($item['nome']); ?></span>
                                <p class="registro-medicamento-detalhes">R$ <?php echo number_format($item['valor_total'], 2, ',', '.'); ?></p>
                            </div>
                            <span class="selo-solicitacao-aprovada">Realizadas</span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted p-3">Nenhum faturamento por convênio neste mês.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- ========================= FATURAMENTO ESTIMADO POR MÉDICO ========================= -->
        <div class="cartao-config-agenda">
            <h2 class="titulo-cartao-config">Faturamento estimado por médico</h2>

            <div class="lista-config-registros">
                <?php if (!empty($valoresMedicos)): ?>
                    <?php foreach ($valoresMedicos as $item): ?>
                        <div class="registro-config">
                            <div>
                                <span class="registro-config-tipo"><?php echo htmlspecialchars($item['medico']); ?></span>
                                <p class="registro-medicamento-detalhes">R$ <?php echo number_format($item['valor'], 2, ',', '.'); ?></p>
                            </div>
                            <span class="selo-solicitacao-aprovada">Consultas realizadas</span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted p-3">Nenhuma consulta realizada neste mês.</p>
                <?php endif; ?>
            </div>
        </div>

    </div>

</div>

</body>
</html>