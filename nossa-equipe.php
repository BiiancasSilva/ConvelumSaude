<?php
require_once 'includes/header.php';
require_once 'includes/navbar.php';

// Conexão com o banco na raiz do projeto
require_once 'conexao.php';

// Busca médicos e suas especialidades (caso o médico tenha especialidade associada)
$sql = "SELECT 
            m.nome, 
            m.crm, 
            m.foto,
            COALESCE(GROUP_CONCAT(e.nome SEPARATOR ', '), 'Clínico Geral') AS especialidade
        FROM medico m
        LEFT JOIN medico_especialidades me ON m.id = me.id_medico
        LEFT JOIN especialidade e ON me.id_especialidade = e.id
        GROUP BY m.id
        ORDER BY m.nome ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$equipe = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div id="secao-servico-topo">
    <span class="subtitulo-servico">QUEM CUIDA DE VOCÊ</span>
    <h1 class="titulo-servico">Nossa equipe</h1>
    <p class="texto-servico">Profissionais qualificados e comprometidos com o seu bem-estar, em cada etapa do atendimento.</p>
</div>

<div class="secao-equipe">
    <div class="grid-equipe">
        <?php if (!empty($equipe)): ?>
            <?php foreach ($equipe as $membro): ?>
                <div class="card-equipe">
                    <div class="card-equipe-avatar">
                        <?php if (!empty($membro['foto'])): ?>
                            <img src="foto-usuario.php?arquivo=<?php echo urlencode($membro['foto']); ?>" alt="Foto de <?php echo htmlspecialchars($membro['nome']); ?>" style="width:100%; height:100%; border-radius:50%; object-fit:cover;">
                        <?php else: ?>
                            <i class="bi bi-person-fill"></i>
                        <?php endif; ?>
                    </div>
                    <p class="card-equipe-nome"><?php echo htmlspecialchars($membro['nome']); ?></p>
                    <p class="card-equipe-especialidade"><?php echo htmlspecialchars($membro['especialidade']); ?></p>
                    <p class="card-equipe-crm">CRM: <?php echo htmlspecialchars($membro['crm']); ?></p>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="text-align: center; width: 100%;">Nenhum médico cadastrado no momento.</p>
        <?php endif; ?>
    </div>
</div>

<div class="secao-cta-exames">
    <p class="texto-cta-exames">Quer agendar uma consulta com um dos nossos especialistas?</p>
    <a href="auth/login.php" class="botao-ver-todos">Agendar consulta</a>
</div>

<?php require_once 'includes/footer.php'; ?>

</body>
</html>