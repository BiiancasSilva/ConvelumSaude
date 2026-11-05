<?php
// medico/atendimento/atestado.php
session_start();
require_once '../../conexao.php';

if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'medico') {
    header("Location: ../../auth/login.php");
    exit();
}

$idMedico   = $_SESSION['usuario_id'];
$idConsulta = isset($_GET['id_consulta']) ? (int)$_GET['id_consulta'] : 0;
$mensagensErro = [
    'csrf' => 'Sua sessão expirou ou o formulário não pôde ser validado. Atualize a página e tente novamente.',
    'consulta' => 'A consulta não foi encontrada ou não pertence a este médico.',
    'dados' => 'Confira o tipo de atestado, a data, os dias e os limites dos campos.',
    'falha' => 'Não foi possível emitir o atestado. Tente novamente.',
];
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!$idConsulta) {
    header('Location: agenda.php?erro=consulta');
    exit();
}

// ========================= BUSCA DADOS DO PACIENTE / CONSULTA =========================
try {
    $stmtConsulta = $pdo->prepare("
        SELECT c.id AS consulta_id,
               p.id AS paciente_id, p.nome, p.data_nasc, p.id_convenio,
               e.nome AS especialidade_nome,
               conv.nome AS convenio_nome
        FROM consultas c
        JOIN paciente p ON c.id_paciente = p.id
        LEFT JOIN especialidade e ON c.id_especialidade = e.id
        LEFT JOIN convenios conv ON conv.id = COALESCE(c.id_convenio, c.id_convenios, p.id_convenio)
        WHERE c.id = ? AND c.id_medico = ?
    ");
    $stmtConsulta->execute([$idConsulta, $idMedico]);
    $dadosConsulta = $stmtConsulta->fetch(PDO::FETCH_ASSOC);

    if (!$dadosConsulta) {
        header('Location: agenda.php?erro=consulta');
        exit();
    }

    $idade = !empty($dadosConsulta['data_nasc'])
        ? (new DateTimeImmutable($dadosConsulta['data_nasc']))->diff(new DateTimeImmutable('today'))->y
        : null;

    $paciente = [
        'id'       => $dadosConsulta['paciente_id'],
        'nome'     => $dadosConsulta['nome'],
        'idade'    => $idade,
        'convenio' => $dadosConsulta['convenio_nome'] ?: 'Particular',
        'motivo'   => $dadosConsulta['especialidade_nome']
            ? 'Consulta — ' . $dadosConsulta['especialidade_nome']
            : 'Atendimento médico',
    ];

} catch (PDOException $e) {
    error_log('Erro ao carregar tela de atestado: ' . $e->getMessage());
    header('Location: atendimento.php?consulta=' . $idConsulta . '&erro=carregamento');
    exit();
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emitir Atestado - Convelum Saúde</title>

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
        <a href="atendimento.php?consulta=<?php echo $idConsulta; ?>" class="topo-painel-sair">
            <i class="bi bi-arrow-left"></i> Voltar à atendimento
        </a>
    </div>
</div>

<!-- ========================= CABEÇALHO DO PACIENTE ========================= -->
<div class="cabecalho-paciente">
    <div class="cabecalho-paciente-avatar">
        <i class="bi bi-person-fill"></i>
    </div>
    <div class="cabecalho-paciente-info">
        <p class="cabecalho-paciente-nome"><?php echo htmlspecialchars($paciente['nome']); ?></p>
        <p class="cabecalho-paciente-detalhes">
            <?php echo $paciente['idade'] !== null ? $paciente['idade'] . ' anos · ' : ''; ?><?php echo htmlspecialchars($paciente['convenio']); ?>
        </p>
        <p class="cabecalho-paciente-motivo"><?php echo htmlspecialchars($paciente['motivo']); ?></p>
    </div>
</div>

<!-- ========================= AÇÕES DO ATENDIMENTO ========================= -->
<div class="atalhos-atendimento">
    <a href="evolucao.php?id_consulta=<?php echo $idConsulta; ?>" class="atalho-atendimento">
        <i class="bi bi-journal-medical"></i> Evolução
    </a>
    <a href="receita.php?id_consulta=<?php echo $idConsulta; ?>" class="atalho-atendimento">
        <i class="bi bi-prescription2"></i> Receita
    </a>
    <a href="exame.php?id_consulta=<?php echo $idConsulta; ?>" class="atalho-atendimento">
        <i class="bi bi-clipboard2-pulse"></i> Exame
    </a>
    <a href="atestado.php?id_consulta=<?php echo $idConsulta; ?>" class="atalho-atendimento ativo">
        <i class="bi bi-file-earmark-medical"></i> Atestado
    </a>
</div>

<?php if (isset($_GET['sucesso'])): ?>
    <div class="container mt-3" style="max-width: 900px;">
        <div class="alert alert-success" role="alert" id="alertaSucesso" style="display: flex; align-items: center; justify-content: space-between;">
            <span><i class="bi bi-check-circle-fill me-2"></i> Atestado emitido com sucesso!</span>
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
            <span><i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($mensagensErro[(string)$_GET['erro']] ?? 'Não foi possível emitir o atestado. Tente novamente.', ENT_QUOTES, 'UTF-8'); ?></span>
            <button type="button" onclick="document.getElementById('alertaErro').remove()" style="background:none;border:none;color:inherit;font-size:1.2rem;cursor:pointer;line-height:1;">&times;</button>
        </div>
    </div>
<?php endif; ?>
<div class="secao-config-agenda">

    <form method="post" action="actions/salvar-atestado.php" id="formAtestado" class="cartao-config-agenda">
        <input type="hidden" name="id_consulta" value="<?php echo $idConsulta; ?>">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

        <h2 class="titulo-cartao-config">Emitir atestado médico</h2>

        <div class="campo-config">
            <label for="tipoAtestado">Tipo de atestado</label>
            <select id="tipoAtestado" name="tipo" onchange="alternarCampoDias()">
                <option value="afastamento">Afastamento do trabalho/estudo</option>
                <option value="comparecimento">Comparecimento (sem afastamento)</option>
                <option value="repouso">Repouso domiciliar</option>
                <option value="acompanhante">Acompanhante</option>
            </select>
        </div>

        <div class="linha-config-dupla">
            <div class="campo-config">
                <label for="dataInicio">Data de início</label>
                <input type="date" id="dataInicio" name="data_inicio" required>
            </div>
            <div class="campo-config" id="campoDiasAfastamento">
                <label for="diasAfastamento">Dias de afastamento</label>
                <input type="number" id="diasAfastamento" name="dias" min="1" max="365" placeholder="Ex: 3" required>
            </div>
        </div>

        <div class="campo-config">
            <label for="cid">
                CID-10 <span class="campo-opcional">(opcional — some do atestado se deixado em branco)</span>
            </label>
            <input type="text" id="cid" name="cid" maxlength="100" placeholder="Ex: J06.9 - Infecção respiratória aguda">
        </div>

        <div class="campo-config">
            <label for="observacoesAtestado">Observações (aparecem no atestado)</label>
            <textarea class="campo-texto" id="observacoesAtestado" name="observacoes" rows="3" maxlength="10000" placeholder="Texto adicional, se necessário..."></textarea>
        </div>

        <button type="submit" class="botao-entrar botao-config-salvar">Emitir atestado</button>
    </form>

    <!-- ========================= PRÉVIA DO ATESTADO ========================= -->
    <div class="cartao-config-agenda">
        <h2 class="titulo-cartao-config">Prévia</h2>
        <div class="previa-atestado" id="previaAtestado">
            Atesto, para os devidos fins, que o(a) paciente
            <strong><?php echo htmlspecialchars($paciente['nome']); ?></strong>
            esteve sob meus cuidados médicos, necessitando de afastamento
            de suas atividades por um período a definir, a partir da data informada.
        </div>
    </div>

</div>

<script>
const nomePaciente = <?php echo json_encode($paciente['nome'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
function escaparHtml(valor) {
    return String(valor).replace(/[&<>"']/g, function (caractere) {
        return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[caractere];
    });
}

const textosPorTipo = {
    afastamento: function (dias) {
        return 'Atesto, para os devidos fins, que o(a) paciente <strong>' + escaparHtml(nomePaciente) +
            '</strong> esteve sob meus cuidados médicos, necessitando de afastamento de suas ' +
            'atividades laborais/escolares por <strong>' + (dias || '—') + ' dia(s)</strong>, a partir da data informada.';
    },
    comparecimento: function () {
        return 'Atesto, para os devidos fins, que o(a) paciente <strong>' + escaparHtml(nomePaciente) +
            '</strong> compareceu a esta unidade de saúde na data informada para consulta/atendimento médico.';
    },
    repouso: function (dias) {
        return 'Atesto, para os devidos fins, que o(a) paciente <strong>' + escaparHtml(nomePaciente) +
            '</strong> deve permanecer em repouso domiciliar por <strong>' + (dias || '—') + ' dia(s)</strong>, a partir da data informada.';
    },
    acompanhante: function () {
        return 'Atesto, para os devidos fins, que o(a) paciente <strong>' + escaparHtml(nomePaciente) +
            '</strong> necessitou de acompanhante durante o período de atendimento na data informada.';
    }
};

function alternarCampoDias() {
    const tipo = document.getElementById('tipoAtestado').value;
    const campoDias = document.getElementById('campoDiasAfastamento');
    const inputDias = document.getElementById('diasAfastamento');

    const precisaDeDias = tipo === 'afastamento' || tipo === 'repouso';
    campoDias.style.display = precisaDeDias ? 'block' : 'none';
    inputDias.required = precisaDeDias;

    atualizarPrevia();
}

function atualizarPrevia() {
    const tipo = document.getElementById('tipoAtestado').value;
    const dias = document.getElementById('diasAfastamento').value;
    document.getElementById('previaAtestado').innerHTML = textosPorTipo[tipo](dias);
}

document.getElementById('diasAfastamento').addEventListener('input', atualizarPrevia);
alternarCampoDias();
</script>

</body>
</html>