<?php
// medico/atendimento.php
// Menu de atendimento — abre quando o médico clica em "Atender" na agenda,
// e pergunta o que ele quer fazer com aquele paciente.

session_start();
require_once '../../conexao.php';

// Validação de sessão para o perfil de médico
if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'medico') {
    header("Location: ../../auth/login.php");
    exit();
}

$medicoId   = $_SESSION['usuario_id'];
$consultaId = $_GET['consulta'] ?? null;
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!$consultaId || !ctype_digit((string)$consultaId)) {
    header("Location: agenda.php");
    exit();
}

// ================= BUSCANDO DADOS REAIS DA CONSULTA/PACIENTE =================
try {
    $stmt = $pdo->prepare("
        SELECT 
            c.id AS id_consulta, c.tipo_atendimento, c.status,
            p.id AS id_paciente, p.nome, p.data_nasc,
            p.status_convenio, p.plano_convenio,
            conv.nome AS convenio_nome,
            esp.nome AS especialidade
        FROM consultas c
        INNER JOIN paciente p ON c.id_paciente = p.id
        LEFT JOIN convenios conv ON conv.id = COALESCE(c.id_convenio, c.id_convenios, p.id_convenio)
        LEFT JOIN especialidade esp ON c.id_especialidade = esp.id
        WHERE c.id = ? AND c.id_medico = ?
        LIMIT 1
    ");
    $stmt->execute([$consultaId, $medicoId]);
    $dados = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$dados) {
        header("Location: agenda.php");
        exit();
    }

    $idade = null;
    if (!empty($dados['data_nasc'])) {
        $nascimento = new DateTime($dados['data_nasc']);
        $hoje = new DateTime();
        $idade = $hoje->diff($nascimento)->y;
    }

    $convenioExibicao = 'Particular';
    if (($dados['status_convenio'] ?? '') === 'ativo' && !empty($dados['convenio_nome'])) {
        $convenioExibicao = $dados['convenio_nome'];
        if (!empty($dados['plano_convenio'])) {
            $convenioExibicao .= ' — ' . $dados['plano_convenio'];
        }
    }

    $paciente = [
        'id'            => $dados['id_paciente'],
        'nome'          => $dados['nome'],
        'idade'         => $idade,
        'convenio'      => $convenioExibicao,
        'especialidade' => $dados['especialidade'] ?? 'Não definida',
    ];

    $consultaTipo = $dados['tipo_atendimento'];

} catch (PDOException $e) {
    error_log('Erro ao carregar atendimento médico: ' . $e->getMessage());
    header("Location: agenda.php?erro=carregamento");
    exit();
}

$ehTeleconsulta = $consultaTipo === 'Teleconsulta';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atender Paciente - Convelum Saúde</title>

    <link href="../../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/responsivo.css">
</head>
<body>

<!-- ========================= TOPO DO PAINEL ========================= -->
<div class="topo-painel">
    <div class="topo-painel-marca">
        <a href="../../index.php">
        <img src="../../imgs/logofrase.png" alt="Convelum Saúde">
        </a>
    </div>
    <div class="topo-painel-perfil">
        <a href="agenda.php" class="topo-painel-sair">
            <i class="bi bi-arrow-left"></i> Voltar à agenda
        </a>
    </div>
</div>

<!-- ========================= CABEÇALHO DO PACIENTE ========================= -->
<div class="cabecalho-paciente">
    <div class="cabecalho-paciente-avatar">
        <i class="bi bi-person-fill"></i>
    </div>

    <?php if (isset($_GET['erro'])): ?>
        <div class="alert alert-danger mx-auto mt-3" role="alert" style="max-width: 900px;">
            Não foi possível carregar ou salvar o atendimento. Confira os dados e tente novamente.
        </div>
    <?php endif; ?>
    <div class="cabecalho-paciente-info">
        <p class="cabecalho-paciente-nome"><?php echo htmlspecialchars($paciente['nome']); ?></p>
        <p class="cabecalho-paciente-detalhes">
            <?php echo $paciente['idade'] !== null ? $paciente['idade'] . ' anos · ' : ''; ?><?php echo htmlspecialchars($paciente['convenio']); ?>
        </p>
        <p class="cabecalho-paciente-motivo"><?php echo htmlspecialchars($paciente['especialidade']); ?></p>
    </div>
</div>

<!-- ========================= O QUE VOCÊ QUER FAZER? ========================= -->
<div class="secao-acoes-painel secao-acoes-painel-estreita">
    <br><br>
    <h2 class="titulo-secao-painel titulo-menu-atendimento">O que você quer fazer com esse paciente?</h2>

    <div class="grid-acoes-painel">

        <?php if ($ehTeleconsulta): ?>
            <a href="teleconsulta.php?consulta=<?php echo $consultaId; ?>" class="card-acao-painel">
                <i class="bi bi-camera-video icone-acao-painel"></i>
                <p class="titulo-acao-painel">Iniciar teleconsulta</p>
            </a>
        <?php endif; ?>

        <a href="evolucao.php?id_consulta=<?php echo $consultaId; ?>" class="card-acao-painel">
    <i class="bi bi-journal-medical icone-acao-painel"></i>
    <p class="titulo-acao-painel">Registrar evolução</p>
</a>

<a href="receita.php?id_consulta=<?php echo $consultaId; ?>" class="card-acao-painel">
    <i class="bi bi-prescription2 icone-acao-painel"></i>
    <p class="titulo-acao-painel">Emitir receita</p>
</a>

<a href="exame.php?id_consulta=<?php echo $consultaId; ?>" class="card-acao-painel">
    <i class="bi bi-clipboard2-pulse icone-acao-painel"></i>
    <p class="titulo-acao-painel">Solicitar exame</p>
</a>

<a href="atestado.php?id_consulta=<?php echo $consultaId; ?>" class="card-acao-painel">
    <i class="bi bi-file-earmark-medical icone-acao-painel"></i>
    <p class="titulo-acao-painel">Emitir atestado</p>
</a>

    </div>
</div>

</body>
</html>