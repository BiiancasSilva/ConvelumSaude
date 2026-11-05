<?php
// Conexão com o banco (ajuste o caminho do seu arquivo de conexão se necessário)
require_once '../conexao.php'; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Recebe os dados da Etapa 1
    $nome       = $_POST['nome'] ?? '';
    $email      = $_POST['email'] ?? '';
    $telefone   = !empty($_POST['telefone']) ? $_POST['telefone'] : null;
    $celular    = !empty($_POST['celular']) ? $_POST['celular'] : null;
    $nascimento = !empty($_POST['nascimento']) ? $_POST['nascimento'] : null;

    // Recebe os dados da Etapa 3
    $senhaRaw   = $_POST['senha'] ?? '';

    // Validação obrigatória: senha com no mínimo 6 dígitos
    if (strlen($senhaRaw) < 6) {
        echo "<script>alert('A senha deve ter no mínimo 6 dígitos.'); window.history.back();</script>";
        exit;
    }

    $senhaHash  = password_hash($senhaRaw, PASSWORD_DEFAULT);

    // Verifica se é menor de idade (visto no JS)
    $menorInput = $_POST['menor_de_idade'] ?? 'nao';
    $menorIdade = ($menorInput === 'sim') ? 'Y' : 'N';

    // Recebe os dados da Etapa 2 (caso seja menor de idade)
    $nomeResp       = !empty($_POST['nome_responsavel']) ? $_POST['nome_responsavel'] : null;
    $telResp        = !empty($_POST['telefone_responsavel']) ? $_POST['telefone_responsavel'] : null;
    $celResp        = !empty($_POST['celular_responsavel']) ? $_POST['celular_responsavel'] : null;
    $emailResp      = !empty($_POST['email_responsavel']) ? $_POST['email_responsavel'] : null;
    $nascimentoResp = !empty($_POST['nascimento_responsavel']) ? $_POST['nascimento_responsavel'] : null;

    // Gera o UID sequencial no padrão PAC001, PAC002, etc. (igual gestor/médico)
    $queryUltimo = $pdo->query("SELECT id FROM paciente ORDER BY id DESC LIMIT 1");
    $ultimoId    = $queryUltimo->fetchColumn();
    $proximoId   = $ultimoId ? ((int)$ultimoId + 1) : 1;
    $uid         = 'PAC' . str_pad($proximoId, 3, '0', STR_PAD_LEFT);

    // Insere no banco utilizando as colunas da tabela paciente (com primeiro_acesso = 0)
    $sql = "INSERT INTO paciente (
                uid, nome, email, telefone, celular, data_nasc, 
                senha, menor_idade, nome_resp, telefone_resp, 
                celular_resp, email_resp, data_nasc_resp, nivel_acesso, primeiro_acesso
            ) VALUES (
                :uid, :nome, :email, :telefone, :celular, :data_nasc, 
                :senha, :menor_idade, :nome_resp, :telefone_resp, 
                :celular_resp, :email_resp, :data_nasc_resp, 1, 0
            )";

    $stmt = $pdo->prepare($sql);
    
    $sucesso = $stmt->execute([
        ':uid'            => $uid,
        ':nome'           => $nome,
        ':email'          => $email,
        ':telefone'       => $telefone,
        ':celular'        => $celular,
        ':data_nasc'      => $nascimento,
        ':senha'          => $senhaHash,
        ':menor_idade'    => $menorIdade,
        ':nome_resp'      => $nomeResp,
        ':telefone_resp'  => $telResp,
        ':celular_resp'   => $celResp,
        ':email_resp'     => $emailResp,
        ':data_nasc_resp' => $nascimentoResp
    ]);

    if ($sucesso) {
        header("Location: login.php?status=cadastro_sucesso");
        exit;
    } else {
        echo "<script>alert('Erro ao cadastrar paciente.');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar conta - Convelum Saúde</title>

    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/responsivo.css">
</head>
<body class="pagina-login">

<div class="login-card login-card-cadastro">

    <div class="login-card-topo">
        <a href="../index.php">
            <img src="../imgs/logofrase.png" alt="Convelum Saúde">
        </a>
    </div>

    <!-- INDICADOR DE ETAPAS -->
    <div class="indicador-etapas" id="indicadorEtapas">
        <span class="ponto-etapa ativo" data-ponto="1"></span>
        <span class="ponto-etapa" data-ponto="2"></span>
        <span class="ponto-etapa" data-ponto="3"></span>
    </div>

    <form method="post" action="" id="formCadastro" class="form-login" autocomplete="off">

        <!-- ========================= ETAPA 1: DADOS PESSOAIS ========================= -->
        <div class="etapa-cadastro ativa" data-etapa="1">
            <h1>Seus dados</h1>
            <p class="login-subtitulo">Vamos começar com suas informações pessoais</p>

            <div class="campo-login">
                <label for="nome">Nome completo</label>
                <input type="text" id="nome" name="nome" placeholder="Digite seu nome completo">
            </div>

            <!-- No campo de e-mail -->
            <div class="campo-login">
                <label for="email">E-mail</label>
                <input type="email" 
                id="email" 
                name="email" 
                placeholder="Digite seu e-mail" 
                autocomplete="chrome-off" 
                readonly 
                onfocus="this.removeAttribute('readonly');">
            </div>

            <div class="linha-cadastro-dupla">
                <div class="campo-login">
                    <label for="telefone">Telefone <span class="campo-opcional">(opcional)</span></label>
                    <input type="tel" id="telefone" name="telefone" placeholder="(00) 0000-0000">
                </div>
                <div class="campo-login">
                    <label for="celular">Celular</label>
                    <input type="tel" id="celular" name="celular" placeholder="(00) 00000-0000">
                </div>
            </div>

            <div class="campo-login">
                <label for="nascimento">Data de nascimento</label>
                <input type="date" id="nascimento" name="nascimento">
            </div>

            <button type="button" class="botao-entrar" onclick="avancarEtapa1()">Próximo</button>
        </div>

        <!-- ========================= ETAPA 2: RESPONSÁVEL (só se menor de idade) ========================= -->
        <div class="etapa-cadastro" data-etapa="2">
            <h1>Dados do responsável</h1>
            <p class="login-subtitulo">Como você é menor de idade, precisamos dos dados de um responsável</p>

            <div class="campo-login">
                <label for="nomeResponsavel">Nome do responsável</label>
                <input type="text" id="nomeResponsavel" name="nome_responsavel" placeholder="Nome completo do responsável">
            </div>

            <div class="linha-cadastro-dupla">
                <div class="campo-login">
                    <label for="telefoneResponsavel">Telefone <span class="campo-opcional">(opcional)</span></label>
                    <input type="tel" id="telefoneResponsavel" name="telefone_responsavel" placeholder="(00) 0000-0000">
                </div>
                <div class="campo-login">
                    <label for="celularResponsavel">Celular</label>
                    <input type="tel" id="celularResponsavel" name="celular_responsavel" placeholder="(00) 00000-0000">
                </div>
            </div>

            <div class="campo-login">
                <label for="emailResponsavel">E-mail do responsável</label>
                <input type="email" id="emailResponsavel" name="email_responsavel" placeholder="Digite o e-mail do responsável">
            </div>

            <div class="campo-login">
                <label for="nascimentoResponsavel">Data de nascimento do responsável</label>
                <input type="date" id="nascimentoResponsavel" name="nascimento_responsavel">
            </div>

            <div class="botoes-etapa">
                <button type="button" class="botao-voltar-etapa" onclick="voltarEtapa(1)">Voltar</button>
                <button type="button" class="botao-entrar" onclick="avancarEtapa2()">Próximo</button>
            </div>
        </div>

        <!-- ========================= ETAPA 3: SENHA E TERMOS ========================= -->
        <div class="etapa-cadastro" data-etapa="3">
            <h1>Quase lá</h1>
            <p class="login-subtitulo">Crie uma senha pra proteger sua conta</p>

            <div class="campo-login">
                <label for="senha">Senha</label>
                <div class="input-group">
                    <input type="password" id="senha" name="senha" class="form-control" placeholder="Crie uma senha (mínimo 6 dígitos)" minlength="6" required>
                    <button class="btn btn-outline-secondary" type="button" onclick="alternarVisibilidadeSenha('senha', 'iconeSenha')">
                        <i class="bi bi-eye" id="iconeSenha"></i>
                    </button>
                </div>
            </div>

            <div class="campo-login">
                <label for="confirmarSenha">Confirmar senha</label>
                <div class="input-group">
                    <input type="password" id="confirmarSenha" name="confirmar_senha" class="form-control" placeholder="Repita a senha">
                    <button class="btn btn-outline-secondary" type="button" onclick="alternarVisibilidadeSenha('confirmarSenha', 'iconeConfirmarSenha')">
                        <i class="bi bi-eye" id="iconeConfirmarSenha"></i>
                    </button>
                </div>
            </div>

            <p class="mensagem-erro-etapa" id="mensagemErroSenha"></p>

            <label class="lembrar-senha campo-termos">
    <input type="checkbox" id="aceiteTermos" name="aceite_termos">
    Li e aceito os termos de uso e a
    <a href="../politica-privacidade.php" target="_blank">política de privacidade</a>
</label>

            <div class="botoes-etapa">
                <button type="button" class="botao-voltar-etapa" id="botaoVoltarEtapa3" onclick="voltarEtapa(1)">Voltar</button>
                <button type="button" class="botao-entrar" onclick="concluirCadastro()">Criar minha conta</button>
            </div>
        </div>

        <!-- Campo escondido: preenchido pelo JS, indica se o fluxo do responsável foi usado -->
        <input type="hidden" name="menor_de_idade" id="menorDeIdade" value="nao">
    </form>

    <!-- ========================= TELA FINAL: CADASTRO CONCLUÍDO ========================= -->
    <div class="tela-concluido" id="telaConcluido">
        <i class="bi bi-check-circle-fill"></i>
        <h2>Cadastro concluído!</h2>
        <p>Sua conta foi criada com sucesso. Agora você já pode fazer login e agendar suas consultas.</p>
        <a href="login.php?status=cadastro_sucesso" class="botao-entrar botao-ir-login">Ir para o login</a>
    </div>

</div>

<script>
const etapas = document.querySelectorAll('.etapa-cadastro');
const pontos = document.querySelectorAll('.ponto-etapa');
const indicadorEtapas = document.getElementById('indicadorEtapas');
const telaConcluido = document.getElementById('telaConcluido');
const formCadastro = document.getElementById('formCadastro');
const menorDeIdadeInput = document.getElementById('menorDeIdade');

// ========================= MÁSCARAS E FORMATADORES =========================

function mascaraTelefone(input) {
    let valor = input.value.replace(/\D/g, '');
    if (valor.length > 10) valor = valor.substring(0, 10);
    valor = valor.replace(/^(\d{2})(\d)/g, '($1) $2');
    valor = valor.replace(/(\d{4})(\d)/, '$1-$2');
    input.value = valor;
}

function mascaraCelular(input) {
    let valor = input.value.replace(/\D/g, '');
    if (valor.length > 11) valor = valor.substring(0, 11);
    valor = valor.replace(/^(\d{2})(\d)/g, '($1) $2');
    valor = valor.replace(/(\d{5})(\d)/, '$1-$2');
    input.value = valor;
}

function mascaraEmail(input) {
    let valor = input.value.toLowerCase().replace(/\s+/g, '');
    valor = valor.replace(/[^a-z0-9@._-]/g, '');
    input.value = valor;
}

document.addEventListener('DOMContentLoaded', function () {
    const telInput = document.getElementById('telefone');
    const celInput = document.getElementById('celular');
    const telRespInput = document.getElementById('telefoneResponsavel');
    const celRespInput = document.getElementById('celularResponsavel');
    const emailInput = document.getElementById('email');
    const emailRespInput = document.getElementById('emailResponsavel');
    const nascimentoInput = document.getElementById('nascimento');
    const nascimentoRespInput = document.getElementById('nascimentoResponsavel');

    if (telInput) telInput.addEventListener('input', () => mascaraTelefone(telInput));
    if (celInput) celInput.addEventListener('input', () => mascaraCelular(celInput));
    if (telRespInput) telRespInput.addEventListener('input', () => mascaraTelefone(telRespInput));
    if (celRespInput) celRespInput.addEventListener('input', () => mascaraCelular(celRespInput));
    if (emailInput) emailInput.addEventListener('input', () => mascaraEmail(emailInput));
    if (emailRespInput) emailRespInput.addEventListener('input', () => mascaraEmail(emailRespInput));

    if (nascimentoInput) nascimentoInput.value = '';
    if (nascimentoRespInput) nascimentoRespInput.value = '';

    if (emailInput) {
        emailInput.value = '';
        setTimeout(() => { emailInput.value = ''; }, 300);
    }
});

// ========================= NAVEGAÇÃO E ETAPAS =========================

function mostrarEtapa(numero) {
    etapas.forEach(function (etapa) {
        etapa.classList.toggle('ativa', etapa.dataset.etapa === String(numero));
    });
    pontos.forEach(function (ponto) {
        ponto.classList.toggle('ativo', ponto.dataset.ponto === String(numero));
    });
}

function voltarEtapa(numero) {
    mostrarEtapa(numero);
}

function calcularSeEMenorDeIdade(dataNascimento) {
    if (!dataNascimento) return false;
    const nascimento = new Date(dataNascimento);
    const hoje = new Date();
    let idade = hoje.getFullYear() - nascimento.getFullYear();
    const aindaNaoFezAniversario =
        hoje.getMonth() < nascimento.getMonth() ||
        (hoje.getMonth() === nascimento.getMonth() && hoje.getDate() < nascimento.getDate());
    if (aindaNaoFezAniversario) idade--;
    return idade < 18;
}

function avancarEtapa1() {
    const nomeInput = document.getElementById('nome');
    const emailInput = document.getElementById('email');
    const celularInput = document.getElementById('celular');
    const nascimentoInput = document.getElementById('nascimento');

    [nomeInput, emailInput, celularInput, nascimentoInput].forEach(input => {
        if (input) input.setCustomValidity('');
    });

    if (!nomeInput.value.trim()) {
        nomeInput.setCustomValidity('Preencha seu nome completo.');
        nomeInput.reportValidity();
        return;
    }

    const email = emailInput.value.trim();
    const emailValido = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);

    if (!email) {
        emailInput.setCustomValidity('Preencha seu e-mail.');
        emailInput.reportValidity();
        return;
    }

    if (!emailValido) {
        emailInput.setCustomValidity('Informe um e-mail válido contendo "@" e um domínio (ex: nome@email.com).');
        emailInput.reportValidity();
        return;
    }

    if (!celularInput.value.trim()) {
        celularInput.setCustomValidity('Preencha seu celular.');
        celularInput.reportValidity();
        return;
    }

    if (!nascimentoInput.value) {
        nascimentoInput.setCustomValidity('Selecione sua data de nascimento.');
        nascimentoInput.reportValidity();
        return;
    }

    const menor = calcularSeEMenorDeIdade(nascimentoInput.value);
    menorDeIdadeInput.value = menor ? 'sim' : 'nao';

    if (menor) {
        document.getElementById('botaoVoltarEtapa3').setAttribute('onclick', 'voltarEtapa(2)');
        mostrarEtapa(2);
    } else {
        document.getElementById('botaoVoltarEtapa3').setAttribute('onclick', 'voltarEtapa(1)');
        mostrarEtapa(3);
    }
}

function calcularSeEMaiorDeIdade(dataNascimento) {
    if (!dataNascimento) return false;
    const nascimento = new Date(dataNascimento);
    const hoje = new Date();
    let idade = hoje.getFullYear() - nascimento.getFullYear();
    const aindaNaoFezAniversario =
        hoje.getMonth() < nascimento.getMonth() ||
        (hoje.getMonth() === nascimento.getMonth() && hoje.getDate() < nascimento.getDate());
    if (aindaNaoFezAniversario) idade--;
    return idade >= 18;
}

function avancarEtapa2() {
    const nomeRespInput = document.getElementById('nomeResponsavel');
    const celularRespInput = document.getElementById('celularResponsavel');
    const emailRespInput = document.getElementById('emailResponsavel');
    const nascimentoRespInput = document.getElementById('nascimentoResponsavel');

    [nomeRespInput, celularRespInput, emailRespInput, nascimentoRespInput].forEach(input => {
        if (input) input.setCustomValidity('');
    });

    if (!nomeRespInput.value.trim()) {
        nomeRespInput.setCustomValidity('Preencha o nome do responsável.');
        nomeRespInput.reportValidity();
        return;
    }

    if (!celularRespInput.value.trim()) {
        celularRespInput.setCustomValidity('Preencha o celular do responsável.');
        celularRespInput.reportValidity();
        return;
    }

    const emailResp = emailRespInput.value.trim();
    const emailRespValido = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailResp);

    if (!emailResp) {
        emailRespInput.setCustomValidity('Preencha o e-mail do responsável.');
        emailRespInput.reportValidity();
        return;
    }

    if (!emailRespValido) {
        emailRespInput.setCustomValidity('Informe um e-mail válido com "@" e um domínio (ex: responsavel@email.com).');
        emailRespInput.reportValidity();
        return;
    }

    if (!nascimentoRespInput.value) {
        nascimentoRespInput.setCustomValidity('Selecione a data de nascimento do responsável.');
        nascimentoRespInput.reportValidity();
        return;
    }

    if (!calcularSeEMaiorDeIdade(nascimentoRespInput.value)) {
        nascimentoRespInput.setCustomValidity('O responsável precisa ser maior de 18 anos.');
        nascimentoRespInput.reportValidity();
        return;
    }

    mostrarEtapa(3);
}

document.addEventListener('DOMContentLoaded', function () {
    const todosInputs = document.querySelectorAll('input');
    todosInputs.forEach(input => {
        input.addEventListener('input', function () {
            this.setCustomValidity('');
        });
    });
});

function concluirCadastro() {
    const senha = document.getElementById('senha').value;
    const confirmarSenha = document.getElementById('confirmarSenha').value;
    const aceiteTermos = document.getElementById('aceiteTermos').checked;
    const mensagemErro = document.getElementById('mensagemErroSenha');

    mensagemErro.textContent = '';

    if (!senha || !confirmarSenha) {
        mensagemErro.textContent = 'Preencha os dois campos de senha.';
        return;
    }

    if (senha.length < 6) {
        mensagemErro.textContent = 'A senha deve ter no mínimo 6 dígitos.';
        return;
    }

    if (senha !== confirmarSenha) {
        mensagemErro.textContent = 'As senhas não coincidem.';
        return;
    }

    if (!aceiteTermos) {
        mensagemErro.textContent = 'Você precisa aceitar os termos pra continuar.';
        return;
    }

    formCadastro.submit();
}

function alternarVisibilidadeSenha(idCampo, idIcone) {
    const campo = document.getElementById(idCampo);
    const icone = document.getElementById(idIcone);

    if (campo.type === 'password') {
        campo.type = 'text';
        icone.classList.remove('bi-eye');
        icone.classList.add('bi-eye-slash');
    } else {
        campo.type = 'password';
        icone.classList.remove('bi-eye-slash');
        icone.classList.add('bi-eye');
    }
}
</script>

</body>
</html>