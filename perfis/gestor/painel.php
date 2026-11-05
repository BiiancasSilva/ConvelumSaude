<?php
// gestor/painel.php
session_start();
require_once '../../conexao.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_perfil'] !== 'gestor') {
    header("Location: ../../auth/login.php");
    exit();
}

$nomeGestor = $_SESSION['usuario_nome'] ?? 'Administrador';

// ================= COLETANDO DADOS REAIS DO BANCO =================
$erroPainel = false;
try {
    // 1. Contadores e Métricas
    $agendamentosTotal   = $pdo->query("SELECT COUNT(*) FROM consultas")->fetchColumn() ?: 0;
    $consultasRealizadas = $pdo->query("SELECT COUNT(*) FROM consultas WHERE status = 'realizada'")->fetchColumn() ?: 0;
    $consultasCanceladas = $pdo->query("SELECT COUNT(*) FROM consultas WHERE status = 'cancelada'")->fetchColumn() ?: 0;
    $consultasPendentes  = $pdo->query("SELECT COUNT(*) FROM consultas WHERE status IN ('pendente', 'confirmada')")->fetchColumn() ?: 0;
    $teleconsultasTotal  = $pdo->query("SELECT COUNT(*) FROM consultas WHERE tipo_atendimento = 'Teleconsulta'")->fetchColumn() ?: 0;

    // Estimativa pelo preço atual da especialidade; não há valor cobrado
    // armazenado por consulta no esquema fornecido.
    $faturamentoSoma = $pdo->query("
        SELECT COALESCE(SUM(e.preco), 0)
        FROM consultas c 
        INNER JOIN especialidade e ON c.id_especialidade = e.id 
        WHERE c.status = 'realizada'
    ")->fetchColumn() ?: 0;
    
    $faturamentoFormatado = 'R$ ' . number_format($faturamentoSoma, 2, ',', '.');

    // 2. Preço-base atual de cada especialidade associada aos médicos.
    $stmtValoresBase = $pdo->query("
        SELECT m.nome AS medico_nome, esp.nome AS especialidade, COALESCE(esp.preco, 0) AS valor 
        FROM medico m
        LEFT JOIN medico_especialidades me ON m.id = me.id_medico
        LEFT JOIN especialidade esp ON me.id_especialidade = esp.id
        LIMIT 5
    ");
    $valoresBaseMedicos = $stmtValoresBase->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Erro ao carregar painel do gestor: ' . $e->getMessage());
    $erroPainel           = true;
    $agendamentosTotal   = 0;
    $consultasRealizadas = 0;
    $consultasCanceladas = 0;
    $consultasPendentes  = 0;
    $teleconsultasTotal  = 0;
    $faturamentoFormatado = 'Indisponível';
    $valoresBaseMedicos  = [];
}

// Cards da Dashboard
$dashboardGestor = [
    ['rotulo' => 'Agendamentos',         'valor' => $agendamentosTotal,     'icone' => 'bi-calendar2-week'],
    ['rotulo' => 'Consultas realizadas', 'valor' => $consultasRealizadas,  'icone' => 'bi-calendar2-check'],
    ['rotulo' => 'Consultas canceladas', 'valor' => $consultasCanceladas,  'icone' => 'bi-calendar2-x'],
    ['rotulo' => 'Pendentes/confirmadas', 'valor' => $consultasPendentes, 'icone' => 'bi-hourglass-split'],
    ['rotulo' => 'Teleconsultas',        'valor' => $teleconsultasTotal,   'icone' => 'bi-camera-video'],
    ['rotulo' => 'Faturamento estimado (total)', 'valor' => $faturamentoFormatado, 'icone' => 'bi-cash-stack'],
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel do Gestor - Convelum Saúde</title>

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
        <div class="topo-painel-texto">
            <span class="topo-painel-nome"><?php echo htmlspecialchars($nomeGestor); ?></span>
            <span class="topo-painel-subtitulo">Gestão Administrativa</span>
        </div>
        <a href="../../auth/login.php" class="topo-painel-sair">
            <i class="bi bi-box-arrow-right"></i> Sair
        </a>
    </div>
</div>

<!-- ========================= BOAS-VINDAS ========================= -->
<div id="painel-boasvindas">
    <div>
        <span id="subtitulo-painel">VISÃO GERAL — ÚLTIMOS 30 DIAS</span>
        <h1 id="titulo-painel">Bem-vindo(a), <?php echo htmlspecialchars($nomeGestor); ?></h1>
        <p id="texto-painel">Acompanhamento operacional, financeiro e estratégico da clínica.</p>
    </div>
    <a href="relatorios.php" class="botao-acao-principal">
        <i class="bi bi-bar-chart-line"></i> Ver relatórios
    </a>
</div>

<!-- ========================= DASHBOARD GERENCIAL ========================= -->
<div id="resumo-painel">
    <?php foreach ($dashboardGestor as $item): ?>
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

<?php if ($erroPainel): ?>
    <div class="alert alert-danger m-3" role="alert">
        Não foi possível carregar os dados do painel. Verifique a conexão e a estrutura do banco de dados.
    </div>
<?php endif; ?>

<!-- ========================= ESPECIALIDADES E PREÇOS-BASE ========================= -->
<div class="secao-lista-painel">
    <h2 class="titulo-secao-painel">Corpo clínico e preços-base por especialidade</h2>

    <div class="lista-itens-painel">
        <?php if (!empty($valoresBaseMedicos)): ?>
            <?php foreach ($valoresBaseMedicos as $item): ?>
                <div class="linha-item-painel status-ativo">
                    <span class="item-painel-principal">
                        <?php echo htmlspecialchars($item['medico_nome'] . ($item['especialidade'] ? ' — ' . $item['especialidade'] : ' (Sem especialidade)')); ?>
                    </span>
                    <span class="item-painel-tag">
                        <i class="bi bi-cash"></i>
                        R$ <?php echo number_format($item['valor'], 2, ',', '.'); ?>
                    </span>
                    <a href="financeiro.php" class="item-painel-botao">Ver detalhes</a>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-muted p-3">Nenhuma especialidade vinculada a médicos foi encontrada.</p>
        <?php endif; ?>
    </div>
</div>

<!-- ========================= AÇÕES RÁPIDAS ========================= -->
<div class="secao-acoes-painel">
    <h2 class="titulo-secao-painel">Ações rápidas</h2>

    <div class="grid-acoes-painel">
        <a href="relatorios.php" class="card-acao-painel">
            <i class="bi bi-bar-chart-line icone-acao-painel"></i>
            <p class="titulo-acao-painel">Relatórios analíticos</p>
        </a>
        <a href="financeiro.php" class="card-acao-painel">
            <i class="bi bi-cash-stack icone-acao-painel"></i>
            <p class="titulo-acao-painel">Gestão financeira</p>
        </a>
        <a href="cadastrar-usuario.php" class="card-acao-painel">
            <i class="bi bi-person-badge icone-acao-painel"></i>
            <p class="titulo-acao-painel">Cadastrar usuário</p>
        </a>

        <a href="gerenciar-usuarios.php" class="card-acao-painel">
            <i class="bi bi-people icone-acao-painel"></i>
            <p class="titulo-acao-painel">Gerenciar usuários</p>
        </a>
        
        <a href="cadastrar-especialidade.php" class="card-acao-painel">
            <i class="bi bi-clipboard2-plus icone-acao-painel"></i>
            <p class="titulo-acao-painel">Cadastrar especialidade</p>
        </a>
        <a href="cadastrar-convenio.php" class="card-acao-painel">
            <i class="bi bi-shield-plus icone-acao-painel"></i>
            <p class="titulo-acao-painel">Cadastrar convênio</p>
        </a>
        <a href="cadastrar-salas.php" class="card-acao-painel">
    <i class="bi bi-door-open icone-acao-painel"></i>
    <p class="titulo-acao-painel">Cadastrar salas</p>
</a>

        <a href="configurar-feriados.php" class="card-acao-painel">
            <i class="bi bi-calendar-x icone-acao-painel"></i>
            <p class="titulo-acao-painel">Configurar feriados</p>
        </a>
        <a href="configurar-bloqueios.php" class="card-acao-painel">
            <i class="bi bi-lock icone-acao-painel"></i>
            <p class="titulo-acao-painel">Bloqueios de agenda</p>
        </a>
        <a href="minha-conta.php" class="card-acao-painel">
            <i class="bi bi-gear icone-acao-painel"></i>
            <p class="titulo-acao-painel">Minha conta / Configurações</p>
        </a>
    </div>
</div>

</body>
</html>