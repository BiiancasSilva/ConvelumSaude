<?php
// perfis/paciente/configuracoes.php
session_start();
require_once __DIR__ . '/../../conexao.php';

if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'paciente') {
    header("Location: ../../auth/login.php");
    exit();
}

$pacienteId = $_SESSION['usuario_id'];
$pacienteId = filter_var($pacienteId, FILTER_VALIDATE_INT);
if ($pacienteId === false || $pacienteId < 1) {
    header("Location: ../../auth/login.php");
    exit();
}

$paciente = [
    'nome'           => '',
    'email'          => '',
    'celular'        => '',
    'telefone'       => '',
    'tipo_documento' => 'cpf',
    'num_doc'        => ''
];

try {
    $stmt = $pdo->prepare(
        'SELECT nome, email, celular, telefone, tipo_documento, num_doc
         FROM paciente
         WHERE id = :id
         LIMIT 1'
    );
    $stmt->execute([':id' => $pacienteId]);
    $dados = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$dados) {
        header("Location: ../../auth/login.php");
        exit();
    }

    $paciente = array_merge(
        $paciente,
        array_filter($dados, static function ($valor) {
            return $valor !== null;
        })
    );
} catch (PDOException $e) {
    error_log('Falha ao carregar configurações do paciente: ' . $e->getMessage());
    $erroCarregamento = 'Não foi possível carregar seus dados. Atualize a página ou tente novamente mais tarde.';
}

$tipoDocSalvo = strtolower(trim($paciente['tipo_documento'] ?? 'cpf'));
if (empty($tipoDocSalvo)) {
    $tipoDocSalvo = 'cpf';
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações da Conta - Convelum Saúde</title>

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

<!-- ========================= CABEÇALHO ========================= -->
<div id="painel-boasvindas">
    <div>
        <span id="subtitulo-painel">SUA CONTA</span>
        <h1 id="titulo-painel">Configurações da conta</h1>
        <p id="texto-painel">Atualize seus dados pessoais, senha e preferências.</p>
    </div>
</div>

<div class="secao-config-agenda">

    <?php if (!empty($erroCarregamento)): ?>
        <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($erroCarregamento, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php elseif (isset($_GET['sucesso'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $_GET['sucesso'] === 'senha' ? 'Senha atualizada com sucesso!' : 'Dados atualizados com sucesso!'; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php elseif (isset($_GET['erro'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($_GET['erro'], ENT_QUOTES, 'UTF-8'); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- ========================= DADOS PESSOAIS ========================= -->
    <form method="post" action="actions/salvar-configuracoes.php" class="cartao-config-agenda mb-4">
        <h2 class="titulo-cartao-config">Dados pessoais</h2>

        <div class="campo-config mb-3">
            <label for="nomeConta" class="form-label">Nome completo</label>
            <input type="text" id="nomeConta" name="nome" class="form-control" maxlength="200" value="<?php echo htmlspecialchars($paciente['nome'], ENT_QUOTES, 'UTF-8'); ?>" required>
        </div>
        
        <div class="linha-config-dupla row g-3 mb-3">
            <div class="campo-config col-md-6">
                <label for="tipoDocumentoPaciente" class="form-label">Tipo de documento</label>
                <select id="tipoDocumentoPaciente" name="tipo_documento" class="form-select" onchange="ajustarTipoDocumento()">
                    <option value="rg" <?php echo $tipoDocSalvo === 'rg' ? 'selected' : ''; ?>>RG</option>
                    <option value="cpf" <?php echo $tipoDocSalvo === 'cpf' ? 'selected' : ''; ?>>CPF</option>
                    <option value="cnh" <?php echo $tipoDocSalvo === 'cnh' ? 'selected' : ''; ?>>CNH</option>
                    <option value="passaporte" <?php echo $tipoDocSalvo === 'passaporte' ? 'selected' : ''; ?>>Passaporte</option>
                    <option value="outro" <?php echo $tipoDocSalvo === 'outro' ? 'selected' : ''; ?>>Outro documento oficial</option>
                </select>
            </div>
            <div class="campo-config col-md-6">
                <label for="numeroDocumentoPaciente" class="form-label">Número do documento</label>
                <!-- Atributo 'inputmode' e sem restrições rígidas para permitir formato livre -->
                <input type="text" id="numeroDocumentoPaciente" name="numero_documento" maxlength="60" class="form-control" value="<?php echo htmlspecialchars($paciente['num_doc'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Digite o número">
            </div>
        </div>

        <div class="campo-config mb-3">
            <label for="emailConta" class="form-label">E-mail</label>
            <input type="email" id="emailConta" name="email" maxlength="100" class="form-control" value="<?php echo htmlspecialchars($paciente['email'], ENT_QUOTES, 'UTF-8'); ?>" required>
        </div>

        <div class="linha-config-dupla row g-3 mb-3">
            <div class="campo-config col-md-6">
                <label for="celularConta" class="form-label">Celular</label>
                <input type="tel" id="celularConta" name="celular" maxlength="15" class="form-control" value="<?php echo htmlspecialchars($paciente['celular'], ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="campo-config col-md-6">
                <label for="telefoneConta" class="form-label">Telefone <span class="campo-opcional text-muted">(opcional)</span></label>
                <input type="tel" id="telefoneConta" name="telefone" maxlength="20" class="form-control" value="<?php echo htmlspecialchars($paciente['telefone'], ENT_QUOTES, 'UTF-8'); ?>">
            </div>
        </div>

        <button type="submit" class="botao-entrar botao-config-salvar btn btn-primary">Salvar dados pessoais</button>
    </form>

    <!-- ========================= TROCAR SENHA ========================= -->
    <form method="post" action="actions/trocar-senha.php" class="cartao-config-agenda mb-4">
        <h2 class="titulo-cartao-config">Alterar senha</h2>

        <div class="campo-config mb-3">
            <label for="senhaAtual" class="form-label">Senha atual</label>
            <input type="password" id="senhaAtual" name="senha_atual" class="form-control" placeholder="Digite sua senha atual" required>
        </div>

        <div class="linha-config-dupla row g-3 mb-3">
            <div class="campo-config col-md-6">
                <label for="senhaNova" class="form-label">Nova senha</label>
                <input type="password" id="senhaNova" name="senha_nova" class="form-control" placeholder="Digite a nova senha" required>
            </div>
            <div class="campo-config col-md-6">
                <label for="senhaNovaConfirmar" class="form-label">Confirmar nova senha</label>
                <input type="password" id="senhaNovaConfirmar" name="senha_nova_confirmar" class="form-control" placeholder="Repita a nova senha" required>
            </div>
        </div>

        <p class="mensagem-erro-etapa text-danger small mb-3" id="mensagemErroSenhaConta"></p>

        <button type="submit" class="botao-entrar botao-config-salvar btn btn-primary" onclick="return validarNovaSenha()">Atualizar senha</button>
    </form>

    <!-- ========================= NOTIFICAÇÕES ========================= -->
    <div class="cartao-config-agenda mb-4">
        <h2 class="titulo-cartao-config mb-3">Notificações</h2>
        <p class="text-muted">As preferências de notificação ainda não podem ser salvas.</p>

        <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" id="notifEmail" checked disabled>
            <label class="form-check-label" for="notifEmail">
                Lembretes de consulta por e-mail
            </label>
        </div>

        <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" id="notifSms" checked disabled>
            <label class="form-check-label" for="notifSms">
                Lembretes de consulta por SMS/WhatsApp
            </label>
        </div>

        <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" id="notifPromo" disabled>
            <label class="form-check-label" for="notifPromo">
                Novidades e promoções da clínica
            </label>
        </div>
    </div>

    <!-- ========================= ZONA DE RISCO ========================= -->
    <div class="cartao-config-agenda cartao-zona-risco border-danger">
        <h2 class="titulo-cartao-config text-danger">Zona de risco</h2>
        <p class="texto-zona-risco text-muted">A exclusão da conta não está disponível por esta tela. Para solicitar o encerramento, entre em contato com a clínica.</p>
    </div>

</div>

<script>
// Função nativa simples inline do HTML para ajustar comportamento sem travar
function ajustarTipoDocumento() {
    const select = document.getElementById('tipoDocumentoPaciente');
    const input = document.getElementById('numeroDocumentoPaciente');
    
    if (!select || !input) return;

    // Se selecionar Passaporte ou Outro, limpa restrições de formatação nativa
    if (select.value === 'outro' || select.value === 'passaporte' || select.value === 'cnh') {
        input.removeAttribute('maxlength');
        input.placeholder = 'Digite o número livremente';
    } else if (select.value === 'cpf') {
        input.setAttribute('maxlength', '14');
        input.placeholder = '000.000.000-00';
    } else if (select.value === 'rg') {
        input.setAttribute('maxlength', '12');
        input.placeholder = '00.000.000-0';
    }
}

// Executa ao carregar a página para alinhar o placeholder inicial com o que veio do banco
window.addEventListener('DOMContentLoaded', function() {
    ajustarTipoDocumento();
});

function validarNovaSenha() {
    const nova = document.getElementById('senhaNova').value;
    const confirmar = document.getElementById('senhaNovaConfirmar').value;
    const mensagemErro = document.getElementById('mensagemErroSenhaConta');

    mensagemErro.textContent = '';

    if (nova && nova !== confirmar) {
        mensagemErro.textContent = 'As senhas não coincidem.';
        return false;
    }

    return true;
}

</script>

</body>
</html>