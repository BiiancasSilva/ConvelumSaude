<?php
session_start();

// 1. Caminho da conexão (perfis/paciente/ -> 2 níveis acima)
$caminhoConexao = __DIR__ . '/../../conexao.php';
if (file_exists($caminhoConexao)) {
    require_once $caminhoConexao;
}

// 2. Verificação de autenticação do paciente
if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'paciente') {
    header("Location: ../../auth/login.php");
    exit();
}

$pacienteId = $_SESSION['usuario_id'];
$erroDadosPaciente = !isset($pdo);
$erroConsultas = !isset($pdo);

// 3. Busca dados do paciente
$paciente = [
    'nome' => $_SESSION['usuario_nome'] ?? 'Paciente'
];

if (isset($pdo)) {
    try {
        $stmtPaciente = $pdo->prepare("SELECT nome, email FROM paciente WHERE id = :id LIMIT 1");
        $stmtPaciente->execute([':id' => $pacienteId]);
        $dadosBanco = $stmtPaciente->fetch(PDO::FETCH_ASSOC);

        if ($dadosBanco && !empty($dadosBanco['nome'])) {
            $paciente['nome'] = $dadosBanco['nome'];
        }
    } catch (PDOException $e) {
        error_log('Erro ao buscar dados do paciente no painel: ' . $e->getMessage());
        $erroDadosPaciente = true;
    }
}

// 4. Busca as próximas consultas do paciente.
$minhasConsultas = [];
if (isset($pdo)) {
    try {
        $sqlConsultas = "SELECT 
                            c.id, 
                            c.data_hora AS data, 
                            c.tipo_atendimento AS tipo, 
                            c.status, 
                            m.nome AS medico_nome, 
                            e.nome AS especialidade,
                            pc.nome AS pacote_checkup,
                            pc.valor AS valor_pacote
                         FROM consultas c
                         LEFT JOIN medico m ON c.id_medico = m.id
                         LEFT JOIN especialidade e ON c.id_especialidade = e.id
                         LEFT JOIN consulta_pacotes_checkup pc ON pc.id_consulta = c.id
                         WHERE c.id_paciente = :id 
                           AND c.status IN ('confirmada', 'pendente')
                           AND c.data_hora >= NOW()
                         ORDER BY c.data_hora ASC 
                         LIMIT 5";
        $stmtConsultas = $pdo->prepare($sqlConsultas);
        $stmtConsultas->execute([':id' => $pacienteId]);
        $minhasConsultas = $stmtConsultas->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Erro ao buscar consultas do paciente no painel: ' . $e->getMessage());
        $erroDadosPaciente = true;
        $erroConsultas = true;
        $minhasConsultas = [];
    }
}

// 5. Busca contagens conforme as colunas e os status existentes no banco.
$contagensPaciente = [
    'exames_disponiveis' => 0,
    'receitas_ativas' => 0,
    'total_atestados' => 0,
];
if (isset($pdo)) {
    try {
        $stmtContagens = $pdo->prepare("
            SELECT
                (SELECT COUNT(*) FROM exames
                 WHERE id_paciente = :id_exames AND status = 'disponivel') AS exames_disponiveis,
                (SELECT COUNT(*) FROM receitas
                 WHERE id_paciente = :id_receitas AND status = 'ativa') AS receitas_ativas,
                (SELECT COUNT(*) FROM atestados
                 WHERE id_paciente = :id_atestados) AS total_atestados
        ");
        $stmtContagens->execute([
            'id_exames' => $pacienteId,
            'id_receitas' => $pacienteId,
            'id_atestados' => $pacienteId,
        ]);
        $contagensPaciente = $stmtContagens->fetch(PDO::FETCH_ASSOC) ?: $contagensPaciente;
    } catch (PDOException $e) {
        error_log('Erro ao buscar contagens de documentos do paciente: ' . $e->getMessage());
        $erroDadosPaciente = true;
    }
}

// Formatar o valor do card 'Próxima consulta'
$textoProximaConsulta = 'Nenhuma';
if (!empty($minhasConsultas[0]['data'])) {
    $textoProximaConsulta = date('d/m \à\s H:i', strtotime($minhasConsultas[0]['data']));
} elseif ($erroConsultas) {
    $textoProximaConsulta = 'Indisponível';
}

// Data e contadores do resumo
$resumoPaciente = [
    ['rotulo' => 'Próxima consulta', 'valor' => $textoProximaConsulta, 'icone' => 'bi-calendar2-event'],
    ['rotulo' => 'Exames disponíveis', 'valor' => $erroDadosPaciente ? '—' : (int)$contagensPaciente['exames_disponiveis'], 'icone' => 'bi-clipboard2-pulse'],
    ['rotulo' => 'Receitas ativas', 'valor' => $erroDadosPaciente ? '—' : (int)$contagensPaciente['receitas_ativas'], 'icone' => 'bi-prescription2'],
    ['rotulo' => 'Atestados', 'valor' => $erroDadosPaciente ? '—' : (int)$contagensPaciente['total_atestados'], 'icone' => 'bi-file-earmark-medical'],
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Área do Paciente - Convelum Saúde</title>

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
            <span class="topo-painel-nome"><?php echo htmlspecialchars($paciente['nome']); ?></span>
            <span class="topo-painel-subtitulo">Área do Paciente</span>
        </div>
        <a href="../../auth/logout.php" class="topo-painel-sair">
            <i class="bi bi-box-arrow-right"></i> Sair
        </a>
    </div>
</div>

<!-- ========================= BOAS-VINDAS ========================= -->
<div id="painel-boasvindas">
    <div>
        <span id="subtitulo-painel">SUA SAÚDE EM DIA</span>
        <h1 id="titulo-painel">Olá, <?php echo htmlspecialchars(explode(' ', trim($paciente['nome']))[0]); ?></h1>
        <p id="texto-painel">Acompanhe suas consultas, exames e receitas num só lugar.</p>
    </div>
    <a href="agendar.php" class="botao-acao-principal">
        <i class="bi bi-calendar-plus"></i> Agendar consulta
    </a>
</div>

<?php if ($erroDadosPaciente): ?>
    <div class="alert alert-warning mx-3" role="alert">
        Alguns dados do painel não puderam ser carregados. Atualize a página ou tente novamente mais tarde.
    </div>
<?php endif; ?>

<!-- ========================= RESUMO ========================= -->
<div id="resumo-painel">
    <?php foreach ($resumoPaciente as $item): ?>
        <div class="card-resumo-painel">
            <div class="icone-resumo-painel">
                <i class="bi <?php echo $item['icone']; ?>"></i>
            </div>
            <div>
                <p class="valor-resumo-painel"><?php echo htmlspecialchars($item['valor']); ?></p>
                <p class="rotulo-resumo-painel"><?php echo htmlspecialchars($item['rotulo']); ?></p>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- ========================= MINHAS CONSULTAS ========================= -->
<div class="secao-lista-painel">
    <h2 class="titulo-secao-painel">Minhas próximas consultas</h2>

    <div class="lista-itens-painel">
        <?php if (!empty($minhasConsultas)): ?>
            <?php foreach ($minhasConsultas as $item): ?>
                <div class="linha-item-painel status-<?php echo htmlspecialchars($item['status']); ?>">
                    <span class="item-painel-data"><?php echo date('d/m \à\s H:i', strtotime($item['data'])); ?></span>
                    <span class="item-painel-principal">
                        <?php 
                            $medico = !empty($item['medico_nome']) ? 'Dr(a). ' . $item['medico_nome'] : 'Médico a definir';
                            $esp = !empty($item['especialidade']) ? ' — ' . $item['especialidade'] : '';
                            echo htmlspecialchars($medico . $esp); 
                        ?>
                    </span>
                    <span class="item-painel-tag">
                        <i class="bi <?php echo strtolower($item['tipo'] ?? '') === 'teleconsulta' ? 'bi-camera-video' : 'bi-hospital'; ?>"></i>
                        <?php if (!empty($item['pacote_checkup'])): ?>
                            Check-up: <?php echo htmlspecialchars($item['pacote_checkup'], ENT_QUOTES, 'UTF-8'); ?>
                            — R$ <?php echo number_format((float)$item['valor_pacote'], 2, ',', '.'); ?>
                        <?php else: ?>
                            <?php echo htmlspecialchars($item['tipo'] ?? 'Presencial'); ?>
                        <?php endif; ?>
                    </span>
                    <a href="reagendar.php?id=<?php echo $item['id']; ?>" class="item-painel-botao">Reagendar</a>
                </div>
            <?php endforeach; ?>
        <?php elseif ($erroConsultas): ?>
            <div class="p-4 text-center text-muted bg-light rounded-3">
                <p class="m-0">Não foi possível carregar suas consultas.</p>
            </div>
        <?php else: ?>
            <div class="p-4 text-center text-muted bg-light rounded-3">
                <i class="bi bi-calendar-x fs-3 d-block mb-2"></i>
                <p class="m-0">Você não possui consultas agendadas no momento.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ========================= AÇÕES RÁPIDAS ========================= -->
<div class="secao-acoes-painel">
    <h2 class="titulo-secao-painel">Ações rápidas</h2>

    <div class="grid-acoes-painel">
        <a href="agendar.php?tipo=presencial" class="card-acao-painel">
            <i class="bi bi-hospital icone-acao-painel"></i>
            <p class="titulo-acao-painel">Consulta presencial</p>
        </a>

        <a href="agendar.php?tipo=tele" class="card-acao-painel">
            <i class="bi bi-camera-video icone-acao-painel"></i>
            <p class="titulo-acao-painel">Teleconsulta</p>
        </a>

        <a href="reagendar.php" class="card-acao-painel">
            <i class="bi bi-arrow-repeat icone-acao-painel"></i>
            <p class="titulo-acao-painel">Reagendar</p>
        </a>

        <a href="exames.php" class="card-acao-painel">
            <i class="bi bi-clipboard2-pulse icone-acao-painel"></i>
            <p class="titulo-acao-painel">Exames e laudos</p>
        </a>

        <a href="receitas.php" class="card-acao-painel">
            <i class="bi bi-prescription2 icone-acao-painel"></i>
            <p class="titulo-acao-painel">Receitas digitais</p>
        </a>

        <a href="atestados.php" class="card-acao-painel">
            <i class="bi bi-file-earmark-medical icone-acao-painel"></i>
            <p class="titulo-acao-painel">Atestados</p>
        </a>

        <a href="convenio.php" class="card-acao-painel">
            <i class="bi bi-shield-check icone-acao-painel"></i>
            <p class="titulo-acao-painel">Meu convênio</p>
        </a>

        <a href="autorizacao.php" class="card-acao-painel">
            <i class="bi bi-file-earmark-check icone-acao-painel"></i>
            <p class="titulo-acao-painel">Solicitar autorização</p>
        </a>

        <a href="informacoes-clinica.php" class="card-acao-painel">
            <i class="bi bi-info-circle icone-acao-painel"></i>
            <p class="titulo-acao-painel">Informações da clínica</p>
        </a>

        <a href="configuracoes.php" class="card-acao-painel">
            <i class="bi bi-gear icone-acao-painel"></i>
            <p class="titulo-acao-painel">Configurações da conta</p>
        </a>
    </div>
</div>

</body>
</html>