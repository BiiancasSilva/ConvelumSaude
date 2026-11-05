<?php
// recepcionista/meu-perfil.php
session_start();
require_once '../../conexao.php';

if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'recepcionista') {
    header("Location: ../../auth/login.php");
    exit();
}

$dados = null;
$erroCarregamento = false;

try {
    $stmt = $pdo->prepare("SELECT nome, cpf, uid, data_inicio, foto, email, telefone, celular FROM recepcionistas WHERE id = ? LIMIT 1");
    $stmt->execute([$_SESSION['usuario_id']]);
    $dados = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$dados) {
        $erroCarregamento = true;
    }
} catch (PDOException $e) {
    error_log('Falha ao carregar perfil da recepcionista: ' . $e->getMessage());
    $erroCarregamento = true;
}

$dataInicioFormatada = !empty($dados) && !empty($dados['data_inicio'])
    ? date('d/m/Y', strtotime($dados['data_inicio']))
    : 'Não informada';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Perfil - Convelum Saúde</title>

    <link href="../../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/responsivo.css">
    <style>
        .foto-atual-preview { display:flex; align-items:center; gap:12px; margin-bottom:10px; }
        .foto-atual-preview img { width:64px; height:64px; border-radius:50%; object-fit:cover; }
        .foto-atual-preview span { opacity:.75; font-size:.9rem; }
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
        <a href="painel.php" class="topo-painel-sair">
            <i class="bi bi-arrow-left"></i> Voltar ao painel
        </a>
    </div>
</div>

<div id="painel-boasvindas">
    <div>
        <span id="subtitulo-painel">CONFIGURAÇÕES DA CONTA</span>
        <h1 id="titulo-painel">Meu perfil</h1>
        <p id="texto-painel">Atualize seus dados de contato e sua senha de acesso.</p>
    </div>
</div>

<?php if (isset($_GET['sucesso']) && $_GET['sucesso'] == '1'): ?>
    <div class="container mt-3" style="max-width: 900px;">
        <div class="alert alert-success" role="alert" id="alertaSucesso" style="display: flex; align-items: center; justify-content: space-between;">
            <span><i class="bi bi-check-circle-fill me-2"></i> Perfil atualizado com sucesso!</span>
            <button type="button" onclick="document.getElementById('alertaSucesso').remove()" style="background:none;border:none;color:inherit;font-size:1.2rem;cursor:pointer;line-height:1;">&times;</button>
        </div>
    </div>
    <script>
        setTimeout(function () {
            const alerta = document.getElementById('alertaSucesso');
            if (alerta) alerta.remove();
        }, 5000);
    </script>
<?php endif; ?>

<?php if (isset($_GET['erro'])): ?>
    <div class="container mt-3" style="max-width: 900px;">
        <div class="alert alert-danger" role="alert" id="alertaErro" style="display: flex; align-items: center; justify-content: space-between;">
            <span>
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?php
                    if ($_GET['erro'] === 'senha') echo 'A senha atual informada está incorreta.';
                    elseif ($_GET['erro'] === 'campos') echo 'Preencha todos os campos obrigatórios.';
                    elseif ($_GET['erro'] === 'confirmacao') echo 'A nova senha e a confirmação não coincidem.';
                    elseif ($_GET['erro'] === 'senha_curta') echo 'A nova senha deve ter no mínimo 6 caracteres.';
                    elseif ($_GET['erro'] === 'email') echo 'Informe um e-mail válido.';
                    elseif ($_GET['erro'] === 'email_duplicado') echo 'Este e-mail já está em uso por outra conta de recepcionista.';
                    elseif ($_GET['erro'] === 'limite') echo 'Um dos campos excede o limite permitido.';
                    elseif ($_GET['erro'] === 'foto') echo 'Não foi possível salvar a foto. Envie JPG, PNG ou WEBP com até 5 MB.';
                    else echo 'Não foi possível atualizar seus dados.';
                ?>
            </span>
            <button type="button" onclick="document.getElementById('alertaErro').remove()" style="background:none;border:none;color:inherit;font-size:1.2rem;cursor:pointer;line-height:1;">&times;</button>
        </div>
    </div>
<?php endif; ?>

<?php if ($erroCarregamento): ?>
    <div class="container mt-3" style="max-width: 900px;">
        <div class="alert alert-danger" role="alert">Não foi possível carregar seu perfil. Atualize a página ou tente novamente mais tarde.</div>
    </div>
<?php endif; ?>

<?php if ($dados): ?>
<div class="secao-config-agenda">

    <form method="post" action="actions/salvar-perfil.php" class="cartao-config-agenda" enctype="multipart/form-data">
        <h2 class="titulo-cartao-config">Dados pessoais</h2>

        <div class="campo-config">
            <label for="fotoRecep">Foto de perfil</label>
            <?php if (!empty($dados['foto'])): ?>
                <div class="foto-atual-preview">
                    <img src="../../foto-usuario.php?arquivo=<?php echo urlencode($dados['foto']); ?>" alt="Foto atual do perfil">
                    <span>Foto atual — envie outra imagem abaixo para substituí-la.</span>
                </div>
            <?php else: ?>
                <p class="text-muted" style="margin-bottom:8px;">Você ainda não tem foto cadastrada.</p>
            <?php endif; ?>
            <input type="file" id="fotoRecep" name="foto" accept="image/png, image/jpeg, image/webp">
            <small class="texto-ajuda-campo">Formatos aceitos: JPG, PNG ou WEBP. Tamanho máximo: 5 MB.</small>
        </div>

        <div class="campo-config">
            <label for="nomeRecep">Nome completo</label>
            <input type="text" id="nomeRecep" name="nome" maxlength="100" value="<?php echo htmlspecialchars($dados['nome'], ENT_QUOTES, 'UTF-8'); ?>" required>
        </div>

        <div class="campo-config">
            <label for="cpfRecep">CPF</label>
            <input type="text" id="cpfRecep" value="<?php echo htmlspecialchars($dados['cpf'], ENT_QUOTES, 'UTF-8'); ?>" disabled>
        </div>

        <div class="campo-config">
            <label for="emailRecep">E-mail</label>
            <input type="email" id="emailRecep" name="email" maxlength="100" value="<?php echo htmlspecialchars($dados['email'], ENT_QUOTES, 'UTF-8'); ?>" required>
        </div>

        <div class="campo-config">
            <label for="telefoneRecep">Telefone fixo</label>
            <input type="tel" id="telefoneRecep" name="telefone" maxlength="15" value="<?php echo htmlspecialchars($dados['telefone'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="campo-config">
            <label for="celularRecep">Celular</label>
            <input type="tel" id="celularRecep" name="celular" maxlength="15" value="<?php echo htmlspecialchars($dados['celular'], ENT_QUOTES, 'UTF-8'); ?>" required>
        </div>

        <button type="submit" class="botao-entrar botao-config-salvar">Salvar dados</button>
    </form>

    <div class="cartao-config-agenda">
        <h2 class="titulo-cartao-config">Dados profissionais</h2>

        <div class="linha-config-dupla">
            <div class="campo-config">
                <label for="uidRecep">Código da recepcionista</label>
                <input type="text" id="uidRecep" value="<?php echo htmlspecialchars($dados['uid'], ENT_QUOTES, 'UTF-8'); ?>" disabled>
            </div>
            <div class="campo-config">
                <label for="dataInicioRecep">Data de início</label>
                <input type="text" id="dataInicioRecep" value="<?php echo htmlspecialchars($dataInicioFormatada, ENT_QUOTES, 'UTF-8'); ?>" disabled>
            </div>
        </div>

        <p class="aviso-recuperacao">
            CPF, código e data de início são dados profissionais e só podem ser alterados pela gestão da clínica.
        </p>
    </div>

    <form method="post" action="actions/salvar-perfil.php" class="cartao-config-agenda">
        <h2 class="titulo-cartao-config">Alterar senha</h2>

        <input type="hidden" name="apenas_senha" value="1">

        <div class="campo-config">
            <label for="senhaAtual">Senha atual</label>
            <input type="password" id="senhaAtual" name="senha_atual" placeholder="Digite sua senha atual" required>
        </div>

        <div class="campo-config">
            <label for="senhaNova">Nova senha</label>
            <input type="password" id="senhaNova" name="senha_nova" placeholder="Mínimo 6 caracteres" minlength="6" maxlength="72" required>
        </div>

        <div class="campo-config">
            <label for="senhaConfirma">Confirmar nova senha</label>
            <input type="password" id="senhaConfirma" name="senha_confirma" placeholder="Repita a nova senha" minlength="6" maxlength="72" required>
        </div>

        <button type="submit" class="botao-entrar botao-config-salvar">Alterar senha</button>
    </form>

</div>
<?php elseif (!$erroCarregamento): ?>
    <p style="padding: 20px; color: #666;">Não foi possível carregar seus dados. Tente novamente mais tarde.</p>
<?php endif; ?>

</body>
</html>