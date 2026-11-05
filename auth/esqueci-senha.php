<?php
session_start();
require_once '../conexao.php'; // Ajuste o caminho do conexao.php se necessário

$mensagem = "";
$tipoMensagem = "";
// Salva o perfil selecionado para manter na tela após o recarregamento
$perfilSelecionado = $_POST['perfil_select'] ?? 'paciente';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipoFluxo = $_POST['tipo_fluxo'] ?? '';

    // FLUXO 1: PACIENTE (Enviar link por e-mail)
    if ($tipoFluxo === 'paciente') {
        $email = trim($_POST['email'] ?? '');

        if (!empty($email)) {
            try {
                $stmt = $pdo->prepare("SELECT id, nome FROM paciente WHERE email = :email LIMIT 1");
                $stmt->execute([':email' => $email]);
                $paciente = $stmt->fetch();

                if ($paciente) {
                    $mensagem = "Link de recuperação enviado com sucesso para " . htmlspecialchars($email) . "!";
                    $tipoMensagem = "sucesso";
                } else {
                    $mensagem = "Nenhum paciente cadastrado com esse e-mail.";
                    $tipoMensagem = "erro";
                }
            } catch (PDOException $e) {
                $mensagem = "Erro ao consultar banco de dados.";
                $tipoMensagem = "erro";
            }
        }
    }

    // FLUXO 2: EQUIPE (Médico, Gestor, Recepcionista)
    if ($tipoFluxo === 'equipe') {
        $perfil = $_POST['perfil'] ?? '';
        $uid    = trim($_POST['uid'] ?? '');
        $motivo = trim($_POST['motivo'] ?? '');

        if (!empty($perfil) && !empty($uid) && !empty($motivo)) {
            try {
                $sql = "INSERT INTO solicitacoes_senha (perfil, identificador, motivo, status, data_solicitacao) 
                        VALUES (:perfil, :uid, :motivo, 'Pendente', NOW())";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':perfil' => $perfil,
                    ':uid'    => $uid,
                    ':motivo' => $motivo
                ]);

                $mensagem = "Solicitação enviada com sucesso! Aguarde a aprovação do administrador.";
                $tipoMensagem = "sucesso";
            } catch (PDOException $e) {
                $mensagem = "Erro ao registrar solicitação. Verifique se a tabela 'solicitacoes_senha' foi criada.";
                $tipoMensagem = "erro";
            }
        } else {
            $mensagem = "Preencha todos os campos obrigatórios.";
            $tipoMensagem = "erro";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar senha - Convelum Saúde</title>

    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/responsivo.css">
</head>
<body class="pagina-login">

<div class="login-card">

    <div class="login-card-topo">
        <a href="../index.php">
            <img src="../imgs/logofrase.png" alt="Convelum Saúde">
        </a>
    </div>

    <h1>Recuperar senha</h1>
    <p class="login-subtitulo">Selecione seu perfil pra continuar</p>

    <!-- Exibição de mensagens -->
    <?php if (!empty($mensagem)): ?>
        <p style="color: <?php echo ($tipoMensagem === 'sucesso') ? 'green' : 'red'; ?>; text-align: center; font-weight: bold; margin-bottom: 15px;">
            <?php echo $mensagem; ?>
        </p>
    <?php endif; ?>

    <!-- SELETOR DE PERFIL (Com atributo 'selected' mantido pelo PHP) -->
    <div class="campo-login">
        <label for="perfilSelecionado">Perfil de acesso</label>
        <select id="perfilSelecionado" class="select-perfil">
            <option value="paciente" <?php echo ($perfilSelecionado === 'paciente') ? 'selected' : ''; ?>>Paciente</option>
            <option value="medico" <?php echo ($perfilSelecionado === 'medico') ? 'selected' : ''; ?>>Médico</option>
            <option value="gestor" <?php echo ($perfilSelecionado === 'gestor') ? 'selected' : ''; ?>>Gestor</option>
            <option value="recepcionista" <?php echo ($perfilSelecionado === 'recepcionista') ? 'selected' : ''; ?>>Recepcionista</option>
        </select>
    </div>

    <!-- FLUXO 1: PACIENTE -->
    <form method="post" action="" id="formPaciente" class="form-login" style="<?php echo ($perfilSelecionado !== 'paciente') ? 'display:none;' : ''; ?>">
        <input type="hidden" name="tipo_fluxo" value="paciente">
        <input type="hidden" name="perfil_select" value="paciente">

        <div class="campo-login">
            <label for="emailPaciente">E-mail cadastrado</label>
            <input type="email" id="emailPaciente" name="email" placeholder="Digite seu e-mail" required>
        </div>

        <p class="aviso-recuperacao">
            Vamos enviar um link de redefinição de senha pro seu e-mail.
        </p>

        <button type="submit" class="botao-entrar">Enviar link de recuperação</button>
    </form>

    <!-- FLUXO 2: EQUIPE -->
    <form method="post" action="" id="formEquipe" class="form-login" style="<?php echo ($perfilSelecionado === 'paciente') ? 'display:none;' : ''; ?>">
        <input type="hidden" name="tipo_fluxo" value="equipe">
        <input type="hidden" name="perfil_select" id="perfilSelectEquipe" value="<?php echo htmlspecialchars($perfilSelecionado); ?>">
        <input type="hidden" name="perfil" id="perfilEquipe" value="<?php echo htmlspecialchars($perfilSelecionado); ?>">

        <div class="campo-login">
            <label for="uidEquipe">UID</label>
            <input type="text" id="uidEquipe" name="uid" placeholder="Digite seu UID" required>
        </div>

        <div class="campo-login">
            <label for="motivoEquipe">Motivo da solicitação</label>
            <textarea id="motivoEquipe" name="motivo" rows="3" placeholder="Descreva rapidamente o motivo" required></textarea>
        </div>

        <p class="aviso-recuperacao aviso-equipe">
            <i class="bi bi-shield-lock-fill"></i>
            Por segurança, perfis de equipe não redefinem a senha sozinhos.
            Sua solicitação será enviada pra um administrador aprovar antes
            de gerar uma nova senha.
        </p>

        <button type="submit" class="botao-entrar">Enviar solicitação</button>
    </form>

    <a href="login.php" class="link-voltar-login">
        <i class="bi bi-arrow-left"></i> Voltar para o login
    </a>

</div>

<script>
const selectPerfil = document.getElementById('perfilSelecionado');
const formPaciente = document.getElementById('formPaciente');
const formEquipe = document.getElementById('formEquipe');
const perfilEquipe = document.getElementById('perfilEquipe');
const perfilSelectEquipe = document.getElementById('perfilSelectEquipe');

selectPerfil.addEventListener('change', function () {
    if (selectPerfil.value === 'paciente') {
        formPaciente.style.display = 'block';
        formEquipe.style.display = 'none';
    } else {
        formPaciente.style.display = 'none';
        formEquipe.style.display = 'block';
        perfilEquipe.value = selectPerfil.value;
        perfilSelectEquipe.value = selectPerfil.value;
    }
});
</script>

</body>
</html>