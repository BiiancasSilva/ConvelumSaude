<?php
// perfis/paciente/reagendar.php
session_start();
require_once '../../conexao.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_perfil'] !== 'paciente') {
    header("Location: ../../auth/login.php");
    exit();
}

$pacienteId = $_SESSION['usuario_id'];

// Buscar consultas ativas do paciente logado
$minhasConsultas = [];
$erroConsultas = '';
try {
    $sql = "SELECT 
                c.id, 
                c.data_hora, 
                c.tipo_atendimento AS tipo, 
                m.nome AS medico_nome, 
                e.nome AS especialidade_nome
            FROM consultas c
            LEFT JOIN medico m ON c.id_medico = m.id
            LEFT JOIN especialidade e ON c.id_especialidade = e.id
            WHERE c.id_paciente = :id
              AND LOWER(c.status) IN ('pendente', 'confirmada')
              AND c.data_hora > NOW()
            ORDER BY c.data_hora ASC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $pacienteId]);
    $consultasBanco = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($consultasBanco as $c) {
        $dataFormatada = !empty($c['data_hora']) ? date('d/m/Y \à\s H:i', strtotime($c['data_hora'])) : 'Data a definir';
        $medico = !empty($c['medico_nome']) ? $c['medico_nome'] : 'Médico a definir';
        $esp = !empty($c['especialidade_nome']) ? $c['especialidade_nome'] : 'Geral';
        $tipo = !empty($c['tipo']) ? $c['tipo'] : 'Consulta';

        $minhasConsultas[] = [
            'id' => $c['id'],
            'data' => $dataFormatada,
            'principal' => 'Dr(a). ' . $medico . ' — ' . $esp,
            'tipo' => ucfirst($tipo)
        ];
    }
} catch (PDOException $e) {
    error_log('Erro ao carregar consultas para reagendamento: ' . $e->getMessage());
    $erroConsultas = 'Não foi possível carregar suas consultas. Atualize a página ou tente novamente mais tarde.';
}

// Horários disponíveis pra nova data
$horariosDisponiveis = ['08:00', '08:30', '09:30', '10:00', '10:30', '13:30', '14:00', '15:00', '16:30'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reagendar Consulta - Convelum Saúde</title>

    <link href="../../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/responsivo.css">
</head>
<body>

<!-- TOPO DO PAINEL -->
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

<div class="secao-config-agenda">
    <div class="cartao-config-agenda">

        <!-- INDICADOR DE ETAPAS -->
        <div class="indicador-etapas" id="indicadorEtapas">
            <span class="ponto-etapa ativo" data-ponto="1"></span>
            <span class="ponto-etapa" data-ponto="2"></span>
            <span class="ponto-etapa" data-ponto="3"></span>
        </div>

        <form method="post" action="actions/salvar-reagendamento.php" id="formReagendar">

            <!-- ETAPA 1: QUAL CONSULTA -->
            <div class="etapa-cadastro ativa" data-etapa="1">
                <h1>Qual consulta você quer reagendar?</h1>
                <p class="login-subtitulo">Escolha uma das suas consultas marcadas</p>

                <?php if ($erroConsultas !== ''): ?>
                    <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($erroConsultas, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>

                <div class="lista-pacotes-agendar" id="listaConsultasReagendar">
                    <?php if (!empty($minhasConsultas)): ?>
                        <?php foreach ($minhasConsultas as $consulta): ?>
                            <button type="button"
                                    class="opcao-pacote-agendar opcao-consulta-reagendar"
                                    data-id="<?php echo $consulta['id']; ?>"
                                    data-descricao="<?php echo htmlspecialchars($consulta['principal'] . ' · ' . $consulta['tipo'], ENT_QUOTES, 'UTF-8'); ?>"
                                    onclick="escolherConsulta(this)">
                                <div>
                                    <span class="opcao-pacote-nome"><?php echo htmlspecialchars($consulta['principal']); ?></span>
                                    <p class="opcao-consulta-detalhes">
                                        <i class="bi <?php echo strtolower($consulta['tipo']) === 'teleconsulta' ? 'bi-camera-video' : 'bi-hospital'; ?>"></i>
                                        <?php echo htmlspecialchars($consulta['tipo']); ?> · Marcada pra <?php echo htmlspecialchars($consulta['data']); ?>
                                    </p>
                                </div>
                                <i class="bi bi-chevron-right"></i>
                            </button>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="p-4 text-center text-muted bg-light rounded-3 my-3">
                            <i class="bi bi-calendar-x fs-3 d-block mb-2"></i>
                            <p class="m-0">Você não possui consultas em aberto para reagendar.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <input type="hidden" name="consulta_id" id="consultaIdSelecionada" value="">
            </div>

            <!-- ETAPA 2: NOVA DATA E HORÁRIO -->
            <div class="etapa-cadastro" data-etapa="2">
                <h1>Escolha a nova data</h1>
                <p class="login-subtitulo" id="descricaoConsultaEscolhida"></p>

                <div class="campo-config">
                    <label for="novaData">Nova data</label>
                    <input type="date" id="novaData" name="nova_data" min="<?php echo date('Y-m-d'); ?>">
                </div>

                <div class="campo-config">
                    <label>Novo horário</label>
                    <div class="grid-horarios" id="gridHorariosReagendar"></div>
                    <input type="hidden" name="novo_horario" id="novoHorarioSelecionado" value="">
                </div>

                <div class="botoes-etapa">
                    <button type="button" class="botao-voltar-etapa" onclick="mostrarEtapa(1)">Voltar</button>
                    <button type="button" class="botao-entrar" onclick="avancarParaResumo()">Próximo</button>
                </div>
            </div>

            <!-- ETAPA 3: RESUMO E CONFIRMAÇÃO -->
            <div class="etapa-cadastro" data-etapa="3">
                <h1>Confirme o reagendamento</h1>
                <p class="login-subtitulo">Revise antes de confirmar a mudança</p>

                <div class="resumo-agendamento p-3 bg-light rounded-3 mb-3" id="resumoReagendamento"></div>

                <p class="aviso-recuperacao">
                    O horário anterior será liberado assim que você confirmar o novo.
                </p>

                <div class="botoes-etapa">
                    <button type="button" class="botao-voltar-etapa" onclick="mostrarEtapa(2)">Voltar</button>
                    <button type="button" class="botao-entrar" id="btnConfirmarReagendamento" onclick="prepararEnvio()">Confirmar reagendamento</button>
                </div>
            </div>

        </form>

        <!-- TELA FINAL -->
        <div class="tela-concluido" id="telaConcluido" style="display: none;">
            <i class="bi bi-check-circle-fill"></i>
            <h2>Consulta reagendada com sucesso!</h2>
            <p>Enviamos todos os detalhes do novo horário e as orientações para o seu e-mail cadastrado.</p>
            <a href="painel.php" class="botao-entrar botao-ir-login">Ver minhas consultas</a>
        </div>

    </div>
</div>

<script>
const horariosDisponiveis = <?php echo json_encode($horariosDisponiveis); ?>;

let descricaoConsultaEscolhida = '';
let horarioEscolhido = '';

const etapas = document.querySelectorAll('.etapa-cadastro');
const pontos = document.querySelectorAll('.ponto-etapa');

function mostrarEtapa(numero) {
    etapas.forEach(function (etapa) {
        etapa.classList.toggle('ativa', etapa.dataset.etapa === String(numero));
    });
    pontos.forEach(function (ponto) {
        ponto.classList.toggle('ativo', ponto.dataset.ponto === String(numero));
    });
}

function escolherConsulta(botao) {
    document.getElementById('consultaIdSelecionada').value = botao.dataset.id;
    descricaoConsultaEscolhida = botao.dataset.descricao;
    document.getElementById('descricaoConsultaEscolhida').textContent = descricaoConsultaEscolhida;

    document.querySelectorAll('.opcao-consulta-reagendar').forEach(function (b) {
        b.classList.remove('selecionado');
    });
    botao.classList.add('selecionado');

    montarGradeHorarios();
    mostrarEtapa(2);
}

function montarGradeHorarios() {
    const grid = document.getElementById('gridHorariosReagendar');
    grid.innerHTML = '';
    horariosDisponiveis.forEach(function (hora) {
        const botao = document.createElement('button');
        botao.type = 'button';
        botao.className = 'horario-opcao';
        botao.textContent = hora;
        botao.addEventListener('click', function () {
            grid.querySelectorAll('.horario-opcao').forEach(function (b) { b.classList.remove('ativo'); });
            botao.classList.add('ativo');
            horarioEscolhido = hora;
            document.getElementById('novoHorarioSelecionado').value = hora;
        });
        grid.appendChild(botao);
    });
}

function avancarParaResumo() {
    const data = document.getElementById('novaData').value;

    if (!data || !horarioEscolhido) {
        alert('Escolha a nova data e o novo horário pra continuar.');
        return;
    }

    const partes = data.split('-');
    const dataFormatada = partes[2] + '/' + partes[1] + '/' + partes[0];

    const resumo = document.getElementById('resumoReagendamento');
    resumo.replaceChildren();
    [
        ['Consulta', descricaoConsultaEscolhida],
        ['Nova data', dataFormatada],
        ['Novo horário', horarioEscolhido]
    ].forEach(function (item) {
        const linha = document.createElement('div');
        linha.className = 'linha-resumo';
        const rotulo = document.createElement('span');
        rotulo.textContent = item[0];
        const valor = document.createElement('strong');
        valor.textContent = item[1];
        linha.append(rotulo, valor);
        resumo.appendChild(linha);
    });

    mostrarEtapa(3);
}

function prepararEnvio() {
    const btn = document.getElementById('btnConfirmarReagendamento');
    btn.disabled = true;
    btn.innerText = 'Processando...';

    const form = document.getElementById('formReagendar');
    const formData = new FormData(form);

    fetch('actions/salvar-reagendamento.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json().then(data => {
        if (!response.ok && data.sucesso) {
            throw new Error('Não foi possível concluir o reagendamento.');
        }
        return data;
    }))
    .then(data => {
        if (data.sucesso) {
            document.getElementById('formReagendar').style.display = 'none';
            document.getElementById('indicadorEtapas').style.display = 'none';
            document.getElementById('telaConcluido').style.display = 'block';
        } else {
            alert('Erro ao reagendar: ' + (data.erro || 'Tente novamente.'));
            btn.disabled = false;
            btn.innerText = 'Confirmar reagendamento';
        }
    })
    .catch(error => {
        console.error('Erro na requisição:', error);
        alert(error.message || 'Ocorreu um erro ao conectar com o servidor.');
        btn.disabled = false;
        btn.innerText = 'Confirmar reagendamento';
    });
}
</script>

</body>
</html>