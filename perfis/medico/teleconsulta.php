<?php
session_start();
require_once '../../conexao.php';

if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'medico') {
    header('Location: ../../auth/login.php');
    exit();
}

$medicoId = (int)$_SESSION['usuario_id'];
$consultaId = filter_input(INPUT_GET, 'consulta', FILTER_VALIDATE_INT);
if (!$consultaId || $consultaId < 1) {
    header('Location: agenda.php?erro=consulta');
    exit();
}

try {
    $stmt = $pdo->prepare("
        SELECT c.id AS id_consulta, c.data_hora, c.tipo_atendimento, c.status,
               p.id AS id_paciente, p.nome, p.data_nasc, p.email, p.celular,
               p.status_convenio, p.plano_convenio,
               conv.nome AS convenio_nome,
               esp.nome AS especialidade
        FROM consultas c
        INNER JOIN paciente p ON p.id = c.id_paciente
        LEFT JOIN convenios conv
            ON conv.id = COALESCE(c.id_convenio, c.id_convenios, p.id_convenio)
        LEFT JOIN especialidade esp ON esp.id = c.id_especialidade
        WHERE c.id = ? AND c.id_medico = ?
        LIMIT 1
    ");
    $stmt->execute([$consultaId, $medicoId]);
    $dados = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Erro ao carregar teleconsulta: ' . $e->getMessage());
    header('Location: agenda.php?erro=carregamento');
    exit();
}

if (!$dados) {
    header('Location: agenda.php?erro=consulta');
    exit();
}

$consultaCancelada = $dados['status'] === 'cancelada';
$consultaRealizada = $dados['status'] === 'realizada';
$naoEhTeleconsulta = $dados['tipo_atendimento'] !== 'Teleconsulta';
$podeIniciar = !$consultaCancelada && !$consultaRealizada && !$naoEhTeleconsulta;
$idade = null;
if (!empty($dados['data_nasc'])) {
    try {
        $idade = (new DateTimeImmutable($dados['data_nasc']))->diff(new DateTimeImmutable('today'))->y;
    } catch (Exception $e) {
        error_log('Data de nascimento inválida ao carregar teleconsulta: ' . $e->getMessage());
    }
}

$convenioExibicao = 'Particular';
if (($dados['status_convenio'] ?? '') === 'ativo' && !empty($dados['convenio_nome'])) {
    $convenioExibicao = (string)$dados['convenio_nome'];
    if (!empty($dados['plano_convenio'])) {
        $convenioExibicao .= ' — ' . $dados['plano_convenio'];
    }
}
$dataHoraConsulta = new DateTimeImmutable($dados['data_hora']);
$dataHoraFormatada = $dataHoraConsulta->format('d/m/Y \à\s H:i');
$nomePacienteJs = json_encode((string)$dados['nome'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$emailPacienteJs = json_encode((string)($dados['email'] ?? ''), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$dataHoraJs = json_encode($dataHoraFormatada, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teleconsulta - Convelum Saúde</title>

    <link href="../../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/responsivo.css">
</head>
<body>

<div class="topo-painel">
    <div class="topo-painel-marca">
        <a href="../../index.php">
            <img src="../../imgs/logofrase.png" alt="Convelum Saúde">
        </a>
    </div>
    <div class="topo-painel-perfil">
        <a href="atendimento.php?consulta=<?php echo (int)$consultaId; ?>" class="topo-painel-sair">
            <i class="bi bi-arrow-left"></i> Voltar ao atendimento
        </a>
    </div>
</div>

<div class="cabecalho-paciente">
    <div class="cabecalho-paciente-avatar">
        <i class="bi bi-person-fill"></i>
    </div>
    <div class="cabecalho-paciente-info">
        <p class="cabecalho-paciente-nome"><?php echo htmlspecialchars((string)$dados['nome'], ENT_QUOTES, 'UTF-8'); ?></p>
        <p class="cabecalho-paciente-detalhes">
            <?php echo $idade !== null ? $idade . ' anos · ' : ''; ?><?php echo htmlspecialchars($convenioExibicao, ENT_QUOTES, 'UTF-8'); ?>
        </p>
        <p class="cabecalho-paciente-motivo">
            <?php echo htmlspecialchars((string)($dados['especialidade'] ?: 'Atendimento médico'), ENT_QUOTES, 'UTF-8'); ?>
            · <?php echo htmlspecialchars($dataHoraFormatada, ENT_QUOTES, 'UTF-8'); ?>
        </p>
    </div>
</div>

<div class="secao-acoes-painel secao-acoes-painel-estreita">
    <?php if ($consultaCancelada): ?>
        <div class="alert alert-danger" role="alert">Esta consulta foi cancelada. Não é possível iniciar a teleconsulta.</div>
    <?php elseif ($consultaRealizada): ?>
        <div class="alert alert-info" role="alert">Esta consulta já foi marcada como realizada. A sala não pode ser iniciada por esta tela.</div>
    <?php elseif ($naoEhTeleconsulta): ?>
        <div class="alert alert-warning" role="alert">
            Esta consulta está marcada como <strong><?php echo htmlspecialchars((string)$dados['tipo_atendimento'], ENT_QUOTES, 'UTF-8'); ?></strong>, não como teleconsulta.
        </div>
    <?php endif; ?>

    <div class="cartao-config-agenda">
        <h2 class="titulo-cartao-config">Sala de teleconsulta</h2>
        <p class="aviso-recuperacao">
            Abra o Google Meet para criar a sala. Depois, cole o link da reunião aqui para preparar o convite.
            O Google Meet exige que você esteja conectado à sua conta Google.
        </p>

        <?php if ($podeIniciar): ?>
            <a href="https://meet.google.com/new" target="_blank" rel="noopener noreferrer"
               class="botao-entrar" style="display:inline-flex;align-items:center;gap:.5rem;text-decoration:none;margin-bottom:1.25rem;">
                <i class="bi bi-camera-video-fill"></i> Criar sala no Google Meet
            </a>

            <div class="campo-config">
                <label for="linkMeet">Link da chamada do Google Meet</label>
                <input type="url" id="linkMeet" inputmode="url" autocomplete="url"
                       placeholder="https://meet.google.com/xxx-xxxx-xxx"
                       aria-describedby="erroLinkMeet">
                <small id="erroLinkMeet" class="text-danger" role="alert" style="display:none;"></small>
            </div>

            <button type="button" class="botao-entrar botao-config-salvar" id="botaoPrepararConvite">
                Preparar convite
            </button>
        <?php else: ?>
            <p class="text-muted">Não é possível criar um convite para esta consulta.</p>
        <?php endif; ?>
    </div>

    <?php if ($podeIniciar): ?>
        <div class="cartao-config-agenda" id="cartaoMensagem" hidden>
            <h2 class="titulo-cartao-config">Convite para o paciente</h2>

            <div class="campo-config">
                <label for="mensagemPaciente">Mensagem</label>
                <textarea class="campo-texto" id="mensagemPaciente" rows="7" readonly></textarea>
            </div>

            <div class="linha-config-dupla">
                <button type="button" class="botao-entrar botao-config-salvar" id="botaoCopiarMensagem">
                    <i class="bi bi-clipboard-fill"></i> Copiar mensagem
                </button>
                <a id="linkEnviarEmail" href="#" class="botao-entrar botao-config-salvar"
                   style="text-align:center;text-decoration:none;" hidden>
                    <i class="bi bi-envelope-fill"></i> Abrir e-mail
                </a>
            </div>
            <p class="mt-2 mb-0" id="estadoConvite" role="status" aria-live="polite"></p>
        </div>
    <?php endif; ?>

    <div class="cartao-config-agenda">
        <h2 class="titulo-cartao-config">Ações do atendimento</h2>
        <div class="atalhos-atendimento atalhos-atendimento-coluna">
            <a href="evolucao.php?id_consulta=<?php echo (int)$consultaId; ?>" class="atalho-atendimento">
                <i class="bi bi-journal-medical"></i> Registrar evolução
            </a>
            <a href="receita.php?id_consulta=<?php echo (int)$consultaId; ?>" class="atalho-atendimento">
                <i class="bi bi-prescription2"></i> Emitir receita
            </a>
            <a href="exame.php?id_consulta=<?php echo (int)$consultaId; ?>" class="atalho-atendimento">
                <i class="bi bi-clipboard2-pulse"></i> Solicitar exame
            </a>
            <a href="atestado.php?id_consulta=<?php echo (int)$consultaId; ?>" class="atalho-atendimento">
                <i class="bi bi-file-earmark-medical"></i> Emitir atestado
            </a>
        </div>
    </div>
</div>

<?php if ($podeIniciar): ?>
<script>
const nomePaciente = <?php echo $nomePacienteJs; ?>;
const emailPaciente = <?php echo $emailPacienteJs; ?>;
const dataHoraConsulta = <?php echo $dataHoraJs; ?>;
const linkMeet = document.getElementById('linkMeet');
const erroLinkMeet = document.getElementById('erroLinkMeet');
const cartaoMensagem = document.getElementById('cartaoMensagem');
const campoMensagem = document.getElementById('mensagemPaciente');
const estadoConvite = document.getElementById('estadoConvite');
const linkEnviarEmail = document.getElementById('linkEnviarEmail');

function validarLinkMeet(valor) {
    try {
        const url = new URL(valor);
        return url.protocol === 'https:'
            && url.hostname.toLowerCase() === 'meet.google.com'
            && /^\/[a-z]{3}-[a-z]{4}-[a-z]{3}\/?$/i.test(url.pathname);
    } catch (erro) {
        return false;
    }
}

function prepararConvite() {
    const link = linkMeet.value.trim();
    if (!validarLinkMeet(link)) {
        erroLinkMeet.textContent = 'Cole um link válido do Google Meet no formato https://meet.google.com/xxx-xxxx-xxx.';
        erroLinkMeet.style.display = 'block';
        linkMeet.setAttribute('aria-invalid', 'true');
        linkMeet.focus();
        return;
    }

    erroLinkMeet.textContent = '';
    erroLinkMeet.style.display = 'none';
    linkMeet.removeAttribute('aria-invalid');

    const mensagem = `Olá, ${nomePaciente}!\n\n`
        + `Sua teleconsulta na Convelum Saúde está marcada para ${dataHoraConsulta}.\n`
        + `Acesse este link no horário combinado para entrar na chamada:\n\n`
        + `${link}\n\n`
        + 'Recomendamos entrar alguns minutos antes e testar câmera e microfone.\n'
        + 'Qualquer dúvida, estamos à disposição.';

    campoMensagem.value = mensagem;
    cartaoMensagem.hidden = false;
    estadoConvite.textContent = '';

    if (emailPaciente) {
        const assunto = encodeURIComponent('Link da sua teleconsulta - Convelum Saúde');
        const corpo = encodeURIComponent(mensagem);
        linkEnviarEmail.href = `mailto:${encodeURIComponent(emailPaciente)}?subject=${assunto}&body=${corpo}`;
        linkEnviarEmail.hidden = false;
    } else {
        linkEnviarEmail.removeAttribute('href');
        linkEnviarEmail.hidden = true;
        estadoConvite.textContent = 'O paciente não tem e-mail cadastrado. Copie a mensagem e envie por outro canal seguro.';
    }

    cartaoMensagem.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

document.getElementById('botaoPrepararConvite').addEventListener('click', prepararConvite);
document.getElementById('botaoCopiarMensagem').addEventListener('click', async function () {
    try {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(campoMensagem.value);
        } else {
            campoMensagem.focus();
            campoMensagem.select();
            if (!document.execCommand('copy')) {
                throw new Error('Clipboard copy command failed');
            }
        }
        estadoConvite.textContent = 'Mensagem copiada.';
    } catch (erro) {
        estadoConvite.textContent = 'Não foi possível copiar automaticamente. Selecione e copie o texto da mensagem.';
        campoMensagem.focus();
        campoMensagem.select();
    }
});
</script>
<?php endif; ?>

</body>
</html>
