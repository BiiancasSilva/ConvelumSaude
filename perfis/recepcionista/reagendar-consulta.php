<?php
session_start();
require_once '../../conexao.php';

if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'recepcionista') {
    header('Location: ../../auth/login.php');
    exit();
}

$rawId = $_GET['id'] ?? null;
$consultaId = is_string($rawId)
    ? filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
if (!$consultaId) {
    header('Location: painel.php');
    exit();
}

$csrfToken = $_SESSION['csrf_consultas_detalhes'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_consultas_detalhes'] = $csrfToken;

$mensagens = [
    'dados_invalidos' => 'Confira a data e o horário selecionados.',
    'indisponivel' => 'Esse horário não está mais disponível. Escolha outro.',
    'nao_reagendavel' => 'Esta consulta não pode mais ser reagendada.',
    'erro' => 'Não foi possível reagendar a consulta. Tente novamente.',
    'csrf' => 'Sua sessão expirou. Atualize a página e tente novamente.',
];
$chaveErro = isset($_GET['erro']) && is_string($_GET['erro']) ? $_GET['erro'] : '';
$erro = $mensagens[$chaveErro] ?? '';

try {
    $stmt = $pdo->prepare("
        SELECT c.id, c.data_hora, c.status, c.id_medico, c.id_paciente,
               p.nome AS paciente, m.nome AS medico
        FROM consultas c
        INNER JOIN paciente p ON p.id = c.id_paciente
        INNER JOIN medico m ON m.id = c.id_medico
        WHERE c.id = ?
        LIMIT 1
    ");
    $stmt->execute([$consultaId]);
    $consulta = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Falha ao carregar consulta para reagendamento #' . $consultaId . ': ' . $e->getMessage());
    http_response_code(500);
    exit('Não foi possível carregar os dados da consulta.');
}

if (
    !$consulta ||
    !in_array($consulta['status'], ['pendente', 'confirmada'], true) ||
    $consulta['data_hora'] <= date('Y-m-d H:i:s')
) {
    header('Location: detalhes-consulta.php?id=' . $consultaId . '&msg=sem_alteracao');
    exit();
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reagendar consulta - Convelum Saúde</title>
    <link href="../../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/responsivo.css">
    <style>
        #gridHorariosReagendar {
            max-height: 260px;
            overflow-y: auto;
            padding-right: 4px;
        }
    </style>
</head>
<body class="paginas-arrumar">
<div class="topo-painel">
    <div class="topo-painel-marca">
        <a href="../../index.php"><img src="../../imgs/logofrase.png" alt="Convelum Saúde"></a>
    </div>
    <div class="topo-painel-perfil">
        <a href="detalhes-consulta.php?id=<?php echo (int)$consultaId; ?>" class="topo-painel-sair">
            <i class="bi bi-arrow-left"></i> Voltar aos detalhes
        </a>
    </div>
</div>

<div id="painel-boasvindas">
    <div>
        <span id="subtitulo-painel">AGENDA GERAL</span>
        <h1 id="titulo-painel">Reagendar consulta</h1>
        <p id="texto-painel"><?php echo htmlspecialchars($consulta['paciente'], ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars($consulta['medico'], ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
</div>

<div class="secao-config-agenda">
    <form method="post" action="actions/salvar-reagendamento-recepcao.php" class="cartao-config-agenda" id="formReagendarConsulta">
        <h2 class="titulo-cartao-config">Novo horário</h2>
        <?php if ($erro !== ''): ?>
            <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="consulta_id" value="<?php echo (int)$consultaId; ?>">
        <input type="hidden" name="novo_horario" id="horarioSelecionado" required>

        <div class="campo-config">
            <label for="novaData">Nova data</label>
            <input type="date" id="novaData" name="nova_data" min="<?php echo date('Y-m-d'); ?>" required>
        </div>
        <div class="campo-config">
            <label>Novo horário</label>
            <p id="avisoHorarios" class="text-muted" style="font-size:14px;">
                <i class="bi bi-info-circle"></i> Escolha uma data para ver os horários disponíveis.
            </p>
            <div class="grid-horarios" id="gridHorariosReagendar" style="display:none;"></div>
        </div>
        <button type="submit" id="botaoSalvar" class="botao-entrar botao-config-salvar" disabled>
            Confirmar reagendamento
        </button>
    </form>
</div>

<script>
const medicoId = <?php echo (int)$consulta['id_medico']; ?>;
const consultaId = <?php echo (int)$consultaId; ?>;
let requisicaoAtual = 0;
const dataInput = document.getElementById('novaData');
const grid = document.getElementById('gridHorariosReagendar');
const aviso = document.getElementById('avisoHorarios');
const horaInput = document.getElementById('horarioSelecionado');
const botaoSalvar = document.getElementById('botaoSalvar');

dataInput.addEventListener('change', function () {
    const requisicao = ++requisicaoAtual;
    grid.replaceChildren();
    grid.style.display = 'none';
    horaInput.value = '';
    botaoSalvar.disabled = true;

    if (!dataInput.value) {
        aviso.textContent = 'Escolha uma data para ver os horários disponíveis.';
        aviso.style.display = 'block';
        return;
    }

    aviso.textContent = 'Buscando horários disponíveis...';
    aviso.style.display = 'block';
    fetch('actions/horarios-disponiveis-encaixe.php?id_medico=' + encodeURIComponent(medicoId) + '&data=' + encodeURIComponent(dataInput.value) + '&id_consulta=' + encodeURIComponent(consultaId))
        .then(function (response) {
            return response.json().then(function (dados) {
                if (!response.ok) {
                    throw new Error(dados.erro || 'Não foi possível consultar os horários.');
                }
                return dados;
            });
        })
        .then(function (dados) {
            if (requisicao !== requisicaoAtual) {
                return;
            }
            if (!dados.horarios || dados.horarios.length === 0) {
                aviso.textContent = dados.erro || 'Não há horários disponíveis nessa data.';
                return;
            }
            dados.horarios.forEach(function (item) {
                const escolha = document.createElement('button');
                escolha.type = 'button';
                escolha.className = 'horario-opcao' + (item.disponivel ? '' : ' horario-ocupado');
                escolha.textContent = item.hora;
                escolha.disabled = !item.disponivel;
                if (item.disponivel) {
                    escolha.addEventListener('click', function () {
                        grid.querySelectorAll('.horario-opcao').forEach(function (botao) {
                            botao.classList.remove('ativo');
                        });
                        escolha.classList.add('ativo');
                        horaInput.value = item.hora;
                        botaoSalvar.disabled = false;
                    });
                } else {
                    escolha.title = 'Horário já ocupado';
                }
                grid.appendChild(escolha);
            });
            aviso.style.display = 'none';
            grid.style.display = 'grid';
        })
        .catch(function (error) {
            if (requisicao !== requisicaoAtual) {
                return;
            }
            aviso.textContent = error.message || 'Erro ao buscar horários. Tente novamente.';
            aviso.style.display = 'block';
        });
});

document.getElementById('formReagendarConsulta').addEventListener('submit', function (event) {
    if (!dataInput.value || !horaInput.value) {
        event.preventDefault();
        alert('Escolha uma data e um horário disponível.');
    }
});
</script>
</body>
</html>
