<?php
// gestor/cadastrar-usuario.php
session_start();
require_once '../../conexao.php'; // Ajuste o caminho do conexao.php se necessário

// Proteção de acesso: Apenas gestores
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_perfil'] !== 'gestor') {
    header("Location: ../../auth/login.php");
    exit();
}

// Perfil pré-selecionado vindo da URL (ex: cadastrar-usuario.php?perfil=medico)
$perfilInicial = $_GET['perfil'] ?? 'recepcionista';

// ================= CONSULTAS AO BANCO DE DADOS =================
// Cada consulta tem seu próprio try/catch: assim, se uma falhar (ex: alguma
// tabela de usuários ainda não foi criada no banco), isso não derruba a
// lista de especialidades reais e faz cair na lista fictícia por engano.

// 1. Busca usuários cadastrados recentemente unindo as 4 tabelas reais de usuários
try {
    $stmtUsuarios = $pdo->query("
        SELECT id, nome, 'medico' AS perfil, uid, 'Ativo' AS status FROM medico
        UNION ALL
        SELECT id AS id, nome, 'recepcionista' AS perfil, uid, 'Ativo' AS status FROM recepcionistas
        UNION ALL
        SELECT id, nome, 'gestor' AS perfil, uid, status FROM gestores
        UNION ALL
        SELECT id, nome, 'paciente' AS perfil, uid, 'Ativo' AS status FROM paciente
        ORDER BY id DESC 
        LIMIT 50
    ");
    $usuariosRecentes = $stmtUsuarios->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $usuariosRecentes = [];
}

// 2. Busca lista de especialidades (tabela no singular: 'especialidade')
try {
    $stmtEsp = $pdo->query("SELECT id, nome FROM especialidade ORDER BY nome ASC");
    $listaEspecialidades = $stmtEsp->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Só cai na lista fictícia se a tabela 'especialidade' de fato não existir/falhar
    $listaEspecialidades = [
        ['id' => 1, 'nome' => 'Clínico Geral'],
        ['id' => 2, 'nome' => 'Cardiologia'],
        ['id' => 3, 'nome' => 'Dermatologia'],
        ['id' => 4, 'nome' => 'Ortopedia'],
        ['id' => 5, 'nome' => 'Ginecologia'],
        ['id' => 6, 'nome' => 'Pediatria']
    ];
}

$rotulosPerfil = [
    'medico'        => 'Médico',
    'gestor'        => 'Gestor',
    'recepcionista' => 'Recepcionista',
    'paciente'      => 'Paciente',
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastrar Usuário - Convelum Saúde</title>

    <link href="../../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/responsivo.css">
    <style>
        /* Estilos de apoio para busca/paginação/edição dos cadastrados recentemente.
           Ficam aqui pra não depender de mexer nos CSS globais do sistema. */
        .campo-busca-registros { margin-bottom: 12px; }
        .registro-config-acoes { display: flex; align-items: center; gap: 14px; }
        .registro-config-editar { color: inherit; opacity: .75; text-decoration: none; }
        .registro-config-editar:hover { opacity: 1; }
        .botao-config-mostrar-mais { display: inline-block; margin-top: 12px; width: auto; padding: 8px 20px; }

        /* Busca de especialidade (substitui os botões antigos) */
        .campo-especialidade-busca { position: relative; }
        .lista-sugestoes-especialidade {
            position: absolute; z-index: 20; top: calc(100% + 4px); left: 0; right: 0;
            background: #fff; border: 1px solid #d8dde3; border-radius: 8px;
            box-shadow: 0 6px 18px rgba(0,0,0,.12); max-height: 220px; overflow-y: auto;
        }
        .sugestao-especialidade-item { padding: 10px 14px; cursor: pointer; font-size: .95rem; }
        .sugestao-especialidade-item:hover { background: #f2f5f9; }
        .sugestao-especialidade-vazia { padding: 10px 14px; color: #8a94a3; font-size: .9rem; }
        .tags-especialidades-selecionadas { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
        .tag-especialidade-selecionada {
            display: inline-flex; align-items: center; gap: 6px;
            background: #eaf1ff; color: #1d4ed8; border-radius: 999px;
            padding: 6px 10px 6px 14px; font-size: .88rem;
        }
        .tag-especialidade-selecionada i { cursor: pointer; opacity: .7; }
        .tag-especialidade-selecionada i:hover { opacity: 1; }
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

<!-- ========================= CABEÇALHO ========================= -->
<div id="painel-boasvindas">
    <div>
        <span id="subtitulo-painel">CONFIGURAÇÕES DO SISTEMA</span>
        <h1 id="titulo-painel">Cadastrar usuário</h1>
        <p id="texto-painel">Crie o acesso de médicos, gestores, recepcionistas e pacientes.</p>
    </div>
</div>

<div class="secao-config-agenda">

<?php if (isset($_GET['status']) && $_GET['status'] === 'sucesso'): 
    $mensagemSucesso = match ($_GET['acao'] ?? '') {
        'inativacao' => 'Usuário inativado com sucesso! O histórico dele foi mantido.',
        'reativacao' => 'Usuário reativado com sucesso!',
        'edicao'     => 'Usuário atualizado com sucesso!',
        default      => 'Usuário cadastrado com sucesso!',
    };
?>
    <div class="alert alert-success alert-dismissible w-100 mb-4" role="alert" id="alertaUsuario">
        <i class="bi bi-check-circle-fill me-2"></i> <?php echo $mensagemSucesso; ?>
        <button type="button" class="btn-close" aria-label="Fechar" onclick="document.getElementById('alertaUsuario').remove();"></button>
    </div>
<?php elseif (isset($_GET['erro'])): ?>
    <div class="alert alert-danger alert-dismissible w-100 mb-4" role="alert" id="alertaUsuarioErro">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> Erro ao cadastrar: <?php echo htmlspecialchars($_GET['erro']); ?>
        <button type="button" class="btn-close" aria-label="Fechar" onclick="document.getElementById('alertaUsuarioErro').remove();"></button>
    </div>
<?php endif; ?>

    <!-- ========================= FORMULÁRIO ========================= -->
    <!-- enctype obrigatório aqui para o upload de foto funcionar -->
    <form method="post" action="actions/salvar-usuario.php" class="cartao-config-agenda" enctype="multipart/form-data">
        <h2 class="titulo-cartao-config">Dados pessoais</h2>

        <div class="campo-config">
            <label for="perfilUsuario">Perfil de acesso</label>
            <select id="perfilUsuario" name="perfil" onchange="atualizarCamposPorPerfil()">
                <option value="recepcionista">Recepcionista</option>
                <option value="medico">Médico</option>
                <option value="gestor">Gestor</option>
                <option value="paciente">Paciente</option>
            </select>
        </div>

        <div class="campo-config">
            <label for="nomeUsuario">Nome completo</label>
            <input type="text" id="nomeUsuario" name="nome" placeholder="Digite o nome completo" required>
        </div>

        <!-- Foto de perfil: aparece pra médico, recepcionista e gestor (obrigatória só pro médico) -->
        <div class="campo-config" id="campoFotoUsuario" style="display:none;">
            <label for="fotoUsuario"><span id="labelFotoUsuario">Foto de perfil</span></label>
            <input type="file" id="fotoUsuario" name="foto" accept="image/png, image/jpeg, image/webp">
            <small class="texto-ajuda-campo" id="ajudaFotoUsuario">Formatos aceitos: JPG, PNG ou WEBP.</small>
            <div id="previaFotoUsuario" style="display:none; margin-top:8px;">
                <img id="previaFotoUsuarioImg" src="" alt="Pré-visualização da foto" style="width:80px;height:80px;border-radius:50%;object-fit:cover;">
            </div>
        </div>

        <!-- Tipo de documento: só aparece pro perfil paciente. Nos demais perfis o documento é sempre CPF. -->
        <div class="campo-config" id="campoTipoDocPaciente" style="display:none;">
            <label for="tipoDocPaciente">Tipo de documento</label>
            <select id="tipoDocPaciente" name="tipo_doc" onchange="atualizarMascaraDocumento('paciente')">
                <option value="CPF">CPF</option>
                <option value="RG">RG</option>
                <option value="Passaporte">Passaporte</option>
                <option value="Certidao">Certidão de nascimento</option>
                <option value="Outro">Outro</option>
            </select>
        </div>

        <div class="campo-config">
            <label for="cpfUsuario"><span id="labelCpfUsuario">CPF</span></label>
            <input type="text" id="cpfUsuario" name="cpf" placeholder="000.000.000-00" required>
        </div>

        <!-- Data de início: médico / recepcionista / gestor -->
        <div class="campo-config" id="campoDataInicio">
            <label for="dataInicioUsuario">Data de início</label>
            <input type="date" id="dataInicioUsuario" name="data_inicio" value="<?php echo date('Y-m-d'); ?>">
        </div>

        <!-- Data de nascimento: só paciente -->
        <div class="campo-config" id="campoDataNascimento" style="display:none;">
            <label for="dataNascPaciente">Data de nascimento</label>
            <input type="date" id="dataNascPaciente" name="data_nasc">
        </div>

        <div class="campo-config">
            <label for="emailUsuario">E-mail</label>
            <input type="email" id="emailUsuario" name="email" placeholder="Digite o e-mail" required>
        </div>

        <div class="linha-config-dupla">
            <div class="campo-config">
                <label for="telefoneUsuario">Telefone</label>
                <input type="tel" id="telefoneUsuario" name="telefone" placeholder="(00) 0000-0000" maxlength="14">
            </div>
            <div class="campo-config">
                <label for="celularUsuario">Celular</label>
                <input type="tel" id="celularUsuario" name="celular" placeholder="(00) 00000-0000" maxlength="16" required>
            </div>
        </div>

        <!-- ========================= CAMPOS EXTRAS: SÓ MÉDICO ========================= -->
        <div id="camposMedico" style="display:none;">

            <h2 class="titulo-cartao-config titulo-config-secundario">Dados profissionais</h2>

            <div class="campo-config">
                <label for="crmMedico">CRM</label>
                <input type="text" id="crmMedico" name="crm" placeholder="Ex: CRM/SP 123456">
            </div>

            <div class="campo-config">
                <label for="buscaEspecialidadeUsuario">Especialidades</label>
                <div class="campo-especialidade-busca">
                    <input type="text" id="buscaEspecialidadeUsuario" placeholder="Digite pra buscar uma especialidade..."
                           autocomplete="off"
                           oninput="buscarEspecialidades(this.value)"
                           onfocus="buscarEspecialidades(this.value)">
                    <div id="sugestoesEspecialidade" class="lista-sugestoes-especialidade" style="display:none;"></div>
                </div>
                <div id="especialidadesSelecionadasLista" class="tags-especialidades-selecionadas"></div>
                <input type="hidden" name="especialidades" id="especialidadesSelecionadas" value="">
            </div>

        </div>

        <!-- ========================= CAMPOS EXTRAS: SÓ PACIENTE ========================= -->
        <div id="camposPaciente" style="display:none;">

            <h2 class="titulo-cartao-config titulo-config-secundario">Dados do paciente</h2>

            <div class="campo-config">
                <label for="menorIdadePaciente">Paciente é menor de idade?</label>
                <select id="menorIdadePaciente" name="menor_idade" onchange="alternarCamposResponsavel()">
                    <option value="N">Não</option>
                    <option value="Y">Sim</option>
                </select>
            </div>

            <!-- Dados do responsável: só aparece se "menor de idade" = Sim -->
            <div id="camposResponsavel" style="display:none;">

                <h2 class="titulo-cartao-config titulo-config-secundario">Dados do responsável</h2>

                <div class="campo-config">
                    <label for="nomeResp">Nome do responsável</label>
                    <input type="text" id="nomeResp" name="nome_resp" placeholder="Digite o nome do responsável">
                </div>

                <div class="campo-config">
                    <label for="tipoDocResp">Tipo de documento do responsável</label>
                    <select id="tipoDocResp" name="tipo_doc_resp" onchange="atualizarMascaraDocumento('resp')">
                        <option value="CPF">CPF</option>
                        <option value="RG">RG</option>
                        <option value="Passaporte">Passaporte</option>
                        <option value="Outro">Outro</option>
                    </select>
                </div>

                <div class="campo-config">
                    <label for="cpfResp"><span id="labelCpfResp">CPF do responsável</span></label>
                    <input type="text" id="cpfResp" name="num_doc_resp" placeholder="000.000.000-00">
                </div>

                <div class="campo-config">
                    <label for="dataNascResp">Data de nascimento do responsável</label>
                    <input type="date" id="dataNascResp" name="data_nasc_resp">
                </div>

                <div class="linha-config-dupla">
                    <div class="campo-config">
                        <label for="telefoneResp">Telefone do responsável</label>
                        <input type="tel" id="telefoneResp" name="telefone_resp" placeholder="(00) 0000-0000" maxlength="14">
                    </div>
                    <div class="campo-config">
                        <label for="celularResp">Celular do responsável</label>
                        <input type="tel" id="celularResp" name="celular_resp" placeholder="(00) 00000-0000" maxlength="16">
                    </div>
                </div>

                <div class="campo-config">
                    <label for="emailResp">E-mail do responsável</label>
                    <input type="email" id="emailResp" name="email_resp" placeholder="Digite o e-mail do responsável">
                </div>

            </div>

        </div>

        <p class="aviso-recuperacao">
            O sistema vai gerar um UID de acesso automaticamente e enviar as
            instruções de primeiro login pro e-mail informado.
        </p>

        <button type="submit" class="botao-entrar botao-config-salvar">Cadastrar usuário</button>
    </form>

       <!-- ========================= CADASTRADOS RECENTEMENTE ========================= -->
    <div class="cartao-config-agenda">
        <h2 class="titulo-cartao-config">Cadastrados recentemente</h2>

        <?php if (!empty($usuariosRecentes)): ?>
            <div class="campo-config campo-busca-registros">
                <label for="buscaUsuariosRecentes">Buscar</label>
                <input type="text" id="buscaUsuariosRecentes" placeholder="Buscar por nome, perfil ou UID..." oninput="filtrarUsuariosRecentes()">
            </div>
        <?php endif; ?>

        <div class="lista-config-registros" id="listaUsuariosRecentes">
            <?php if (!empty($usuariosRecentes)): ?>
                <?php foreach ($usuariosRecentes as $indice => $usuario):
                    $rotulo = $rotulosPerfil[$usuario['perfil']] ?? ucfirst($usuario['perfil']);
                    $textoBusca = mb_strtolower($usuario['nome'] . ' ' . $rotulo . ' ' . $usuario['uid'], 'UTF-8');
                ?>
                    <div class="registro-config registro-usuario-recente"
                         data-busca="<?php echo htmlspecialchars($textoBusca); ?>"
                         data-indice="<?php echo $indice; ?>"
                         <?php echo $indice >= 5 ? 'style="display:none;"' : ''; ?>>
                        <div>
                            <span class="registro-config-tipo"><?php echo htmlspecialchars($usuario['nome']); ?></span>
                            <span class="registro-config-periodo">
                                <?php echo htmlspecialchars($rotulo); ?>
                            </span>
                            <p class="registro-medicamento-detalhes">UID: <?php echo htmlspecialchars($usuario['uid']); ?></p>
                        </div>
                        <div class="registro-config-acoes">
                            <a href="editar-usuario.php?id=<?php echo $usuario['id']; ?>&perfil=<?php echo urlencode($usuario['perfil']); ?>"
                                class="registro-config-editar"
                                title="Editar">
                                <i class="bi bi-pencil-fill"></i>
                            </a>
                            <a href="actions/excluir-usuario.php?id=<?php echo $usuario['id']; ?>&perfil=<?php echo urlencode($usuario['perfil']); ?>&origem=cadastro"
                                class="registro-config-remover"
                                title="Excluir"
                                onclick="return confirm('Tem certeza que deseja excluir <?php echo htmlspecialchars(addslashes($usuario['nome'])); ?>?');">
                                <i class="bi bi-trash"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted p-3">Nenhum usuário cadastrado recentemente.</p>
            <?php endif; ?>
        </div>

        <?php if (count($usuariosRecentes) > 5): ?>
            <button type="button" id="botaoMostrarMaisUsuarios" class="botao-entrar botao-config-mostrar-mais" onclick="mostrarMaisUsuariosRecentes()">
                Mostrar mais
            </button>
        <?php endif; ?>

        <p id="avisoSemResultadosUsuarios" class="text-muted p-3" style="display:none;">Nenhum usuário encontrado para essa busca.</p>
    </div>

</div>

<script>
const perfilInicial = <?php echo json_encode($perfilInicial); ?>;
const listaEspecialidadesDisponiveis = <?php echo json_encode(array_map(fn($e) => ['id' => (int)$e['id'], 'nome' => $e['nome']], $listaEspecialidades)); ?>;
let especialidadesEscolhidas = [];

// ===================== BUSCA E PAGINAÇÃO DOS CADASTRADOS RECENTEMENTE =====================
const QUANTIDADE_POR_PAGINA = 5;
let quantidadeVisivelUsuarios = QUANTIDADE_POR_PAGINA;

function mostrarMaisUsuariosRecentes() {
    quantidadeVisivelUsuarios += QUANTIDADE_POR_PAGINA;
    filtrarUsuariosRecentes();
}

function filtrarUsuariosRecentes() {
    const campoBusca = document.getElementById('buscaUsuariosRecentes');
    const termo = campoBusca ? campoBusca.value.trim().toLowerCase() : '';
    const registros = document.querySelectorAll('#listaUsuariosRecentes .registro-usuario-recente');
    const botaoMostrarMais = document.getElementById('botaoMostrarMaisUsuarios');
    const avisoSemResultados = document.getElementById('avisoSemResultadosUsuarios');

    let visiveis = 0;
    let totalCorrespondentes = 0;

    registros.forEach(function(registro) {
        const textoBusca = registro.dataset.busca || '';
        const correspondeABusca = termo === '' || textoBusca.includes(termo);

        if (!correspondeABusca) {
            registro.style.display = 'none';
            return;
        }

        totalCorrespondentes++;

        // Com busca ativa, mostra todos os resultados; sem busca, respeita a paginação
        if (termo !== '' || totalCorrespondentes <= quantidadeVisivelUsuarios) {
            registro.style.display = '';
            visiveis++;
        } else {
            registro.style.display = 'none';
        }
    });

    if (avisoSemResultados) {
        avisoSemResultados.style.display = (totalCorrespondentes === 0) ? 'block' : 'none';
    }

    if (botaoMostrarMais) {
        // Esconde o botão durante a busca ou quando não há mais itens pra mostrar
        botaoMostrarMais.style.display = (termo === '' && totalCorrespondentes > quantidadeVisivelUsuarios) ? 'inline-block' : 'none';
    }
}

function atualizarCamposPorPerfil() {
    const perfil = document.getElementById('perfilUsuario').value;

    const campoDataInicio      = document.getElementById('campoDataInicio');
    const campoDataNascimento  = document.getElementById('campoDataNascimento');
    const camposMedico         = document.getElementById('camposMedico');
    const camposPaciente       = document.getElementById('camposPaciente');
    const campoTipoDocPaciente = document.getElementById('campoTipoDocPaciente');
    const inputDataInicio      = document.getElementById('dataInicioUsuario');
    const inputDataNascimento  = document.getElementById('dataNascPaciente');

    // Campo de foto (médico obrigatório, recepcionista/gestor opcional, paciente não tem)
    const campoFotoUsuario = document.getElementById('campoFotoUsuario');
    const inputFotoUsuario = document.getElementById('fotoUsuario');
    const labelFotoUsuario = document.getElementById('labelFotoUsuario');
    const ajudaFotoUsuario = document.getElementById('ajudaFotoUsuario');

    // Reseta tudo antes de aplicar as regras do perfil escolhido
    camposMedico.style.display = 'none';
    camposPaciente.style.display = 'none';

    if (perfil === 'paciente') {
        campoDataInicio.style.display = 'none';
        campoDataNascimento.style.display = 'block';
        inputDataInicio.required = false;
        inputDataNascimento.required = true;
        camposPaciente.style.display = 'block';
        campoTipoDocPaciente.style.display = 'block';
        alternarCamposResponsavel();
        atualizarMascaraDocumento('paciente');

        // Paciente não tem campo de foto neste cadastro
        campoFotoUsuario.style.display = 'none';
        inputFotoUsuario.required = false;
    } else {
        campoDataInicio.style.display = 'block';
        campoDataNascimento.style.display = 'none';
        inputDataInicio.required = true;
        inputDataNascimento.required = false;
        campoTipoDocPaciente.style.display = 'none';

        // Perfis que não são paciente usam sempre CPF
        document.getElementById('labelCpfUsuario').textContent = 'CPF';
        document.getElementById('cpfUsuario').placeholder = '000.000.000-00';
        document.getElementById('cpfUsuario').maxLength = 14;

        // Médico, recepcionista e gestor têm campo de foto
        campoFotoUsuario.style.display = 'block';

        if (perfil === 'medico') {
            camposMedico.style.display = 'block';
            inputFotoUsuario.required = true;
            labelFotoUsuario.textContent = 'Foto de perfil (obrigatória)';
            ajudaFotoUsuario.textContent = 'Obrigatória para médicos. Formatos aceitos: JPG, PNG ou WEBP.';
        } else {
            inputFotoUsuario.required = false;
            labelFotoUsuario.textContent = 'Foto de perfil (opcional)';
            ajudaFotoUsuario.textContent = 'Opcional. Formatos aceitos: JPG, PNG ou WEBP.';
        }
    }
}

function alternarCamposResponsavel() {
    const menor = document.getElementById('menorIdadePaciente').value;
    document.getElementById('camposResponsavel').style.display = (menor === 'Y') ? 'block' : 'none';
    if (menor === 'Y') {
        atualizarMascaraDocumento('resp');
    }
}

// Alterna label/placeholder/máscara do campo de documento conforme o tipo escolhido.
// Só aplica a máscara de CPF (000.000.000-00) quando o tipo selecionado for "CPF";
// pros demais tipos (RG, Passaporte, Certidão, Outro) o campo fica livre.
function atualizarMascaraDocumento(contexto) {
    const ehPaciente = contexto === 'paciente';
    const tipo   = ehPaciente
        ? document.getElementById('tipoDocPaciente').value
        : document.getElementById('tipoDocResp').value;
    const input  = ehPaciente
        ? document.getElementById('cpfUsuario')
        : document.getElementById('cpfResp');
    const label  = ehPaciente
        ? document.getElementById('labelCpfUsuario')
        : document.getElementById('labelCpfResp');

    input.value = ''; // evita ficar com valor formatado de outro tipo de documento
    input.removeEventListener('input', aplicarMascaraCpf);

    if (tipo === 'CPF') {
        input.placeholder = '000.000.000-00';
        input.maxLength = 14;
        label.textContent = ehPaciente ? 'CPF do paciente' : 'CPF do responsável';
        input.addEventListener('input', aplicarMascaraCpf);
    } else {
        input.placeholder = 'Digite o número do documento';
        input.removeAttribute('maxlength');
        label.textContent = ehPaciente ? 'Documento do paciente' : 'Documento do responsável';
    }
}

function escaparHtmlEsp(texto) {
    const div = document.createElement('div');
    div.textContent = texto;
    return div.innerHTML;
}

function renderizarEspecialidadesSelecionadas() {
    const container = document.getElementById('especialidadesSelecionadasLista');
    container.innerHTML = '';
    especialidadesEscolhidas.forEach(function (esp) {
        const tag = document.createElement('span');
        tag.className = 'tag-especialidade-selecionada';
        tag.innerHTML = escaparHtmlEsp(esp.nome) + ' <i class="bi bi-x-lg" data-id="' + esp.id + '"></i>';
        container.appendChild(tag);
    });
    document.getElementById('especialidadesSelecionadas').value = especialidadesEscolhidas.map(function (e) { return e.id; }).join(',');
}

function buscarEspecialidades(termo) {
    const caixa = document.getElementById('sugestoesEspecialidade');
    const termoNorm = termo.trim().toLowerCase();

    if (termoNorm === '') {
        caixa.style.display = 'none';
        caixa.innerHTML = '';
        return;
    }

    const idsEscolhidos = especialidadesEscolhidas.map(function (e) { return e.id; });
    const resultados = listaEspecialidadesDisponiveis.filter(function (esp) {
        return esp.nome.toLowerCase().includes(termoNorm) && !idsEscolhidos.includes(esp.id);
    }).slice(0, 8);

    if (resultados.length === 0) {
        caixa.innerHTML = '<div class="sugestao-especialidade-vazia">Nenhuma especialidade encontrada.</div>';
        caixa.style.display = 'block';
        return;
    }

    caixa.innerHTML = resultados.map(function (esp) {
        return '<div class="sugestao-especialidade-item" data-id="' + esp.id + '">' + escaparHtmlEsp(esp.nome) + '</div>';
    }).join('');
    caixa.style.display = 'block';
}

function selecionarEspecialidadePorId(id) {
    const esp = listaEspecialidadesDisponiveis.find(function (e) { return e.id === id; });
    if (!esp || especialidadesEscolhidas.some(function (e) { return e.id === id; })) return;

    especialidadesEscolhidas.push(esp);
    renderizarEspecialidadesSelecionadas();

    document.getElementById('buscaEspecialidadeUsuario').value = '';
    document.getElementById('sugestoesEspecialidade').style.display = 'none';
    document.getElementById('sugestoesEspecialidade').innerHTML = '';
}

function removerEspecialidadePorId(id) {
    especialidadesEscolhidas = especialidadesEscolhidas.filter(function (e) { return e.id !== id; });
    renderizarEspecialidadesSelecionadas();
}

document.getElementById('sugestoesEspecialidade').addEventListener('click', function (e) {
    const item = e.target.closest('.sugestao-especialidade-item');
    if (item) selecionarEspecialidadePorId(parseInt(item.dataset.id, 10));
});

document.getElementById('especialidadesSelecionadasLista').addEventListener('click', function (e) {
    const icone = e.target.closest('[data-id]');
    if (icone) removerEspecialidadePorId(parseInt(icone.dataset.id, 10));
});

// Fecha a lista de sugestões ao clicar fora dela
document.addEventListener('click', function (e) {
    if (!e.target.closest('.campo-especialidade-busca')) {
        document.getElementById('sugestoesEspecialidade').style.display = 'none';
    }
});

// Máscara do CPF (000.000.000-00) — usada tanto pro CPF do usuário quanto do responsável
function aplicarMascaraCpf(e) {
    let v = e.target.value.replace(/\D/g, '');
    if (v.length > 11) v = v.slice(0, 11);
    v = v.replace(/(\d{3})(\d)/, '$1.$2');
    v = v.replace(/(\d{3})(\d)/, '$1.$2');
    v = v.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
    e.target.value = v;
}

// Máscara de telefone FIXO: máximo 10 dígitos -> (XX) XXXX-XXXX
function aplicarMascaraTelefoneFixo(e) {
    let v = e.target.value.replace(/\D/g, '');
    if (v.length > 10) v = v.slice(0, 10);

    if (v.length > 6) {
        v = v.replace(/^(\d{2})(\d{4})(\d{0,4}).*$/, '($1) $2-$3');
    } else if (v.length > 2) {
        v = v.replace(/^(\d{2})(\d{0,4})/, '($1) $2');
    } else if (v.length > 0) {
        v = v.replace(/^(\d*)/, '($1');
    }
    e.target.value = v;
}

// Máscara de CELULAR: máximo 11 dígitos -> (XX) XXXXX-XXXX
function aplicarMascaraCelular(e) {
    let v = e.target.value.replace(/\D/g, '');
    if (v.length > 11) v = v.slice(0, 11);

    if (v.length > 7) {
        v = v.replace(/^(\d{2})(\d{5})(\d{0,4}).*$/, '($1) $2-$3');
    } else if (v.length > 2) {
        v = v.replace(/^(\d{2})(\d{0,5})/, '($1) $2');
    } else if (v.length > 0) {
        v = v.replace(/^(\d*)/, '($1');
    }
    e.target.value = v;
}

// Aplicando máscaras aos campos de Telefone e Celular (usuário + responsável)
document.getElementById('telefoneUsuario').addEventListener('input', aplicarMascaraTelefoneFixo);
document.getElementById('celularUsuario').addEventListener('input', aplicarMascaraCelular);
document.getElementById('telefoneResp').addEventListener('input', aplicarMascaraTelefoneFixo);
document.getElementById('celularResp').addEventListener('input', aplicarMascaraCelular);

// Ajuste do E-mail
document.getElementById('emailUsuario').addEventListener('input', function(e) {
    e.target.value = e.target.value.toLowerCase().trim();
});

// Aplica a máscara de CPF por padrão pro perfil inicial (caso já venha como paciente com tipo CPF)
document.getElementById('cpfUsuario').addEventListener('input', aplicarMascaraCpf);

// Pré-visualização da foto selecionada
document.getElementById('fotoUsuario').addEventListener('change', function(e) {
    const previa = document.getElementById('previaFotoUsuario');
    const previaImg = document.getElementById('previaFotoUsuarioImg');
    const arquivo = e.target.files[0];

    if (arquivo) {
        const leitor = new FileReader();
        leitor.onload = function(ev) {
            previaImg.src = ev.target.result;
            previa.style.display = 'block';
        };
        leitor.readAsDataURL(arquivo);
    } else {
        previa.style.display = 'none';
        previaImg.src = '';
    }
});

document.getElementById('perfilUsuario').value = perfilInicial;
atualizarCamposPorPerfil();
</script>

</body>
</html>