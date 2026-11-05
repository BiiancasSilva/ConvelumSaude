<?php
// gestor/cadastrar-salas.php
session_start();
require_once '../../conexao.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_perfil'] !== 'gestor') {
    header("Location: ../../auth/login.php");
    exit();
}

$mensagem = $_GET['msg'] ?? '';
$erro = $_GET['erro'] ?? '';

// ================= LISTAR SALAS (com médico vinculado, se houver) =================
try {
    $salas = $pdo->query("
        SELECT s.id, s.nome, s.status, m.nome AS medico_nome
        FROM salas s
        LEFT JOIN medico m ON s.id_medico = m.id
        ORDER BY s.nome ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $salas = [];
}

// ================= LISTAR MÉDICOS PARA O SELECT =================
try {
    $medicos = $pdo->query("SELECT id, nome FROM medico ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $medicos = [];
}

// ================= SALA EM EDIÇÃO (?editar=ID) =================
$salaEditando = null;
if (isset($_GET['editar'])) {
    $idEditar = (int) $_GET['editar'];
    try {
        $stmtEditar = $pdo->prepare("SELECT id, nome, id_medico FROM salas WHERE id = ?");
        $stmtEditar->execute([$idEditar]);
        $salaEditando = $stmtEditar->fetch(PDO::FETCH_ASSOC);

        if (!$salaEditando) {
            header("Location: cadastrar-salas.php?erro=" . urlencode('Sala não encontrada.'));
            exit();
        }
    } catch (PDOException $e) {
        header("Location: cadastrar-salas.php?erro=" . urlencode('Erro ao carregar sala.'));
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastrar Salas - Convelum Saúde</title>

    <link href="../../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/responsivo.css">

    <style>
        /* Estilo isolado pro alerta, pra não herdar nada gigante do style.css */
        .aviso-flutuante {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 360px;
            width: calc(100% - 40px);
            padding: 14px 40px 14px 16px;
            border-radius: 8px;
            font-size: 14px;
            line-height: 1.4;
            box-shadow: 0 4px 14px rgba(0,0,0,0.15);
            display: flex;
            align-items: flex-start;
            gap: 8px;
            animation: aviso-entrar 0.25s ease-out;
        }
        .aviso-sucesso { background: #d1f7e0; color: #0a6d3c; border: 1px solid #a3ecc2; }
        .aviso-erro    { background: #fde2e2; color: #a3231f; border: 1px solid #f7b8b6; }
        .aviso-fechar {
            position: absolute;
            top: 8px;
            right: 10px;
            background: none;
            border: none;
            font-size: 18px;
            line-height: 1;
            cursor: pointer;
            color: inherit;
            opacity: 0.6;
        }
        .aviso-fechar:hover { opacity: 1; }
        @keyframes aviso-entrar {
            from { transform: translateX(20px); opacity: 0; }
            to   { transform: translateX(0); opacity: 1; }
        }

        /* A linha de cada sala tem 3 ações (Editar / Bloquear / Excluir), então
           não dá pra usar o .item-painel-botao "puro" (min-width: 140px) sem
           quebrar o layout — aqui a gente agrupa e permite quebra de linha. */
        .linha-item-painel {
            flex-wrap: wrap;
            row-gap: 10px;
        }
        .acoes-sala {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-left: auto;
        }
        .acoes-sala .item-painel-botao {
            min-width: 0;
            padding: 7px 14px;
        }
        .botao-cancelar-sala {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            background-color: #6b7280;
            color: #ffffff;
            padding: 12px 22px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            white-space: nowrap;
            transition: background-color 0.2s ease;
        }
        .botao-cancelar-sala:hover {
            background-color: #4b5563;
            color: #ffffff;
        }
    </style>
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

<!-- ========================= BOAS-VINDAS ========================= -->
<div id="painel-boasvindas">
    <div>
        <span id="subtitulo-painel">INFRAESTRUTURA</span>
        <h1 id="titulo-painel">Cadastrar salas</h1>
        <p id="texto-painel">Crie e gerencie as salas de atendimento da clínica. Elas aparecem automaticamente na tela de ocupação da recepção.</p>
    </div>
</div>

<?php if ($mensagem): ?>
    <div class="aviso-flutuante aviso-sucesso" id="aviso">
        <i class="bi bi-check-circle-fill"></i>
        <span><?php echo htmlspecialchars($mensagem); ?></span>
        <button type="button" class="aviso-fechar" onclick="document.getElementById('aviso').remove()">&times;</button>
    </div>
<?php endif; ?>
<?php if ($erro): ?>
    <div class="aviso-flutuante aviso-erro" id="aviso-erro">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <span><?php echo htmlspecialchars($erro); ?></span>
        <button type="button" class="aviso-fechar" onclick="document.getElementById('aviso-erro').remove()">&times;</button>
    </div>
<?php endif; ?>

<script>
    // Some sozinho depois de 5 segundos
    setTimeout(function () {
        ['aviso', 'aviso-erro'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.remove();
        });
    }, 5000);
</script>

<!-- ========================= FORMULÁRIO DE CADASTRO ========================= -->
<div class="secao-config-agenda">
    <div class="card p-4 mb-4">
        <h2 class="titulo-secao-painel"><?php echo $salaEditando ? 'Editar sala' : 'Nova sala'; ?></h2>
        <form method="POST" action="actions/salvar-sala.php" class="row g-3 align-items-end">
            <input type="hidden" name="acao" value="<?php echo $salaEditando ? 'editar' : 'criar'; ?>">
            <?php if ($salaEditando): ?>
                <input type="hidden" name="id" value="<?php echo (int) $salaEditando['id']; ?>">
            <?php endif; ?>

            <div class="col-md-6">
                <label for="nome" class="form-label">Nome da sala</label>
                <input type="text" class="form-control" id="nome" name="nome" placeholder="Ex: Sala 1, Consultório A..."
                       value="<?php echo htmlspecialchars($salaEditando['nome'] ?? ''); ?>" required maxlength="100">
            </div>

            <div class="col-md-4">
                <label for="id_medico" class="form-label">Médico responsável (opcional)</label>
                <?php $idMedicoAtual = $salaEditando['id_medico'] ?? null; ?>
                <select class="form-select" id="id_medico" name="id_medico">
                    <option value="">Sala compartilhada (sem médico fixo)</option>
                    <?php foreach ($medicos as $medico): ?>
                        <option value="<?php echo (int) $medico['id']; ?>"
                            <?php echo ((int) $idMedicoAtual === (int) $medico['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($medico['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Se escolher um médico, a sala fica marcada como fixa dele. Deixe em branco pra qualquer médico usar.</div>
            </div>

            <div class="col-md-2">
                <button type="submit" class="botao-acao-principal w-100 justify-content-center">
                    <i class="bi bi-<?php echo $salaEditando ? 'check2' : 'plus-circle'; ?>"></i>
                    <?php echo $salaEditando ? 'Salvar' : 'Cadastrar'; ?>
                </button>
            </div>

            <?php if ($salaEditando): ?>
                <div class="col-12 d-flex justify-content-end">
                    <a href="cadastrar-salas.php" class="botao-cancelar-sala">Cancelar edição</a>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- ========================= LISTA DE SALAS ========================= -->
    <h2 class="titulo-secao-painel">Salas cadastradas</h2>

    <?php if (empty($salas)): ?>
        <div class="admin-vazio" style="padding: 50px; text-align: center; color: #666;">
            <i class="bi bi-door-closed" style="font-size: 2.5rem; display: block; margin-bottom: 10px;"></i>
            <p>Nenhuma sala cadastrada ainda.</p>
        </div>
    <?php else: ?>
        <div class="lista-itens-painel">
            <?php foreach ($salas as $sala): ?>
                <div class="linha-item-painel">
                    <span class="item-painel-principal">
                        <i class="bi bi-door-open"></i> <?php echo htmlspecialchars($sala['nome']); ?>
                        <?php if (!empty($sala['medico_nome'])): ?>
                            <small class="text-muted d-block">Fixa de: <?php echo htmlspecialchars($sala['medico_nome']); ?></small>
                        <?php endif; ?>
                    </span>

                    <span class="item-painel-tag">
                        <?php echo $sala['status'] === 'bloqueada'
                            ? '<i class="bi bi-lock-fill"></i> Bloqueada'
                            : '<i class="bi bi-unlock"></i> Livre'; ?>
                    </span>

                    <div class="acoes-sala">
                        <a href="cadastrar-salas.php?editar=<?php echo (int) $sala['id']; ?>"
                           class="item-painel-botao text-decoration-none" title="Editar sala">
                            <i class="bi bi-pencil"></i> Editar
                        </a>

                        <form method="POST" action="actions/salvar-sala.php" style="display:inline;">
                            <input type="hidden" name="acao" value="alterar_status">
                            <input type="hidden" name="id" value="<?php echo (int) $sala['id']; ?>">
                            <input type="hidden" name="novo_status" value="<?php echo $sala['status'] === 'bloqueada' ? 'livre' : 'bloqueada'; ?>">
                            <button type="submit" class="item-painel-botao">
                                <?php echo $sala['status'] === 'bloqueada' ? 'Desbloquear' : 'Bloquear'; ?>
                            </button>
                        </form>

                        <form method="POST" action="actions/salvar-sala.php" style="display:inline;" onsubmit="return confirm('Tem certeza que deseja excluir esta sala?');">
                            <input type="hidden" name="acao" value="excluir">
                            <input type="hidden" name="id" value="<?php echo (int) $sala['id']; ?>">
                            <button type="submit" class="item-painel-botao text-danger">
                                <i class="bi bi-trash"></i> Excluir
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>