<?php
// gestor/cadastrar-convenio.php
session_start();
require_once '../../conexao.php';

// Proteção de acesso: Apenas gestores
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_perfil'] !== 'gestor') {
    header("Location: ../../auth/login.php");
    exit();
}

// Busca os convênios e faz o JOIN com a tabela correta 'convenio_planos'
try {
    $stmt = $pdo->query("
        SELECT c.*, COUNT(p.id) AS total_planos 
        FROM convenios c 
        LEFT JOIN convenio_planos p ON c.id = p.id_convenio 
        GROUP BY c.id 
        ORDER BY c.id DESC
    ");
    $conveniosCadastrados = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $conveniosCadastrados = [];
}

// ================= CONVÊNIO EM EDIÇÃO (?editar=ID) =================
$convenioEditando = null;
$planosEditando = [];
if (isset($_GET['editar'])) {
    $idEditar = (int) $_GET['editar'];
    try {
        $stmtConv = $pdo->prepare("SELECT * FROM convenios WHERE id = ?");
        $stmtConv->execute([$idEditar]);
        $convenioEditando = $stmtConv->fetch(PDO::FETCH_ASSOC);

        if (!$convenioEditando) {
            header("Location: cadastrar-convenio.php?erro=" . urlencode('Convênio não encontrado.'));
            exit();
        }

        $stmtPlanos = $pdo->prepare("SELECT nome_plano AS nome, cobertura FROM convenio_planos WHERE id_convenio = ? ORDER BY id ASC");
        $stmtPlanos->execute([$idEditar]);
        $planosEditando = $stmtPlanos->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        header("Location: cadastrar-convenio.php?erro=" . urlencode('Erro ao carregar convênio.'));
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastrar Convênio - Convelum Saúde</title>

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
        <h1 id="titulo-painel">Cadastrar convênio</h1>
        <p id="texto-painel">Gerencie os convênios aceitos pela clínica e seus planos.</p>
    </div>
</div>

<div class="secao-config-agenda">

<!-- Mensagens de Alerta (Sucesso ou Erro) -->
<?php if (isset($_GET['status']) && $_GET['status'] === 'sucesso'): ?>
    <div class="alert alert-success alert-dismissible w-100 mb-4" role="alert" id="alertaConvenio">
        <i class="bi bi-check-circle-fill me-2"></i> Convênio cadastrado com sucesso!
        <button type="button" class="btn-close" aria-label="Fechar" onclick="document.getElementById('alertaConvenio').remove();"></button>
    </div>
<?php elseif (isset($_GET['status']) && $_GET['status'] === 'editado'): ?>
    <div class="alert alert-success alert-dismissible w-100 mb-4" role="alert" id="alertaConvenio">
        <i class="bi bi-check-circle-fill me-2"></i> Convênio atualizado com sucesso!
        <button type="button" class="btn-close" aria-label="Fechar" onclick="document.getElementById('alertaConvenio').remove();"></button>
    </div>
<?php elseif (isset($_GET['erro'])): ?>
    <div class="alert alert-danger alert-dismissible w-100 mb-4" role="alert" id="alertaConvenioErro">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> Erro ao cadastrar: <?php echo htmlspecialchars($_GET['erro']); ?>
        <button type="button" class="btn-close" aria-label="Fechar" onclick="document.getElementById('alertaConvenioErro').remove();"></button>
    </div>
<?php endif; ?>

    <!-- ========================= FORMULÁRIO ========================= -->
    <form method="post" action="actions/<?php echo $convenioEditando ? 'atualizar-convenio.php' : 'salvar-convenio.php'; ?>" class="cartao-config-agenda">
        <h2 class="titulo-cartao-config"><?php echo $convenioEditando ? 'Editar convênio' : 'Novo convênio'; ?></h2>

        <?php if ($convenioEditando): ?>
            <input type="hidden" name="id" value="<?php echo (int) $convenioEditando['id']; ?>">
        <?php endif; ?>

        <div class="linha-config-dupla">
            <div class="campo-config">
                <label for="nomeConvenio">Nome do convênio</label>
                <input type="text" id="nomeConvenio" name="nome" placeholder="Ex: VidaMed"
                       value="<?php echo htmlspecialchars($convenioEditando['nome'] ?? ''); ?>" required>
            </div>
            <div class="campo-config">
                <label for="registroAns">Registro ANS <span class="campo-opcional">(opcional)</span></label>
                <input type="text" id="registroAns" name="registro_ans" placeholder="Ex: 123456"
                       value="<?php echo htmlspecialchars($convenioEditando['registro_ans'] ?? ''); ?>">
            </div>
        </div>

        <div class="linha-config-dupla">
            <div class="campo-config">
                <label for="prazoFaturamento">Prazo de faturamento</label>
                <?php $prazoAtual = $convenioEditando ? (int) $convenioEditando['prazo_faturamento'] : 30; ?>
                <select id="prazoFaturamento" name="prazo_faturamento">
                    <option value="15" <?php echo $prazoAtual === 15 ? 'selected' : ''; ?>>15 dias</option>
                    <option value="30" <?php echo $prazoAtual === 30 ? 'selected' : ''; ?>>30 dias</option>
                    <option value="45" <?php echo $prazoAtual === 45 ? 'selected' : ''; ?>>45 dias</option>
                    <option value="60" <?php echo $prazoAtual === 60 ? 'selected' : ''; ?>>60 dias</option>
                </select>
            </div>
            <div class="campo-config">
                <label for="statusConvenio">Status</label>
                <?php $statusAtual = strtolower($convenioEditando['status'] ?? 'ativo'); ?>
                <select id="statusConvenio" name="status">
                    <option value="ativo" <?php echo $statusAtual === 'ativo' ? 'selected' : ''; ?>>Ativo</option>
                    <option value="inativo" <?php echo $statusAtual === 'inativo' ? 'selected' : ''; ?>>Inativo</option>
                </select>
            </div>
        </div>

        <!-- ========================= PLANOS DO CONVÊNIO ========================= -->
        <div class="campo-config">
            <label>Planos oferecidos</label>
        </div>

        <div class="lista-medicamentos" id="listaPlanos"></div>
        <p class="medicamentos-vazio" id="planosVazio">Nenhum plano adicionado ainda.</p>

        <div class="cartao-novo-medicamento">
            <div class="linha-config-dupla">
                <div class="campo-config">
                    <label for="nomePlano">Nome do plano</label>
                    <input type="text" id="nomePlano" placeholder="Ex: Plano Família Premium">
                </div>
                <div class="campo-config">
                    <label for="coberturaPlano">Cobertura</label>
                    <input type="text" id="coberturaPlano" placeholder="Ex: 100% consultas, 80% exames">
                </div>
            </div>
            <button type="button" class="botao-config-adicionar" onclick="adicionarPlano()">
                <i class="bi bi-plus-lg"></i> Adicionar plano
            </button>
        </div>

        <input type="hidden" name="planos" id="planosJson" value="[]">

        <div style="display:flex; gap:12px;">
            <button type="submit" class="botao-entrar botao-config-salvar">
                <?php echo $convenioEditando ? 'Salvar alterações' : 'Cadastrar convênio'; ?>
            </button>
            <?php if ($convenioEditando): ?>
                <a href="cadastrar-convenio.php" class="botao-entrar" style="background:#6b7280; color:#fff; text-decoration:none; display:inline-flex; align-items:center; justify-content:center;">
                    Cancelar
                </a>
            <?php endif; ?>
        </div>
    </form>

    <!-- ========================= CONVÊNIOS CADASTRADOS ========================= -->
    <div class="cartao-config-agenda">
        <h2 class="titulo-cartao-config">Convênios cadastrados</h2>

        <div class="lista-config-registros">
            <?php if (!empty($conveniosCadastrados)): ?>
                <?php foreach ($conveniosCadastrados as $convenio): 
                    $ehAtivo = strtolower($convenio['status'] ?? '') === 'ativo';
                ?>
                    <div class="registro-config">
                        <div>
                            <span class="registro-config-tipo"><?php echo htmlspecialchars($convenio['nome']); ?></span>
                            <p class="registro-medicamento-detalhes">
                                <?php echo $convenio['total_planos']; ?> plano(s) · Faturamento a cada <?php echo htmlspecialchars($convenio['prazo_faturamento'] ?? ''); ?>
                            </p>
                        </div>
                        <div style="display:flex; align-items:center; gap:12px;">
                            <span class="selo-solicitacao-<?php echo $ehAtivo ? 'aprovada' : 'negada'; ?>">
                                <?php echo $ehAtivo ? 'Ativo' : 'Inativo'; ?>
                            </span>
                            <a href="cadastrar-convenio.php?editar=<?php echo (int) $convenio['id']; ?>"
                               class="registro-config-remover text-decoration-none"
                               style="color:#2563eb;"
                               title="Editar convênio">
                                <i class="bi bi-pencil"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted p-3">Nenhum convênio cadastrado até o momento.</p>
            <?php endif; ?>
        </div>
    </div>

</div>

<script>
const planos = <?php echo json_encode($planosEditando, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

// Escapa texto antes de inserir no innerHTML, evitando XSS caso o nome/cobertura
// do plano contenha tags HTML digitadas pelo usuário.
function escaparHtml(texto) {
    const div = document.createElement('div');
    div.textContent = texto;
    return div.innerHTML;
}

function renderizarPlanos() {
    const lista = document.getElementById('listaPlanos');
    const vazio = document.getElementById('planosVazio');

    lista.innerHTML = '';
    vazio.style.display = planos.length === 0 ? 'block' : 'none';

    planos.forEach(function (plano, indice) {
        const linha = document.createElement('div');
        linha.className = 'registro-config registro-medicamento';
        linha.innerHTML =
            '<div>' +
                '<span class="registro-config-tipo">' + escaparHtml(plano.nome) + '</span>' +
                '<p class="registro-medicamento-detalhes">' + escaparHtml(plano.cobertura) + '</p>' +
            '</div>' +
            '<button type="button" class="registro-config-remover" onclick="removerPlano(' + indice + ')" title="Remover">' +
                '<i class="bi bi-trash"></i>' +
            '</button>';
        lista.appendChild(linha);
    });

    document.getElementById('planosJson').value = JSON.stringify(planos);
}

function adicionarPlano() {
    const nome = document.getElementById('nomePlano').value.trim();
    const cobertura = document.getElementById('coberturaPlano').value.trim();

    if (!nome || !cobertura) {
        alert('Preencha o nome e a cobertura do plano.');
        return;
    }

    planos.push({ nome, cobertura });
    renderizarPlanos();

    document.getElementById('nomePlano').value = '';
    document.getElementById('coberturaPlano').value = '';
    document.getElementById('nomePlano').focus();
}

function removerPlano(indice) {
    planos.splice(indice, 1);
    renderizarPlanos();
}

// Ao entrar em modo de edição, já renderiza os planos existentes do convênio
// e garante que o campo escondido "planos" saia preenchido mesmo que o
// gestor não adicione/remova nenhum plano antes de salvar.
renderizarPlanos();
</script>

</body>
</html>