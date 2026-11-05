<?php
// perfis/paciente/agendar.php
session_start();
require_once '../../conexao.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_perfil'] !== 'paciente') {
    header("Location: ../../auth/login.php");
    exit();
}

$pacienteId = $_SESSION['usuario_id'];

$erroAgendamento = isset($_GET['erro']) ? trim((string)$_GET['erro']) : '';
$stmtPaciente = $pdo->prepare("
    SELECT id, num_doc, tipo_documento, doc_comprovante, menor_idade,
           tipo_documento_resp, num_doc_resp, doc_comprovante_resp, id_convenio
    FROM paciente
    WHERE id = ?
    LIMIT 1
");
$stmtPaciente->execute([$pacienteId]);
$pacienteDados = $stmtPaciente->fetch(PDO::FETCH_ASSOC);
if (!$pacienteDados) {
    http_response_code(404);
    exit('Cadastro do paciente não encontrado.');
}

// Cadastros feitos pela equipe já têm o documento principal preenchido; só
// pedimos documentos no primeiro agendamento quando os dados estão ausentes.
$precisaDocumentoPaciente = empty($pacienteDados['num_doc']) || empty($pacienteDados['tipo_documento']);
$pacienteMenorCadastro = ($pacienteDados['menor_idade'] ?? 'N') === 'Y';
$precisaDocumentoResponsavel = $pacienteMenorCadastro
    && (empty($pacienteDados['tipo_documento_resp']) || empty($pacienteDados['num_doc_resp']));
$precisaDocumento = $precisaDocumentoPaciente || $precisaDocumentoResponsavel;
$precisaComprovantePaciente = empty($pacienteDados['doc_comprovante']);
$precisaComprovanteResponsavel = $pacienteMenorCadastro && empty($pacienteDados['doc_comprovante_resp']);

// 2. Buscar Especialidades
try {
    $stmtEspecialidades = $pdo->query("
        SELECT DISTINCT e.id, e.nome, e.preco
        FROM especialidade e
        INNER JOIN medico_especialidades me ON me.id_especialidade = e.id
        INNER JOIN medico m ON m.id = me.id_medico
        WHERE e.status = 'Ativo'
          AND m.status = 'Ativo'
        ORDER BY e.nome ASC
    ");
    $listaEspecialidades = $stmtEspecialidades->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Erro ao buscar especialidades para agendamento: ' . $e->getMessage());
    $erroAgendamento = 'Não foi possível carregar as especialidades. Tente novamente.';
    $listaEspecialidades = [];
}

// Convênios ativos e planos associados para a etapa após a escolha do médico.
$listaConvenios = [];
try {
    $stmtConvenios = $pdo->query("
        SELECT c.id, c.nome, p.id AS plano_id, p.nome_plano
        FROM convenios c
        LEFT JOIN convenio_planos p ON p.id_convenio = c.id
        WHERE c.status = 'Ativo'
        ORDER BY c.nome, p.nome_plano
    ");
    foreach ($stmtConvenios->fetchAll(PDO::FETCH_ASSOC) as $linha) {
        $idConvenio = (int)$linha['id'];
        if (!isset($listaConvenios[$idConvenio])) {
            $listaConvenios[$idConvenio] = [
                'id' => $idConvenio,
                'nome' => $linha['nome'],
                'planos' => [],
            ];
        }
        if ($linha['plano_id'] !== null) {
            $listaConvenios[$idConvenio]['planos'][] = [
                'id' => (int)$linha['plano_id'],
                'nome' => $linha['nome_plano'],
            ];
        }
    }

    $listaConvenios = array_values($listaConvenios);
} catch (PDOException $e) {
    error_log('Erro ao carregar convênios do agendamento: ' . $e->getMessage());
    $erroAgendamento = 'Não foi possível carregar os convênios. Tente novamente.';
}

// OBS: a lista de médicos não é mais carregada aqui.
// Agora ela é buscada via AJAX (actions/medicos-disponiveis.php) depois que
// o paciente escolhe especialidade + data + horário, pra só mostrar quem
// realmente está disponível naquele momento.

// 3. Preços por ID de especialidade para JS
$precosEspecialidade = [];
foreach ($listaEspecialidades as $esp) {
    $precosEspecialidade[$esp['id']] = (float)$esp['preco'];
}

$especialidadeCheckupId = null;
foreach ($listaEspecialidades as $especialidade) {
    if (strcasecmp(trim($especialidade['nome']), 'Clínico Geral') === 0) {
        $especialidadeCheckupId = (int)$especialidade['id'];
        break;
    }
}

$tipoInicial = $_GET['tipo'] ?? '';

$horariosDisponiveis = ['08:00', '08:30', '09:30', '10:00', '10:30', '13:30', '14:00', '15:00', '16:30'];

// Definição dos Pacotes de Check-up
$pacotes = [
    [
        'id' => 'basico',
        'nome' => 'Básico',
        'preco' => 180,
        'destaque' => false,
        'descricao' => 'Ideal para rotina anual e acompanhamento geral.',
        'itens' => [
            'Consulta com Clínico Geral',
            'Hemograma Completo',
            'Glicemia em Jejum',
            'Colesterol Total e Frações',
            'Exame de Urina (EAS)'
        ]
    ],
    [
        'id' => 'intermediario',
        'nome' => 'Intermediário',
        'preco' => 320,
        'destaque' => true,
        'descricao' => 'Avaliação detalhada com exames cardiológicos.',
        'itens' => [
            'Tudo do pacote Básico',
            'Eletrocardiograma (ECG)',
            'Taxa Hormonal (TSH / T4 Livre)',
            'Ureia e Creatinina (Função Renal)',
            'Retorno garantido em 30 dias'
        ]
    ],
    [
        'id' => 'completo',
        'nome' => 'Completo',
        'preco' => 550,
        'destaque' => false,
        'descricao' => 'Mapeamento amplo da saúde física e metabólica.',
        'itens' => [
            'Tudo do pacote Intermediário',
            'Ultrassonografia Abdominal Total',
            'Ecocardiograma ou Teste Ergométrico',
            'Vitamina D e Vitamina B12',
            'Avaliação Nutricional'
        ]
    ]
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agendar Consulta - Convelum Saúde</title>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="../../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/responsivo.css">

    <script src="https://unpkg.com/imask"></script>
    <style>
        .busca-especialidade {
            position: relative;
        }

        .lista-resultados-especialidade {
            position: absolute;
            top: 100%;
            right: 0;
            left: 0;
            z-index: 1050;
            max-height: 260px;
            overflow-y: auto;
            background: #fff;
            border: 1px solid #ced4da;
            border-radius: 8px;
            box-shadow: 0 6px 16px rgba(0, 0, 0, .15);
        }

        .resultado-especialidade {
            display: block;
            width: 100%;
            padding: 10px 14px;
            border: 0;
            border-bottom: 1px solid #eee;
            background: #fff;
            color: #212529;
            text-align: left;
            cursor: pointer;
        }

        .resultado-especialidade:hover,
        .resultado-especialidade:focus {
            background: #f1f5f8;
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

<div class="secao-config-agenda">
    <div class="cartao-config-agenda">
        <?php if ($erroAgendamento !== ''): ?>
            <div class="alert alert-warning" role="alert"><?php echo htmlspecialchars($erroAgendamento); ?></div>
        <?php endif; ?>

        <!-- INDICADOR DE ETAPAS -->
        <div class="indicador-etapas" id="indicadorEtapas">
            <span class="ponto-etapa ativo" data-ponto="1"></span>
            <span class="ponto-etapa" data-ponto="2"></span>
            <span class="ponto-etapa" data-ponto="3"></span>
        </div>

        <form method="post" action="actions/salvar-agendamento.php" id="formAgendar" enctype="multipart/form-data">

            <!-- ========================= ETAPA 1: TIPO DE AGENDAMENTO ========================= -->
            <div class="etapa-cadastro ativa" data-etapa="1">
                <h1>O que você quer agendar?</h1>
                <p class="login-subtitulo">Escolha o tipo de atendimento</p>

                <div class="grid-tipo-agendamento">
                    <button type="button" class="opcao-tipo-agendamento" data-tipo="presencial" onclick="escolherTipo('presencial')">
                        <i class="bi bi-hospital"></i>
                        <span class="opcao-tipo-titulo">Consulta presencial</span>
                        <span class="opcao-tipo-preco">a partir de R$110</span>
                    </button>

                    <button type="button" class="opcao-tipo-agendamento" data-tipo="tele" onclick="escolherTipo('tele')">
                        <i class="bi bi-camera-video"></i>
                        <span class="opcao-tipo-titulo">Teleconsulta</span>
                        <span class="opcao-tipo-preco">a partir de R$90</span>
                    </button>

                    <button type="button" class="opcao-tipo-agendamento" data-tipo="checkup" onclick="escolherTipo('checkup')" <?php echo $especialidadeCheckupId === null ? 'disabled title="Clínico Geral não está cadastrado ou ativo"' : ''; ?>>
                        <i class="bi bi-box-seam"></i>
                        <span class="opcao-tipo-titulo">Pacote de Check-up</span>
                        <span class="opcao-tipo-preco">Consulta e exames do pacote</span>
                    </button>
                </div>

                <input type="hidden" name="tipo" id="tipoSelecionado" value="">
            </div>

            <!-- ========================= ETAPA 1B: DOCUMENTOS (1º Agendamento) ========================= -->
            <div class="etapa-cadastro" data-etapa="documentos">
                <h1>Antes de continuar</h1>
                <p class="login-subtitulo">Complete os dados de documento que ainda faltam no seu cadastro.</p>

                <div class="linha-config-dupla">
                    <div class="campo-config" id="campoDocumentoPaciente">
                        <label for="tipoDocumentoPaciente">Tipo de documento</label>
                        <select id="tipoDocumentoPaciente" name="tipo_documento" onchange="atualizarMascaraDocumento()">
                            <option value="CPF" selected>CPF</option>
                            <option value="RG">RG</option>
                            <option value="CNH">CNH</option>
                            <option value="Passaporte">Passaporte</option>
                            <option value="Outro">Outro</option>
                        </select>
                    </div>
                    <div class="campo-config" id="campoNumeroDocumentoPaciente">
                        <label for="numeroDocumentoPaciente">Número do documento</label>
                        <input type="text" id="numeroDocumentoPaciente" name="numero_documento" value="<?php echo htmlspecialchars($pacienteDados['num_doc'] ?? ''); ?>" placeholder="000.000.000-00">
                    </div>
                </div>

                <div id="blocoDocumentoResponsavel" style="display:none;">
                    <h2 class="titulo-cartao-config">Documento do responsável</h2>
                    <p class="text-muted">Como o paciente é menor de idade, também precisamos do documento do responsável legal.</p>
                    <div class="linha-config-dupla">
                        <div class="campo-config">
                            <label for="tipoDocumentoResponsavel">Tipo de documento do responsável</label>
                            <select id="tipoDocumentoResponsavel" name="tipo_documento_responsavel">
                                <option value="">Selecione</option>
                                <?php foreach (['CPF', 'RG', 'CNH', 'Passaporte', 'Outro'] as $tipoDocResponsavel): ?>
                                    <option value="<?php echo htmlspecialchars($tipoDocResponsavel); ?>" <?php echo ($pacienteDados['tipo_documento_resp'] ?? '') === $tipoDocResponsavel ? 'selected' : ''; ?>><?php echo htmlspecialchars($tipoDocResponsavel); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="campo-config">
                            <label for="numeroDocumentoResponsavel">Número do documento do responsável</label>
                            <input type="text" id="numeroDocumentoResponsavel" name="numero_documento_responsavel" value="<?php echo htmlspecialchars($pacienteDados['num_doc_resp'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <div class="campo-config">
                    <label for="enderecoPaciente">Endereço completo</label>
                    <input type="text" id="enderecoPaciente" name="endereco" placeholder="Rua, número, bairro, cidade">
                </div>

                <!-- ========== ESCOLHA: ENVIAR AGORA OU LEVAR NA RECEPÇÃO ========== -->
                <div class="campo-config" id="opcoesModoDocumento">
                    <label>Como você quer confirmar o documento?</label>
                    <div class="grid-dias-semana">
                        <button type="button" class="dia-semana-opcao ativo" data-modo="agora" onclick="escolherModoDocumento(this)">
                            <i class="bi bi-upload"></i> Enviar agora
                        </button>
                        <button type="button" class="dia-semana-opcao" data-modo="recepcao" onclick="escolherModoDocumento(this)">
                            <i class="bi bi-hospital"></i> Levar na recepção
                        </button>
                    </div>
                    <input type="hidden" name="modo_documento" id="modoDocumentoSelecionado" value="agora">
                </div>

                <!-- Upload — só aparece quando "Enviar agora" está selecionado -->
                <div class="campo-config" id="blocoUploadDocumento">
                    <div id="blocoUploadPaciente">
                        <label for="documentoUpload">Foto ou scan do documento do paciente</label>
                        <input type="file" id="documentoUpload" name="foto_documento" accept=".pdf,.jpg,.jpeg,.png" onchange="mostrarNomeArquivoDocumento(this, 'nomeArquivoDocumento')">
                        <span class="nome-arquivo-anexado" id="nomeArquivoDocumento"></span>
                    </div>
                    <div id="blocoUploadResponsavel" style="display:none;">
                        <label for="documentoResponsavelUpload">Foto ou scan do documento do responsável</label>
                        <input type="file" id="documentoResponsavelUpload" name="foto_documento_responsavel" accept=".pdf,.jpg,.jpeg,.png" onchange="mostrarNomeArquivoDocumento(this, 'nomeArquivoResponsavel')">
                        <span class="nome-arquivo-anexado" id="nomeArquivoResponsavel"></span>
                    </div>
                </div>

                <!-- Aviso — só aparece quando "Levar na recepção" está selecionado -->
                <div class="aviso-recuperacao" id="avisoLevarRecepcao" style="display:none; background:#fff3cd; color:#7a5b00; border-radius:8px; padding:12px 16px;">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    Você poderá apresentar o documento do paciente<?php echo ($pacienteDados['menor_idade'] ?? 'N') === 'Y' ? ' e do responsável legal' : ''; ?> pessoalmente na recepção, até o dia da consulta.
                </div>

                <p class="aviso-recuperacao">
                    Esses dados ficam salvos no seu cadastro para não serem solicitados novamente.
                </p>

                <div class="botoes-etapa">
                    <button type="button" class="botao-voltar-etapa" onclick="mostrarEtapa(1)">Voltar</button>
                    <button type="button" class="botao-entrar" onclick="avancarDeDocumentos()">Próximo</button>
                </div>
            </div>

            <!-- ========================= ETAPA 2A: CONSULTA / TELECONSULTA ========================= -->
            <div class="etapa-cadastro" data-etapa="2-consulta">
                <h1 id="tituloEtapaConsulta">Detalhes da consulta</h1>
                <p class="login-subtitulo">Pesquise a especialidade e escolha a data e o horário. O médico é escolhido no próximo passo, de acordo com a disponibilidade.</p>

                <div class="campo-config" id="blocoBuscaEspecialidade">
                    <label for="pesquisarEspecialidade">Buscar especialidade</label>
                    <div class="busca-especialidade">
                        <input type="search" id="pesquisarEspecialidade" placeholder="Digite o nome da especialidade" autocomplete="off" onfocus="filtrarEspecialidades()" oninput="filtrarEspecialidades()" aria-controls="resultadosEspecialidade" aria-expanded="false">
                        <div id="resultadosEspecialidade" class="lista-resultados-especialidade" role="listbox" style="display:none;"></div>
                    </div>
                    <p id="especialidadeSelecionada" class="aviso-recuperacao" aria-live="polite">Nenhuma especialidade selecionada.</p>
                    <input type="hidden" id="especialidade" name="especialidade_id" value="">
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

                <div class="botoes-etapa">
                    <button type="button" class="botao-voltar-etapa" onclick="voltarParaEtapaInicial()">Voltar</button>
                    <button type="button" class="botao-entrar" onclick="abrirEtapaMedico()">Próximo</button>
                </div>
            </div>

            <!-- ========================= ETAPA 2B: ESCOLHA DO MÉDICO DISPONÍVEL ========================= -->
            <div class="etapa-cadastro" data-etapa="2-medico">
                <h1>Escolha o médico</h1>
                <p class="login-subtitulo">Estes são os médicos dessa especialidade disponíveis na data e horário escolhidos</p>

                <div class="grid-tipo-agendamento" id="listaMedicosDisponiveis"></div>
                <p class="aviso-recuperacao" id="avisoSemMedico" style="display:none;">
                    Nenhum médico disponível nesse dia/horário. Volte e escolha outra data ou horário.
                </p>

                <input type="hidden" name="medico_id" id="medicoSelecionadoId" value="">
                <input type="hidden" id="medicoSelecionadoNome" value="">

                <div class="botoes-etapa">
                    <button type="button" class="botao-voltar-etapa" onclick="mostrarEtapa('2-consulta')">Voltar</button>
                    <button type="button" class="botao-entrar" id="botaoProximoMedico" onclick="avancarParaConvenio()" disabled>Próximo</button>
                </div>
            </div>

            <!-- ========================= ETAPA 2C: CONVÊNIO ========================= -->
            <div class="etapa-cadastro" data-etapa="2-convenio">
                <h1>Convênio</h1>
                <p class="login-subtitulo">Você tem convênio médico?</p>
                <div class="grid-dias-semana">
                    <button type="button" class="dia-semana-opcao" data-possui-convenio="1" onclick="escolherPossuiConvenio(true)">Sim, tenho convênio</button>
                    <button type="button" class="dia-semana-opcao" data-possui-convenio="0" onclick="escolherPossuiConvenio(false)">Não, vou como particular</button>
                </div>
                <input type="hidden" name="possui_convenio" id="possuiConvenio" value="">

                <div id="blocoEscolhaConvenio" style="display:none;">
                    <?php if (!empty($listaConvenios)): ?>
                        <div class="campo-config">
                            <label for="pesquisarConvenio">Pesquisar convênio</label>
                            <div class="busca-especialidade">
                                <input type="search" id="pesquisarConvenio" placeholder="Digite o nome do convênio" autocomplete="off" onfocus="filtrarConvenios()" oninput="filtrarConvenios()" aria-controls="resultadosConvenio" aria-expanded="false">
                                <div id="resultadosConvenio" class="lista-resultados-especialidade" role="listbox" style="display:none;"></div>
                            </div>
                            <p id="convenioEscolhidoTexto" class="aviso-recuperacao" aria-live="polite">Nenhum convênio selecionado.</p>
                            <input type="hidden" name="convenio_id" id="convenioSelecionado" value="">
                        </div>
                        <div class="campo-config" id="blocoPlanosConvenio" style="display:none;">
                            <label for="planoSelecionado">Plano</label>
                            <select name="plano_id" id="planoSelecionado">
                                <option value="">Selecione o plano</option>
                            </select>
                        </div>
                    <?php else: ?>
                        <p class="alert alert-info">No momento não há convênios ativos cadastrados. Selecione a opção particular para continuar.</p>
                    <?php endif; ?>
                </div>

                <div class="botoes-etapa">
                    <button type="button" class="botao-voltar-etapa" onclick="mostrarEtapa('2-medico')">Voltar</button>
                    <button type="button" class="botao-entrar" onclick="avancarParaConfirmacao()">Próximo</button>
                </div>
            </div>

            <!-- ========================= ETAPA 2C: PACOTE DE CHECK-UP ========================= -->
            <div class="etapa-cadastro" data-etapa="2-checkup">
                <h1>Escolha seu pacote de Check-up</h1>
                <p class="login-subtitulo">Escolha o pacote e depois agende a consulta presencial com Clínico Geral. O pagamento será feito na recepção.</p>

                <div class="grid-tipo-agendamento mb-4">
                    <?php foreach ($pacotes as $pacote): ?>
                        <div class="opcao-tipo-agendamento cartao-pacote-item"
                             id="card-pacote-<?php echo $pacote['id']; ?>"
                             onclick="escolherPacote('<?php echo htmlspecialchars($pacote['nome'], ENT_QUOTES, 'UTF-8'); ?>', <?php echo (float)$pacote['preco']; ?>, '<?php echo htmlspecialchars($pacote['id'], ENT_QUOTES, 'UTF-8'); ?>')">
                            
                            <?php if ($pacote['destaque']): ?>
                                <span class="selo-pacote-popular selo-pacote-popular-mini">Mais popular</span>
                            <?php endif; ?>

                            <span class="opcao-tipo-titulo text-start d-block mt-2"><?php echo htmlspecialchars($pacote['nome']); ?></span>
                            <span class="opcao-tipo-preco text-start d-block mb-2">R$ <?php echo number_format($pacote['preco'], 2, ',', '.'); ?></span>
                            
                            <p class="aviso-recuperacao text-start m-0 mb-3"><?php echo htmlspecialchars($pacote['descricao']); ?></p>

                            <ul class="list-unstyled text-start w-100 m-0 border-top pt-2">
                                <?php foreach ($pacote['itens'] as $item): ?>
                                    <li class="small mb-1 text-secondary">
                                        <i class="bi bi-check2-circle text-primary"></i> <?php echo htmlspecialchars($item); ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                </div>

                <input type="hidden" name="pacote" id="pacoteSelecionado" value="">

                <div class="botoes-etapa">
                    <button type="button" class="botao-voltar-etapa" onclick="mostrarEtapa(precisaDocumento ? 'documentos' : 1)">Voltar</button>
                    <button type="button" class="botao-entrar" onclick="avancarDoPacote()">Continuar para agendamento</button>
                </div>
            </div>

            <!-- ========================= ETAPA 3: CONFIRMAÇÃO ========================= -->
            <div class="etapa-cadastro" data-etapa="confirmacao">
                <h1>Confirme seu agendamento</h1>
                <p class="login-subtitulo">Confira os dados antes de enviar a solicitação</p>

                <div class="resumo-agendamento p-3 bg-light rounded-3 mb-3" id="resumoAgendamento"></div>

                <div class="alert alert-info" role="status">
                    <strong>Pagamento presencial na recepção:</strong> o pagamento deverá ser feito no dia da consulta, antes do atendimento.
                    <div id="avisoPagamentoTeleconsulta" style="display:none;">
                        Para teleconsultas, você receberá mais informações depois da confirmação do agendamento.
                    </div>
                </div>

                <div class="botoes-etapa">
                    <button type="button" class="botao-voltar-etapa" onclick="mostrarEtapa('2-convenio')">Voltar</button>
                    <button type="button" class="botao-entrar" onclick="confirmarAgendamento()">Solicitar agendamento</button>
                </div>
            </div>

        </form>
    </div>
</div>

<script>
const horariosDisponiveis = <?php echo json_encode($horariosDisponiveis); ?>;
const tipoInicial = <?php echo json_encode($tipoInicial); ?>;
const precisaDocumento = <?php echo json_encode($precisaDocumento); ?>;
const conveniosDisponiveis = <?php echo json_encode($listaConvenios, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
const especialidadesDisponiveis = <?php echo json_encode(array_map(function ($especialidade) {
    return [
        'id' => (int)$especialidade['id'],
        'nome' => $especialidade['nome'],
        'preco' => (float)$especialidade['preco'],
    ];
}, $listaEspecialidades), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
const convenioAtualPaciente = <?php echo json_encode((int)($pacienteDados['id_convenio'] ?? 0)); ?>;
const pacienteMenor = <?php echo json_encode(($pacienteDados['menor_idade'] ?? 'N') === 'Y'); ?>;
const precisaDocumentoPaciente = <?php echo json_encode($precisaDocumentoPaciente); ?>;
const precisaDocumentoResponsavel = <?php echo json_encode($precisaDocumentoResponsavel); ?>;
const precisaComprovantePaciente = <?php echo json_encode($precisaComprovantePaciente); ?>;
const precisaComprovanteResponsavel = <?php echo json_encode($precisaComprovanteResponsavel); ?>;
const tipoDocumentoSalvo = <?php echo json_encode($pacienteDados['tipo_documento'] ?? 'CPF'); ?>;
const tipoDocumentoResponsavelSalvo = <?php echo json_encode($pacienteDados['tipo_documento_resp'] ?? ''); ?>;
const especialidadeCheckupId = <?php echo json_encode($especialidadeCheckupId); ?>;
const especialidadeCheckupNome = 'Clínico Geral';

let tipoEscolhido = '';
let pacoteEscolhido = '';
let precoPacoteEscolhido = 0;
let horarioConsulta = '';
let modoDocumentoEscolhido = 'agora';
let mascaraDocumento = null;

const etapas = document.querySelectorAll('.etapa-cadastro');
const pontos = document.querySelectorAll('.ponto-etapa');

const pontoPorEtapa = {
    '1': '1',
    'documentos': '2',
    '2-consulta': '2',
    '2-medico': '2',
    '2-convenio': '2',
    '2-checkup': '2',
    'confirmacao': '3',
};

function aplicarMascaraDocumento() {
    const selectTipo = document.getElementById('tipoDocumentoPaciente');
    const inputDoc = document.getElementById('numeroDocumentoPaciente');
    
    if (mascaraDocumento) {
        mascaraDocumento.destroy();
    }

    const tipo = selectTipo.value;

    if (tipo === 'CPF') {
        inputDoc.placeholder = '000.000.000-00';
        mascaraDocumento = IMask(inputDoc, { mask: '000.000.000-00' });
    } else if (tipo === 'RG') {
        inputDoc.placeholder = '00.000.000-0';
        mascaraDocumento = IMask(inputDoc, { mask: '00.000.000-0' });
    } else if (tipo === 'CNH') {
        inputDoc.placeholder = '00000000000';
        mascaraDocumento = IMask(inputDoc, { mask: '00000000000' });
    } else {
        inputDoc.placeholder = 'Número do passaporte';
        mascaraDocumento = IMask(inputDoc, { mask: /^[a-zA-Z0-9]*$/ });
    }
}

function atualizarMascaraDocumento() {
    const inputDoc = document.getElementById('numeroDocumentoPaciente');
    inputDoc.value = '';
    aplicarMascaraDocumento();
}

function mostrarEtapa(chave) {
    etapas.forEach(function (etapa) {
        etapa.classList.toggle('ativa', etapa.dataset.etapa === String(chave));
    });
    pontos.forEach(function (ponto) {
        ponto.classList.toggle('ativo', ponto.dataset.ponto === (pontoPorEtapa[chave] || '1'));
    });
}

function montarGradeHorarios(idContainer, idInputEscondido) {
    const grid = document.getElementById(idContainer);
    grid.innerHTML = '';
    document.getElementById(idInputEscondido).value = '';
    horarioConsulta = '';
    horariosDisponiveis.forEach(function (hora) {
        const botao = document.createElement('button');
        botao.type = 'button';
        botao.className = 'horario-opcao';
        botao.textContent = hora;
        botao.addEventListener('click', function () {
            grid.querySelectorAll('.horario-opcao').forEach(function (b) { b.classList.remove('ativo'); });
            botao.classList.add('ativo');
            document.getElementById(idInputEscondido).value = hora;
            horarioConsulta = hora;
        });
        grid.appendChild(botao);
    });
}

function escolherTipo(tipo) {
    if (!['presencial', 'tele', 'checkup'].includes(tipo)) {
        alert('Tipo de agendamento inválido.');
        return;
    }
    if (tipo === 'checkup' && !especialidadeCheckupId) {
        alert('O pacote de Check-up exige uma especialidade Clínica Geral ativa.');
        return;
    }
    tipoEscolhido = tipo;
    document.getElementById('tipoSelecionado').value = tipo;

    document.querySelectorAll('.opcao-tipo-agendamento').forEach(function (b) {
        b.classList.toggle('ativo', b.dataset.tipo === tipo);
    });

    if (precisaDocumento) {
        mostrarEtapa('documentos');
    } else if (tipo === 'checkup') {
        mostrarEtapa('2-checkup');
    } else {
        irParaEtapaDoTipo();
    }
}

// ================== ESCOLHA DO MODO DE ENVIO DO DOCUMENTO ==================

function escolherModoDocumento(botao) {
    document.querySelectorAll('.campo-config .grid-dias-semana .dia-semana-opcao').forEach(function (b) {
        if (b.dataset.modo) b.classList.remove('ativo');
    });
    botao.classList.add('ativo');
    modoDocumentoEscolhido = botao.dataset.modo;
    document.getElementById('modoDocumentoSelecionado').value = modoDocumentoEscolhido;

    const blocoUpload = document.getElementById('blocoUploadDocumento');
    const avisoRecepcao = document.getElementById('avisoLevarRecepcao');
    const uploadPaciente = document.getElementById('documentoUpload');
    const uploadResponsavel = document.getElementById('documentoResponsavelUpload');

    if (modoDocumentoEscolhido === 'recepcao') {
        blocoUpload.style.display = 'none';
        avisoRecepcao.style.display = 'block';
        uploadPaciente.value = '';
        if (uploadResponsavel) uploadResponsavel.value = '';
        document.getElementById('nomeArquivoDocumento').textContent = '';
        document.getElementById('nomeArquivoResponsavel').textContent = '';
    } else {
        blocoUpload.style.display = '';
        avisoRecepcao.style.display = 'none';
    }
}

function avancarDeDocumentos() {
    const numDoc = document.getElementById('numeroDocumentoPaciente').value.trim();

    if (precisaDocumentoPaciente && !numDoc) {
        alert('Informe o número do documento do paciente para continuar.');
        return;
    }

    if (pacienteMenor) {
        const tipoResp = document.getElementById('tipoDocumentoResponsavel').value;
        const numeroResp = document.getElementById('numeroDocumentoResponsavel').value.trim();
        if (precisaDocumentoResponsavel && (!tipoResp || !numeroResp)) {
            alert('Informe o tipo e o número do documento do responsável legal.');
            return;
        }
    }

    if (modoDocumentoEscolhido === 'agora') {
        const arquivoPaciente = !precisaComprovantePaciente || document.getElementById('documentoUpload').files.length;
        const arquivoResponsavel = !precisaComprovanteResponsavel || document.getElementById('documentoResponsavelUpload').files.length;
        if (!arquivoPaciente || !arquivoResponsavel) {
            alert(pacienteMenor
                ? 'Anexe os documentos do paciente e do responsável, ou escolha "Levar na recepção".'
                : 'Anexe o documento ou escolha "Levar na recepção".');
            return;
        }
    }

    if (tipoEscolhido === 'checkup') {
        mostrarEtapa('2-checkup');
    } else {
        irParaEtapaDoTipo();
    }
}

function irParaEtapaDoTipo() {
    if (tipoEscolhido === 'checkup') {
        if (!pacoteEscolhido) {
            mostrarEtapa('2-checkup');
            return;
        }
        document.getElementById('blocoBuscaEspecialidade').style.display = 'none';
        document.getElementById('especialidade').value = String(especialidadeCheckupId);
        document.getElementById('especialidadeSelecionada').textContent = 'Selecionada: ' + especialidadeCheckupNome;
        document.getElementById('tituloEtapaConsulta').textContent = 'Agende a consulta do seu Check-up';
    } else {
        document.getElementById('blocoBuscaEspecialidade').style.display = '';
        document.getElementById('tituloEtapaConsulta').textContent =
            tipoEscolhido === 'tele' ? 'Detalhes da teleconsulta' : 'Detalhes da consulta';
    }
    montarGradeHorarios('gridHorariosConsulta', 'horarioSelecionadoConsulta');
    mostrarEtapa('2-consulta');
}

function voltarParaEtapaInicial() {
    if (tipoEscolhido === 'checkup') {
        mostrarEtapa('2-checkup');
        return;
    }
    mostrarEtapa(precisaDocumento ? 'documentos' : 1);
}

// ================== ETAPA DE MÉDICO DISPONÍVEL ==================

function abrirEtapaMedico() {
    const especialidadeId = document.getElementById('especialidade').value;
    const data = document.getElementById('dataConsulta').value;

    if (tipoEscolhido === 'checkup' && !document.getElementById('pacoteSelecionado').value) {
        mostrarEtapa('2-checkup');
        alert('Escolha um pacote de Check-up para continuar.');
        return;
    }

    if (!especialidadeId) {
        alert('Pesquise e selecione uma especialidade para continuar.');
        return;
    }

    if (!data || !horarioConsulta) {
        alert('Escolha a data e o horário para continuar.');
        return;
    }

    carregarMedicosDisponiveis(especialidadeId, data, horarioConsulta, tipoEscolhido === 'tele' ? 'tele' : 'presencial');
}

function filtrarEspecialidades() {
    const campo = document.getElementById('pesquisarEspecialidade');
    const resultados = document.getElementById('resultadosEspecialidade');
    const termo = campo.value.trim().toLocaleLowerCase('pt-BR');
    const especialidadeSelecionada = document.getElementById('especialidadeSelecionada');
    if (campo.value !== especialidadeSelecionada.textContent.replace(/^Selecionada:\s*/, '')) {
        document.getElementById('especialidade').value = '';
        especialidadeSelecionada.textContent = 'Nenhuma especialidade selecionada.';
    }
    resultados.replaceChildren();

    const encontrados = especialidadesDisponiveis.filter(function (especialidade) {
        return especialidade.nome.toLocaleLowerCase('pt-BR').includes(termo);
    });
    if (!encontrados.length) {
        const vazio = document.createElement('p');
        vazio.className = 'aviso-recuperacao';
        vazio.textContent = termo ? 'Nenhuma especialidade encontrada.' : 'Nenhuma especialidade cadastrada.';
        resultados.appendChild(vazio);
    } else {
        encontrados.forEach(function (especialidade) {
            const opcao = document.createElement('button');
            opcao.type = 'button';
            opcao.className = 'resultado-especialidade';
            opcao.setAttribute('role', 'option');
            opcao.textContent = especialidade.nome + ' (R$ ' +
                Number(especialidade.preco).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ')';
            opcao.addEventListener('click', function () {
                document.getElementById('especialidade').value = String(especialidade.id);
                campo.value = especialidade.nome;
                document.getElementById('especialidadeSelecionada').textContent = 'Selecionada: ' + especialidade.nome;
                resultados.style.display = 'none';
                campo.setAttribute('aria-expanded', 'false');
            });
            resultados.appendChild(opcao);
        });
    }
    resultados.style.display = 'block';
    campo.setAttribute('aria-expanded', 'true');
}

function carregarMedicosDisponiveis(especialidadeId, data, horario, tipo) {
    const lista = document.getElementById('listaMedicosDisponiveis');
    const aviso = document.getElementById('avisoSemMedico');
    const botaoProximo = document.getElementById('botaoProximoMedico');

    lista.className = 'grid-tipo-agendamento';
    lista.innerHTML = '<p class="aviso-recuperacao">Buscando médicos disponíveis...</p>';
    aviso.style.display = 'none';
    botaoProximo.disabled = true;
    document.getElementById('medicoSelecionadoId').value = '';
    document.getElementById('medicoSelecionadoNome').value = '';

    const params = new URLSearchParams({
        especialidade_id: especialidadeId,
        data: data,
        horario: horario,
        tipo: tipo
    });

    fetch('actions/medicos-disponiveis.php?' + params.toString())
        .then(function (r) {
            return r.json().then(function (resp) {
                if (!r.ok) throw new Error(resp.erro || 'Não foi possível consultar os médicos.');
                return resp;
            });
        })
        .then(function (resp) {
            lista.innerHTML = '';

            if (!resp.sucesso || !resp.medicos || !resp.medicos.length) {
                if (resp.erro) {
                    lista.textContent = resp.erro;
                    lista.className = 'aviso-recuperacao';
                    aviso.style.display = 'none';
                } else {
                    aviso.style.display = 'block';
                }
                mostrarEtapa('2-medico');
                return;
            }

            resp.medicos.forEach(function (med) {
                const botao = document.createElement('button');
                botao.type = 'button';
                botao.className = 'opcao-tipo-agendamento';
                const icone = document.createElement('i');
                icone.className = 'bi bi-person-badge';
                const nome = document.createElement('span');
                nome.className = 'opcao-tipo-titulo';
                nome.textContent = med.nome;
                botao.append(icone, nome);
                botao.addEventListener('click', function () {
                    lista.querySelectorAll('.opcao-tipo-agendamento').forEach(function (b) { b.classList.remove('ativo'); });
                    botao.classList.add('ativo');
                    document.getElementById('medicoSelecionadoId').value = med.id;
                    document.getElementById('medicoSelecionadoNome').value = med.nome;
                    botaoProximo.disabled = false;
                });
                lista.appendChild(botao);
            });

            mostrarEtapa('2-medico');
        })
        .catch(function (erro) {
            lista.textContent = erro.message || 'Erro ao buscar médicos disponíveis. Tente novamente.';
            lista.className = 'aviso-recuperacao';
            aviso.style.display = 'none';
            mostrarEtapa('2-medico');
        });
}

// ================== CHECK-UP ==================

function escolherPacote(nome, preco, cardId) {
    pacoteEscolhido = nome;
    precoPacoteEscolhido = preco;
    document.getElementById('pacoteSelecionado').value = cardId;
    
    document.querySelectorAll('.cartao-pacote-item').forEach(function (card) {
        card.classList.remove('ativo');
    });
    
    const cardSelecionado = document.getElementById('card-pacote-' + cardId);
    if (cardSelecionado) {
        cardSelecionado.classList.add('ativo');
    }
}

function avancarDoPacote() {
    if (!pacoteEscolhido || !document.getElementById('pacoteSelecionado').value) {
        alert('Escolha um pacote de Check-up para continuar.');
        return;
    }
    irParaEtapaDoTipo();
}

function mostrarNomeArquivoDocumento(input, idNome) {
    const nome = document.getElementById(idNome);
    nome.textContent = input.files.length ? '📎 ' + input.files[0].name : '';
}

function avancarParaConvenio() {
    if (!document.getElementById('medicoSelecionadoId').value) {
        alert('Escolha um médico disponível para continuar.');
        return;
    }
    mostrarEtapa('2-convenio');
    if (convenioAtualPaciente && conveniosDisponiveis.some(function (c) { return c.id === convenioAtualPaciente; })) {
        escolherPossuiConvenio(true);
    }
}

function escolherPossuiConvenio(possui) {
    if (possui && !conveniosDisponiveis.length) {
        alert('Não há convênios ativos cadastrados. Selecione a opção particular.');
        return;
    }
    const valor = possui ? '1' : '0';
    document.getElementById('possuiConvenio').value = valor;
    document.querySelectorAll('[data-possui-convenio]').forEach(function (botao) {
        botao.classList.toggle('ativo', botao.dataset.possuiConvenio === valor);
    });
    document.getElementById('blocoEscolhaConvenio').style.display = possui ? 'block' : 'none';
    if (possui) {
        if (convenioAtualPaciente) {
            const convenioAtual = conveniosDisponiveis.find(function (c) { return c.id === convenioAtualPaciente; });
            if (convenioAtual) selecionarConvenio(convenioAtual);
            else renderizarResultadosConvenios();
        } else {
            renderizarResultadosConvenios();
        }
    } else {
        const convenioSelect = document.getElementById('convenioSelecionado');
        const planoSelect = document.getElementById('planoSelecionado');
        const blocoPlanos = document.getElementById('blocoPlanosConvenio');
        document.getElementById('pesquisarConvenio').value = '';
        document.getElementById('convenioEscolhidoTexto').textContent = 'Nenhum convênio selecionado.';
        document.getElementById('resultadosConvenio').style.display = 'none';
        document.getElementById('resultadosConvenio').replaceChildren();
        if (convenioSelect) convenioSelect.value = '';
        if (planoSelect) planoSelect.value = '';
        if (blocoPlanos) blocoPlanos.style.display = 'none';
    }
}

function filtrarConvenios() {
    const termo = document.getElementById('pesquisarConvenio').value.trim().toLocaleLowerCase('pt-BR');
    const idSelecionado = document.getElementById('convenioSelecionado').value;
    const selecionado = conveniosDisponiveis.find(function (item) { return String(item.id) === idSelecionado; });
    if (selecionado && document.getElementById('pesquisarConvenio').value !== selecionado.nome) {
        document.getElementById('convenioSelecionado').value = '';
        document.getElementById('convenioEscolhidoTexto').textContent = 'Nenhum convênio selecionado.';
        document.getElementById('blocoPlanosConvenio').style.display = 'none';
    }
    renderizarResultadosConvenios(termo);
}

function renderizarResultadosConvenios(termoInicial) {
    const campo = document.getElementById('pesquisarConvenio');
    const resultados = document.getElementById('resultadosConvenio');
    const termo = (termoInicial === undefined ? campo.value.trim() : termoInicial).toLocaleLowerCase('pt-BR');
    resultados.replaceChildren();
    const encontrados = conveniosDisponiveis.filter(function (convenio) {
        return convenio.nome.toLocaleLowerCase('pt-BR').includes(termo);
    });
    if (!encontrados.length) {
        const vazio = document.createElement('p');
        vazio.className = 'aviso-recuperacao';
        vazio.textContent = 'Nenhum convênio encontrado.';
        resultados.appendChild(vazio);
    } else {
        encontrados.forEach(function (convenio) {
            const opcao = document.createElement('button');
            opcao.type = 'button';
            opcao.className = 'resultado-especialidade';
            opcao.setAttribute('role', 'option');
            opcao.textContent = convenio.nome;
            opcao.addEventListener('click', function () { selecionarConvenio(convenio); });
            resultados.appendChild(opcao);
        });
    }
    resultados.style.display = 'block';
    campo.setAttribute('aria-expanded', 'true');
}

function selecionarConvenio(convenio) {
    document.getElementById('convenioSelecionado').value = String(convenio.id);
    document.getElementById('pesquisarConvenio').value = convenio.nome;
    document.getElementById('convenioEscolhidoTexto').textContent = 'Convênio selecionado: ' + convenio.nome;
    document.getElementById('resultadosConvenio').style.display = 'none';
    document.getElementById('pesquisarConvenio').setAttribute('aria-expanded', 'false');
    atualizarPlanosConvenio(convenio);
}

function atualizarPlanosConvenio(convenioSelecionado) {
    const selectPlano = document.getElementById('planoSelecionado');
    const blocoPlanos = document.getElementById('blocoPlanosConvenio');
    if (!selectPlano || !blocoPlanos) return;
    const convenio = convenioSelecionado || conveniosDisponiveis.find(function (item) {
        return String(item.id) === document.getElementById('convenioSelecionado').value;
    });
    selectPlano.innerHTML = '<option value="">Selecione o plano</option>';
    if (!convenio) {
        blocoPlanos.style.display = 'none';
        return;
    }
    if (!convenio.planos.length) {
        selectPlano.innerHTML = '<option value="">Nenhum plano cadastrado para este convênio</option>';
        blocoPlanos.style.display = 'block';
        return;
    }
    convenio.planos.forEach(function (plano) {
        const option = document.createElement('option');
        option.value = String(plano.id);
        option.textContent = plano.nome;
        selectPlano.appendChild(option);
    });
    blocoPlanos.style.display = 'block';
}

function avancarParaConfirmacao() {
    const possuiConvenio = document.getElementById('possuiConvenio').value;
    if (possuiConvenio === '') {
        alert('Informe se você tem convênio.');
        return;
    }

    let nomeConvenio = 'Particular';
    if (possuiConvenio === '1') {
        const idConvenio = Number(document.getElementById('convenioSelecionado').value);
        const convenio = conveniosDisponiveis.find(function (item) { return item.id === idConvenio; });
        if (!convenio) {
            alert('Selecione um convênio ativo.');
            return;
        }
        if (convenio.planos.length && !document.getElementById('planoSelecionado').value) {
            alert('Selecione o plano do convênio.');
            return;
        }
        nomeConvenio = convenio.nome;
    }

    const espNome = document.getElementById('especialidadeSelecionada').textContent.replace(/^Selecionada:\s*/, '');
    const resumo = document.getElementById('resumoAgendamento');
    const dadosResumo = [
        ['Tipo', tipoEscolhido === 'checkup' ? 'Pacote de Check-up' : (tipoEscolhido === 'tele' ? 'Teleconsulta' : 'Consulta presencial')],
        ['Especialidade', espNome],
        ['Médico', document.getElementById('medicoSelecionadoNome').value],
        ['Data', formatarData(document.getElementById('dataConsulta').value)],
        ['Horário', horarioConsulta],
        ['Convênio', nomeConvenio],
    ];
    if (tipoEscolhido === 'checkup') {
        dadosResumo.splice(1, 0, ['Pacote', pacoteEscolhido]);
        dadosResumo.splice(2, 0, ['Valor para pagamento na recepção', 'R$ ' + precoPacoteEscolhido.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})]);
    }
    resumo.innerHTML = dadosResumo.map(function (item) {
        return '<div class="linha-resumo"><span>' + escaparHtml(item[0]) + '</span><strong>' + escaparHtml(item[1]) + '</strong></div>';
    }).join('');
    document.getElementById('avisoPagamentoTeleconsulta').style.display = tipoEscolhido === 'tele' ? 'block' : 'none';
    mostrarEtapa('confirmacao');
}

function escaparHtml(valor) {
    return String(valor).replace(/[&<>"']/g, function (caractere) {
        return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[caractere];
    });
}

function formatarData(dataIso) {
    const partes = dataIso.split('-');
    return partes[2] + '/' + partes[1] + '/' + partes[0];
}

document.addEventListener('DOMContentLoaded', function() {
    const blocoDocumentoResponsavel = document.getElementById('blocoDocumentoResponsavel');
    const blocoUploadResponsavel = document.getElementById('blocoUploadResponsavel');
    if (pacienteMenor) {
        blocoDocumentoResponsavel.style.display = 'block';
        blocoUploadResponsavel.style.display = 'block';
    }
    const tipoDocumento = document.getElementById('tipoDocumentoPaciente');
    const tipoAtual = tipoDocumentoSalvo;
    if (tipoDocumento && Array.from(tipoDocumento.options).some(function (option) { return option.value === tipoAtual; })) {
        tipoDocumento.value = tipoAtual;
    }
    const tipoResponsavel = document.getElementById('tipoDocumentoResponsavel');
    if (tipoResponsavel && tipoDocumentoResponsavelSalvo) tipoResponsavel.value = tipoDocumentoResponsavelSalvo;
    if (!precisaDocumentoPaciente) {
        document.getElementById('campoDocumentoPaciente').style.display = 'none';
        document.getElementById('campoNumeroDocumentoPaciente').style.display = 'none';
    }
    if (pacienteMenor && !precisaDocumentoResponsavel) {
        document.getElementById('blocoDocumentoResponsavel').style.display = 'none';
    }
    if (!precisaComprovantePaciente) {
        document.getElementById('blocoUploadPaciente').style.display = 'none';
    }
    if (pacienteMenor && !precisaComprovanteResponsavel) {
        document.getElementById('blocoUploadResponsavel').style.display = 'none';
    }
    if (!precisaComprovantePaciente && !precisaComprovanteResponsavel) {
        document.getElementById('opcoesModoDocumento').style.display = 'none';
        document.getElementById('blocoUploadDocumento').style.display = 'none';
        document.getElementById('avisoLevarRecepcao').style.display = 'none';
    }
    aplicarMascaraDocumento();

    if (tipoInicial === 'presencial' || tipoInicial === 'tele' || tipoInicial === 'checkup') {
        escolherTipo(tipoInicial);
    }
});

function confirmarAgendamento() {
    Swal.fire({
        title: 'Enviar solicitação de agendamento?',
        text: 'Sua consulta ficará pendente até a confirmação da clínica. O pagamento será tratado na recepção.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Enviar solicitação',
        cancelButtonText: 'Voltar',
        confirmButtonColor: '#002B49'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('formAgendar').submit();
        }
    });
}
</script>

</body>
</html>