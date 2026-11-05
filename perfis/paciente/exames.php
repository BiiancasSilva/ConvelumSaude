<?php
// perfis/paciente/exames.php
session_start();

$caminhoConexao = __DIR__ . '/../../conexao.php';
if (file_exists($caminhoConexao)) {
    require_once $caminhoConexao;
}

if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'paciente') {
    header("Location: ../../auth/login.php");
    exit();
}

$pacienteId = $_SESSION['usuario_id'];
$exames = [];
$erroExames = !isset($pdo);

// CONSULTA NO BANCO DE DADOS
if (isset($pdo)) {
    try {
        $sql = "SELECT 
                    e.id, 
                    e.nome_exame AS nome, 
                    e.data_exame, 
                    e.status, 
                    e.arquivo_laudo AS arquivo,
                    m.nome AS medico_nome
                FROM exames e
                LEFT JOIN medico m ON e.id_medico = m.id
                WHERE e.id_paciente = :paciente_id
                ORDER BY e.data_exame DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':paciente_id' => $pacienteId]);
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($resultados as $row) {
            $medico = !empty($row['medico_nome']) ? 'Dr(a). ' . $row['medico_nome'] : 'Solicitado pelo médico';

            $exames[] = [
                'nome'    => $row['nome'],
                'medico'  => $medico,
                'data'    => date('d/m/Y', strtotime($row['data_exame'])),
                'status'  => strtolower($row['status']),
                'arquivo' => $row['arquivo']
            ];
        }
    } catch (PDOException $e) {
        error_log('Erro ao buscar exames do paciente: ' . $e->getMessage());
        $erroExames = true;
        $exames = [];
    }
}

$rotulosStatus = [
    'disponivel' => 'Disponível',
    'pendente'   => 'Aguardando resultado',
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exames e Laudos - Convelum Saúde</title>

    <link href="../../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/responsivo.css">
</head>
<body>

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
        <span id="subtitulo-painel">SEU HISTÓRICO</span>
        <h1 id="titulo-painel">Exames e laudos</h1>
        <p id="texto-painel">Acompanhe seus resultados e baixe os laudos disponíveis.</p>
    </div>
</div>

<?php if ($erroExames): ?>
    <div class="alert alert-warning mx-3" role="alert">
        Não foi possível carregar seus exames. Tente novamente mais tarde.
    </div>
<?php endif; ?>

<!-- ========================= FILTROS ========================= -->
<div class="alternador-visualizacao">
    <button type="button" class="botao-visualizacao ativo" onclick="filtrarExames('todos', this)">Todos</button>
    <button type="button" class="botao-visualizacao" onclick="filtrarExames('disponivel', this)">Disponíveis</button>
    <button type="button" class="botao-visualizacao" onclick="filtrarExames('pendente', this)">Aguardando</button>
</div>

<!-- ========================= LISTA DE EXAMES ========================= -->
<div class="secao-lista-painel">
    <div class="lista-itens-painel" id="listaExamesPaciente">
        <?php if (!empty($exames)): ?>
            <?php foreach ($exames as $exame): ?>
                <div class="linha-item-painel status-<?php echo $exame['status'] === 'pendente' ? 'pendente' : 'confirmada'; ?>"
                     data-status="<?php echo htmlspecialchars($exame['status']); ?>">

                    <span class="item-painel-data"><?php echo $exame['data']; ?></span>

                    <span class="item-painel-principal">
                        <?php echo htmlspecialchars($exame['nome']); ?>
                        <span class="item-painel-submedico"><?php echo htmlspecialchars($exame['medico']); ?></span>
                    </span>

                    <span class="item-painel-tag">
                        <i class="bi <?php echo $exame['status'] === 'disponivel' ? 'bi-check-circle' : 'bi-hourglass-split'; ?>"></i>
                        <?php echo $rotulosStatus[$exame['status']] ?? 'Aguardando resultado'; ?>
                    </span>

                    <?php if ($exame['status'] === 'disponivel' && !empty($exame['arquivo'])): ?>
                        <a href="../../uploads/laudos/<?php echo rawurlencode(basename($exame['arquivo'])); ?>" class="item-painel-botao" download>
                            <i class="bi bi-download"></i> Baixar
                        </a>
                    <?php else: ?>
                        <span class="item-painel-botao item-painel-botao-desabilitado">Aguardando</span>
                    <?php endif; ?>

                </div>
            <?php endforeach; ?>
        <?php elseif ($erroExames): ?>
            <p class="text-center text-muted py-4">Não foi possível carregar seus exames.</p>
        <?php else: ?>
            <p class="text-center text-muted py-4">Nenhum exame cadastrado no seu histórico.</p>
        <?php endif; ?>
    </div>

    <p class="historico-vazio" id="mensagemVazioExames" style="display:none;">
        Nenhum exame encontrado nesse filtro.
    </p>
</div>

<script>
function filtrarExames(status, botaoClicado) {
    document.querySelectorAll('.alternador-visualizacao .botao-visualizacao').forEach(function (b) {
        b.classList.remove('ativo');
    });
    botaoClicado.classList.add('ativo');

    const linhas = document.querySelectorAll('#listaExamesPaciente .linha-item-painel');
    let algumVisivel = false;

    linhas.forEach(function (linha) {
        const mostrar = status === 'todos' || linha.dataset.status === status;
        linha.style.display = mostrar ? 'flex' : 'none';
        if (mostrar) algumVisivel = true;
    });

    document.getElementById('mensagemVazioExames').style.display = algumVisivel ? 'none' : 'block';
}
</script>

</body>
</html>