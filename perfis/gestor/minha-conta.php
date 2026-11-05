<?php
// gestor/minha-conta.php
session_start();
require_once '../../conexao.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_perfil'] !== 'gestor') {
    header("Location: ../../auth/login.php");
    exit();
}

$gestorId = $_SESSION['usuario_id'];

try {
    $stmt = $pdo->prepare("SELECT * FROM gestores WHERE uid = ? OR id = ?");
    $stmt->execute([$gestorId, $gestorId]);
    $gestor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$gestor) {
        die("Perfil do gestor não encontrado.");
    }
} catch (PDOException $e) {
    die("Erro ao buscar dados do gestor: " . $e->getMessage());
}

// Caminho relativo salvo no banco (ex: "gestor/GEST001.jpg")
$fotoAtual = $gestor['foto'] ?? null;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minha Conta - Convelum Saúde</title>

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
        <span id="subtitulo-painel">CONFIGURAÇÕES DE PERFIL</span>
        <h1 id="titulo-painel">Meus Dados</h1>
        <p id="texto-painel">Atualize suas informações pessoais e credenciais de acesso.</p>
    </div>
</div>

<div class="secao-config-agenda">

    <?php if (isset($_GET['sucesso'])): ?>
        <div class="alert alert-success alert-dismissible fade show w-100 mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> Dados atualizados com sucesso!
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['erro'])): ?>
        <div class="alert alert-danger alert-dismissible fade show w-100 mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <?php 
                if ($_GET['erro'] === 'senha_incorreta') echo 'A senha atual informada está incorreta.';
                else if ($_GET['erro'] === 'senha_curta') echo 'A nova senha deve ter no mínimo 6 caracteres.';
                else if ($_GET['erro'] === 'foto_envio') echo 'Erro ao enviar a foto. Tente novamente.';
                else if ($_GET['erro'] === 'foto_tamanho') echo 'A foto deve ter no máximo 5MB.';
                else if ($_GET['erro'] === 'foto_formato') echo 'Formato de foto inválido. Use JPG, PNG ou WEBP.';
                else if ($_GET['erro'] === 'foto_pasta') echo 'Não foi possível preparar a pasta de fotos.';
                else if ($_GET['erro'] === 'foto_salvar') echo 'Não foi possível salvar a foto.';
                else echo 'Erro ao atualizar os dados. Tente novamente.';
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <form method="post" action="actions/atualizar-perfil.php" class="cartao-config-agenda" enctype="multipart/form-data">
        <h2 class="titulo-cartao-config">Informações Pessoais</h2>

        <div class="campo-config">
            <label for="fotoUsuario">Foto de perfil</label>
            <?php if (!empty($fotoAtual)): ?>
                <div class="foto-atual-preview">
                    <img src="../../foto-usuario.php?arquivo=<?php echo urlencode($fotoAtual); ?>" alt="Foto atual">
                    <span>Foto atual — envie um arquivo abaixo só se quiser substituí-la.</span>
                </div>
            <?php else: ?>
                <p class="text-muted" style="margin-bottom:8px;">Você ainda não tem foto cadastrada.</p>
            <?php endif; ?>
            <input type="file" id="fotoUsuario" name="foto" accept="image/png, image/jpeg, image/webp">
            <small class="texto-ajuda-campo">Formatos aceitos: JPG, PNG ou WEBP. Deixe em branco pra manter a foto atual.</small>
        </div>

        <div class="linha-config-dupla">
            <div class="campo-config">
                <label for="nome">Nome Completo</label>
                <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($gestor['nome'] ?? ''); ?>" required>
            </div>
            <div class="campo-config">
                <label for="email">E-mail de Acesso</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($gestor['email'] ?? ''); ?>" required>
            </div>
        </div>

        <div class="linha-config-dupla">
            <div class="campo-config">
                <label for="cpf">CPF</label>
                <input type="text" id="cpf" name="cpf" value="<?php echo htmlspecialchars($gestor['cpf'] ?? ''); ?>" maxlength="14" placeholder="000.000.000-00" oninput="mascaraCPF(this)">
            </div>
            <div class="campo-config">
                <label for="celular">Celular</label>
                <input type="text" id="celular" name="celular" value="<?php echo htmlspecialchars($gestor['celular'] ?? ''); ?>" maxlength="15" placeholder="(00) 00000-0000" oninput="mascaraCelular(this)">
            </div>
        </div>

        <div class="linha-config-dupla">
            <div class="campo-config">
                <label for="telefone">Telefone Fixo</label>
                <input type="text" id="telefone" name="telefone" value="<?php echo htmlspecialchars($gestor['telefone'] ?? ''); ?>" maxlength="14" placeholder="(00) 0000-0000" oninput="mascaraTelefone(this)">
            </div>
            <div class="campo-config">
                <label for="cargo">Cargo</label>
                <input type="text" id="cargo" name="cargo" value="<?php echo htmlspecialchars($gestor['cargo'] ?? ''); ?>" placeholder="Ex: Gestor Geral">
            </div>
        </div>

        <h2 class="titulo-cartao-config mt-4">Alterar Senha (Opcional)</h2>

        <div class="linha-config-dupla">
            <div class="campo-config">
                <label for="senha_atual">Senha Atual (obrigatória para salvar)</label>
                <input type="password" id="senha_atual" name="senha_atual" placeholder="Digite sua senha atual" required>
            </div>
            <div class="campo-config">
                <label for="nova_senha">Nova Senha (deixe em branco para manter)</label>
                <input type="password" id="nova_senha" name="nova_senha" placeholder="Nova senha">
            </div>
        </div>

        <button type="submit" class="botao-entrar botao-config-salvar mt-3">Salvar alterações</button>
    </form>
</div>

<script src="../../js/bootstrap.bundle.min.js"></script>
<script>
function mascaraCPF(input) {
    let v = input.value.replace(/\D/g, "");
    v = v.replace(/(\d{3})(\d)/, "$1.$2");
    v = v.replace(/(\d{3})(\d)/, "$1.$2");
    v = v.replace(/(\d{3})(\d{1,2})$/, "$1-$2");
    input.value = v.substring(0, 14);
}

function mascaraCelular(input) {
    let v = input.value.replace(/\D/g, "");
    v = v.replace(/^(\d{2})(\d)/g, "($1) $2");
    v = v.replace(/(\d{5})(\d)/, "$1-$2");
    input.value = v.substring(0, 15);
}

function mascaraTelefone(input) {
    let v = input.value.replace(/\D/g, "");
    v = v.replace(/^(\d{2})(\d)/g, "($1) $2");
    v = v.replace(/(\d{4})(\d)/, "$1-$2");
    input.value = v.substring(0, 14);
}

// Aplica as máscaras ao carregar os dados existentes da página
window.addEventListener('DOMContentLoaded', () => {
    const cpfInput = document.getElementById('cpf');
    const celularInput = document.getElementById('celular');
    const telefoneInput = document.getElementById('telefone');

    if (cpfInput.value) mascaraCPF(cpfInput);
    if (celularInput.value) mascaraCelular(celularInput);
    if (telefoneInput.value) mascaraTelefone(telefoneInput);
});
</script>
</body>
</html>