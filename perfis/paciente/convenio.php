<?php
// perfis/paciente/convenio.php
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
$erroConvenio = '';

// Valóres padrão caso o paciente não tenha convênio cadastrado
$meuConvenio = [
    'nome'        => 'Sem Convênio Vinculado',
    'plano'       => 'Atendimento Particular',
    'carteirinha' => 'N/A',
    'validade'    => 'N/A',
    'status'      => 'inativo',
];

$redeCredenciada = [];

if (isset($pdo)) {
    try {
        // 1. Busca os dados do convênio do paciente
        $sqlConvenio = "SELECT 
                            c.nome AS convenio_nome, 
                            p.plano_convenio AS plano, 
                            p.num_carteirinha AS carteirinha, 
                            p.validade_carteirinha AS validade,
                            p.status_convenio AS status
                        FROM paciente p
                        LEFT JOIN convenios c ON p.id_convenio = c.id
                        WHERE p.id = :paciente_id
                        LIMIT 1";

        $stmt = $pdo->prepare($sqlConvenio);
        $stmt->execute([':paciente_id' => $pacienteId]);
        $dadosConvenio = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($dadosConvenio && !empty($dadosConvenio['convenio_nome'])) {
            $meuConvenio = [
                'nome'        => $dadosConvenio['convenio_nome'],
                'plano'       => $dadosConvenio['plano'] ?? 'Plano Padrão',
                'carteirinha' => $dadosConvenio['carteirinha'] ?? 'Não informada',
                'validade'    => !empty($dadosConvenio['validade']) ? date('m/Y', strtotime($dadosConvenio['validade'])) : 'Indeterminada',
                'status'      => strtolower($dadosConvenio['status'] ?? 'ativo'),
            ];
        }

        // 2. Busca a rede credenciada vinculada no banco de dados
        $sqlRede = "SELECT nome, especialidade, cidade FROM rede_credenciada ORDER BY nome ASC";
        $stmtRede = $pdo->prepare($sqlRede);
        $stmtRede->execute();
        $redeCredenciada = $stmtRede->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log('Erro ao carregar convênio do paciente: ' . $e->getMessage());
        $erroConvenio = 'Não foi possível carregar os dados do convênio.';
        $redeCredenciada = [];
    }
} else {
    $erroConvenio = 'Não foi possível conectar ao banco de dados.';
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Convênio - Convelum Saúde</title>

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
        <span id="subtitulo-painel">SEU PLANO</span>
        <h1 id="titulo-painel">Meu convênio</h1>
        <p id="texto-painel">Dados do seu plano e consulta à rede credenciada.</p>
    </div>
    <a href="autorizacao.php" class="botao-acao-principal">
        <i class="bi bi-file-earmark-check"></i> Solicitar autorização
    </a>
</div>

<div class="secao-config-agenda">
    <?php if ($erroConvenio !== ''): ?>
        <div class="alert alert-warning" role="alert"><?php echo htmlspecialchars($erroConvenio, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <!-- ========================= CARTÃO DO CONVÊNIO ========================= -->
    <div class="cartao-carteirinha">
        <div class="cartao-carteirinha-topo d-flex justify-content-between align-items-center">
            <span class="cartao-carteirinha-nome"><?php echo htmlspecialchars($meuConvenio['nome']); ?></span>
            <span class="selo-receita-<?php echo $meuConvenio['status'] === 'ativo' ? 'ativa' : 'expirada'; ?> badge bg-<?php echo $meuConvenio['status'] === 'ativo' ? 'success' : 'secondary'; ?>">
                <?php echo $meuConvenio['status'] === 'ativo' ? 'Ativo' : 'Inativo'; ?>
            </span>
        </div>

        <p class="cartao-carteirinha-plano"><?php echo htmlspecialchars($meuConvenio['plano']); ?></p>

        <div class="cartao-carteirinha-linha">
            <div>
                <span class="cartao-carteirinha-rotulo">Número da carteirinha</span>
                <span class="cartao-carteirinha-valor"><?php echo htmlspecialchars($meuConvenio['carteirinha']); ?></span>
            </div>
            <div>
                <span class="cartao-carteirinha-rotulo">Validade</span>
                <span class="cartao-carteirinha-valor"><?php echo htmlspecialchars($meuConvenio['validade']); ?></span>
            </div>
        </div>
    </div>

    <!-- ========================= REDE CREDENCIADA ========================= -->
    <div class="cartao-config-agenda mt-4">
        <h2 class="titulo-cartao-config">Rede credenciada</h2>

        <div class="linha-config-dupla">
            <div class="campo-config">
                <label for="buscaRede">Buscar</label>
                <input type="text" id="buscaRede" class="form-control" placeholder="Nome, especialidade ou cidade..." oninput="filtrarRede()">
            </div>
            <div class="campo-config">
                <label for="filtroEspecialidade">Especialidade</label>
                <select id="filtroEspecialidade" class="form-select" onchange="filtrarRede()">
                    <option value="">Todas</option>
                    <?php
                    $especialidades = array_unique(array_filter(array_column($redeCredenciada, 'especialidade')));
                    foreach ($especialidades as $esp):
                    ?>
                        <option value="<?php echo htmlspecialchars($esp); ?>"><?php echo htmlspecialchars($esp); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="lista-itens-painel mt-3" id="listaRedeCredenciada">
            <?php if (!empty($redeCredenciada)): ?>
                <?php foreach ($redeCredenciada as $local): ?>
                    <div class="linha-item-painel status-confirmada mb-2"
                         data-nome="<?php echo strtolower(htmlspecialchars($local['nome'])); ?>"
                         data-especialidade="<?php echo htmlspecialchars($local['especialidade']); ?>"
                         data-cidade="<?php echo strtolower(htmlspecialchars($local['cidade'])); ?>">

                        <span class="item-painel-principal">
                            <?php echo htmlspecialchars($local['nome']); ?>
                            <span class="item-painel-submedico text-muted small d-block"><?php echo htmlspecialchars($local['cidade']); ?></span>
                        </span>

                        <span class="item-painel-tag badge bg-light text-dark border">
                            <i class="bi bi-heart-pulse text-primary"></i>
                            <?php echo htmlspecialchars($local['especialidade']); ?>
                        </span>

                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-center text-muted py-4">Nenhum credenciado encontrado no sistema.</p>
            <?php endif; ?>
        </div>

        <p class="historico-vazio text-center text-muted py-3" id="mensagemVazioRede" style="display:none;">
            Nenhum resultado encontrado pra essa busca.
        </p>
    </div>

</div>

<script>
function filtrarRede() {
    const termo = document.getElementById('buscaRede').value.trim().toLowerCase();
    const especialidade = document.getElementById('filtroEspecialidade').value;
    const linhas = document.querySelectorAll('#listaRedeCredenciada .linha-item-painel');
    let algumVisivel = false;

    linhas.forEach(function (linha) {
        const bateTermo = !termo ||
            linha.dataset.nome.includes(termo) ||
            linha.dataset.cidade.includes(termo) ||
            linha.dataset.especialidade.toLowerCase().includes(termo);

        const bateEspecialidade = !especialidade || linha.dataset.especialidade === especialidade;

        const mostrar = bateTermo && bateEspecialidade;
        linha.style.display = mostrar ? 'flex' : 'none';
        if (mostrar) algumVisivel = true;
    });

    document.getElementById('mensagemVazioRede').style.display = algumVisivel ? 'none' : 'block';
}
</script>

</body>
</html>