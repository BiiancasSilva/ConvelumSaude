<?php
// gestor/cadastrar-especialidade.php
session_start();
require_once '../../conexao.php';

// Proteção de acesso: Apenas gestores
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_perfil'] !== 'gestor') {
    header("Location: ../../auth/login.php");
    exit();
}

// ================= CONSULTAS AO BANCO DE DADOS =================
try {
    $stmt = $pdo->query("
        SELECT 
            e.id, 
            e.nome, 
            e.preco, 
            e.icone, 
            e.descricao,
            e.status,
            COUNT(me.id_medico) AS medicos_count
        FROM especialidade e
        LEFT JOIN medico_especialidades me ON e.id = me.id_especialidade
        GROUP BY e.id
        ORDER BY (e.status = 'Ativo') DESC, e.nome ASC
    ");
    $especialidadesCadastradas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $especialidadesCadastradas = [];
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastrar Especialidade - Convelum Saúde</title>

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
        <span id="subtitulo-painel">CONFIGURAÇÕES DO SISTEMA</span>
        <h1 id="titulo-painel">Cadastrar especialidade</h1>
        <p id="texto-painel">Gerencie as especialidades oferecidas pela clínica.</p>
    </div>
</div>

<div class="secao-config-agenda">

    <?php if (isset($_GET['status']) && $_GET['status'] === 'sucesso'): 
        $acaoRealizada = $_GET['acao'] ?? '';
        $mensagensSucesso = [
            'exclusao'   => 'Especialidade excluída com sucesso!',
            'edicao'     => 'Especialidade atualizada com sucesso!',
            'ativacao'   => 'Especialidade reativada com sucesso!',
            'inativacao' => 'Especialidade inativada com sucesso!',
        ];
        $mensagemSucesso = $mensagensSucesso[$acaoRealizada] ?? 'Especialidade cadastrada com sucesso!';
    ?>
        <div class="alert alert-success alert-dismissible w-100 mb-4" role="alert" id="alertaEspecialidade">
            <i class="bi bi-check-circle-fill me-2"></i> <?php echo $mensagemSucesso; ?>
            <button type="button" class="btn-close" aria-label="Fechar" onclick="document.getElementById('alertaEspecialidade').remove();"></button>
        </div>
    <?php elseif (isset($_GET['erro'])): ?>
        <div class="alert alert-danger alert-dismissible w-100 mb-4" role="alert" id="alertaEspecialidadeErro">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> Ocorreu um erro ao cadastrar: <?php echo htmlspecialchars($_GET['erro']); ?>
            <button type="button" class="btn-close" aria-label="Fechar" onclick="document.getElementById('alertaEspecialidadeErro').remove();"></button>
        </div>
    <?php endif; ?>

    <!-- ========================= FORMULÁRIO ========================= -->
    <form method="post" action="actions/salvar-especialidade.php" enctype="multipart/form-data" class="cartao-config-agenda">
        <h2 class="titulo-cartao-config">Nova especialidade</h2>

        <div class="campo-config">
            <label for="nomeEspecialidade">Nome da especialidade</label>
            <input type="text" id="nomeEspecialidade" name="nome" placeholder="Ex: Neurologia" required>
        </div>

        <div class="linha-config-dupla">
            <div class="campo-config">
                <label for="precoEspecialidade">Preço base da consulta (R$)</label>
                <input type="number" step="0.01" id="precoEspecialidade" name="preco" placeholder="Ex: 150.00" required>
            </div>
            <div class="campo-config">
                <label for="iconeEspecialidade">Ícone (imagem)</label>
                <input type="file" id="iconeEspecialidade" name="icone" accept=".png,.jpg,.jpeg,.svg">
            </div>
        </div>

        <div class="campo-config">
            <label for="descricaoEspecialidade">Descrição curta <span class="campo-opcional">(opcional, aparece no site)</span></label>
            <textarea id="descricaoEspecialidade" name="descricao" rows="2" placeholder="Uma frase sobre a especialidade..."></textarea>
        </div>

        <button type="submit" class="botao-entrar botao-config-salvar">Cadastrar especialidade</button>
    </form>

    <!-- ========================= ESPECIALIDADES CADASTRADAS ========================= -->
    <div class="cartao-config-agenda">
        <h2 class="titulo-cartao-config">Especialidades cadastradas</h2>

        <div class="lista-config-registros">
            <?php if (!empty($especialidadesCadastradas)): ?>
                <?php foreach ($especialidadesCadastradas as $esp):
                    $ehAtivo = ($esp['status'] ?? 'Ativo') === 'Ativo';
                ?>
                    <div class="registro-config registro-especialidade">
                        <div class="registro-especialidade-icone">
                            <?php if (!empty($esp['icone']) && file_exists("../../uploads/especialidades/" . $esp['icone'])): ?>
                                <img src="../../uploads/especialidades/<?php echo $esp['icone']; ?>" alt="<?php echo htmlspecialchars($esp['nome']); ?>" style="width: 24px; height: 24px; object-fit: contain;">
                            <?php else: ?>
                                <i class="bi bi-stethoscope"></i>
                            <?php endif; ?>
                        </div>
                        <div class="registro-especialidade-info">
                            <span class="registro-config-tipo"><?php echo htmlspecialchars($esp['nome']); ?></span>
                            <p class="registro-medicamento-detalhes">
                                A partir de R$ <?php echo number_format($esp['preco'] ?? 0, 2, ',', '.'); ?> · <?php echo $esp['medicos_count']; ?> médico(s) vinculado(s)
                            </p>
                        </div>
                        <span class="selo-solicitacao-<?php echo $ehAtivo ? 'aprovada' : 'negada'; ?>">
                            <?php echo $ehAtivo ? 'Ativa' : 'Inativa'; ?>
                        </span>
                        <a href="editar-especialidade.php?id=<?php echo $esp['id']; ?>" class="registro-config-remover" title="Editar">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <a href="actions/alternar-status-especialidade.php?id=<?php echo $esp['id']; ?>" class="registro-config-remover" title="<?php echo $ehAtivo ? 'Inativar' : 'Ativar'; ?>"
                           onclick="return confirm('<?php echo $ehAtivo ? 'Inativar esta especialidade? Ela deixará de aparecer para agendamento.' : 'Reativar esta especialidade?'; ?>');">
                            <i class="bi <?php echo $ehAtivo ? 'bi-toggle-on' : 'bi-toggle-off'; ?>"></i>
                        </a>
                        <a href="actions/excluir-especialidade.php?id=<?php echo $esp['id']; ?>" class="registro-config-remover" title="Excluir" onclick="return confirm('Excluir apaga a especialidade e TODAS as consultas vinculadas a ela permanentemente. Prefira inativar. Deseja realmente excluir?');">
                            <i class="bi bi-trash"></i>
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted p-3">Nenhuma especialidade cadastrada até o momento.</p>
            <?php endif; ?>
        </div>
    </div>

</div>

</body>
</html>