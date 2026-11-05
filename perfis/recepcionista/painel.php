<?php
// recepcionista/painel.php
session_start();
require_once '../../conexao.php';

// Validação de sessão para a recepcionista
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_perfil'] !== 'recepcionista') {
    header("Location: ../../auth/login.php");
    exit();
}

$nomeRecepcionista = $_SESSION['usuario_nome'] ?? 'Bianca da Silva Teixeira';
$hoje = date('Y-m-d');

$agendaGeral = [];
$totalConsultas = 0;
$totalPendentes = 0;
$totalEncaixes = 0;

try {
    // 1. Buscando consultas normais do dia (is_encaixe = 0)
    $stmtConsultas = $pdo->prepare("
        SELECT c.id, c.data_hora, c.status, c.tipo_atendimento, c.is_encaixe,
               p.nome as paciente_nome, m.nome as medico_nome
        FROM consultas c
        JOIN paciente p ON c.id_paciente = p.id
        JOIN medico m ON c.id_medico = m.id
        WHERE DATE(c.data_hora) = ? AND c.is_encaixe = 0
    ");
    $stmtConsultas->execute([$hoje]);
    $consultasBanco = $stmtConsultas->fetchAll(PDO::FETCH_ASSOC);

    foreach ($consultasBanco as $row) {
        $agendaGeral[] = [
            'id'        => (int)$row['id'],
            'hora'      => date('H:i', strtotime($row['data_hora'])),
            'principal' => $row['paciente_nome'] . ' — ' . ($row['medico_nome'] ?? 'Médico'),
            'tipo'      => ucfirst($row['tipo_atendimento']),
            'status'    => $row['status']
        ];

        if ($row['status'] === 'pendente') {
            $totalPendentes++;
        }
    }
    $totalConsultas = count($consultasBanco);

    // 2. Buscando encaixes que viraram consultas de fato (is_encaixe = 1 na tabela consultas)
    $stmtEncaixesConsulta = $pdo->prepare("
        SELECT c.id, c.data_hora, c.tipo_atendimento, c.status,
               p.nome as paciente_nome, m.nome as medico_nome
        FROM consultas c
        JOIN paciente p ON c.id_paciente = p.id
        LEFT JOIN medico m ON c.id_medico = m.id
        WHERE DATE(c.data_hora) = ? AND c.is_encaixe = 1
    ");
    $stmtEncaixesConsulta->execute([$hoje]);
    $encaixesConsultaBanco = $stmtEncaixesConsulta->fetchAll(PDO::FETCH_ASSOC);

    foreach ($encaixesConsultaBanco as $row) {
        $agendaGeral[] = [
            'id'        => (int)$row['id'],
            'hora'      => date('H:i', strtotime($row['data_hora'])),
            'principal' => 'Encaixe: ' . $row['paciente_nome'] . ' — ' . ($row['medico_nome'] ?? 'Médico'),
            'tipo'      => ucfirst($row['tipo_atendimento'] ?? 'presencial'),
            'status'    => 'encaixe'
        ];
    }

    // 3. Buscando encaixes da tabela DEDICADA "encaixes" (criados pela tela de Inserir Encaixe)
    // Esses NÃO têm id de consulta (são registros soltos), então ficam sem botão de Detalhes.
    $stmtEncaixesTabela = $pdo->prepare("
        SELECT id, paciente, medico, horario, tipo_atendimento
        FROM encaixes
        WHERE data_encaixe = ? AND status <> 'cancelada'
    ");
    $stmtEncaixesTabela->execute([$hoje]);
    $encaixesTabelaBanco = $stmtEncaixesTabela->fetchAll(PDO::FETCH_ASSOC);

    foreach ($encaixesTabelaBanco as $row) {
        $agendaGeral[] = [
            'id'         => null,
            'encaixe_id' => (int)$row['id'], // id da tabela "encaixes" (abre detalhes-encaixe.php)
            'hora'       => substr($row['horario'], 0, 5), // garante formato H:i mesmo se vier H:i:s
            'principal'  => 'Encaixe: ' . $row['paciente'] . ' — ' . ($row['medico'] ?: 'Médico'),
            'tipo'       => in_array(strtolower($row['tipo_atendimento'] ?? ''), ['tele', 'teleconsulta'], true) ? 'Teleconsulta' : 'Presencial',
            'status'     => 'encaixe'
        ];
    }

    // Total de encaixes = os que viraram consulta + os da tabela dedicada
    $totalEncaixes = count($encaixesConsultaBanco) + count($encaixesTabelaBanco);

    // Ordenando a agenda geral cronologicamente por horário
    usort($agendaGeral, function($a, $b) {
        return strcmp($a['hora'], $b['hora']);
    });

} catch (PDOException $e) {
    // Caso alguma tabela não exista ainda, mantém valores seguros
    $agendaGeral = [];
}

$resumoRecepcao = [
    ['rotulo' => 'Consultas hoje',        'valor' => $totalConsultas, 'icone' => 'bi-calendar2-check'],
    ['rotulo' => 'Confirmações pendentes','valor' => $totalPendentes, 'icone' => 'bi-telephone'],
    ['rotulo' => 'Encaixes hoje',         'valor' => $totalEncaixes,  'icone' => 'bi-person-plus'],
];

// Mensagens de feedback vindas de outras telas via redirect (?agendado=sucesso, etc.)
$mensagemSucesso = null;
if (isset($_GET['agendado']) && $_GET['agendado'] === 'sucesso') {
    $mensagemSucesso = 'Consulta agendada com sucesso!';
} elseif (isset($_GET['encaixe']) && $_GET['encaixe'] === 'cancelado') {
    $mensagemSucesso = 'Encaixe cancelado. O horário foi liberado.';
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel da Recepção - Convelum Saúde</title>

    <link href="../../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/responsivo.css">

    <style>
        /* Resumo do dia: as colunas se ajustam à quantidade de cards (3 hoje),
           então não sobra espaço vazio. Só vale para o painel da recepção. */
        #resumo-painel {
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        }
    </style>
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
            <span class="topo-painel-nome"><?php echo htmlspecialchars($nomeRecepcionista); ?></span>
            <span class="topo-painel-subtitulo">Recepção</span>
        </div>
        <a href="../../auth/login.php" class="topo-painel-sair">
            <i class="bi bi-box-arrow-right"></i> Sair
        </a>
    </div>
</div>

<!-- ========================= ALERTA DE SUCESSO ========================= -->
<?php if ($mensagemSucesso): ?>
<div class="alerta-feedback alerta-sucesso" id="alertaFeedback" style="margin: 16px 20px; padding: 14px 18px; border-radius: 10px; background: #d1f7e0; color: #0a6b3f; display: flex; align-items: center; gap: 10px; justify-content: space-between;">
    <span><i class="bi bi-check-circle-fill"></i> <?php echo htmlspecialchars($mensagemSucesso); ?></span>
    <button type="button" onclick="document.getElementById('alertaFeedback').remove()" style="background:none;border:none;color:#0a6b3f;font-size:1.2rem;cursor:pointer;line-height:1;">&times;</button>
</div>
<script>
    // Some sozinho depois de alguns segundos e limpa o ?agendado=sucesso da URL
    setTimeout(function () {
        const alerta = document.getElementById('alertaFeedback');
        if (alerta) alerta.remove();
    }, 5000);

    if (window.history.replaceState) {
        const url = new URL(window.location.href);
        url.searchParams.delete('agendado');
        url.searchParams.delete('encaixe');
        window.history.replaceState({}, document.title, url.toString());
    }
</script>
<?php endif; ?>

<!-- ========================= BOAS-VINDAS ========================= -->
<div id="painel-boasvindas">
    <div>
        <span id="subtitulo-painel">HOJE, <?php echo strtoupper(date('d \d\e F')); ?></span>
        <h1 id="titulo-painel">Bem-vinda, <?php echo htmlspecialchars($nomeRecepcionista); ?></h1>
        <p id="texto-painel">Acompanhe a agenda de todos os médicos e a movimentação da clínica.</p>
    </div>
    <a href="cadastrar-paciente.php" class="botao-acao-principal">
        <i class="bi bi-person-plus"></i> Cadastrar paciente
    </a>
</div>

<!-- ========================= RESUMO DO DIA ========================= -->
<div id="resumo-painel">
    <?php foreach ($resumoRecepcao as $item): ?>
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

<!-- ========================= AGENDA GERAL ========================= -->
<div class="secao-lista-painel">
    <h2 class="titulo-secao-painel">Agenda geral — todos os médicos</h2>

    <div class="lista-itens-painel">
        <?php if (empty($agendaGeral)): ?>
            <p style="padding: 20px; color: #666;">Nenhuma consulta agendada para hoje.</p>
        <?php else: ?>
            <?php foreach ($agendaGeral as $item): ?>
                <div class="linha-item-painel status-<?php echo $item['status']; ?>">
                    <span class="item-painel-data"><?php echo $item['hora']; ?></span>
                    <span class="item-painel-principal"><?php echo htmlspecialchars($item['principal']); ?></span>
                    <span class="item-painel-tag">
                        <i class="bi <?php echo $item['tipo'] === 'Teleconsulta' ? 'bi-camera-video' : 'bi-door-open'; ?>"></i>
                        <?php echo $item['tipo']; ?>
                    </span>
                    <?php if ($item['status'] === 'pendente'): ?>
                        <a href="confirmar-consultas.php" class="item-painel-botao">Confirmar</a>
                    <?php elseif ($item['status'] !== 'bloqueado' && !empty($item['id'])): ?>
                        <a href="detalhes-consulta.php?id=<?php echo (int)$item['id']; ?>" class="item-painel-botao">Detalhes</a>
                    <?php elseif (!empty($item['encaixe_id'])): ?>
                        <a href="detalhes-encaixe.php?id=<?php echo (int)$item['encaixe_id']; ?>" class="item-painel-botao">Detalhes</a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- ========================= AÇÕES RÁPIDAS ========================= -->
<div class="secao-acoes-painel">
    <h2 class="titulo-secao-painel">Ações rápidas</h2>

    <div class="grid-acoes-painel">

        <a href="cadastrar-paciente.php" class="card-acao-painel">
            <i class="bi bi-person-plus icone-acao-painel"></i>
            <p class="titulo-acao-painel">Cadastrar paciente</p>
        </a>

        <a href="agendar-consulta.php" class="card-acao-painel">
            <i class="bi bi-calendar-plus icone-acao-painel"></i>
            <p class="titulo-acao-painel">Agendar consulta</p>
        </a>

        <a href="atualizar-cadastro.php" class="card-acao-painel">
            <i class="bi bi-pencil-square icone-acao-painel"></i>
            <p class="titulo-acao-painel">Atualizar cadastro</p>
        </a>

        <a href="inserir-encaixe.php" class="card-acao-painel">
            <i class="bi bi-calendar-plus icone-acao-painel"></i>
            <p class="titulo-acao-painel">Inserir encaixe</p>
        </a>

        <a href="confirmar-consultas.php" class="card-acao-painel">
            <i class="bi bi-check2-circle icone-acao-painel"></i>
            <p class="titulo-acao-painel">Confirmar consultas</p>
        </a>

        <a href="ocupacao-salas.php" class="card-acao-painel">
            <i class="bi bi-door-open icone-acao-painel"></i>
            <p class="titulo-acao-painel">Ocupação de salas</p>
        </a>

        <a href="atendimento-telefonico.php" class="card-acao-painel">
            <i class="bi bi-telephone icone-acao-painel"></i>
            <p class="titulo-acao-painel">Atendimento telefônico</p>
        </a>

        <a href="meu-perfil.php" class="card-acao-painel">
    <i class="bi bi-person-gear icone-acao-painel"></i>
    <p class="titulo-acao-painel">Meu perfil</p>
</a>

    </div>
</div>

</body>
</html>