<?php
// gestor/gerenciar-usuarios.php
session_start();
require_once '../../conexao.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_perfil'] !== 'gestor') {
    header("Location: ../../auth/login.php");
    exit();
}

// Nota: 'medico', 'recepcionistas' e 'paciente' precisam da coluna `status`
// (ver migracao_status_usuarios.sql). 'gestores' já tem essa coluna.
try {
    $stmt = $pdo->query("
        SELECT id, nome, cpf, email, 'medico' AS perfil, uid, status FROM medico
        UNION ALL
        SELECT id, nome, cpf, email, 'recepcionista' AS perfil, uid, status FROM recepcionistas
        UNION ALL
        SELECT id, nome, cpf, email, 'gestor' AS perfil, uid, status FROM gestores
        UNION ALL
        SELECT id, nome, num_doc AS cpf, email, 'paciente' AS perfil, uid, status FROM paciente
        ORDER BY nome ASC
    ");
    $todosUsuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $todosUsuarios = [];
}

$rotulosPerfil = [
    'medico'        => 'Médico',
    'gestor'        => 'Gestor',
    'recepcionista' => 'Recepcionista',
    'paciente'      => 'Paciente',
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Usuários - Convelum Saúde</title>

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
        <h1 id="titulo-painel">Gerenciar usuários</h1>
        <p id="texto-painel">Edite, inative, reative ou exclua médicos, gestores, recepcionistas e pacientes cadastrados.</p>
    </div>
    <a href="cadastrar-usuario.php" class="botao-acao-principal">
        <i class="bi bi-person-plus"></i> Cadastrar novo usuário
    </a>
</div>

<div class="secao-config-agenda">

<?php if (isset($_GET['status']) && $_GET['status'] === 'sucesso'):
    $mensagemSucesso = match ($_GET['acao'] ?? '') {
        'inativacao' => 'Usuário inativado com sucesso! O histórico dele foi mantido.',
        'reativacao' => 'Usuário reativado com sucesso!',
        'edicao'     => 'Usuário atualizado com sucesso!',
        'exclusao'   => 'Usuário excluído definitivamente.',
        default      => 'Operação realizada com sucesso!',
    };
?>
    <div class="alert alert-success alert-dismissible w-100 mb-4" role="alert" id="alertaGerenciar">
        <i class="bi bi-check-circle-fill me-2"></i> <?php echo $mensagemSucesso; ?>
        <button type="button" class="btn-close" aria-label="Fechar" onclick="document.getElementById('alertaGerenciar').remove();"></button>
    </div>
<?php elseif (isset($_GET['erro'])): ?>
    <div class="alert alert-danger alert-dismissible w-100 mb-4" role="alert" id="alertaGerenciarErro">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($_GET['erro']); ?>
        <button type="button" class="btn-close" aria-label="Fechar" onclick="document.getElementById('alertaGerenciarErro').remove();"></button>
    </div>
<?php endif; ?>

    <div class="cartao-config-agenda" style="width:100%;">
        <h2 class="titulo-cartao-config">Todos os usuários</h2>

        <!-- ========================= BUSCA / FILTRO ========================= -->
        <div class="linha-config-dupla" style="margin-bottom:16px;">
            <div class="campo-config" style="flex:2;">
                <label for="buscaUsuario">Buscar</label>
                <input type="text"
                       id="buscaUsuario"
                       placeholder="Nome, e-mail ou UID..."
                       autocomplete="off"
                       oninput="filtrarUsuarios()">
            </div>
            <div class="campo-config" style="flex:1;">
                <label for="filtroPerfil">Perfil</label>
                <select id="filtroPerfil" onchange="filtrarUsuarios()">
                    <option value="">Todos</option>
                    <option value="medico">Médico</option>
                    <option value="recepcionista">Recepcionista</option>
                    <option value="gestor">Gestor</option>
                    <option value="paciente">Paciente</option>
                </select>
            </div>
        </div>

        <div class="lista-config-registros" id="listaUsuarios">
            <?php if (!empty($todosUsuarios)): ?>
                <?php foreach ($todosUsuarios as $usuario):
                    $inativo = ($usuario['status'] ?? 'Ativo') === 'Inativo';
                    $nomeEscapado = htmlspecialchars(addslashes($usuario['nome']));
                    $textoBusca = mb_strtolower(
                        $usuario['nome'] . ' ' . $usuario['email'] . ' ' . $usuario['uid'],
                        'UTF-8'
                    );
                ?>
                    <div class="registro-config"
                         data-perfil="<?php echo htmlspecialchars($usuario['perfil']); ?>"
                         data-busca="<?php echo htmlspecialchars($textoBusca); ?>"
                         style="<?php echo $inativo ? 'opacity:0.55;' : ''; ?>">
                        <div>
                            <span class="registro-config-tipo"><?php echo htmlspecialchars($usuario['nome']); ?></span>
                            <span class="registro-config-periodo">
                                <?php echo $rotulosPerfil[$usuario['perfil']] ?? ucfirst($usuario['perfil']); ?>
                                <?php if ($inativo): ?>
                                    · <strong style="color:#dc2626;">Inativo</strong>
                                <?php endif; ?>
                            </span>
                            <p class="registro-medicamento-detalhes">
                                UID: <?php echo htmlspecialchars($usuario['uid']); ?>
                                · <?php echo htmlspecialchars($usuario['email']); ?>
                            </p>
                        </div>
                        <div style="display:flex; align-items:center; gap:12px;">
                            <a href="editar-usuario.php?id=<?php echo $usuario['id']; ?>&perfil=<?php echo urlencode($usuario['perfil']); ?>"
                               class="registro-config-remover"
                               title="Editar"
                               style="color:#2563eb;">
                                <i class="bi bi-pencil"></i>
                            </a>

                            <?php if ($inativo): ?>
                                <a href="actions/excluir-usuario.php?id=<?php echo $usuario['id']; ?>&perfil=<?php echo urlencode($usuario['perfil']); ?>"
                                   class="registro-config-remover"
                                   title="Reativar"
                                   style="color:#16a34a;"
                                   onclick="return confirm('Reativar <?php echo $nomeEscapado; ?>? Ele volta a ter acesso ao sistema.');">
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                </a>
                            <?php else: ?>
                                <a href="actions/excluir-usuario.php?id=<?php echo $usuario['id']; ?>&perfil=<?php echo urlencode($usuario['perfil']); ?>"
                                   class="registro-config-remover"
                                   title="Inativar"
                                   onclick="return confirm('Inativar <?php echo $nomeEscapado; ?>? Ele perde o acesso, mas o histórico (consultas, prontuário etc.) continua salvo.');">
                                    <i class="bi bi-slash-circle"></i>
                                </a>
                            <?php endif; ?>

                            <a href="actions/excluir-definitivo.php?id=<?php echo $usuario['id']; ?>&perfil=<?php echo urlencode($usuario['perfil']); ?>"
                               class="registro-config-remover"
                               title="Excluir definitivamente"
                               style="color:#7f1d1d;"
                               onclick="return confirm('ATENÇÃO: isso vai excluir <?php echo $nomeEscapado; ?> PERMANENTEMENTE, junto com todo o histórico de consultas, prontuário, receitas, exames e atestados vinculados. Essa ação NÃO PODE ser desfeita.\n\nSe você só quer remover o acesso dele, cancele e use \'Inativar\' em vez disso.\n\nDeseja continuar com a exclusão definitiva?');">
                                <i class="bi bi-trash-fill"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted p-3">Nenhum usuário cadastrado até o momento.</p>
            <?php endif; ?>
        </div>

        <p class="text-muted p-3" id="semResultados" style="display:none;">
            Nenhum usuário encontrado para essa busca.
        </p>
    </div>

</div>

<script>
function filtrarUsuarios() {
    const termo  = document.getElementById('buscaUsuario').value.trim().toLowerCase();
    const perfil = document.getElementById('filtroPerfil').value;
    const registros = document.querySelectorAll('#listaUsuarios .registro-config');
    let visiveis = 0;

    registros.forEach(function (registro) {
        const buscaOk  = termo === '' || (registro.dataset.busca || '').includes(termo);
        const perfilOk = perfil === '' || registro.dataset.perfil === perfil;
        const mostrar  = buscaOk && perfilOk;

        registro.style.display = mostrar ? '' : 'none';
        if (mostrar) visiveis++;
    });

    document.getElementById('semResultados').style.display = (visiveis === 0) ? 'block' : 'none';
}
</script>

</body>
</html>