<?php
require_once 'includes/header.php';
require_once 'includes/navbar.php';
require_once 'conexao.php';

// Busca exclusivamente as especialidades cadastradas no banco de dados
try {
    $stmt = $pdo->query("SELECT nome, preco, icone, descricao FROM especialidade WHERE status = 'Ativo' ORDER BY nome ASC");
    $especialidades = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $especialidades = [];
}

// A coluna "icone" guarda o NOME DO ARQUIVO de imagem enviado pelo gestor
// (salvo em uploads/especialidades/ — veja gestor/actions/salvar-especialidade.php),
// não uma classe de ícone do Bootstrap.
$pastaIconesEspecialidade = __DIR__ . '/uploads/especialidades/';
?>

<div id="secao-servico-topo">
    <span class="subtitulo-servico">NOSSAS ESPECIALIDADES</span>
    <h1 class="titulo-servico">Todas as especialidades</h1>
    <p class="texto-servico">Encontre o especialista certo pra você e agende sua consulta.</p>
</div>

<div class="secao-especialidades-completa">

    <div class="campo-busca-especialidade">
        <i class="bi bi-search"></i>
        <input type="text" id="buscaEspecialidade" placeholder="Buscar especialidade..." oninput="filtrarEspecialidades()">
    </div>

    <div class="grid-especialidades-completa">
        <?php if (!empty($especialidades)): ?>
            <?php foreach ($especialidades as $esp): ?>
                <div class="card-especialidade card-especialidade-completa" data-nome="<?php echo strtolower(htmlspecialchars($esp['nome'])); ?>">
                    <?php if (!empty($esp['icone']) && file_exists($pastaIconesEspecialidade . $esp['icone'])): ?>
                        <img src="uploads/especialidades/<?php echo htmlspecialchars($esp['icone']); ?>" alt="<?php echo htmlspecialchars($esp['nome']); ?>" class="icone-especialidade">
                    <?php else: ?>
                        <div class="icone-especialidade-fonte">
                            <i class="bi bi-heart-pulse"></i>
                        </div>
                    <?php endif; ?>
                    <p class="texto-especialidade"><?php echo htmlspecialchars($esp['nome']); ?></p>
                    <?php if (!empty($esp['descricao'])): ?>
                        <p class="descricao-especialidade"><?php echo htmlspecialchars($esp['descricao']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($esp['preco'])): ?>
                        <p class="preco-especialidade-servico">a partir de R$<?php echo htmlspecialchars($esp['preco']); ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <p class="mensagem-vazio-especialidade" id="mensagemVazioEspecialidade" style="<?= empty($especialidades) ? 'display:block;' : 'display:none;' ?>">
        <?= empty($especialidades) ? 'Nenhuma especialidade cadastrada no banco de dados.' : 'Nenhuma especialidade encontrada com esse nome.' ?>
    </p>

    <div class="secao-cta-exames">
        <p class="texto-cta-exames">Não achou o que procurava? Fale com a gente.</p>
        <a href="index.php#secao-contato" class="botao-ver-todos">Entrar em contato</a>
    </div>

</div>

<script>
function filtrarEspecialidades() {
    const termo = document.getElementById('buscaEspecialidade').value.trim().toLowerCase();
    const cards = document.querySelectorAll('.card-especialidade-completa');
    let algumVisivel = false;

    cards.forEach(function (card) {
        const mostrar = card.dataset.nome.includes(termo);
        card.style.display = mostrar ? 'flex' : 'none';
        if (mostrar) algumVisivel = true;
    });

    document.getElementById('mensagemVazioEspecialidade').style.display = algumVisivel ? 'none' : 'block';
}
</script>

<?php require_once 'includes/footer.php'; ?>

</body>
</html>