<?php
// paciente/informacoes-clinica.php
// Informações de contato e funcionamento da clínica.

// Dados da clínica — troque pela consulta real, se algum dia vier do banco
$clinica = [
    'endereco'  => 'Rua Exemplo, 123 – Centro, São Paulo, SP',
    'telefone1' => '(11) 99999-9999',
    'telefone2' => '(11) 1234-5678',
    'email'     => 'contato@convelumsaude.com.br',
    'horario'   => 'Seg a Sex – 8h às 18h',
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informações da Clínica - Convelum Saúde</title>

        <link href="../../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/responsivo.css">
</head>
<body class="paginas-arrumar">

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
        <span id="subtitulo-painel">FALE CONOSCO</span>
        <h1 id="titulo-painel">Informações da clínica</h1>
        <p id="texto-painel">Endereço, telefones e horário de atendimento.</p>
    </div>
</div>

<div class="secao-info-clinica">

    <!-- ========================= DADOS DE CONTATO ========================= -->
    <div class="cartao-config-agenda">
        <h2 class="titulo-cartao-config">Contato</h2>

        <ul class="lista-info-clinica">
            <li>
                <i class="bi bi-geo-alt-fill"></i>
                <span><?php echo htmlspecialchars($clinica['endereco']); ?></span>
            </li>
            <li>
                <i class="bi bi-telephone-fill"></i>
                <span><?php echo $clinica['telefone1']; ?></span>
            </li>
            <li>
                <i class="bi bi-telephone-fill"></i>
                <span><?php echo $clinica['telefone2']; ?></span>
            </li>
            <li>
                <i class="bi bi-envelope-fill"></i>
                <span><?php echo htmlspecialchars($clinica['email']); ?></span>
            </li>
            <li>
                <i class="bi bi-clock-fill"></i>
                <span><?php echo $clinica['horario']; ?></span>
            </li>
        </ul>
    </div>

    <!-- ========================= MAPA ========================= -->
    <div class="cartao-config-agenda cartao-mapa-clinica">
        <h2 class="titulo-cartao-config">Como chegar</h2>

        <div class="area-mapa-clinica">
            <iframe
                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3703.0!2d-46.6333!3d-23.5505!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2zMjPCsDMzJzAxLjgiUyA0NsKwMzgnMDAuMCJX!5e0!3m2!1spt-BR!2sbr!4v1234567890"
                width="100%"
                height="100%"
                style="border:0;"
                allowfullscreen=""
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade">
            </iframe>
        </div>
    </div>

</div>

</body>
</html>