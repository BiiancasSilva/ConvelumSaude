<?php
require_once 'includes/header.php';
require_once 'includes/navbar.php';
require_once 'conexao.php';

// Captura e limpa o termo pesquisado
$termo = isset($_GET['q']) ? trim($_GET['q']) : '';

$medicos = [];
$especialidades = [];
$servicos = [];

// Lista base de serviços oferecidos na clínica para busca rápida
$listaServicos = [
    ['nome' => 'Consultas', 'preco' => 'a partir de R$110,00', 'icone' => 'bi-clipboard2-pulse', 'link' => 'auth/login.php'],
    ['nome' => 'Exames', 'preco' => 'a partir de R$14,00', 'icone' => 'bi-droplet-half', 'link' => 'auth/login.php'],
    ['nome' => 'Ultrassom', 'preco' => 'a partir de R$100,00', 'icone' => 'bi-soundwave', 'link' => 'auth/login.php'],
    ['nome' => 'Check-up', 'preco' => 'a partir de R$250,00', 'icone' => 'bi-clipboard2-check', 'link' => 'servicos-checkups.php'],
];

// Pasta onde ficam as fotos de usuários (médico/recepcionista/gestor) e os
// ícones de especialidade enviados pelo gestor.
$pastaIconesEspecialidade = __DIR__ . '/uploads/especialidades/';

if (!empty($termo)) {
    // 1. Pesquisa Serviços na lista de serviços da clínica
    foreach ($listaServicos as $s) {
        if (stripos($s['nome'], $termo) !== false) {
            $servicos[] = $s;
        }
    }

    // 2. Pesquisa Médicos pelo Nome ou CRM
    $sqlMedicos = "SELECT 
                        m.nome, 
                        m.crm, 
                        m.foto,
                        COALESCE(GROUP_CONCAT(e.nome SEPARATOR ', '), 'Clínico Geral') AS especialidade
                   FROM medico m
                   LEFT JOIN medico_especialidades me ON m.id = me.id_medico
                   LEFT JOIN especialidade e ON me.id_especialidade = e.id
                   WHERE m.nome LIKE :termo OR m.crm LIKE :termo
                   GROUP BY m.id
                   ORDER BY m.nome ASC";
                   
    $stmtM = $pdo->prepare($sqlMedicos);
    $stmtM->execute(['termo' => '%' . $termo . '%']);
    $medicos = $stmtM->fetchAll(PDO::FETCH_ASSOC);

    // 3. Pesquisa Especialidades pelo Nome
    $sqlEsp = "SELECT nome, icone FROM especialidade WHERE nome LIKE :termo AND status = 'Ativo' ORDER BY nome ASC";
    $stmtE = $pdo->prepare($sqlEsp);
    $stmtE->execute(['termo' => '%' . $termo . '%']);
    $especialidades = $stmtE->fetchAll(PDO::FETCH_ASSOC);
}
?>
<body>
<!-- TOPO DA PÁGINA -->
<div id="secao-servico-topo">
    <span class="subtitulo-servico">RESULTADOS DA BUSCA</span>
    <h1 class="titulo-servico">Pesquisa por: "<?= htmlspecialchars($termo) ?>"</h1>
</div>

<div style="max-width: 1200px; margin: 0 auto; padding: 40px 20px;">

    <!-- 1. SERVIÇOS ENCONTRADOS -->
    <h2 style="text-align: center; margin-bottom: 25px; color: #333;">Serviços Encontrados</h2>
    <div id="grid-ajuda" style="margin-bottom: 40px;">
        <?php if (!empty($servicos)): ?>
            <?php foreach ($servicos as $serv): ?>
                <div class="card-ajuda">
                    <i class="bi <?= $serv['icone'] ?> icone-ajuda"></i>
                    <p class="titulo-card-ajuda"><?= htmlspecialchars($serv['nome']) ?></p>
                    <p class="preco-card-ajuda"><?= htmlspecialchars($serv['preco']) ?></p>
                    <a href="<?= $serv['link'] ?>" class="botao-agendar-ajuda">Saiba mais <i class="bi bi-chevron-right"></i></a>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="text-align: center; width: 100%; color: #777;">Nenhum serviço encontrado para este termo.</p>
        <?php endif; ?>
    </div>

    <hr style="margin: 40px 0; border: 0; border-top: 1px solid #e0e0e0;">

    <!-- 2. MÉDICOS ENCONTRADOS -->
    <h2 style="text-align: center; margin-bottom: 25px; color: #333;">Médicos Encontrados</h2>
    <div class="secao-equipe" style="margin-bottom: 40px;">
        <div class="grid-equipe">
            <?php if (!empty($medicos)): ?>
                <?php foreach ($medicos as $membro): ?>
                    <div class="card-equipe">
                        <div class="card-equipe-avatar">
                            <?php if (!empty($membro['foto'])): ?>
                                <img src="foto-usuario.php?arquivo=<?= urlencode($membro['foto']); ?>" alt="Foto de <?= htmlspecialchars($membro['nome']); ?>" style="width:100%; height:100%; border-radius:50%; object-fit:cover;">
                            <?php else: ?>
                                <i class="bi bi-person-fill"></i>
                            <?php endif; ?>
                        </div>
                        <p class="card-equipe-nome"><?= htmlspecialchars($membro['nome']); ?></p>
                        <p class="card-equipe-especialidade"><?= htmlspecialchars($membro['especialidade']); ?></p>
                        <p class="card-equipe-crm">CRM: <?= htmlspecialchars($membro['crm']); ?></p>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="text-align: center; width: 100%; color: #777;">Nenhum médico encontrado com esse termo.</p>
            <?php endif; ?>
        </div>
    </div>

    <hr style="margin: 40px 0; border: 0; border-top: 1px solid #e0e0e0;">

    <!-- 3. ESPECIALIDADES ENCONTRADAS -->
    <div id="secao-especialidades">
        <h2 id="titulo-especialidades" style="margin-bottom: 25px;">Especialidades Encontradas</h2>

        <div id="grid-especialidades">
            <?php if (!empty($especialidades)): ?>
                <?php foreach ($especialidades as $esp): ?>
                    <?php
                        // A coluna "icone" guarda o NOME DO ARQUIVO enviado pelo gestor
                        // (salvo em uploads/especialidades/), não um caminho pronto.
                        $icone = (!empty($esp['icone']) && file_exists($pastaIconesEspecialidade . $esp['icone']))
                            ? 'uploads/especialidades/' . htmlspecialchars($esp['icone'])
                            : 'imgs/default.png';
                    ?>
                    <div class="card-especialidade">
                        <img src="<?= $icone ?>" alt="<?= htmlspecialchars($esp['nome']); ?>" class="icone-especialidade">
                        <p class="texto-especialidade"><?= htmlspecialchars($esp['nome']); ?></p>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="text-align: center; width: 100%; color: #777;">Nenhuma especialidade encontrada com esse termo.</p>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- CALL TO ACTION -->
<div class="secao-cta-exames">
    <p class="texto-cta-exames">Não encontrou o que procurava?</p>
    <a href="index.php#secao-contato" class="botao-ver-todos">Fale Conosco</a>
</div>

<?php require_once 'includes/footer.php'; ?>

</body>
</html>