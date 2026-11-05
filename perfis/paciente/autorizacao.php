<?php
// perfis/paciente/autorizacao.php
session_start();

$caminhoConexao = __DIR__ . '/../../conexao.php';
if (file_exists($caminhoConexao)) {
    require_once $caminhoConexao;
}

if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'paciente') {
    header("Location: ../../auth/login.php");
    exit();
}

$pacienteId = (int)$_SESSION['usuario_id'];
$autorizacoes = [];
$erroAutorizacoes = !isset($pdo);
if (isset($pdo)) {
    try {
        $stmtAutorizacoes = $pdo->prepare("
            SELECT id, procedimento, medico, data_pedido, justificativa, arquivo, status, data_solicitacao
            FROM autorizacoes
            WHERE paciente_id = ?
            ORDER BY data_solicitacao DESC
        ");
        $stmtAutorizacoes->execute([$pacienteId]);
        $autorizacoes = $stmtAutorizacoes->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Erro ao carregar autorizações do paciente: ' . $e->getMessage());
        $erroAutorizacoes = true;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitar Autorização - Convelum Saúde</title>

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

<div id="painel-boasvindas">
    <div>
        <span id="subtitulo-painel">CONVÊNIO</span>
        <h1 id="titulo-painel">Solicitar autorização</h1>
        <p id="texto-painel">Peça a liberação prévia de procedimentos, exames ou cirurgias.</p>
    </div>
</div>

<div class="secao-config-agenda">

    <?php if (isset($_GET['sucesso'])): ?>
        <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
            Solicitação de autorização enviada com sucesso!
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php elseif (isset($_GET['erro'])): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
            <?php echo htmlspecialchars($_GET['erro']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($erroAutorizacoes): ?>
        <div class="alert alert-warning" role="alert">
            Não foi possível carregar suas solicitações de autorização. O formulário continua disponível.
        </div>
    <?php endif; ?>

    <form method="post" action="actions/salvar-autorizacao.php" enctype="multipart/form-data" class="cartao-config-agenda mb-4">
        <h2 class="titulo-cartao-config">Nova solicitação</h2>

        <div class="campo-config mb-3">
            <label for="procedimento" class="form-label">Procedimento, exame ou cirurgia</label>
            <input type="text" id="procedimento" name="procedimento" class="form-control" placeholder="Ex: Ressonância magnética..." required>
        </div>

        <div class="linha-config-dupla row g-3 mb-3">
            <div class="campo-config col-md-6">
                <label for="medico" class="form-label">Médico solicitante</label>
                <input type="text" id="medico" name="medico" class="form-control" placeholder="Nome do médico" required>
            </div>
            <div class="campo-config col-md-6">
                <label for="dataPedido" class="form-label">Data do pedido médico</label>
                <input type="date" id="dataPedido" name="data_pedido" class="form-control">
            </div>
        </div>

        <div class="campo-config mb-3">
            <label for="justificativa" class="form-label">Justificativa clínica</label>
            <textarea id="justificativa" name="justificativa" class="form-control" rows="4" placeholder="O que o médico descreveu como motivo do pedido..."></textarea>
        </div>

        <div class="campo-config mb-3">
            <label for="arquivoPedido" class="form-label">Anexar pedido médico (foto ou PDF)</label>
            <input type="file" id="arquivoPedido" name="arquivo_pedido" class="form-control" accept=".pdf, .jpg, .jpeg, .png">
        </div>

        <p class="text-muted small mb-3">Após enviar, você pode acompanhar o status da solicitação nesta página.</p>

        <button type="submit" class="botao-entrar botao-config-salvar btn btn-primary">Enviar solicitação</button>
    </form>

    <div class="cartao-config-agenda">
        <h2 class="titulo-cartao-config">Minhas solicitações</h2>
        <?php if (empty($autorizacoes)): ?>
            <p class="text-muted mb-0"><?php echo $erroAutorizacoes ? 'As solicitações não puderam ser carregadas.' : 'Você ainda não enviou solicitações de autorização.'; ?></p>
        <?php else: ?>
            <div class="lista-itens-painel">
                <?php foreach ($autorizacoes as $autorizacao): ?>
                    <?php
                    $statusAutorizacao = (string)($autorizacao['status'] ?? 'Pendente');
                    $statusClasse = strtolower($statusAutorizacao);
                    $dataSolicitacao = !empty($autorizacao['data_solicitacao'])
                        ? date('d/m/Y H:i', strtotime($autorizacao['data_solicitacao']))
                        : 'Data não informada';
                    ?>
                    <div class="linha-item-painel status-pendente mb-3">
                        <span class="item-painel-data"><?php echo htmlspecialchars($dataSolicitacao, ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="item-painel-principal">
                            <?php echo htmlspecialchars($autorizacao['procedimento'], ENT_QUOTES, 'UTF-8'); ?>
                            <span class="item-painel-submedico">Médico: <?php echo htmlspecialchars($autorizacao['medico'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php if (!empty($autorizacao['justificativa'])): ?>
                                <span class="item-painel-submedico"><?php echo htmlspecialchars($autorizacao['justificativa'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </span>
                        <span class="item-painel-tag"><?php echo htmlspecialchars($statusAutorizacao, ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php if (!empty($autorizacao['arquivo'])): ?>
                            <a href="../../uploads/autorizacoes/<?php echo rawurlencode(basename($autorizacao['arquivo'])); ?>"
                               class="item-painel-botao" download>
                                <i class="bi bi-download"></i> Ver anexo
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

</body>
</html>