<?php
session_start();
require_once '../conexao.php';

$mensagem = "";
$forcarTrocaSenha = false;
$dadosTempUsuario = [];

// Guarda o que a pessoa digitou/selecionou, pra repopular o formulário
// se der erro (antes disso, a página sempre resetava pra "Paciente").
$perfilSelecionadoAtual = $_POST['perfil'] ?? $_COOKIE['lembrar_perfil'] ?? 'paciente';
$usuarioDigitadoAtual   = $_POST['usuario'] ?? $_COOKIE['lembrar_usuario'] ?? '';
$lembrarMarcado         = isset($_POST['lembrar']) || isset($_COOKIE['lembrar_usuario']);

// Se o usuário submeteu a nova senha (etapa de troca obrigatória)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['botao-salvar-nova-senha'])) {
    $uidTemp           = $_POST['uid_temp'] ?? '';
    $tabelaTemp        = $_POST['tabela_temp'] ?? '';
    $perfilTemp        = $_POST['perfil_temp'] ?? '';
    $senhaAtualInput   = $_POST['senha_atual'] ?? '';
    $senhaNovaInput    = $_POST['senha_nova'] ?? '';
    $senhaConfInput    = $_POST['senha_confirmar'] ?? '';

    if (!empty($senhaAtualInput) && !empty($senhaNovaInput) && !empty($senhaConfInput)) {
        if ($senhaNovaInput !== $senhaConfInput) {
            $mensagem = "<p style='color: red; text-align: center; font-weight: bold; margin-top: 15px;'>As novas senhas não coincidem.</p>";
            $forcarTrocaSenha = true;
            $dadosTempUsuario = ['uid' => $uidTemp, 'tabela' => $tabelaTemp, 'perfil' => $perfilTemp];
        } else {
            try {
                // Valida a senha atual do usuário
                $sqlVerifica = "SELECT * FROM {$tabelaTemp} WHERE uid = :uid OR email = :uid LIMIT 1";
                $stmtV = $pdo->prepare($sqlVerifica);
                $stmtV->execute([':uid' => $uidTemp]);
                $userCheck = $stmtV->fetch();

                if ($userCheck && password_verify($senhaAtualInput, $userCheck['senha'])) {
                    // Criptografa a nova senha e zera o primeiro_acesso para 0
                    $novaSenhaHash = password_hash($senhaNovaInput, PASSWORD_DEFAULT);
                    $idCol = $userCheck['id'] ?? $userCheck['id_recepcionista'];

                    $stmtU = $pdo->prepare("UPDATE {$tabelaTemp} SET senha = :senha, primeiro_acesso = 0 WHERE id = :id");
                    $stmtU->execute([':senha' => $novaSenhaHash, ':id' => $idCol]);

                    // Configura sessão e redireciona com sucesso
                    $_SESSION['usuario_id']     = $idCol;
                    $_SESSION['usuario_nome']   = $userCheck['nome'];
                    $_SESSION['usuario_perfil'] = $perfilTemp;
                    $_SESSION['nivel_acesso']   = $userCheck['nivel_acesso'] ?? null;

                    $destino = "../perfis/" . $perfilTemp . "/painel.php";

                    echo "
                    <!DOCTYPE html>
                    <html lang='pt-br'>
                    <head><meta charset='UTF-8'><title>Sucesso</title></head>
                    <body>
                        <script>
                            alert('Senha alterada com sucesso! Bem-vindo ao sistema.');
                            window.location.href = '{$destino}';
                        </script>
                    </body>
                    </html>";
                    exit();

                } else {
                    $mensagem = "<p style='color: red; text-align: center; font-weight: bold; margin-top: 15px;'>A senha atual está incorreta.</p>";
                    $forcarTrocaSenha = true;
                    $dadosTempUsuario = ['uid' => $uidTemp, 'tabela' => $tabelaTemp, 'perfil' => $perfilTemp];
                }
            } catch (PDOException $e) {
                $mensagem = "<p style='color: red; text-align: center; font-weight: bold; margin-top: 15px;'>Erro ao atualizar senha no banco de dados.</p>";
                $forcarTrocaSenha = true;
                $dadosTempUsuario = ['uid' => $uidTemp, 'tabela' => $tabelaTemp, 'perfil' => $perfilTemp];
            }
        }
    } else {
        $mensagem = "<p style='color: red; text-align: center; font-weight: bold; margin-top: 15px;'>Preencha todos os campos de senha.</p>";
        $forcarTrocaSenha = true;
        $dadosTempUsuario = ['uid' => $uidTemp, 'tabela' => $tabelaTemp, 'perfil' => $perfilTemp];
    }
}

// Se o usuário enviar o formulário normal de login via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['botao-entrar'])) {
    $usuarioInput      = trim($_POST['usuario'] ?? '');
    $senhaInput        = $_POST['senha'] ?? '';
    $perfilSelecionado = $_POST['perfil'] ?? 'paciente';
    $lembrar           = isset($_POST['lembrar']);

    $tabelasPerfis = [
        'paciente'      => 'paciente',
        'medico'        => 'medico',
        'gestor'        => 'gestores',
        'recepcionista' => 'recepcionistas'
    ];

    if (!empty($usuarioInput) && !empty($senhaInput) && array_key_exists($perfilSelecionado, $tabelasPerfis)) {
        $tabela = $tabelasPerfis[$perfilSelecionado];

        try {
            $sql = "SELECT * FROM {$tabela} WHERE uid = :input OR email = :input LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':input' => $usuarioInput]);
            $dadosUsuario = $stmt->fetch();

        $sql = "SELECT * FROM {$tabela} WHERE uid = :input OR email = :input LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute([':input' => $usuarioInput]);
$dadosUsuario = $stmt->fetch();

if ($dadosUsuario && password_verify($senhaInput, $dadosUsuario['senha'])) {


                if (isset($dadosUsuario['status']) && $dadosUsuario['status'] !== 'Ativo') {
                    $mensagem = "<p style='color: red; text-align: center; font-weight: bold; margin-top: 15px;'>Acesso negado: Usuário inativo.</p>";
                } else {

                    // VERIFICAÇÃO DA FLAG DE PRIMEIRO ACESSO NO BANCO
                    $primeiroAcesso = isset($dadosUsuario['primeiro_acesso']) ? (int)$dadosUsuario['primeiro_acesso'] : 0;

                    if ($primeiroAcesso === 1) {
                        $forcarTrocaSenha = true;
                        $dadosTempUsuario = [
                            'uid' => $usuarioInput,
                            'tabela' => $tabela,
                            'perfil' => $perfilSelecionado
                        ];
                    } else {
                        // Fluxo normal de login
                        if ($lembrar) {
                            setcookie('lembrar_usuario', $usuarioInput, time() + (30 * 24 * 60 * 60), "/");
                            setcookie('lembrar_perfil', $perfilSelecionado, time() + (30 * 24 * 60 * 60), "/");
                        } else {
                            setcookie('lembrar_usuario', '', time() - 3600, "/");
                            setcookie('lembrar_perfil', '', time() - 3600, "/");
                        }

                        $_SESSION['usuario_id']     = $dadosUsuario['id'] ?? $dadosUsuario['id_recepcionista'];
                        $_SESSION['usuario_nome']   = $dadosUsuario['nome'];
                        $_SESSION['usuario_perfil'] = $perfilSelecionado;
                        $_SESSION['nivel_acesso']   = $dadosUsuario['nivel_acesso'] ?? null;

                        $destino = "../perfis/" . $perfilSelecionado . "/painel.php";

                        if (!headers_sent()) {
                            header("Location: " . $destino);
                        } else {
                            echo "<script>window.location.href='" . $destino . "';</script>";
                        }
                        exit();
                    }
                }

            } else {
                $mensagem = "<p style='color: red; text-align: center; font-weight: bold; margin-top: 15px;'>Credenciais incorretas.</p>";
            }

        } catch (PDOException $e) {
            $mensagem = "<p style='color: red; text-align: center; font-weight: bold; margin-top: 15px;'>Erro ao consultar banco de dados.</p>";
        }

    } else {
        $mensagem = "<p style='color: red; text-align: center; font-weight: bold; margin-top: 15px;'>Preencha todos os campos corretamente.</p>";
    }

    // Depois de um POST de login (com erro ou não), o formulário deve
    // reaparecer com o MESMO perfil e usuário que a pessoa digitou —
    // antes disso o JS forçava de volta pra "Paciente" e confundia.
    $perfilSelecionadoAtual = $perfilSelecionado;
    $usuarioDigitadoAtual   = $usuarioInput;
    $lembrarMarcado         = $lembrar;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Convelum Saúde</title>

    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/responsivo.css">

    <style>
        /* Estilo para alinhar o input de senha com o ícone de olho */
        .input-senha-grupo {
            position: relative;
            display: flex;
            align-items: center;
        }
        .input-senha-grupo input {
            width: 100%;
            padding-right: 40px !important;
        }
        .botao-olho {
            position: absolute;
            right: 12px;
            background: none;
            border: none;
            cursor: pointer;
            color: #6c757d;
            font-size: 1.1rem;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
        }
        .botao-olho:focus {
            outline: none;
        }
    </style>
</head>
<body class="pagina-login">

<div class="login-card">

    <div class="login-card-topo">
        <?php if (isset($_GET['status']) && $_GET['status'] === 'cadastro_sucesso'): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    alert('Cadastro realizado com sucesso! Faça login para continuar.');
                });
            </script>
        <?php endif; ?>
        <a href="../index.php">
            <img src="../imgs/logofrase.png" alt="Convelum Saúde">
        </a>
    </div>

    <?php if ($forcarTrocaSenha): ?>
        <!-- TELA DE PRIMEIRO LOGIN / ALTERAÇÃO DE SENHA OBRIGATÓRIA -->
        <div class="alert alert-warning text-center mb-3" style="font-size: 14px; border-radius: 8px;">
            <i class="bi bi-exclamation-triangle-fill"></i> <strong>Atenção:</strong> Este é o seu primeiro login! Por segurança, adicione uma senha melhor.
        </div>

        <h1>Redefinir Senha</h1>
        <p class="login-subtitulo">Crie uma nova senha de acesso pessoal</p>

        <form method="post" action="" class="form-login" autocomplete="off">
            <input type="hidden" name="uid_temp" value="<?php echo htmlspecialchars($dadosTempUsuario['uid'] ?? ''); ?>">
            <input type="hidden" name="tabela_temp" value="<?php echo htmlspecialchars($dadosTempUsuario['tabela'] ?? ''); ?>">
            <input type="hidden" name="perfil_temp" value="<?php echo htmlspecialchars($dadosTempUsuario['perfil'] ?? ''); ?>">

            <div class="campo-login">
                <label for="senha_atual">Senha atual (ou padrão)</label>
                <div class="input-senha-grupo">
                    <input type="password" id="senha_atual" name="senha_atual" placeholder="Digite sua senha atual" required>
                    <button type="button" class="botao-olho" onclick="alternarSenha('senha_atual', 'icone_olho_atual')">
                        <i class="bi bi-eye" id="icone_olho_atual"></i>
                    </button>
                </div>
            </div>

            <div class="campo-login">
                <label for="senha_nova">Nova senha</label>
                <div class="input-senha-grupo">
                    <input type="password" id="senha_nova" name="senha_nova" placeholder="Digite sua nova senha" required>
                    <button type="button" class="botao-olho" onclick="alternarSenha('senha_nova', 'icone_olho_nova')">
                        <i class="bi bi-eye" id="icone_olho_nova"></i>
                    </button>
                </div>
            </div>

            <div class="campo-login">
                <label for="senha_confirmar">Confirmar nova senha</label>
                <div class="input-senha-grupo">
                    <input type="password" id="senha_confirmar" name="senha_confirmar" placeholder="Confirme a nova senha" required>
                    <button type="button" class="botao-olho" onclick="alternarSenha('senha_confirmar', 'icone_olho_conf')">
                        <i class="bi bi-eye" id="icone_olho_conf"></i>
                    </button>
                </div>
            </div>

            <button type="submit" name="botao-salvar-nova-senha" class="botao-entrar">Salvar Nova Senha</button>

            <?php echo $mensagem; ?>
        </form>

    <?php else: ?>
        <!-- TELA DE LOGIN NORMAL -->
        <h1>Bem-vindo</h1>
        <p class="login-subtitulo">Escolha seu perfil e faça login para continuar</p>

        <form method="post" action="" class="form-login" autocomplete="off">

            <div class="campo-login">
                <label for="perfilSelecionado">Perfil de acesso</label>
                <select id="perfilSelecionado" name="perfil" class="select-perfil">
                    <option value="paciente" <?php echo $perfilSelecionadoAtual === 'paciente' ? 'selected' : ''; ?>>Paciente</option>
                    <option value="medico" <?php echo $perfilSelecionadoAtual === 'medico' ? 'selected' : ''; ?>>Médico</option>
                    <option value="gestor" <?php echo $perfilSelecionadoAtual === 'gestor' ? 'selected' : ''; ?>>Gestor</option>
                    <option value="recepcionista" <?php echo $perfilSelecionadoAtual === 'recepcionista' ? 'selected' : ''; ?>>Recepcionista</option>
                </select>
            </div>

            <div class="campo-login">
                <label for="usuario" id="labelUsuario">E-mail ou UID</label>
                <input
                    type="text"
                    id="usuario"
                    name="usuario"
                    class="form-control"
                    placeholder="Digite seu e-mail ou UID"
                    value="<?php echo htmlspecialchars($usuarioDigitadoAtual); ?>"
                    autocomplete="off"
                    required
                >
            </div>

            <div class="campo-login">
                <label for="senha">Senha</label>
                <div class="input-senha-grupo">
                    <input type="password" id="senha" name="senha" placeholder="Digite sua senha" autocomplete="new-password" required>
                    <button type="button" class="botao-olho" onclick="alternarSenha('senha', 'icone_olho_login')">
                        <i class="bi bi-eye" id="icone_olho_login"></i>
                    </button>
                </div>
            </div>

            <div class="linha-opcoes">
                <label class="lembrar-senha">
                    <input type="checkbox" name="lembrar" id="checkboxLembrar" <?php echo $lembrarMarcado ? 'checked' : ''; ?>>
                    Lembrar senha
                </label>
                <a href="./esqueci-senha.php" class="link-esqueci">Esqueci minha senha</a>
            </div>

            <button type="submit" name="botao-entrar" class="botao-entrar">Entrar</button>

            <?php echo $mensagem; ?>
        </form>

        <hr class="bg-gray-300 my-2 mt-sm-3 mb-sm-2">

        <p class="text-center" id="texto-cadastrar">
            <span id="texto-cadastro">Ainda não tem conta?</span>
            <a href="./cadastro.php" id="linkcadastro">Faça seu cadastro!</a>
        </p>
    <?php endif; ?>

</div>

<script>
const selectPerfil = document.getElementById('perfilSelecionado');
const labelUsuario = document.getElementById('labelUsuario');
const inputUsuario = document.getElementById('usuario');
const textoCadastro = document.getElementById('texto-cadastrar');

// O perfil e usuário já vêm corretos do PHP (repopulados após POST).
// O JS só ajusta o texto do rótulo (label/placeholder) de acordo com
// o perfil que já está selecionado — sem apagar o que a pessoa digitou.
function atualizarInterfacePerfil() {
    if (selectPerfil && selectPerfil.value === 'paciente') {
        labelUsuario.textContent = 'E-mail ou UID';
        inputUsuario.placeholder = 'Digite seu e-mail ou UID';
        if (textoCadastro) textoCadastro.style.display = 'block';
    } else if (selectPerfil) {
        labelUsuario.textContent = 'UID';
        inputUsuario.placeholder = 'Digite seu UID';
        if (textoCadastro) textoCadastro.style.display = 'none';
    }
}

// Função para mostrar/esconder a senha e alternar o ícone do olho
function alternarSenha(idInput, idIcone) {
    const input = document.getElementById(idInput);
    const icone = document.getElementById(idIcone);

    if (input.type === 'password') {
        input.type = 'text';
        icone.classList.remove('bi-eye');
        icone.classList.add('bi-eye-slash');
    } else {
        input.type = 'password';
        icone.classList.remove('bi-eye-slash');
        icone.classList.add('bi-eye');
    }
}

if (selectPerfil) {
    selectPerfil.addEventListener('change', atualizarInterfacePerfil);
}
document.addEventListener('DOMContentLoaded', atualizarInterfacePerfil);
</script>

</body>
</html>