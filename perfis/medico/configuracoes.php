<?php
// medico/configuracoes.php
session_start();
require_once '../../conexao.php';

// Validação de sessão para o perfil de médico
if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'medico') {
    header("Location: ../../auth/login.php");
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$medicoId = $_SESSION['usuario_id'];
$erro = '';
$sucesso = '';
$erroCarregamento = false;

if (isset($_SESSION['config_sucesso'])) {
    $sucesso = $_SESSION['config_sucesso'];
    unset($_SESSION['config_sucesso']);
}
if (isset($_SESSION['config_erro'])) {
    $erro = $_SESSION['config_erro'];
    unset($_SESSION['config_erro']);
}

// ================= BUSCANDO DADOS REAIS DO MÉDICO =================
try {
    $stmt = $pdo->prepare("
        SELECT nome, email, celular, telefone, crm, cpf, uid, data_inicio, foto,
               dias_trabalho, hora_inicio, hora_fim, duracao_consulta, intervalo_consulta
        FROM medico
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$medicoId]);
    $dados = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$dados) {
        $erroCarregamento = true;
        $erro = 'Não foi possível localizar os dados desta conta.';
    }

    $medico = [
        'nome'               => $dados['nome'] ?? '',
        'email'              => $dados['email'] ?? '',
        'celular'            => $dados['celular'] ?? '',
        'telefone'           => $dados['telefone'] ?? '',
        'crm'                => $dados['crm'] ?? '',
        'cpf'                => $dados['cpf'] ?? '',
        'uid'                => $dados['uid'] ?? '',
        'foto'               => $dados['foto'] ?? '',
        'data_inicio'        => $dados['data_inicio'] ?? '',
        'dias_trabalho'      => $dados['dias_trabalho'] ?? '',
        'hora_inicio'        => $dados['hora_inicio'] ?? '08:00',
        'hora_fim'           => $dados['hora_fim'] ?? '18:00',
        'duracao_consulta'   => $dados['duracao_consulta'] ?? 30,
        'intervalo_consulta' => $dados['intervalo_consulta'] ?? 10,
    ];
} catch (PDOException $e) {
    error_log('Falha ao carregar configurações do médico: ' . $e->getMessage());
    $erroCarregamento = true;
    $medico = [
        'nome' => '', 'email' => '', 'celular' => '', 'telefone' => '',
        'crm' => '', 'cpf' => '', 'uid' => '', 'foto' => '', 'data_inicio' => '',
        'dias_trabalho' => '', 'hora_inicio' => '08:00', 'hora_fim' => '18:00',
        'duracao_consulta' => 30, 'intervalo_consulta' => 10,
    ];
    $erro = 'Não foi possível carregar seus dados no momento.';
}

$diasTrabalhoSelecionados = $medico['dias_trabalho'] !== ''
    ? explode(',', $medico['dias_trabalho'])
    : [];

$diasSemana = [
    'seg' => 'Segunda',
    'ter' => 'Terça',
    'qua' => 'Quarta',
    'qui' => 'Quinta',
    'sex' => 'Sexta',
    'sab' => 'Sábado',
    'dom' => 'Domingo',
];

$dataInicioFormatada = '';
if (!empty($medico['data_inicio'])) {
    $dataInicioFormatada = date('d/m/Y', strtotime($medico['data_inicio']));
}
$diasSelecionados = array_values(array_filter(array_map('trim', $diasTrabalhoSelecionados)));
$diasTrabalhoFormatados = array_values(array_intersect_key($diasSemana, array_flip($diasSelecionados)));
$diasTrabalhoTexto = $diasTrabalhoFormatados ? implode(', ', $diasTrabalhoFormatados) : 'Não configurados';
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
    <style>
        .grid-dias-trabalho {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem 1.5rem;
            margin-top: 0.5rem;
        }
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

<!-- ========================= CABEÇALHO ========================= -->
<div id="painel-boasvindas">
    <div>
        <span id="subtitulo-painel">SUA CONTA</span>
        <h1 id="titulo-painel">Configurações da conta</h1>
        <p id="texto-painel">Atualize seus dados pessoais e senha e consulte sua agenda profissional.</p>
    </div>
</div>

<?php if ($sucesso): ?>
    <div class="alert alert-success alert-dismissible" style="margin: 0 2rem; display: flex; align-items: center; justify-content: space-between;">
        <span><?php echo htmlspecialchars($sucesso); ?></span>
        <button type="button" onclick="this.parentElement.remove()" style="background: none; border: none; font-size: 1.25rem; line-height: 1; cursor: pointer; padding: 0 0 0 1rem;">&times;</button>
    </div>
<?php endif; ?>
<?php if ($erro): ?>
    <div class="alert alert-danger alert-dismissible" style="margin: 0 2rem; display: flex; align-items: center; justify-content: space-between;">
        <span><?php echo htmlspecialchars($erro); ?></span>
        <button type="button" onclick="this.parentElement.remove()" style="background: none; border: none; font-size: 1.25rem; line-height: 1; cursor: pointer; padding: 0 0 0 1rem;">&times;</button>
    </div>
<?php endif; ?>

<?php if (!$erroCarregamento): ?>
<div class="secao-config-agenda">

    <!-- ========================= DADOS PESSOAIS ========================= -->
    <form method="post" action="actions/salvar-configuracoes.php" class="cartao-config-agenda" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
        <h2 class="titulo-cartao-config">Dados pessoais</h2>

        <div class="campo-config">
            <label for="fotoConta">Foto de perfil</label>
            <?php if (!empty($medico['foto'])): ?>
                <div class="foto-atual-preview">
                    <img src="../../foto-usuario.php?arquivo=<?php echo urlencode($medico['foto']); ?>" alt="Foto atual">
                    <span>Foto atual — envie um arquivo abaixo só se quiser substituí-la.</span>
                </div>
            <?php else: ?>
                <p class="text-muted" style="margin-bottom:8px;">Você ainda não tem foto cadastrada.</p>
            <?php endif; ?>
            <input type="file" id="fotoConta" name="foto" accept="image/png, image/jpeg, image/webp">
            <small class="texto-ajuda-campo">Formatos aceitos: JPG, PNG ou WEBP. Deixe em branco pra manter a foto atual.</small>
        </div>

        <div class="campo-config">
            <label for="nomeConta">Nome completo</label>
            <input type="text" id="nomeConta" name="nome" maxlength="100" value="<?php echo htmlspecialchars($medico['nome'], ENT_QUOTES, 'UTF-8'); ?>" required>
        </div>

        <div class="campo-config">
            <label for="emailConta">E-mail</label>
            <input type="email" id="emailConta" name="email" maxlength="100" value="<?php echo htmlspecialchars($medico['email'], ENT_QUOTES, 'UTF-8'); ?>" required>
        </div>

        <div class="linha-config-dupla">
            <div class="campo-config">
                <label for="celularConta">Celular</label>
                <input type="tel" id="celularConta" name="celular" maxlength="15" value="<?php echo htmlspecialchars($medico['celular'], ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
            <div class="campo-config">
                <label for="telefoneConta">Telefone</label>
                <input type="tel" id="telefoneConta" name="telefone" maxlength="15" value="<?php echo htmlspecialchars($medico['telefone'], ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
        </div>

        <button type="submit" class="botao-entrar botao-config-salvar">Salvar dados pessoais</button>
    </form>

    <!-- ========================= DADOS PROFISSIONAIS ========================= -->
    <div class="cartao-config-agenda">
        <h2 class="titulo-cartao-config">Dados profissionais</h2>

        <div class="linha-config-dupla">
            <div class="campo-config">
                <label>CRM</label>
                <input type="text" value="<?php echo htmlspecialchars($medico['crm'], ENT_QUOTES, 'UTF-8'); ?>" disabled>
            </div>
            <div class="campo-config">
                <label>CPF</label>
                <input type="text" value="<?php echo htmlspecialchars($medico['cpf'], ENT_QUOTES, 'UTF-8'); ?>" disabled>
            </div>
        </div>

        <div class="linha-config-dupla">
            <div class="campo-config">
                <label>Código do médico</label>
                <input type="text" value="<?php echo htmlspecialchars($medico['uid'], ENT_QUOTES, 'UTF-8'); ?>" disabled>
            </div>
            <div class="campo-config">
                <label>Médico(a) desde</label>
                <input type="text" value="<?php echo htmlspecialchars($dataInicioFormatada, ENT_QUOTES, 'UTF-8'); ?>" disabled>
            </div>
        </div>

        <p class="aviso-recuperacao">
            CRM, CPF e código do médico só podem ser alterados pela gestão da clínica.
            Fale com o setor administrativo se precisar corrigir algo.
        </p>

        <h3>Agenda profissional</h3>
        <div class="linha-config-dupla">
            <div class="campo-config">
                <label>Dias de atendimento</label>
                <input type="text" value="<?php echo htmlspecialchars($diasTrabalhoTexto, ENT_QUOTES, 'UTF-8'); ?>" disabled>
            </div>
            <div class="campo-config">
                <label>Horário de atendimento</label>
                <input type="text" value="<?php echo htmlspecialchars(substr((string)$medico['hora_inicio'], 0, 5) . ' às ' . substr((string)$medico['hora_fim'], 0, 5), ENT_QUOTES, 'UTF-8'); ?>" disabled>
            </div>
        </div>
        <p class="aviso-recuperacao">
            Consultas: <?php echo (int)$medico['duracao_consulta']; ?> min; intervalo entre consultas: <?php echo (int)$medico['intervalo_consulta']; ?> min.
            Para alterar a agenda, fale com a gestão da clínica.
        </p>
    </div>

    <!-- ========================= TROCAR SENHA ========================= -->
    <form method="post" action="actions/trocar-senha.php" class="cartao-config-agenda">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
        <h2 class="titulo-cartao-config">Alterar senha</h2>

        <div class="campo-config">
            <label for="senhaAtual">Senha atual</label>
            <input type="password" id="senhaAtual" name="senha_atual" placeholder="Digite sua senha atual" required>
        </div>

        <div class="linha-config-dupla">
            <div class="campo-config">
                <label for="senhaNova">Nova senha</label>
                <input type="password" id="senhaNova" name="senha_nova" placeholder="Digite a nova senha" required minlength="6" maxlength="72">
            </div>
            <div class="campo-config">
                <label for="senhaNovaConfirmar">Confirmar nova senha</label>
                <input type="password" id="senhaNovaConfirmar" name="senha_nova_confirmar" placeholder="Repita a nova senha" required minlength="6" maxlength="72">
            </div>
        </div>

        <p class="mensagem-erro-etapa" id="mensagemErroSenhaConta"></p>

        <button type="submit" class="botao-entrar botao-config-salvar" onclick="return validarNovaSenha()">Atualizar senha</button>
    </form>

</div>
<?php endif; ?>

<script>
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