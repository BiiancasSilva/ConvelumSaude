<?php
// gestor/editar-especialidade.php
session_start();
require_once '../../conexao.php';

// Proteção de acesso: Apenas gestores
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_perfil'] !== 'gestor') {
    header("Location: ../../auth/login.php");
    exit();
}

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header("Location: cadastrar-especialidade.php?erro=" . urlencode('Especialidade inválida.'));
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT * FROM especialidade WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $especialidade = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $especialidade = false;
}

if (!$especialidade) {
    header("Location: cadastrar-especialidade.php?erro=" . urlencode('Especialidade não encontrada.'));
    exit();
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Especialidade - Convelum Saúde</title>

    <link href="../../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/responsivo.css">
    <style>
        .icone-atual-preview { display:flex; align-items:center; gap:12px; margin-bottom:10px; }
        .icone-atual-preview img { width:40px; height:40px; object-fit:contain; }
        .icone-atual-preview span { opacity:.75; font-size:.9rem; }
    </style>
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
        <a href="cadastrar-especialidade.php" class="topo-painel-sair">
            <i class="bi bi-arrow-left"></i> Voltar ao painel
        </a>
    </div>
</div>

<!-- ========================= CABEÇALHO ========================= -->
<div id="painel-boasvindas">
    <div>
        <span id="subtitulo-painel">CONFIGURAÇÕES DO SISTEMA</span>
        <h1 id="titulo-painel">Editar especialidade</h1>
        <p id="texto-painel">Atualize os dados de <?php echo htmlspecialchars($especialidade['nome']); ?>.</p>
    </div>
</div>

<div class="secao-config-agenda">

<?php if (isset($_GET['erro'])): ?>
    <div class="alert alert-danger alert-dismissible w-100 mb-4" role="alert" id="alertaEspecialidadeErro">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> Erro ao salvar: <?php echo htmlspecialchars($_GET['erro']); ?>
        <button type="button" class="btn-close" aria-label="Fechar" onclick="document.getElementById('alertaEspecialidadeErro').remove();"></button>
    </div>
<?php endif; ?>

    <form method="post" action="actions/atualizar-especialidade.php" enctype="multipart/form-data" class="cartao-config-agenda">
        <h2 class="titulo-cartao-config">Dados da especialidade</h2>

        <input type="hidden" name="id" value="<?php echo $especialidade['id']; ?>">

        <div class="campo-config">
            <label for="nomeEspecialidade">Nome da especialidade</label>
            <input type="text" id="nomeEspecialidade" name="nome" value="<?php echo htmlspecialchars($especialidade['nome']); ?>" required>
        </div>

        <div class="linha-config-dupla">
            <div class="campo-config">
                <label for="precoEspecialidade">Preço base da consulta (R$)</label>
                <input type="number" step="0.01" id="precoEspecialidade" name="preco" value="<?php echo htmlspecialchars($especialidade['preco']); ?>" required>
            </div>
            <div class="campo-config">
                <label for="statusEspecialidade">Status</label>
                <select id="statusEspecialidade" name="status">
                    <option value="Ativo" <?php echo $especialidade['status'] === 'Ativo' ? 'selected' : ''; ?>>Ativa</option>
                    <option value="Inativo" <?php echo $especialidade['status'] === 'Inativo' ? 'selected' : ''; ?>>Inativa</option>
                </select>
            </div>
        </div>

        <div class="campo-config">
            <label for="iconeEspecialidade">Ícone (imagem)</label>
            <?php if (!empty($especialidade['icone']) && file_exists("../../uploads/especialidades/" . $especialidade['icone'])): ?>
                <div class="icone-atual-preview">
                    <img src="../../uploads/especialidades/<?php echo htmlspecialchars($especialidade['icone']); ?>" alt="Ícone atual">
                    <span>Ícone atual — envie um arquivo abaixo só se quiser substituí-lo.</span>
                </div>
            <?php else: ?>
                <p class="text-muted" style="margin-bottom:8px;">Esta especialidade ainda não tem ícone cadastrado.</p>
            <?php endif; ?>
            <input type="file" id="iconeEspecialidade" name="icone" accept=".png,.jpg,.jpeg,.svg">
        </div>

        <div class="campo-config">
            <label for="descricaoEspecialidade">Descrição curta <span class="campo-opcional">(opcional, aparece no site)</span></label>
            <textarea id="descricaoEspecialidade" name="descricao" rows="2"><?php echo htmlspecialchars($especialidade['descricao'] ?? ''); ?></textarea>
        </div>

        <button type="submit" class="botao-entrar botao-config-salvar">Salvar alterações</button>
    </form>

</div>

</body>
</html>