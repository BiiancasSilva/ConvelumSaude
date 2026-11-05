<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Descobre a profundidade do arquivo para calcular a URL base ($baseUrl)
$scriptPath = $_SERVER['PHP_SELF'];
if (strpos($scriptPath, '/perfis/') !== false) {
    $baseUrl = '../../';
} elseif (strpos($scriptPath, '/auth/') !== false) {
    $baseUrl = '../';
} else {
    $baseUrl = './';
}

// Carrega o Head (CSS/Bootstrap) e a Navbar
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<!-- TOPO DA PÁGINA -->
<div id="secao-servico-topo">
    <span class="subtitulo-servico">CONHEÇA NOSSAS OPÇÕES</span>
    <h1 class="titulo-servico">Nossos Serviços</h1>
    <p class="texto-servico">Selecione uma das categorias abaixo para ver os detalhes e opções disponíveis.</p>
</div>

<!-- SEÇÃO DE CARDS (AGORA COM 4 ITENS PARA ALINHAMENTO PERFEITO) -->
<div id="secao-ajuda">
    <div id="grid-ajuda">

        <!-- CARD CONSULTAS -->
        <div class="card-ajuda">
            <i class="bi bi-clipboard2-pulse icone-ajuda"></i>
            <p class="titulo-card-ajuda">Consultas Médicas</p>
            <p class="preco-card-ajuda">Atendimento com especialistas</p>
            <a href="<?= $baseUrl ?>servicos-consultas.php" class="botao-agendar-ajuda">Ver Consultas <i class="bi bi-chevron-right"></i></a>
        </div>

        <!-- CARD EXAMES -->
        <div class="card-ajuda">
            <i class="bi bi-droplet-half icone-ajuda"></i>
            <p class="titulo-card-ajuda">Exames Laboratoriais</p>
            <p class="preco-card-ajuda">Análises clínicas e rotina</p>
            <a href="<?= $baseUrl ?>servicos-exames.php" class="botao-agendar-ajuda">Ver Exames <i class="bi bi-chevron-right"></i></a>
        </div>

        <!-- CARD ULTRASSOM -->
        <div class="card-ajuda">
            <i class="bi bi-soundwave icone-ajuda"></i>
            <p class="titulo-card-ajuda">Exames de Imagem</p>
            <p class="preco-card-ajuda">Ultrassom e diagnósticos</p>
            <a href="<?= $baseUrl ?>servicos-exames.php" class="botao-agendar-ajuda">Ver Opções <i class="bi bi-chevron-right"></i></a>
        </div>

        <!-- CARD CHECK-UPS -->
        <div class="card-ajuda">
            <i class="bi bi-clipboard2-check icone-ajuda"></i>
            <p class="titulo-card-ajuda">Pacotes de Check-up</p>
            <p class="preco-card-ajuda">Avaliações completas e preventivas</p>
            <a href="<?= $baseUrl ?>servicos-checkups.php" class="botao-agendar-ajuda">Ver Check-ups <i class="bi bi-chevron-right"></i></a>
        </div>

    </div>
</div>

<?php 
// Rodapé
require_once 'includes/footer.php'; 
?>

</body>
</html>