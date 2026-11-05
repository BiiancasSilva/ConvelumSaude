<?php
require_once 'includes/header.php';
require_once 'includes/navbar.php';

$categoriasExames = [
    [
        'categoria' => 'Exames laboratoriais',
        'icone'     => 'bi-droplet-half',
        'itens'     => ['Hemograma completo', 'Glicemia', 'Colesterol total e frações', 'Função renal e hepática', 'Exame de urina'],
    ],
    [
        'categoria' => 'Exames de imagem',
        'icone'     => 'bi-heart-pulse',
        'itens'     => ['Ultrassom abdominal', 'Raio-X', 'Eletrocardiograma', 'Ecocardiograma'],
    ],
    [
        'categoria' => 'Check-ups preventivos',
        'icone'     => 'bi-clipboard2-pulse',
        'itens'     => ['Avaliação cardiológica', 'Exame oftalmológico', 'Avaliação clínica geral'],
    ],
];
?>

<div id="secao-servico-topo">
    <span class="subtitulo-servico">DIAGNÓSTICO</span>
    <h1 class="titulo-servico">Exames</h1>
    <p class="texto-servico">Estrutura completa pra exames laboratoriais e de imagem, com resultado direto no seu painel.</p>
</div>

<div class="secao-categorias-exames">
    <?php foreach ($categoriasExames as $cat): ?>
        <div class="card-categoria-exame">
            <div class="card-categoria-exame-topo">
                <i class="bi <?php echo $cat['icone']; ?>"></i>
                <p class="card-categoria-exame-titulo"><?php echo htmlspecialchars($cat['categoria']); ?></p>
            </div>
            <ul class="lista-categoria-exame">
                <?php foreach ($cat['itens'] as $item): ?>
                    <li><i class="bi bi-check-circle-fill"></i> <?php echo htmlspecialchars($item); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endforeach; ?>
</div>

<div class="secao-cta-exames">
    <p class="texto-cta-exames">Precisa de um exame que não está na lista? Fale com a gente.</p>
    <a href="index.php#secao-contato" class="botao-ver-todos">Entrar em contato</a>
</div>

<?php require_once 'includes/footer.php'; ?>

</body>
</html>