<?php
// medico/painel.php
session_start();
require_once '../../conexao.php';

// Validação de sessão para o perfil de médico
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_perfil'] !== 'medico') {
    header("Location: ../../auth/login.php");
    exit();
}

$medicoId = $_SESSION['usuario_id'];
$dataHoje = date('Y-m-d');

// ================= COLETANDO DADOS DO BANCO DE DADOS =================
try {
    // 1. Dados do Médico Logado
    $stmtMedico = $pdo->prepare("
        SELECT nome 
        FROM medico
        WHERE id = ?
        LIMIT 1
    ");
    $stmtMedico->execute([$medicoId]);
    $dadosMedico = $stmtMedico->fetch(PDO::FETCH_ASSOC);

    $medico = [
        'nome' => $dadosMedico['nome'] ?? $_SESSION['usuario_nome'] ?? 'Dr(a). Médico(a)',
    ];

    // 2. Resumo do Dia (Consultas de hoje para este médico)
    // Total de consultas hoje
    $stmtConsultasHoje = $pdo->prepare("SELECT COUNT(*) FROM consultas WHERE id_medico = ? AND DATE(data_hora) = ?");
    $stmtConsultasHoje->execute([$medicoId, $dataHoje]);
    $totalConsultasHoje = $stmtConsultasHoje->fetchColumn() ?: 0;

    // Teleconsultas hoje
    $stmtTele = $pdo->prepare("SELECT COUNT(*) FROM consultas WHERE id_medico = ? AND DATE(data_hora) = ? AND tipo_atendimento = 'Teleconsulta'");
    $stmtTele->execute([$medicoId, $dataHoje]);
    $teleconsultasHoje = $stmtTele->fetchColumn() ?: 0;

    // Como encaixes ou horários livres dependem da lógica de grade, definimos com base nas consultas ou dejamos dinâmico
    $resumoDia = [
        ['rotulo' => 'Consultas hoje',   'valor' => $totalConsultasHoje, 'icone' => 'bi-calendar2-check'],
        ['rotulo' => 'Encaixes',         'valor' => 0,                   'icone' => 'bi-person-plus'],
        ['rotulo' => 'Teleconsultas',    'valor' => $teleconsultasHoje,  'icone' => 'bi-camera-video'],
        ['rotulo' => 'Horários livres',  'valor' => 0,                   'icone' => 'bi-clock-history'],
    ];

    // 3. Agenda do Dia (Buscando consultas reais associadas ao médico logado para a data de hoje)
    $stmtAgenda = $pdo->prepare("
        SELECT c.id, c.data_hora, p.nome AS paciente_nome, c.tipo_atendimento, c.status 
        FROM consultas c
        INNER JOIN paciente p ON c.id_paciente = p.id
        WHERE c.id_medico = ? AND DATE(c.data_hora) = ?
        ORDER BY c.data_hora ASC
    ");
    $stmtAgenda->execute([$medicoId, $dataHoje]);
    $consultasBanco = $stmtAgenda->fetchAll(PDO::FETCH_ASSOC);

    $agendaDia = [];
    if (!empty($consultasBanco)) {
        foreach ($consultasBanco as $row) {
            $agendaDia[] = [
                'id'       => $row['id'],
                'hora'     => date('H:i', strtotime($row['data_hora'])),
                'paciente' => $row['paciente_nome'],
                'tipo'     => $row['tipo_atendimento'] ?? 'Presencial',
                'status'   => $row['status'] ?? 'confirmada'
            ];
        }
    } else {
        // Caso não haja consultas para hoje no banco, mantemos um array vazio ou aviso
        $agendaDia = [];
    }

} catch (PDOException $e) {
    // Fallback de segurança caso ocorra erro na query
    $medico = [
        'nome' => $_SESSION['usuario_nome'] ?? 'Dr(a). Médico(a)',
    ];
    $resumoDia = [
        ['rotulo' => 'Consultas hoje',   'valor' => 0,  'icone' => 'bi-calendar2-check'],
        ['rotulo' => 'Encaixes',         'valor' => 0,  'icone' => 'bi-person-plus'],
        ['rotulo' => 'Teleconsultas',    'valor' => 0,  'icone' => 'bi-camera-video'],
        ['rotulo' => 'Horários livres',  'valor' => 0,  'icone' => 'bi-clock-history'],
    ];
    $agendaDia = [];
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel do Médico - Convelum Saúde</title>

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
            <span class="topo-painel-nome"><?php echo htmlspecialchars($medico['nome']); ?></span>
            <p class="topo-painel-subtitulo">Área do médico</p>
        </div>
        <a href="../../auth/login.php" class="topo-painel-sair">
            <i class="bi bi-box-arrow-right"></i> Sair
        </a>
    </div>
</div>

<!-- ========================= BOAS-VINDAS ========================= -->
<div id="painel-boasvindas">
    <div>
        <span id="subtitulo-painel">HOJE, <?php echo strtoupper(date('d \d\e F')); ?></span>
        <h1 id="titulo-painel">Bem-vindo, <?php echo htmlspecialchars($medico['nome']); ?></h1>
        <p id="texto-painel">Aqui está o resumo da sua agenda de hoje.</p>
    </div>
    <a href="agenda.php" class="botao-acao-principal">
        <i class="bi bi-calendar3"></i> Ver agenda completa
    </a>
</div>

<!-- ========================= RESUMO DO DIA ========================= -->
<div id="resumo-painel">
    <?php foreach ($resumoDia as $item): ?>
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

<!-- ========================= AGENDA DO DIA ========================= -->
<div class="secao-lista-painel">
    <h2 class="titulo-secao-painel">Agenda do dia</h2>

    <div class="lista-itens-painel">
        <?php if (!empty($agendaDia)): ?>
            <?php foreach ($agendaDia as $item): ?>
                <div class="linha-item-painel status-<?php echo $item['status']; ?>">
                    <span class="item-painel-data"><?php echo $item['hora']; ?></span>
                    <span class="item-painel-principal"><?php echo htmlspecialchars($item['paciente']); ?></span>
                    <span class="item-painel-tag">
                        <i class="bi <?php echo $item['tipo'] === 'Teleconsulta' ? 'bi-camera-video' : ($item['tipo'] === 'Bloqueado' ? 'bi-slash-circle' : 'bi-hospital'); ?>"></i>
                        <?php echo $item['tipo']; ?>
                    </span>
                    <?php if ($item['status'] !== 'bloqueado'): ?>
                        <a href="atendimento.php?consulta=<?php echo $item['id']; ?>" class="item-painel-botao">
                            Atender
                        </a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-muted p-3">Nenhuma consulta agendada para hoje.</p>
        <?php endif; ?>
    </div>
</div>

<!-- ========================= AÇÕES RÁPIDAS ========================= -->
<div class="secao-acoes-painel">
    <h2 class="titulo-secao-painel">Ações rápidas</h2>

    <div class="grid-acoes-painel">

        <a href="agenda.php" class="card-acao-painel">
            <i class="bi bi-calendar3 icone-acao-painel"></i>
            <p class="titulo-acao-painel">Ver agenda do dia</p>
        </a>

        <a href="teleconsulta.php" class="card-acao-painel">
            <i class="bi bi-camera-video icone-acao-painel"></i>
            <p class="titulo-acao-painel">Iniciar teleconsulta</p>
        </a>

        <a href="agenda-configurar.php" class="card-acao-painel">
            <i class="bi bi-gear icone-acao-painel"></i>
            <p class="titulo-acao-painel">Configurar agenda</p>
        </a>

        <a href="configuracoes.php" class="card-acao-painel">
            <i class="bi bi-person-gear icone-acao-painel"></i>
            <p class="titulo-acao-painel">Configurações da conta</p>
        </a>

    </div>
</div>

</body>
</html>