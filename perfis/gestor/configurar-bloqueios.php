<?php
// gestor/configurar-bloqueios.php
session_start();
require_once '../../conexao.php';
require_once 'actions/bloqueios-helpers.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_perfil'] !== 'gestor') {
    header("Location: ../../auth/login.php");
    exit();
}

// Token usado nos formulários de cadastro/edição/exclusão desta página
$csrfToken = gerarTokenCsrfBloqueio();

// Se veio ?editar=ID, carrega os dados desse bloqueio pra pré-preencher o formulário
$bloqueioEditando = null;
if (isset($_GET['editar'])) {
    $idEditar = (int)$_GET['editar'];
    try {
        $stmtEditar = $pdo->prepare("SELECT * FROM bloqueios WHERE id = ?");
        $stmtEditar->execute([$idEditar]);
        $bloqueioEditando = $stmtEditar->fetch(PDO::FETCH_ASSOC);

        if (!$bloqueioEditando) {
            header("Location: configurar-bloqueios.php?erro=" . urlencode('Bloqueio não encontrado.'));
            exit();
        }
    } catch (PDOException $e) {
        header("Location: configurar-bloqueios.php?erro=" . urlencode('Erro ao carregar bloqueio.'));
        exit();
    }
}

// Busca médicos, recepcionistas e gestores do banco de dados
$medicos = [];
$recepcionistas = [];
$gestores = [];
$erroBanco = null;

try {
    $stmtMed = $pdo->query("SELECT uid AS id, nome FROM medico ORDER BY nome ASC");
    $medicos = $stmtMed->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $erroBanco = "Erro ao buscar médicos.";
}

try {
    $stmtRec = $pdo->query("SELECT uid AS id, nome FROM recepcionistas ORDER BY nome ASC");
    $recepcionistas = $stmtRec->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $erroBanco = $erroBanco ?? "Erro ao buscar recepcionistas.";
}

try {
    $stmtGest = $pdo->query("SELECT uid AS id, nome FROM gestores ORDER BY nome ASC");
    $gestores = $stmtGest->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $erroBanco = $erroBanco ?? "Erro ao buscar gestores.";
}

// Busca bloqueios salvos
try {
    $stmt = $pdo->query("SELECT * FROM bloqueios ORDER BY data_inicio DESC");
    $bloqueiosCadastrados = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $bloqueiosCadastrados = [];
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurar Bloqueios - Convelum Saúde</title>

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
        <span id="subtitulo-painel">CONFIGURAÇÕES DO SISTEMA</span>
        <h1 id="titulo-painel">Configurar bloqueios de agenda</h1>
        <p id="texto-painel">Bloqueie datas ou horários para médicos, recepcionistas, gestores ou toda a clínica.</p>
    </div>
</div>

<div class="secao-config-agenda">

    <?php if (isset($_GET['sucesso'])): ?>
        <div class="alert alert-success alert-dismissible fade show w-100" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <?php 
                if ($_GET['sucesso'] === 'excluido') {
                    echo 'Bloqueio removido com sucesso!';
                } elseif ($_GET['sucesso'] === 'editado') {
                    echo 'Bloqueio atualizado com sucesso!';
                } else {
                    echo 'Bloqueio cadastrado com sucesso!';
                }
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php elseif (isset($_GET['erro'])): ?>
        <div class="alert alert-danger alert-dismissible fade show w-100" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <?php echo htmlspecialchars($_GET['erro']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php elseif ($erroBanco): ?>
        <div class="alert alert-danger alert-dismissible fade show w-100" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <?php echo htmlspecialchars($erroBanco); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <form method="post"
          action="<?php echo $bloqueioEditando ? 'actions/atualizar-bloqueio.php' : 'actions/salvar-bloqueio.php'; ?>"
          class="cartao-config-agenda">
        <h2 class="titulo-cartao-config">
            <?php echo $bloqueioEditando ? 'Editar bloqueio' : 'Novo bloqueio'; ?>
        </h2>

        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

        <?php if ($bloqueioEditando): ?>
            <input type="hidden" name="id" value="<?php echo (int)$bloqueioEditando['id']; ?>">
        <?php endif; ?>

        <div class="linha-config-dupla">
            <!-- Select 1: Categoria do Alvo -->
            <div class="campo-config">
                <label for="tipoAlvo">Aplicar a quem?</label>
                <select id="tipoAlvo" name="tipo_alvo" onchange="atualizarSelecaoAlvo()" required>
                    <option value="" disabled <?php echo !$bloqueioEditando ? 'selected' : ''; ?>>Selecione uma categoria...</option>
                    <option value="geral" <?php echo ($bloqueioEditando['tipo_alvo'] ?? '') === 'geral' ? 'selected' : ''; ?>>Toda a Clínica</option>
                    <option value="medico" <?php echo ($bloqueioEditando['tipo_alvo'] ?? '') === 'medico' ? 'selected' : ''; ?>>Médicos</option>
                    <option value="recepcionista" <?php echo ($bloqueioEditando['tipo_alvo'] ?? '') === 'recepcionista' ? 'selected' : ''; ?>>Recepcionistas</option>
                    <option value="gestor" <?php echo ($bloqueioEditando['tipo_alvo'] ?? '') === 'gestor' ? 'selected' : ''; ?>>Gestores</option>
                </select>
            </div>

            <!-- Select 2: Profissional Específico ou Todos da Categoria -->
            <div class="campo-config" id="containerSubAlvo" style="display: none;">
                <label for="subAlvo" id="labelSubAlvo">Selecione o usuário</label>
                <select id="subAlvo" name="alvo_id">
                    <!-- Opções dinâmicas via JS -->
                </select>
            </div>

            <div class="campo-config">
                <label for="motivoBloqueio">Motivo / Descrição</label>
                <input type="text" id="motivoBloqueio" name="motivo" placeholder="Ex: Folga / Reunião / Manutenção"
                       value="<?php echo htmlspecialchars($bloqueioEditando['motivo'] ?? ''); ?>" required>
            </div>
        </div>

        <div class="linha-config-dupla">
            <div class="campo-config">
                <label for="dataInicio">Data Início</label>
                <input type="date" id="dataInicio" name="data_inicio"
                       value="<?php echo htmlspecialchars($bloqueioEditando['data_inicio'] ?? ''); ?>" required>
            </div>
            <div class="campo-config">
                <label for="horaInicio">Hora Início</label>
                <input type="time" id="horaInicio" name="hora_inicio"
                       value="<?php echo htmlspecialchars($bloqueioEditando['hora_inicio'] ?? ''); ?>">
            </div>
        </div>

        <div class="linha-config-dupla">
            <div class="campo-config">
                <label for="dataFim">Data Fim</label>
                <input type="date" id="dataFim" name="data_fim"
                       value="<?php echo htmlspecialchars($bloqueioEditando['data_fim'] ?? ''); ?>" required>
            </div>
            <div class="campo-config">
                <label for="horaFim">Hora Fim</label>
                <input type="time" id="horaFim" name="hora_fim"
                       value="<?php echo htmlspecialchars($bloqueioEditando['hora_fim'] ?? ''); ?>">
            </div>
        </div>

        <p class="text-muted" id="avisoPeriodo" style="display:none; color:#dc2626; font-size:0.9em;">
            A data/hora de término não pode ser antes do início.
        </p>

        <div style="display:flex; gap:12px;">
            <button type="submit" class="botao-entrar botao-config-salvar">
                <?php echo $bloqueioEditando ? 'Salvar alterações' : 'Cadastrar bloqueio'; ?>
            </button>
            <?php if ($bloqueioEditando): ?>
                <a href="configurar-bloqueios.php" class="botao-entrar" style="background:#6b7280; color:#fff; text-decoration:none; display:inline-flex; align-items:center; justify-content:center;">
                    Cancelar
                </a>
            <?php endif; ?>
        </div>
    </form>

    <div class="cartao-config-agenda">
        <h2 class="titulo-cartao-config">Bloqueios ativos e agendados</h2>

        <div class="lista-config-registros">
            <?php if (!empty($bloqueiosCadastrados)): ?>
                <?php foreach ($bloqueiosCadastrados as $bloqueio): 
                    $dataIni = date('d/m/Y', strtotime($bloqueio['data_inicio']));
                    $dataFim = date('d/m/Y', strtotime($bloqueio['data_fim']));
                    $horaIni = !empty($bloqueio['hora_inicio']) ? date('H:i', strtotime($bloqueio['hora_inicio'])) : 'Dia todo';
                    $horaFim = !empty($bloqueio['hora_fim']) ? date('H:i', strtotime($bloqueio['hora_fim'])) : 'Dia todo';
                    $alvo    = !empty($bloqueio['nome_alvo']) ? $bloqueio['nome_alvo'] : 'Toda a Clínica';
                ?>
                    <div class="registro-config">
                        <div>
                            <span class="registro-config-tipo">
                                <?php echo htmlspecialchars($bloqueio['motivo']); ?> 
                                <small class="text-primary">(<?php echo htmlspecialchars($alvo); ?>)</small>
                            </span>
                            <p class="registro-medicamento-detalhes">
                                De: <?php echo "$dataIni ($horaIni)"; ?> | Até: <?php echo "$dataFim ($horaFim)"; ?>
                            </p>
                        </div>
                        <div style="display:flex; align-items:center; gap:12px;">
                            <a href="configurar-bloqueios.php?editar=<?php echo $bloqueio['id']; ?>"
                               class="registro-config-remover text-decoration-none"
                               style="color:#2563eb;"
                               title="Editar bloqueio">
                                <i class="bi bi-pencil"></i>
                            </a>

                            <form method="post" action="actions/excluir-bloqueio.php" style="display:inline;"
                                  onsubmit="return confirm('Deseja realmente remover este bloqueio?');">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <input type="hidden" name="id" value="<?php echo (int)$bloqueio['id']; ?>">
                                <button type="submit"
                                        class="registro-config-remover"
                                        style="background:none; border:none; padding:0; cursor:pointer;"
                                        title="Excluir bloqueio">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted p-3">Nenhum bloqueio cadastrado até o momento.</p>
            <?php endif; ?>
        </div>
    </div>

</div>

<script src="../../js/bootstrap.bundle.min.js"></script>

<script>
const medicos = <?php echo json_encode($medicos); ?>;
const recepcionistas = <?php echo json_encode($recepcionistas); ?>;
const gestores = <?php echo json_encode($gestores); ?>;
const alvoAtualId = <?php echo json_encode($bloqueioEditando['alvo_id'] ?? null); ?>;

function atualizarSelecaoAlvo() {
    const tipo = document.getElementById('tipoAlvo').value;
    const container = document.getElementById('containerSubAlvo');
    const selectSub = document.getElementById('subAlvo');
    const labelSub = document.getElementById('labelSubAlvo');

    selectSub.innerHTML = '';

    if (tipo === 'geral' || !tipo) {
        container.style.display = 'none';
        selectSub.removeAttribute('required');
        return;
    }

    container.style.display = 'block';
    selectSub.setAttribute('required', 'required');

    let lista = [];
    let textoTodos = '';

    if (tipo === 'medico') {
        labelSub.innerText = 'Qual médico?';
        textoTodos = 'Todos os Médicos';
        lista = medicos;
    } else if (tipo === 'recepcionista') {
        labelSub.innerText = 'Qual recepcionista?';
        textoTodos = 'Todos os Recepcionistas';
        lista = recepcionistas;
    } else if (tipo === 'gestor') {
        labelSub.innerText = 'Qual gestor?';
        textoTodos = 'Todos os Gestores';
        lista = gestores;
    }

    const optTodos = document.createElement('option');
    optTodos.value = '0';
    optTodos.textContent = textoTodos;
    selectSub.appendChild(optTodos);

    lista.forEach(item => {
        const opt = document.createElement('option');
        opt.value = item.id;
        opt.textContent = item.nome;
        selectSub.appendChild(opt);
    });

    // Se estiver editando, marca o valor salvo como selecionado
    if (alvoAtualId !== null) {
        selectSub.value = String(alvoAtualId);
    }
}

// Se já veio um tipo_alvo preenchido (modo edição), dispara a montagem do segundo select
document.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById('tipoAlvo').value) {
        atualizarSelecaoAlvo();
    }
});

// Aviso visual (não bloqueia o envio — a validação de verdade é no servidor)
// se a data/hora de término ficar antes da data/hora de início.
function checarPeriodo() {
    const dataInicio = document.getElementById('dataInicio').value;
    const horaInicio = document.getElementById('horaInicio').value || '00:00';
    const dataFim = document.getElementById('dataFim').value;
    const horaFim = document.getElementById('horaFim').value || '23:59';
    const aviso = document.getElementById('avisoPeriodo');

    if (!dataInicio || !dataFim) {
        aviso.style.display = 'none';
        return;
    }

    const inicio = new Date(`${dataInicio}T${horaInicio}`);
    const fim = new Date(`${dataFim}T${horaFim}`);

    aviso.style.display = (fim < inicio) ? 'block' : 'none';
}

['dataInicio', 'horaInicio', 'dataFim', 'horaFim'].forEach(function (idCampo) {
    document.getElementById(idCampo).addEventListener('change', checarPeriodo);
});
</script>

</body>
</html>