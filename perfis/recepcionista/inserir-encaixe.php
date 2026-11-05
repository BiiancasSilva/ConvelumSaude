<?php
// recepcionista/inserir-encaixe.php
session_start();
require_once '../../conexao.php';

// Validação de sessão da recepcionista
if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'recepcionista') {
    header("Location: ../../auth/login.php");
    exit();
}

$csrfToken = $_SESSION['csrf_encaixes'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_encaixes'] = $csrfToken;
$erroCarregamento = false;

// Busca médicos REAIS cadastrados no sistema (com as especialidades deles)
try {
    $stmtMed = $pdo->query("
        SELECT m.id, m.nome, GROUP_CONCAT(e.nome SEPARATOR ', ') AS especialidades
        FROM medico m
        LEFT JOIN medico_especialidades me ON me.id_medico = m.id
        LEFT JOIN especialidade e ON e.id = me.id_especialidade
        GROUP BY m.id
        ORDER BY m.nome ASC
    ");
    $listaMedicos = $stmtMed->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Falha ao carregar médicos para encaixes: ' . $e->getMessage());
    $listaMedicos = [];
    $erroCarregamento = true;
}

// Busca pacientes REAIS cadastrados, pra sugestão no campo de texto
try {
    $stmtPac = $pdo->query("SELECT nome FROM paciente ORDER BY nome ASC");
    $listaPacientes = $stmtPac->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Falha ao carregar pacientes para encaixes: ' . $e->getMessage());
    $listaPacientes = [];
    $erroCarregamento = true;
}

try {
    $stmtRecentes = $pdo->query("
        SELECT id, paciente, medico, data_encaixe, horario, tipo_atendimento, motivo, criado_em, status
        FROM encaixes
        ORDER BY criado_em DESC, id DESC
        LIMIT 20
    ");
    $encaixesRecentes = $stmtRecentes->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Falha ao carregar encaixes recentes: ' . $e->getMessage());
    $encaixesRecentes = [];
    $erroCarregamento = true;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inserir Encaixe - Convelum Saúde</title>

    <link href="../../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/responsivo.css">

    <style>
        .horario-ocupado {
            opacity: 0.4;
            text-decoration: line-through;
            cursor: not-allowed;
        }

        /* Grade de horários: sem limite de altura ela cresce indefinidamente
           quando o médico tem muitos horários no dia e empurra a página
           inteira pra baixo. Agora tem altura máxima e rola por dentro. */
        #gridHorariosEncaixe {
            max-height: 260px;
            overflow-y: auto;
            padding-right: 4px;
        }
        #gridHorariosEncaixe::-webkit-scrollbar {
            width: 6px;
        }
        #gridHorariosEncaixe::-webkit-scrollbar-thumb {
            background: #c7c7c7;
            border-radius: 4px;
        }
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

<div id="painel-boasvindas">
    <div>
        <span id="subtitulo-painel">AGENDA GERAL</span>
        <h1 id="titulo-painel">Inserir encaixe</h1>
        <p id="texto-painel">Adicione um paciente num horário fora da agenda normal.</p>
    </div>
</div>

<?php if (isset($_GET['sucesso']) && $_GET['sucesso'] == '1'): ?>
    <div class="container mt-3" style="max-width: 900px;">
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> Encaixe inserido com sucesso na agenda!
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
<?php elseif (isset($_GET['erro'])): ?>
    <div class="container mt-3" style="max-width: 900px;">
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($_GET['erro']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
<?php endif; ?>
<?php if (isset($_GET['excluido']) && $_GET['excluido'] === '1'): ?>
    <div class="container mt-3" style="max-width: 900px;">
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Encaixe excluído com sucesso.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    </div>
<?php endif; ?>

<div class="secao-config-agenda">

    <form method="post" action="actions/salvar-encaixe.php" class="cartao-config-agenda" id="formEncaixe" onsubmit="return validarEncaixe();">
        <h2 class="titulo-cartao-config">Dados do encaixe</h2>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

        <div class="campo-config">
            <label for="pacienteEncaixe">Paciente</label>
            <input type="text" id="pacienteEncaixe" name="paciente" list="listaPacientesEncaixe"
                   placeholder="Digite o nome do paciente" required
                   oninput="verificarPacienteCadastrado(this.value)">
            <datalist id="listaPacientesEncaixe">
                <?php foreach ($listaPacientes as $p): ?>
                    <option value="<?php echo htmlspecialchars($p['nome']); ?>">
                <?php endforeach; ?>
            </datalist>
            <small class="text-muted" id="avisoPacienteNaoEncontrado" style="display:none; color:#b45309;">
                <i class="bi bi-exclamation-triangle"></i> Esse nome não está entre os pacientes cadastrados. Você pode continuar mesmo assim, mas confira se digitou certo.
            </small>
        </div>

        <div class="linha-config-dupla">
            <div class="campo-config">
                <label for="medicoEncaixe">Médico</label>
                <select id="medicoEncaixe" name="medico" required onchange="buscarHorariosDisponiveis()">
                    <option value="">Selecione o médico</option>
                    <?php foreach ($listaMedicos as $med): ?>
                        <option value="<?php echo (int)$med['id']; ?>" data-id="<?php echo (int)$med['id']; ?>">
                            <?php echo htmlspecialchars($med['nome']); ?><?php echo $med['especialidades'] ? ' — ' . htmlspecialchars($med['especialidades']) : ''; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($listaMedicos)): ?>
                    <small class="text-danger">Nenhum médico cadastrado no sistema ainda.</small>
                <?php endif; ?>
            </div>
            <div class="campo-config">
                <label for="dataEncaixe">Data</label>
                <input type="date" id="dataEncaixe" name="data" required onchange="buscarHorariosDisponiveis()" min="<?php echo date('Y-m-d'); ?>">
            </div>
        </div>

        <div class="campo-config">
            <label>Horário</label>

            <!-- Estado 1: nada selecionado ainda -->
            <p id="avisoSelecioneAntes" class="text-muted" style="font-size: 14px;">
                <i class="bi bi-info-circle"></i> Selecione o médico e a data para ver os horários disponíveis.
            </p>

            <!-- Estado 2: carregando -->
            <p id="carregandoHorarios" style="display:none; font-size: 14px;">
                <i class="bi bi-hourglass-split"></i> Buscando horários disponíveis...
            </p>

            <!-- Estado 3: nenhum horário livre -->
            <p id="semHorarios" class="text-danger" style="display:none; font-size: 14px;">
                <i class="bi bi-x-circle"></i> <span id="textoSemHorarios">Esse médico não tem horários livres nessa data. Tente outra data.</span>
            </p>

            <!-- Estado 4: grid de horários -->
            <div class="grid-horarios" id="gridHorariosEncaixe" style="display:none;"></div>

            <input type="hidden" name="horario" id="horarioEncaixeSelecionado" required>
        </div>

        <div class="campo-config">
            <label for="tipoEncaixe">Tipo de atendimento</label>
            <select id="tipoEncaixe" name="tipo">
                <option value="presencial">Presencial</option>
                <option value="tele">Teleconsulta</option>
            </select>
        </div>

        <div class="campo-config">
            <label for="motivoEncaixe">Motivo</label>
            <textarea id="motivoEncaixe" name="motivo" rows="2" placeholder="Motivo do encaixe (dor aguda, retorno urgente, etc.)"></textarea>
        </div>

        <button type="submit" class="botao-entrar botao-config-salvar" id="botaoSalvarEncaixe" disabled>Inserir encaixe</button>
    </form>

    <section class="cartao-config-agenda mt-4" aria-labelledby="tituloEncaixesRecentes">
        <h2 class="titulo-cartao-config" id="tituloEncaixesRecentes">Encaixes recentes</h2>
        <?php if ($erroCarregamento): ?>
            <div class="alert alert-danger" role="alert">Não foi possível carregar todos os dados. Atualize a página ou verifique a conexão com o banco.</div>
        <?php elseif (empty($encaixesRecentes)): ?>
            <p class="text-muted mb-0">Ainda não há encaixes cadastrados.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th>Paciente</th>
                            <th>Médico</th>
                            <th>Data e horário</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($encaixesRecentes as $encaixe): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($encaixe['paciente'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($encaixe['medico'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <?php echo (new DateTime($encaixe['data_encaixe']))->format('d/m/Y'); ?>
                                às <?php echo htmlspecialchars(substr($encaixe['horario'], 0, 5), ENT_QUOTES, 'UTF-8'); ?>
                            </td>
                            <td><?php echo htmlspecialchars(ucfirst($encaixe['status']), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="text-nowrap">
                                                <a class="btn btn-sm btn-outline-primary" href="detalhes-encaixe.php?id=<?php echo (int)$encaixe['id']; ?>">
                                                    <i class="bi bi-<?php echo in_array($encaixe['status'], ['pendente', 'confirmada'], true) ? 'pencil-square' : 'eye'; ?>"></i>
                                                    <?php echo in_array($encaixe['status'], ['pendente', 'confirmada'], true) ? 'Editar' : 'Ver'; ?>
                                </a>
                                <form method="post" action="actions/excluir-encaixe.php" class="d-inline" onsubmit="return confirm('Excluir definitivamente este encaixe? Esta ação não pode ser desfeita.');">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="id" value="<?php echo (int)$encaixe['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-trash"></i> Excluir
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

</div>

<script>
// Lista de nomes de pacientes cadastrados, pra avisar quando o nome digitado não bate com nenhum
const nomesPacientesCadastrados = <?php
    echo json_encode(array_column($listaPacientes, 'nome'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
?>;

// Texto padrão da mensagem de "sem horários", usado pra restaurar depois
// de um erro de rede (evita ficar preso com o texto do erro anterior).
const TEXTO_PADRAO_SEM_HORARIOS = 'Esse médico não tem horários livres nessa data. Tente outra data.';
let requisicaoHorariosEncaixe = 0;

function verificarPacienteCadastrado(valorDigitado) {
    const aviso = document.getElementById('avisoPacienteNaoEncontrado');
    const encontrado = nomesPacientesCadastrados.some(function (nome) {
        return nome.toLowerCase() === valorDigitado.trim().toLowerCase();
    });
    aviso.style.display = (!encontrado && valorDigitado.trim() !== '') ? 'block' : 'none';
}

function mostrarEstadoHorarios(estado) {
    document.getElementById('avisoSelecioneAntes').style.display = (estado === 'aguardando') ? 'block' : 'none';
    document.getElementById('carregandoHorarios').style.display  = (estado === 'carregando') ? 'block' : 'none';
    document.getElementById('semHorarios').style.display          = (estado === 'vazio') ? 'block' : 'none';
    document.getElementById('gridHorariosEncaixe').style.display  = (estado === 'lista') ? 'grid' : 'none';
}

function buscarHorariosDisponiveis() {
    const requisicaoAtual = ++requisicaoHorariosEncaixe;
    const selectMedico = document.getElementById('medicoEncaixe');
    const inputData = document.getElementById('dataEncaixe');
    const idMedico = selectMedico.options[selectMedico.selectedIndex]?.dataset.id;
    const data = inputData.value;

    const grid = document.getElementById('gridHorariosEncaixe');
    const inputEscondido = document.getElementById('horarioEncaixeSelecionado');
    const botaoSalvar = document.getElementById('botaoSalvarEncaixe');

    grid.innerHTML = '';
    inputEscondido.value = '';
    botaoSalvar.disabled = true;

    if (!idMedico || !data) {
        mostrarEstadoHorarios('aguardando');
        return;
    }

    mostrarEstadoHorarios('carregando');

    fetch('actions/horarios-disponiveis-encaixe.php?id_medico=' + encodeURIComponent(idMedico) + '&data=' + encodeURIComponent(data))
        .then(function (resp) {
            return resp.json().then(function (dados) {
                if (!resp.ok) {
                    throw new Error(dados.erro || 'Não foi possível consultar os horários.');
                }
                return dados;
            });
        })
        .then(function (dados) {
            if (requisicaoAtual !== requisicaoHorariosEncaixe) {
                return;
            }
            if (dados.erro) {
                mostrarEstadoHorarios('vazio');
                document.getElementById('textoSemHorarios').textContent = dados.erro;
                return;
            }

            if (!dados.horarios || dados.horarios.length === 0) {
                mostrarEstadoHorarios('vazio');
                document.getElementById('textoSemHorarios').textContent = TEXTO_PADRAO_SEM_HORARIOS;
                return;
            }

            dados.horarios.forEach(function (item) {
                const botao = document.createElement('button');
                botao.type = 'button';
                botao.className = 'horario-opcao' + (item.disponivel ? '' : ' horario-ocupado');
                botao.textContent = item.hora;

                if (!item.disponivel) {
                    botao.disabled = true;
                    botao.title = 'Horário já ocupado';
                } else {
                    botao.addEventListener('click', function () {
                        grid.querySelectorAll('.horario-opcao').forEach(function (b) { b.classList.remove('ativo'); });
                        botao.classList.add('ativo');
                        inputEscondido.value = item.hora;
                        botaoSalvar.disabled = false;
                    });
                }

                grid.appendChild(botao);
            });

            mostrarEstadoHorarios('lista');
        })
        .catch(function (erro) {
            if (requisicaoAtual !== requisicaoHorariosEncaixe) {
                return;
            }
            mostrarEstadoHorarios('vazio');
            document.getElementById('textoSemHorarios').textContent = erro.message || 'Erro ao buscar horários. Tente novamente.';
        });
}

function validarEncaixe() {
    const medico = document.getElementById('medicoEncaixe').value;
    const horario = document.getElementById('horarioEncaixeSelecionado').value;

    if (!medico) {
        alert('Selecione um médico cadastrado no sistema.');
        return false;
    }
    if (!horario) {
        alert('Selecione um horário disponível.');
        return false;
    }
    return true;
}

// Estado inicial
mostrarEstadoHorarios('aguardando');
</script>

</body>
</html>