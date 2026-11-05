<?php
// medico/atendimento/exame.php
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
    'dados' => 'Confira os exames adicionados e os limites dos campos.',
    'falha' => 'Não foi possível solicitar os exames. Tente novamente.',
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
               p.id AS paciente_id, p.nome, p.data_nasc,
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
    error_log('Erro ao carregar tela de exames: ' . $e->getMessage());
    header('Location: atendimento.php?consulta=' . $idConsulta . '&erro=carregamento');
    exit();
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitar Exame - Convelum Saúde</title>

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
    <a href="exame.php?id_consulta=<?php echo $idConsulta; ?>" class="atalho-atendimento ativo">
        <i class="bi bi-clipboard2-pulse"></i> Exame
    </a>
    <a href="atestado.php?id_consulta=<?php echo $idConsulta; ?>" class="atalho-atendimento">
        <i class="bi bi-file-earmark-medical"></i> Atestado
    </a>
</div>

<?php if (isset($_GET['sucesso'])): ?>
    <div class="container mt-3" style="max-width: 900px;">
        <div class="alert alert-success" role="alert" id="alertaSucesso" style="display: flex; align-items: center; justify-content: space-between;">
            <span><i class="bi bi-check-circle-fill me-2"></i> Exame(s) solicitado(s) com sucesso!</span>
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
            <span><i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($mensagensErro[(string)$_GET['erro']] ?? 'Adicione pelo menos um exame antes de solicitar.', ENT_QUOTES, 'UTF-8'); ?></span>
            <button type="button" onclick="document.getElementById('alertaErro').remove()" style="background:none;border:none;color:inherit;font-size:1.2rem;cursor:pointer;line-height:1;">&times;</button>
        </div>
    </div>
<?php endif; ?>

<div class="secao-config-agenda">

    <form method="post" action="actions/salvar-exame.php" id="formExame" class="cartao-config-agenda">
        <input type="hidden" name="id_consulta" value="<?php echo $idConsulta; ?>">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

        <h2 class="titulo-cartao-config">Solicitar exames</h2>

        <!-- ========================= EXAMES ADICIONADOS ========================= -->
        <div class="lista-medicamentos" id="listaExames"></div>

        <p class="medicamentos-vazio" id="examesVazio">Nenhum exame adicionado ainda.</p>

        <!-- ========================= FORMULÁRIO DE NOVO EXAME ========================= -->
        <div class="cartao-novo-medicamento">
            <div class="campo-config">
                <label for="nomeExame">Exame</label>
                <input type="text" id="nomeExame" maxlength="255" placeholder="Ex: Hemograma completo, Eletrocardiograma...">
            </div>

            <div class="linha-config-dupla">
                <div class="campo-config">
                    <label for="urgenciaExame">Urgência</label>
                    <select id="urgenciaExame">
                        <option value="Rotina">Rotina</option>
                        <option value="Urgente">Urgente</option>
                    </select>
                </div>
                <div class="campo-config">
                    <label for="jejumExame">Preparo necessário</label>
                    <select id="jejumExame">
                        <option value="Nenhum">Nenhum</option>
                        <option value="Jejum de 8h">Jejum de 8h</option>
                        <option value="Jejum de 12h">Jejum de 12h</option>
                        <option value="Outro">Outro (ver observações)</option>
                    </select>
                </div>
            </div>

            <div class="campo-config">
                <label for="justificativaExame">Justificativa clínica</label>
                <input type="text" id="justificativaExame" maxlength="255" placeholder="Motivo da solicitação">
            </div>

            <button type="button" class="botao-config-adicionar botao-adicionar-medicamento" onclick="adicionarExame()">
                <i class="bi bi-plus-lg"></i> Adicionar exame
            </button>
        </div>

        <div class="campo-config campo-observacoes-receita">
            <label for="observacoesExame">Observações gerais</label>
            <textarea class="campo-texto" id="observacoesExame" name="observacoes" rows="3" maxlength="10000" placeholder="Instruções adicionais pro paciente ou pro laboratório..."></textarea>
        </div>

        <!-- Preenchido via JS com todos os exames adicionados, em JSON -->
        <input type="hidden" name="exames" id="examesJson" value="[]">

        <p class="mensagem-erro-etapa" id="mensagemErroExame"></p>

        <button type="submit" class="botao-entrar botao-config-salvar" onclick="return validarExames()">
            Solicitar exames
        </button>
    </form>

</div>

<script>
const exames = [];

function renderizarExames() {
    const lista = document.getElementById('listaExames');
    const vazio = document.getElementById('examesVazio');

    lista.innerHTML = '';
    vazio.style.display = exames.length === 0 ? 'block' : 'none';

    exames.forEach(function (exame, indice) {
        const linha = document.createElement('div');
        linha.className = 'registro-config registro-medicamento';

        const urgenciaClasse = exame.urgencia === 'Urgente' ? 'selo-urgente' : 'selo-rotina';

        const detalhes = document.createElement('div');
        const titulo = document.createElement('span');
        titulo.className = 'registro-config-tipo';
        titulo.textContent = exame.nome;
        const urgenciaTexto = document.createElement('span');
        urgenciaTexto.className = urgenciaClasse;
        urgenciaTexto.textContent = exame.urgencia;
        const descricao = document.createElement('p');
        descricao.className = 'registro-medicamento-detalhes';
        descricao.textContent = exame.preparo + (exame.justificativa ? ' · ' + exame.justificativa : '');
        const remover = document.createElement('button');
        remover.type = 'button';
        remover.className = 'registro-config-remover';
        remover.title = 'Remover';
        remover.innerHTML = '<i class="bi bi-trash"></i>';
        remover.addEventListener('click', function () { removerExame(indice); });
        detalhes.append(titulo, urgenciaTexto, descricao);
        linha.append(detalhes, remover);
        lista.appendChild(linha);
    });

    document.getElementById('examesJson').value = JSON.stringify(exames);
}

function adicionarExame() {
    const nome = document.getElementById('nomeExame').value.trim();
    const urgencia = document.getElementById('urgenciaExame').value;
    const preparo = document.getElementById('jejumExame').value;
    const justificativa = document.getElementById('justificativaExame').value.trim();

    if (!nome) {
        alert('Digite o nome do exame.');
        return;
    }

    exames.push({ nome, urgencia, preparo, justificativa });
    renderizarExames();

    document.getElementById('nomeExame').value = '';
    document.getElementById('justificativaExame').value = '';
    document.getElementById('nomeExame').focus();
}

function removerExame(indice) {
    exames.splice(indice, 1);
    renderizarExames();
}

function validarExames() {
    const mensagemErro = document.getElementById('mensagemErroExame');
    mensagemErro.textContent = '';

    if (exames.length === 0) {
        mensagemErro.textContent = 'Adicione pelo menos um exame antes de solicitar.';
        return false;
    }

    return true;
}
</script>

</body>
</html>