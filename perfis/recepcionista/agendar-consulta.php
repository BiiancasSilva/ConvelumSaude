<?php
// recepcionista/agendar-consulta.php
session_start();
require_once '../../conexao.php';

if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'recepcionista') {
    header("Location: ../../auth/login.php");
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Especialidades pra montar o select
try {
    $stmtEspecialidades = $pdo->query("SELECT id, nome, preco FROM especialidade WHERE status = 'Ativo' ORDER BY nome ASC");
    $listaEspecialidades = $stmtEspecialidades->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $listaEspecialidades = [];
}

$horariosDisponiveis = [];
for ($minutos = 8 * 60; $minutos <= 17 * 60 + 30; $minutos += 30) {
    $horariosDisponiveis[] = sprintf('%02d:%02d', intdiv($minutos, 60), $minutos % 60);
}

// Convênios ATIVOS e seus planos (tabelas convenios e convenio_planos)
$listaConvenios = [];
try {
    $stmtConvenios = $pdo->query("SELECT id, nome FROM convenios WHERE status = 'Ativo' ORDER BY nome ASC");
    foreach ($stmtConvenios->fetchAll(PDO::FETCH_ASSOC) as $cv) {
        $listaConvenios[(int)$cv['id']] = [
            'id'     => (int)$cv['id'],
            'nome'   => $cv['nome'],
            'planos' => []
        ];
    }

    if (!empty($listaConvenios)) {
        $stmtPlanos = $pdo->query("SELECT id, id_convenio, nome_plano FROM convenio_planos ORDER BY nome_plano ASC");
        foreach ($stmtPlanos->fetchAll(PDO::FETCH_ASSOC) as $pl) {
            $idConv = (int)$pl['id_convenio'];
            if (isset($listaConvenios[$idConv])) {
                $listaConvenios[$idConv]['planos'][] = [
                    'id'   => (int)$pl['id'],
                    'nome' => $pl['nome_plano']
                ];
            }
        }
    }
} catch (PDOException $e) {
    $listaConvenios = [];
}
$listaConvenios = array_values($listaConvenios);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agendar Consulta - Convelum Saúde</title>

    <link href="../../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/responsivo.css">

    <style>
        .selo-sala-fixa-inline {
            display: block;
            font-size: 11px;
            opacity: 0.75;
            margin-top: 2px;
        }

        /* Grade de horários: antes crescia sem limite e empurrava a página
           inteira pra baixo quando havia muitos horários. Agora tem altura
           máxima e rola por dentro, mantendo os botões "Voltar/Próximo"
           sempre visíveis logo abaixo, sem precisar rolar a tela toda. */
        #gridHorariosConsulta {
            max-height: 260px;
            overflow-y: auto;
            padding-right: 4px;
        }

        /* Mesma lógica pra lista de médicos e de salas, que também podem
           crescer bastante dependendo de quantos resultados a busca traz. */
        #listaMedicosDisponiveis,
        #listaSalasDisponiveis {
            max-height: 320px;
            overflow-y: auto;
            padding-right: 4px;
        }

        /* Scrollbar mais discreta nesses blocos (funciona em navegadores
           baseados em Chromium/Webkit; em outros cai no padrão do SO). */
        #gridHorariosConsulta::-webkit-scrollbar,
        #listaMedicosDisponiveis::-webkit-scrollbar,
        #listaSalasDisponiveis::-webkit-scrollbar {
            width: 6px;
        }
        #gridHorariosConsulta::-webkit-scrollbar-thumb,
        #listaMedicosDisponiveis::-webkit-scrollbar-thumb,
        #listaSalasDisponiveis::-webkit-scrollbar-thumb {
            background: #c7c7c7;
            border-radius: 4px;
        }
    </style>
</head>
<body>

<div class="topo-painel">
    <div class="topo-painel-marca">
        <a href="../../index.php"><img src="../../imgs/logofrase.png" alt="Convelum Saúde"></a>
    </div>
    <div class="topo-painel-perfil">
        <a href="painel.php" class="topo-painel-sair">
            <i class="bi bi-arrow-left"></i> Voltar ao painel
        </a>
    </div>
</div>

<?php if (isset($_GET['erro'])): ?>
    <div class="container mt-3" style="max-width: 900px;">
        <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars((string)$_GET['erro'], ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
<?php endif; ?>

<div class="secao-config-agenda">
    <div class="cartao-config-agenda">

        <!-- INDICADOR DE ETAPAS -->
        <div class="indicador-etapas" id="indicadorEtapas">
            <span class="ponto-etapa ativo" data-ponto="1"></span>
            <span class="ponto-etapa" data-ponto="2"></span>
            <span class="ponto-etapa" data-ponto="3"></span>
            <span class="ponto-etapa" data-ponto="4"></span>
            <span class="ponto-etapa" data-ponto="5"></span>
        </div>

        <form method="post" action="actions/salvar-agendamento-recepcao.php" id="formAgendarRecepcao">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

            <!-- ========================= ETAPA 1: PACIENTE E TIPO ========================= -->
            <div class="etapa-cadastro ativa" data-etapa="paciente">
                <h1>Agendar consulta</h1>
                <p class="login-subtitulo">Busque o paciente pelo nome, CPF ou telefone</p>

                <div class="campo-config" id="blocoBuscaPaciente">
                    <label for="buscaPaciente">Paciente</label>
                    <input type="text" id="buscaPaciente" placeholder="Digite o nome, CPF ou telefone..." autocomplete="off">
                    <div class="lista-itens-painel" id="resultadosBuscaPaciente" style="margin-top:8px;"></div>
                </div>

                <div class="campo-config" id="blocoPacienteSelecionado" style="display:none;">
                    <label>Paciente selecionado</label>
                    <div class="linha-item-painel">
                        <span class="item-painel-principal" id="nomePacienteSelecionado"></span>
                        <button type="button" class="item-painel-botao" style="background:#6c757d;" onclick="trocarPaciente()">Trocar</button>
                    </div>
                </div>

                <input type="hidden" name="paciente_id" id="pacienteSelecionadoId" value="">

                <p class="login-subtitulo mt-3">Tipo de atendimento</p>
                <div class="grid-tipo-agendamento">
                    <button type="button" class="opcao-tipo-agendamento" data-tipo="Presencial" onclick="escolherTipo('Presencial')">
                        <i class="bi bi-hospital"></i>
                        <span class="opcao-tipo-titulo">Presencial</span>
                    </button>
                    <button type="button" class="opcao-tipo-agendamento" data-tipo="Teleconsulta" onclick="escolherTipo('Teleconsulta')">
                        <i class="bi bi-camera-video"></i>
                        <span class="opcao-tipo-titulo">Teleconsulta</span>
                    </button>
                </div>
                <input type="hidden" name="tipo_atendimento" id="tipoAtendimentoSelecionado" value="">

                <div class="botoes-etapa">
                    <button type="button" class="botao-entrar" onclick="avancarDePaciente()">Próximo</button>
                </div>
            </div>

            <!-- ========================= ETAPA 2: ESPECIALIDADE / DATA / HORÁRIO / CONVÊNIO ========================= -->
            <div class="etapa-cadastro" data-etapa="detalhes">
                <h1>Detalhes da consulta</h1>
                <p class="login-subtitulo">Escolha especialidade, data e horário</p>

                <div class="campo-config">
                    <label for="especialidade">Especialidade</label>
                    <select id="especialidade" name="especialidade_id">
                        <option value="">Selecione uma especialidade</option>
                        <?php foreach ($listaEspecialidades as $esp): ?>
                            <option value="<?php echo $esp['id']; ?>" data-nome="<?php echo htmlspecialchars($esp['nome']); ?>">
                                <?php echo htmlspecialchars($esp['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="campo-config">
                    <label for="dataConsulta">Data</label>
                    <input type="date" id="dataConsulta" name="data" min="<?php echo date('Y-m-d'); ?>">
                </div>

                <div class="campo-config">
                    <label>Horário</label>
                    <div class="grid-horarios" id="gridHorariosConsulta"></div>
                    <input type="hidden" name="horario" id="horarioSelecionadoConsulta" value="">
                </div>

                <!-- CONVÊNIO -->
                <div class="campo-config">
                    <label>O paciente tem convênio?</label>
                    <div class="grid-tipo-agendamento">
                        <button type="button" class="opcao-tipo-agendamento" data-convenio="1" onclick="escolherConvenio(true)">
                            <i class="bi bi-shield-check"></i>
                            <span class="opcao-tipo-titulo">Sim</span>
                        </button>
                        <button type="button" class="opcao-tipo-agendamento" data-convenio="0" onclick="escolherConvenio(false)">
                            <i class="bi bi-x-circle"></i>
                            <span class="opcao-tipo-titulo">Não</span>
                        </button>
                    </div>
                    <input type="hidden" name="possui_convenio" id="possuiConvenio" value="">
                </div>

                <div class="campo-config" id="blocoConvenio" style="display:none;">
                    <label for="convenioSelect">Qual o convênio?</label>
                    <?php if (empty($listaConvenios)): ?>
                        <p class="aviso-recuperacao">Nenhum convênio ativo cadastrado. Peça ao gestor para cadastrar.</p>
                    <?php else: ?>
                        <select id="convenioSelect" name="convenio_id" onchange="carregarPlanos()">
                            <option value="">Selecione o convênio</option>
                            <?php foreach ($listaConvenios as $cv): ?>
                                <option value="<?php echo $cv['id']; ?>" data-nome="<?php echo htmlspecialchars($cv['nome']); ?>">
                                    <?php echo htmlspecialchars($cv['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </div>

                <div class="campo-config" id="blocoPlano" style="display:none;">
                    <label for="planoSelect">Qual o plano?</label>
                    <select id="planoSelect" name="plano_id">
                        <option value="">Selecione o plano</option>
                    </select>
                </div>

                <div class="botoes-etapa">
                    <button type="button" class="botao-voltar-etapa" onclick="mostrarEtapa('paciente')">Voltar</button>
                    <button type="button" class="botao-entrar" onclick="abrirEtapaMedico()">Próximo</button>
                </div>
            </div>

            <!-- ========================= ETAPA 3: MÉDICO DISPONÍVEL ========================= -->
            <div class="etapa-cadastro" data-etapa="medico">
                <h1>Escolha o médico</h1>
                <p class="login-subtitulo">Médicos dessa especialidade disponíveis na data e horário escolhidos</p>

                <div class="grid-tipo-agendamento" id="listaMedicosDisponiveis"></div>
                <p class="aviso-recuperacao" id="avisoSemMedico" style="display:none;">
                    Nenhum médico disponível nesse dia/horário. Volte e escolha outra data ou horário.
                </p>

                <input type="hidden" name="medico_id" id="medicoSelecionadoId" value="">
                <input type="hidden" id="medicoSelecionadoNome" value="">

                <div class="botoes-etapa">
                    <button type="button" class="botao-voltar-etapa" onclick="mostrarEtapa('detalhes')">Voltar</button>
                    <button type="button" class="botao-entrar" id="botaoProximoMedico" onclick="decidirProximoAposMedico()" disabled>Próximo</button>
                </div>
            </div>

            <!-- ========================= ETAPA 3.5: SALA (só para Presencial) ========================= -->
            <div class="etapa-cadastro" data-etapa="sala">
                <h1>Escolha a sala</h1>
                <p class="login-subtitulo">Salas disponíveis pra esse médico, data e horário</p>

                <div class="grid-tipo-agendamento" id="listaSalasDisponiveis"></div>
                <p class="aviso-recuperacao" id="avisoSemSala" style="display:none;">
                    Nenhuma sala disponível pra esse horário. Volte e escolha outra data/horário, ou avise o gestor pra cadastrar mais salas.
                </p>

                <input type="hidden" name="sala_id" id="salaSelecionadaId" value="">

                <div class="botoes-etapa">
                    <button type="button" class="botao-voltar-etapa" onclick="mostrarEtapa('medico')">Voltar</button>
                    <button type="button" class="botao-entrar" id="botaoProximoSala" onclick="abrirEtapaConfirmar()" disabled>Próximo</button>
                </div>
            </div>

            <!-- ========================= ETAPA 4: CONFIRMAR ========================= -->
            <div class="etapa-cadastro" data-etapa="confirmar">
                <h1>Confirmar agendamento</h1>
                <p class="login-subtitulo">Confira os dados antes de salvar</p>

                <div class="resumo-agendamento p-3 bg-light rounded-3 mb-3" id="resumoAgendamento"></div>

                <!-- AVISO DE PAGAMENTO -->
                <div class="alert alert-info d-flex align-items-start gap-2 mb-3" role="alert">
                    <i class="bi bi-cash-coin"></i>
                    <div>
                        <strong>Pagamento somente na recepção.</strong><br>
                        O pagamento da consulta é feito presencialmente na recepção da clínica, não pelo sistema.
                    </div>
                </div>

                <input type="hidden" name="status" id="statusAgendamento" value="pendente">

                <div class="botoes-etapa" style="flex-wrap:wrap;">
                    <button type="button" class="botao-voltar-etapa" onclick="voltarDeConfirmar()">Voltar</button>
                    <button type="button" class="botao-entrar" style="background:#6c757d;" onclick="salvarAgendamento('pendente')">Salvar como pendente</button>
                    <button type="button" class="botao-entrar" onclick="salvarAgendamento('confirmada')">Salvar já confirmada</button>
                </div>
            </div>

        </form>
    </div>
</div>

<script>
const horariosDisponiveis = <?php echo json_encode($horariosDisponiveis, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const convenios = <?php echo json_encode($listaConvenios, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

let horarioConsulta = '';
let tipoEscolhido = '';
let buscaPacienteTimeout = null;

const etapas = document.querySelectorAll('.etapa-cadastro');
const pontos = document.querySelectorAll('.ponto-etapa');

const pontoPorEtapa = {
    'paciente': '1',
    'detalhes': '2',
    'medico': '3',
    'sala': '4',
    'confirmar': '5',
};

function mostrarEtapa(chave) {
    etapas.forEach(function (etapa) {
        etapa.classList.toggle('ativa', etapa.dataset.etapa === String(chave));
    });
    pontos.forEach(function (ponto) {
        ponto.classList.toggle('ativo', ponto.dataset.ponto === (pontoPorEtapa[chave] || '1'));
    });
}

function escaparHtml(valor) {
    return String(valor).replace(/[&<>"']/g, function (caractere) {
        return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[caractere];
    });
}

// ================== ETAPA 1: PACIENTE ==================

document.getElementById('buscaPaciente').addEventListener('input', function () {
    const termo = this.value.trim();
    clearTimeout(buscaPacienteTimeout);

    const resultados = document.getElementById('resultadosBuscaPaciente');

    if (termo.length < 2) {
        resultados.innerHTML = '';
        return;
    }

    buscaPacienteTimeout = setTimeout(function () {
        fetch('actions/buscar-pacientes.php?termo=' + encodeURIComponent(termo))
            .then(function (r) { return r.json(); })
            .then(function (resp) {
                resultados.innerHTML = '';

                if (!resp.sucesso || !resp.pacientes.length) {
                    resultados.innerHTML = '<p class="aviso-recuperacao">Nenhum paciente encontrado.</p>';
                    return;
                }

                resp.pacientes.forEach(function (pac) {
                    const linha = document.createElement('div');
                    linha.className = 'linha-item-painel';
                    linha.style.cursor = 'pointer';
                    linha.innerHTML =
                        '<span class="item-painel-principal">' + escaparHtml(pac.nome) + '</span>' +
                        '<span class="item-painel-submedico">' + escaparHtml(pac.celular || '') + '</span>';
                    linha.addEventListener('click', function () {
                        selecionarPaciente(pac.id, pac.nome);
                    });
                    resultados.appendChild(linha);
                });
            })
            .catch(function () {
                resultados.innerHTML = '<p class="aviso-recuperacao">Erro ao buscar pacientes.</p>';
            });
    }, 300);
});

function selecionarPaciente(id, nome) {
    document.getElementById('pacienteSelecionadoId').value = id;
    document.getElementById('nomePacienteSelecionado').textContent = nome;
    document.getElementById('blocoBuscaPaciente').style.display = 'none';
    document.getElementById('blocoPacienteSelecionado').style.display = 'block';
    document.getElementById('resultadosBuscaPaciente').innerHTML = '';
    document.getElementById('buscaPaciente').value = '';
}

function trocarPaciente() {
    document.getElementById('pacienteSelecionadoId').value = '';
    document.getElementById('blocoBuscaPaciente').style.display = 'block';
    document.getElementById('blocoPacienteSelecionado').style.display = 'none';
}

function escolherTipo(tipo) {
    tipoEscolhido = tipo;
    document.getElementById('tipoAtendimentoSelecionado').value = tipo;
    document.querySelectorAll('.opcao-tipo-agendamento').forEach(function (b) {
        if (b.dataset.tipo) {
            b.classList.toggle('ativo', b.dataset.tipo === tipo);
        }
    });
}

function avancarDePaciente() {
    if (!document.getElementById('pacienteSelecionadoId').value) {
        alert('Selecione um paciente para continuar.');
        return;
    }
    if (!tipoEscolhido) {
        alert('Escolha o tipo de atendimento.');
        return;
    }

    montarGradeHorarios();
    mostrarEtapa('detalhes');
}

// ================== ETAPA 2: DETALHES ==================

function montarGradeHorarios() {
    const grid = document.getElementById('gridHorariosConsulta');
    grid.innerHTML = '';
    horariosDisponiveis.forEach(function (hora) {
        const botao = document.createElement('button');
        botao.type = 'button';
        botao.className = 'horario-opcao';
        botao.textContent = hora;
        botao.addEventListener('click', function () {
            grid.querySelectorAll('.horario-opcao').forEach(function (b) { b.classList.remove('ativo'); });
            botao.classList.add('ativo');
            document.getElementById('horarioSelecionadoConsulta').value = hora;
            horarioConsulta = hora;
        });
        grid.appendChild(botao);
    });
}

function escolherConvenio(possui) {
    document.getElementById('possuiConvenio').value = possui ? '1' : '0';

    document.querySelectorAll('[data-convenio]').forEach(function (b) {
        b.classList.toggle('ativo', b.dataset.convenio === (possui ? '1' : '0'));
    });

    document.getElementById('blocoConvenio').style.display = possui ? 'block' : 'none';

    if (!possui) {
        const selConv = document.getElementById('convenioSelect');
        if (selConv) selConv.value = '';
        limparPlanos();
    }
}

function limparPlanos() {
    const selPlano = document.getElementById('planoSelect');
    selPlano.innerHTML = '<option value="">Selecione o plano</option>';
    document.getElementById('blocoPlano').style.display = 'none';
}

function carregarPlanos() {
    const selConv = document.getElementById('convenioSelect');
    const selPlano = document.getElementById('planoSelect');

    limparPlanos();

    const convenio = convenios.find(function (c) { return String(c.id) === selConv.value; });
    if (!convenio || !convenio.planos.length) {
        return; // convênio sem planos cadastrados: não pede plano
    }

    convenio.planos.forEach(function (plano) {
        const opt = document.createElement('option');
        opt.value = plano.id;
        opt.textContent = plano.nome;
        selPlano.appendChild(opt);
    });

    document.getElementById('blocoPlano').style.display = 'block';
}

// ================== ETAPA 3: MÉDICO DISPONÍVEL ==================

function abrirEtapaMedico() {
    const espSelect = document.getElementById('especialidade');
    const data = document.getElementById('dataConsulta').value;

    if (!espSelect.value) {
        alert('Selecione uma especialidade.');
        return;
    }
    if (!data || !horarioConsulta) {
        alert('Escolha a data e o horário para continuar.');
        return;
    }
    if (document.getElementById('possuiConvenio').value === '') {
        alert('Informe se o paciente tem convênio.');
        return;
    }
    if (document.getElementById('possuiConvenio').value === '1') {
        const selConv = document.getElementById('convenioSelect');
        if (!selConv || !selConv.value) {
            alert('Selecione o convênio do paciente.');
            return;
        }
        const planoVisivel = document.getElementById('blocoPlano').style.display !== 'none';
        if (planoVisivel && !document.getElementById('planoSelect').value) {
            alert('Selecione o plano do paciente.');
            return;
        }
    }

    carregarMedicosDisponiveis(espSelect.value, data, horarioConsulta, tipoEscolhido);
}

function carregarMedicosDisponiveis(especialidadeId, data, horario, tipo) {
    const lista = document.getElementById('listaMedicosDisponiveis');
    const aviso = document.getElementById('avisoSemMedico');
    const botaoProximo = document.getElementById('botaoProximoMedico');

    lista.innerHTML = '<p class="aviso-recuperacao">Buscando médicos disponíveis...</p>';
    aviso.style.display = 'none';
    botaoProximo.disabled = true;
    document.getElementById('medicoSelecionadoId').value = '';
    document.getElementById('medicoSelecionadoNome').value = '';

    const params = new URLSearchParams({
        especialidade_id: especialidadeId,
        paciente_id: document.getElementById('pacienteSelecionadoId').value,
        data: data,
        horario: horario,
        tipo: tipo
    });

    fetch('actions/medicos-disponiveis.php?' + params.toString())
        .then(function (r) { return r.json(); })
        .then(function (resp) {
            lista.innerHTML = '';

            if (!resp.sucesso || !resp.medicos || !resp.medicos.length) {
                aviso.style.display = 'block';
                mostrarEtapa('medico');
                return;
            }

            resp.medicos.forEach(function (med) {
                const botao = document.createElement('button');
                botao.type = 'button';
                botao.className = 'opcao-tipo-agendamento';
                botao.innerHTML = '<i class="bi bi-person-badge"></i><span class="opcao-tipo-titulo">' + escaparHtml(med.nome) + '</span>';
                botao.addEventListener('click', function () {
                    lista.querySelectorAll('.opcao-tipo-agendamento').forEach(function (b) { b.classList.remove('ativo'); });
                    botao.classList.add('ativo');
                    document.getElementById('medicoSelecionadoId').value = med.id;
                    document.getElementById('medicoSelecionadoNome').value = med.nome;
                    botaoProximo.disabled = false;
                });
                lista.appendChild(botao);
            });

            mostrarEtapa('medico');
        })
        .catch(function () {
            lista.innerHTML = '<p class="aviso-recuperacao">Erro ao buscar médicos. Tente novamente.</p>';
            mostrarEtapa('medico');
        });
}

function decidirProximoAposMedico() {
    if (tipoEscolhido === 'Presencial') {
        abrirEtapaSala();
    } else {
        // Teleconsulta não usa sala física
        document.getElementById('salaSelecionadaId').value = '';
        abrirEtapaConfirmar();
    }
}

// ================== ETAPA 3.5: SALA ==================

function abrirEtapaSala() {
    const medicoId = document.getElementById('medicoSelecionadoId').value;
    const data = document.getElementById('dataConsulta').value;

    const lista = document.getElementById('listaSalasDisponiveis');
    const aviso = document.getElementById('avisoSemSala');
    const botaoProximo = document.getElementById('botaoProximoSala');

    lista.innerHTML = '<p class="aviso-recuperacao">Buscando salas disponíveis...</p>';
    aviso.style.display = 'none';
    botaoProximo.disabled = true;
    document.getElementById('salaSelecionadaId').value = '';

    const params = new URLSearchParams({
        medico_id: medicoId,
        data: data,
        horario: horarioConsulta
    });

    fetch('actions/salas-disponiveis.php?' + params.toString())
        .then(function (r) { return r.json(); })
        .then(function (resp) {
            lista.innerHTML = '';

            if (!resp.sucesso || !resp.salas || !resp.salas.length) {
                aviso.style.display = 'block';
                mostrarEtapa('sala');
                return;
            }

            resp.salas.forEach(function (sala) {
                const botao = document.createElement('button');
                botao.type = 'button';
                botao.className = 'opcao-tipo-agendamento';

                let selo = '';
                if (sala.fixa) {
                    selo = '<span class="selo-sala-fixa-inline"><i class="bi bi-pin-angle-fill"></i> Fixa de ' + escaparHtml(sala.medico_fixo_nome || '') + '</span>';
                } else {
                    selo = '<span class="selo-sala-fixa-inline"><i class="bi bi-people"></i> Compartilhada</span>';
                }

                botao.innerHTML = '<i class="bi bi-door-open"></i><span class="opcao-tipo-titulo">' + escaparHtml(sala.nome) + '</span>' + selo;
                botao.addEventListener('click', function () {
                    lista.querySelectorAll('.opcao-tipo-agendamento').forEach(function (b) { b.classList.remove('ativo'); });
                    botao.classList.add('ativo');
                    document.getElementById('salaSelecionadaId').value = sala.id;
                    botaoProximo.disabled = false;
                });
                lista.appendChild(botao);
            });

            mostrarEtapa('sala');
        })
        .catch(function () {
            lista.innerHTML = '<p class="aviso-recuperacao">Erro ao buscar salas. Tente novamente.</p>';
            mostrarEtapa('sala');
        });
}

function voltarDeConfirmar() {
    if (tipoEscolhido === 'Presencial') {
        mostrarEtapa('sala');
    } else {
        mostrarEtapa('medico');
    }
}

// ================== ETAPA 4: CONFIRMAR ==================

function abrirEtapaConfirmar() {
    const medicoId = document.getElementById('medicoSelecionadoId').value;
    if (!medicoId) {
        alert('Escolha um médico disponível para continuar.');
        return;
    }

    if (tipoEscolhido === 'Presencial' && !document.getElementById('salaSelecionadaId').value) {
        alert('Escolha uma sala para continuar.');
        return;
    }

    const espSelect = document.getElementById('especialidade');
    const espNome = espSelect.options[espSelect.selectedIndex]?.getAttribute('data-nome') || '';
    const medNome = document.getElementById('medicoSelecionadoNome').value;
    const nomePaciente = document.getElementById('nomePacienteSelecionado').textContent;
    const data = document.getElementById('dataConsulta').value;

    const temConvenio = document.getElementById('possuiConvenio').value === '1';
    let textoConvenio = 'Particular (sem convênio)';
    if (temConvenio) {
        const selConv = document.getElementById('convenioSelect');
        const selPlano = document.getElementById('planoSelect');
        textoConvenio = selConv.options[selConv.selectedIndex].getAttribute('data-nome') || '';
        if (selPlano.value) {
            textoConvenio += ' - ' + selPlano.options[selPlano.selectedIndex].textContent;
        }
    }
    const salaAtiva = document.querySelector('#listaSalasDisponiveis .opcao-tipo-agendamento.ativo .opcao-tipo-titulo');
    const nomeSala = tipoEscolhido === 'Presencial' && salaAtiva ? salaAtiva.textContent : 'Não se aplica (teleconsulta)';

    document.getElementById('resumoAgendamento').innerHTML =
        '<div class="linha-resumo"><span>Paciente</span><strong>' + escaparHtml(nomePaciente) + '</strong></div>' +
        '<div class="linha-resumo"><span>Tipo</span><strong>' + escaparHtml(tipoEscolhido) + '</strong></div>' +
        '<div class="linha-resumo"><span>Especialidade</span><strong>' + escaparHtml(espNome) + '</strong></div>' +
        '<div class="linha-resumo"><span>Médico</span><strong>' + escaparHtml(medNome) + '</strong></div>' +
        '<div class="linha-resumo"><span>Data</span><strong>' + escaparHtml(formatarData(data)) + '</strong></div>' +
        '<div class="linha-resumo"><span>Horário</span><strong>' + escaparHtml(horarioConsulta) + '</strong></div>' +
        '<div class="linha-resumo"><span>Convênio</span><strong>' + escaparHtml(textoConvenio) + '</strong></div>' +
        '<div class="linha-resumo"><span>Sala</span><strong>' + escaparHtml(nomeSala) + '</strong></div>';

    mostrarEtapa('confirmar');
}

function formatarData(dataIso) {
    const partes = dataIso.split('-');
    return partes[2] + '/' + partes[1] + '/' + partes[0];
}

// Enter dentro dos campos NÃO envia o formulário (evita salvar sem querer no meio das etapas).
// Só os botões "Salvar como pendente" / "Salvar já confirmada" podem enviar.
const formAgendar = document.getElementById('formAgendarRecepcao');

formAgendar.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && e.target.tagName !== 'BUTTON' && e.target.tagName !== 'TEXTAREA') {
        e.preventDefault();
    }
});

// Bloqueia qualquer envio "automático" do navegador. O submit() chamado por
// salvarAgendamento() não dispara esse evento, então continua funcionando.
formAgendar.addEventListener('submit', function (e) {
    e.preventDefault();
});

function salvarAgendamento(status) {
    document.getElementById('statusAgendamento').value = status;
    formAgendar.submit();
}
</script>

</body>
</html>