<?php
// gestor/configurar-feriados.php
session_start();
require_once '../../conexao.php';

// Proteção de acesso: Apenas gestores
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_perfil'] !== 'gestor') {
    header("Location: ../../auth/login.php");
    exit();
}

// Lógica de exclusão rápida de feriado
if (isset($_GET['excluir'])) {
    $idExcluir = (int)$_GET['excluir'];
    try {
        $stmtDel = $pdo->prepare("DELETE FROM feriados WHERE id = ?");
        $stmtDel->execute([$idExcluir]);

        if ($stmtDel->rowCount() === 0) {
            header("Location: configurar-feriados.php?erro=" . urlencode('Feriado não encontrado.'));
            exit();
        }

        header("Location: configurar-feriados.php?sucesso=excluido");
        exit();
    } catch (PDOException $e) {
        header("Location: configurar-feriados.php?erro=" . urlencode('Erro ao excluir feriado.'));
        exit();
    }
}

// Se veio ?editar=ID, carrega os dados desse feriado pra pré-preencher o formulário
$feriadoEditando = null;
if (isset($_GET['editar'])) {
    $idEditar = (int)$_GET['editar'];
    try {
        $stmtEditar = $pdo->prepare("SELECT * FROM feriados WHERE id = ?");
        $stmtEditar->execute([$idEditar]);
        $feriadoEditando = $stmtEditar->fetch(PDO::FETCH_ASSOC);

        if (!$feriadoEditando) {
            header("Location: configurar-feriados.php?erro=" . urlencode('Feriado não encontrado.'));
            exit();
        }
    } catch (PDOException $e) {
        header("Location: configurar-feriados.php?erro=" . urlencode('Erro ao carregar feriado.'));
        exit();
    }
}

// Busca os feriados cadastrados
try {
    $stmt = $pdo->query("SELECT * FROM feriados ORDER BY data ASC");
    $feriadosCadastrados = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $feriadosCadastrados = [];
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurar Feriados - Convelum Saúde</title>

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
        <h1 id="titulo-painel">Configurar feriados</h1>
        <p id="texto-painel">Cadastre feriados e pontos facultativos em que a clínica estará fechada.</p>
    </div>
</div>

<div class="secao-config-agenda">

    <?php if (isset($_GET['sucesso'])): ?>
        <div class="alert alert-success alert-dismissible fade show w-100" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <?php 
                if ($_GET['sucesso'] === 'excluido') {
                    echo 'Feriado removido com sucesso!';
                } elseif ($_GET['sucesso'] === 'editado') {
                    echo 'Feriado atualizado com sucesso!';
                } else {
                    echo 'Feriado cadastrado com sucesso!';
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
    <?php endif; ?>

    <form method="post"
          action="<?php echo $feriadoEditando ? 'actions/atualizar-feriado.php' : 'actions/salvar-feriado.php'; ?>"
          class="cartao-config-agenda">
        <h2 class="titulo-cartao-config">
            <?php echo $feriadoEditando ? 'Editar feriado' : 'Novo feriado'; ?>
        </h2>

        <?php if ($feriadoEditando): ?>
            <input type="hidden" name="id" value="<?php echo (int)$feriadoEditando['id']; ?>">
        <?php endif; ?>

        <div class="linha-config-dupla">
            <div class="campo-config">
                <label for="nomeFeriado">Nome do feriado / evento</label>
                <input type="text" id="nomeFeriado" name="nome" placeholder="Ex: Proclamação da República"
                       value="<?php echo htmlspecialchars($feriadoEditando['nome'] ?? ''); ?>" required>
            </div>
            <div class="campo-config">
                <label for="dataFeriado">Data</label>
                <input type="date" id="dataFeriado" name="data"
                       value="<?php echo htmlspecialchars($feriadoEditando['data'] ?? ''); ?>" required>
            </div>
        </div>

        <div class="linha-config-dupla">
            <div class="campo-config">
                <label for="tipoFeriado">Tipo</label>
                <?php $tipoAtual = $feriadoEditando['tipo'] ?? 'Nacional'; ?>
                <select id="tipoFeriado" name="tipo">
                    <option value="Nacional" <?php echo $tipoAtual === 'Nacional' ? 'selected' : ''; ?>>Nacional</option>
                    <option value="Estadual" <?php echo $tipoAtual === 'Estadual' ? 'selected' : ''; ?>>Estadual</option>
                    <option value="Municipal" <?php echo $tipoAtual === 'Municipal' ? 'selected' : ''; ?>>Municipal</option>
                    <option value="Corporativo" <?php echo $tipoAtual === 'Corporativo' ? 'selected' : ''; ?>>Facultativo / Interno</option>
                </select>
            </div>
            <div class="campo-config">
                <label for="recorreAnual">Repete todo ano?</label>
                <?php $recorrenteAtual = $feriadoEditando['recorrente'] ?? 1; ?>
                <select id="recorreAnual" name="recorrente">
                    <option value="1" <?php echo (int)$recorrenteAtual === 1 ? 'selected' : ''; ?>>Sim (anual)</option>
                    <option value="0" <?php echo (int)$recorrenteAtual === 0 ? 'selected' : ''; ?>>Não (apenas nesta data)</option>
                </select>
            </div>
        </div>

        <div style="display:flex; gap:12px;">
            <button type="submit" class="botao-entrar botao-config-salvar">
                <?php echo $feriadoEditando ? 'Salvar alterações' : 'Cadastrar feriado'; ?>
            </button>
            <?php if ($feriadoEditando): ?>
                <a href="configurar-feriados.php" class="botao-entrar" style="background:#6b7280; color:#fff; text-decoration:none; display:inline-flex; align-items:center; justify-content:center;">
                    Cancelar
                </a>
            <?php endif; ?>
        </div>
    </form>

    <div class="cartao-config-agenda">
        <h2 class="titulo-cartao-config">Feriados cadastrados</h2>

        <div class="lista-config-registros">
            <?php if (!empty($feriadosCadastrados)): ?>
                <?php foreach ($feriadosCadastrados as $feriado): 
                    $dataFmt = date('d/m/Y', strtotime($feriado['data']));
                ?>
                    <div class="registro-config">
                        <div>
                            <span class="registro-config-tipo"><?php echo htmlspecialchars($feriado['nome']); ?></span>
                            <p class="registro-medicamento-detalhes">
                                Data: <?php echo $dataFmt; ?> · <?php echo htmlspecialchars($feriado['tipo']); ?>
                                <?php if (!empty($feriado['recorrente'])): ?>
                                    <span class="badge bg-light text-dark border ms-1">Anual</span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div style="display:flex; align-items:center; gap:12px;">
                            <a href="configurar-feriados.php?editar=<?php echo $feriado['id']; ?>"
                               class="registro-config-remover text-decoration-none"
                               style="color:#2563eb;"
                               title="Editar feriado">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <a href="configurar-feriados.php?excluir=<?php echo $feriado['id']; ?>" 
                               class="registro-config-remover text-decoration-none" 
                               onclick="return confirm('Deseja realmente remover este feriado?');" 
                               title="Excluir feriado">
                                <i class="bi bi-trash"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted p-3">Nenhum feriado cadastrado até o momento.</p>
            <?php endif; ?>
        </div>
    </div>

</div>

<script src="../../js/bootstrap.bundle.min.js"></script>
</body>
</html>