<?php
// recepcionista/detalhes-consulta.php
// Detalhes de um horário da agenda geral, visto pela recepção.

// Dados da consulta — troque pela consulta real do banco (via $_GET['id'])
$consulta = [
    'paciente'       => 'Marina Souza',
    'telefone'       => '(11) 98888-7777',
    'convenio'       => 'Convelum Saúde Plus',
    'medico'         => 'Dr. Carlos Andrade',
    'especialidade'  => 'Cardiologia',
    'data'           => '13/08/2026',
    'hora'           => '08:00',
    'sala'           => 'Sala 1',
    'tipo'           => 'Presencial',
    'status'         => 'confirmada',
    'motivo'         => 'Consulta de rotina — check-up cardiológico',
    'checkin'        => false,
];

$rotulosStatus = [
    'confirmada' => 'Confirmada',
    'pendente'   => 'Aguardando confirmação',
    'cancelada'  => 'Cancelada',
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes da Consulta - Convelum Saúde</title>

    <link href="../../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/responsivo.css">
</head>
<body class="paginas-arrumar">

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
        <span id="subtitulo-painel">AGENDA GERAL</span>
        <h1 id="titulo-painel">Detalhes da consulta</h1>
        <p id="texto-painel"><?php echo $consulta['data']; ?> às <?php echo $consulta['hora']; ?></p>
    </div>
    <span class="selo-solicitacao-<?php echo $consulta['status'] === 'confirmada' ? 'aprovada' : ($consulta['status'] === 'cancelada' ? 'negada' : 'pendente'); ?> selo-status-detalhe">
        <?php echo $rotulosStatus[$consulta['status']]; ?>
    </span>
</div>

<div class="secao-config-agenda">

    <!-- ========================= DADOS DO PACIENTE ========================= -->
    <div class="cartao-config-agenda">
        <h2 class="titulo-cartao-config">Paciente</h2>

        <div class="cabecalho-paciente cabecalho-paciente-detalhe">
            <div class="cabecalho-paciente-avatar">
                <i class="bi bi-person-fill"></i>
            </div>
            <div>
                <p class="cabecalho-paciente-nome"><?php echo htmlspecialchars($consulta['paciente']); ?></p>
                <p class="cabecalho-paciente-detalhes">
                    <?php echo $consulta['telefone']; ?> · <?php echo htmlspecialchars($consulta['convenio']); ?>
                </p>
            </div>
        </div>
    </div>

    <!-- ========================= DADOS DA CONSULTA ========================= -->
    <div class="cartao-config-agenda">
        <h2 class="titulo-cartao-config">Consulta</h2>

        <div class="resumo-agendamento">
            <div class="linha-resumo">
                <span>Médico</span>
                <strong><?php echo htmlspecialchars($consulta['medico']); ?></strong>
            </div>
            <div class="linha-resumo">
                <span>Especialidade</span>
                <strong><?php echo htmlspecialchars($consulta['especialidade']); ?></strong>
            </div>
            <div class="linha-resumo">
                <span>Tipo</span>
                <strong><?php echo $consulta['tipo']; ?></strong>
            </div>
            <div class="linha-resumo">
                <span><?php echo $consulta['tipo'] === 'Presencial' ? 'Sala' : 'Link'; ?></span>
                <strong><?php echo $consulta['sala']; ?></strong>
            </div>
            <div class="linha-resumo">
                <span>Data</span>
                <strong><?php echo $consulta['data']; ?></strong>
            </div>
            <div class="linha-resumo">
                <span>Horário</span>
                <strong><?php echo $consulta['hora']; ?></strong>
            </div>
        </div>

        <p class="aviso-recuperacao aviso-motivo-consulta">
            <i class="bi bi-info-circle"></i> <?php echo htmlspecialchars($consulta['motivo']); ?>
        </p>
    </div>

    <!-- ========================= AÇÕES ========================= -->
    <div class="cartao-config-agenda">
        <h2 class="titulo-cartao-config">Ações</h2>

        <div class="botoes-acoes-detalhe" >
        <button type="button" class="botao-config-adicionar botao-checkin" onclick="remarcarConsulta()">
                <i class="bi bi-arrow-repeat"></i> Remarcar
            </button>

            <button type="button" class="botao-cancelar-detalhe" onclick="cancelarConsulta()">
                <i class="bi bi-x-circle"></i> Cancelar consulta
            </button>
        </div>
    </div>

</div>

<script>
function remarcarConsulta() {
    if (confirm('Tem certeza que quer remarcar essa consulta? O horário será liberado na agenda.')) {
        alert('Consulta cancelada. (isso ainda precisa ser salvo no backend)');
    }
}

function cancelarConsulta() {
    if (confirm('Tem certeza que quer cancelar essa consulta? O horário será liberado na agenda.')) {
        alert('Consulta cancelada. (isso ainda precisa ser salvo no backend)');
    }
}
</script>

</body>
</html>