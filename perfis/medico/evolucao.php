<?php
// medico/atendimento/evolucao.php  (ajuste o caminho conforme sua pasta real)
session_start();
require_once '../../conexao.php';

if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'medico') {
    header("Location: ../../auth/login.php");
    exit();
}

$idMedico   = $_SESSION['usuario_id'];
$idConsulta = isset($_GET['id_consulta']) ? (int)$_GET['id_consulta'] : 0;
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
        SELECT c.id AS consulta_id, c.data_hora, c.tipo_atendimento,
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
            : ucfirst($dadosConsulta['tipo_atendimento']),
    ];

} catch (PDOException $e) {
    error_log('Erro ao carregar tela de evolução: ' . $e->getMessage());
    header('Location: atendimento.php?consulta=' . $idConsulta . '&erro=carregamento');
    exit();
}

// ========================= HISTÓRICO DE EVOLUÇÕES DESTE PACIENTE =========================
$historicoEvolucoes = [];
$erroHistorico = false;
try {
    $stmtHistorico = $pdo->prepare("
        SELECT c.data_hora, m.nome AS medico_nome, a.evolucao_clinica
        FROM atendimento a
        JOIN consultas c ON a.id_consulta = c.id
        JOIN medico m ON c.id_medico = m.id
        WHERE c.id_paciente = ?
        ORDER BY c.data_hora DESC
    ");
    $stmtHistorico->execute([$paciente['id']]);
    $historicoBanco = $stmtHistorico->fetchAll(PDO::FETCH_ASSOC);

    foreach ($historicoBanco as $row) {
        $historicoEvolucoes[] = [
            'data'   => date('d/m/Y', strtotime($row['data_hora'])),
            'medico' => $row['medico_nome'],
            'resumo' => $row['evolucao_clinica'],
        ];
    }
} catch (PDOException $e) {
    error_log('Erro ao carregar histórico de evolução: ' . $e->getMessage());
    $erroHistorico = true;
    $historicoEvolucoes = [];
}
$mensagensErro = [
    'csrf' => 'Sua sessão expirou ou o formulário não pôde ser validado. Atualize a página e tente novamente.',
    'consulta' => 'A consulta não foi encontrada, foi cancelada ou não pertence a este médico.',
    'campos' => 'Informe a evolução clínica e o diagnóstico para salvar o atendimento.',
    'falha' => 'Não foi possível salvar a evolução. Tente novamente.',
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Evolução - Convelum Saúde</title>

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
    <a href="evolucao.php?id_consulta=<?php echo $idConsulta; ?>" class="atalho-atendimento ativo">
        <i class="bi bi-journal-medical"></i> Evolução
    </a>
    <a href="receita.php?id_consulta=<?php echo $idConsulta; ?>" class="atalho-atendimento">
        <i class="bi bi-prescription2"></i> Receita
    </a>
    <a href="exame.php?id_consulta=<?php echo $idConsulta; ?>" class="atalho-atendimento">
        <i class="bi bi-clipboard2-pulse"></i> Exame
    </a>
    <a href="atestado.php?id_consulta=<?php echo $idConsulta; ?>" class="atalho-atendimento">
        <i class="bi bi-file-earmark-medical"></i> Atestado
    </a>
</div>

<?php if (isset($_GET['sucesso']) && $_GET['sucesso'] == '1'): ?>
    <div class="container mt-3" style="max-width: 900px;">
        <div class="alert alert-success" role="alert" id="alertaSucesso" style="display: flex; align-items: center; justify-content: space-between;">
            <span><i class="bi bi-check-circle-fill me-2"></i> Evolução registrada com sucesso!</span>
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
        <div class="alert alert-danger" role="alert">
            <?php echo htmlspecialchars($mensagensErro[(string)$_GET['erro']] ?? 'Não foi possível salvar a evolução.', ENT_QUOTES, 'UTF-8'); ?>
        </div>
    </div>
<?php endif; ?>

<?php if ($erroHistorico): ?>
    <div class="container mt-3" style="max-width: 900px;">
        <div class="alert alert-warning" role="alert">Não foi possível carregar o histórico de evoluções.</div>
    </div>
<?php endif; ?>

<div class="secao-config-agenda">

    <!-- ========================= FORMULÁRIO DE EVOLUÇÃO ========================= -->
    <form method="post" action="actions/salvar-evolucao.php" class="cartao-config-agenda">
        <h2 class="titulo-cartao-config">Registrar evolução clínica</h2>

        <input type="hidden" name="id_consulta" value="<?php echo $idConsulta; ?>">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

        <div class="campo-config">
            <label for="queixa">Queixa principal</label>
            <textarea class="campo-texto" id="queixa" name="queixa" rows="3" maxlength="10000" placeholder="O que o paciente relata hoje..."></textarea>
        </div>

        <div class="campo-config">
            <label for="exameFisico">Exame físico</label>
            <textarea class="campo-texto" id="exameFisico" name="exame_fisico" rows="3" maxlength="10000" placeholder="Achados do exame físico..."></textarea>
        </div>

        <div class="campo-config">
            <label for="evolucaoClinica">Evolução clínica</label>
            <textarea class="campo-texto" id="evolucaoClinica" name="evolucao_clinica" rows="5" maxlength="10000" placeholder="Descreva a evolução do quadro clínico do paciente..." required></textarea>
        </div>

        <div class="linha-config-dupla">
            <div class="campo-config">
                <label for="diagnostico">Diagnóstico (CID-10)</label>
                <input type="text" id="diagnostico" name="diagnostico" maxlength="10000" placeholder="Ex: I10 - Hipertensão essencial" required>
            </div>
            <div class="campo-config">
                <label for="statusAtendimento">Status do atendimento</label>
                <select id="statusAtendimento" name="status">
                    <option value="em_andamento">Em andamento</option>
                    <option value="finalizado">Finalizado</option>
                    <option value="retorno">Necessita retorno</option>
                </select>
            </div>
        </div>

        <div class="campo-config">
            <label for="conduta">Conduta / Plano terapêutico</label>
            <textarea class="campo-texto" id="conduta" name="conduta" rows="3" maxlength="10000" placeholder="Orientações, próximos passos, encaminhamentos..."></textarea>
        </div>

        <button type="submit" class="botao-entrar botao-config-salvar">Salvar evolução</button>
    </form>

    <!-- ========================= HISTÓRICO DE EVOLUÇÕES ========================= -->
    <div class="cartao-config-agenda">
        <h2 class="titulo-cartao-config">Histórico de evoluções</h2>

        <?php if (empty($historicoEvolucoes)): ?>
            <p class="historico-vazio">Nenhuma evolução registrada ainda pra esse paciente.</p>
        <?php else: ?>
            <div class="lista-config-registros">
                <?php foreach ($historicoEvolucoes as $item): ?>
                    <div class="registro-config registro-evolucao">
                        <div>
                            <span class="registro-config-periodo"><?php echo $item['data']; ?></span>
                            <span class="registro-config-tipo"><?php echo htmlspecialchars($item['medico']); ?></span>
                            <p class="registro-evolucao-resumo"><?php echo htmlspecialchars($item['resumo']); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

</body>
</html>