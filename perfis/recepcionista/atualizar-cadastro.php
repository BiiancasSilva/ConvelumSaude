<?php
// recepcionista/atualizar-cadastro.php
session_start();
require_once '../../conexao.php';

// Validação de sessão da recepcionista
if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'recepcionista') {
    header("Location: ../../auth/login.php");
    exit();
}

$csrfToken = $_SESSION['csrf_atualizar_paciente'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_atualizar_paciente'] = $csrfToken;

$termoBusca = isset($_GET['busca']) && is_string($_GET['busca']) ? trim($_GET['busca']) : '';
$rawIdSelecionado = $_GET['id'] ?? null;
$idSelecionado = is_string($rawIdSelecionado)
    ? filter_var($rawIdSelecionado, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : null;
$buscou        = ($termoBusca !== '');
$pacientes     = [];
$paciente      = null;
$erroBusca     = '';

if ($buscou) {
    try {
        // Busca TODOS os pacientes que batem com Nome, Número de Documento (num_doc) ou UID
        $stmt = $pdo->prepare("
            SELECT * FROM paciente 
            WHERE nome LIKE ? OR num_doc LIKE ? OR uid = ?
            ORDER BY nome ASC
        ");
        $termoLike = '%' . $termoBusca . '%';
        $stmt->execute([$termoLike, $termoLike, $termoBusca]);
        $pacientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$pacientes) {
            $erroBusca = 'Nenhum paciente encontrado com o termo: ' . htmlspecialchars($termoBusca, ENT_QUOTES, 'UTF-8');
        } elseif (count($pacientes) === 1) {
            // Só um resultado: já seleciona ele direto pra edição
            $paciente = $pacientes[0];
        } elseif ($idSelecionado) {
            // Vários resultados, mas o usuário já escolheu um pelo id
            foreach ($pacientes as $p) {
                if ((int)$p['id'] === $idSelecionado) {
                    $paciente = $p;
                    break;
                }
            }
        }
    } catch (PDOException $e) {
        error_log('Falha ao buscar paciente para atualização: ' . $e->getMessage());
        $erroBusca = 'Não foi possível pesquisar pacientes. Tente novamente.';
    }
}

// Busca convênios ativos pra popular o select (só precisa se um paciente estiver selecionado)
$listaConvenios = [];
$erroConvenios = false;
if ($paciente) {
    try {
        $stmtConv = $pdo->prepare("
            SELECT id, nome, status FROM convenios
            WHERE status = 'Ativo' OR id = ?
            ORDER BY status = 'Ativo' DESC, nome ASC
        ");
        $stmtConv->execute([$paciente['id_convenio']]);
        $listaConvenios = $stmtConv->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Falha ao carregar convênios para atualização do paciente: ' . $e->getMessage());
        $listaConvenios = [];
        $erroConvenios = true;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atualizar Cadastro - Convelum Saúde</title>

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
        <a href="painel.php" class="topo-painel-sair">
            <i class="bi bi-arrow-left"></i> Voltar ao painel
        </a>
    </div>
</div>

<div id="painel-boasvindas">
    <div>
        <span id="subtitulo-painel">TRIAGEM E CADASTRO</span>
        <h1 id="titulo-painel">Atualizar cadastro</h1>
        <p id="texto-painel">Busque o paciente pra editar os dados dele.</p>
    </div>
</div>

<?php if (isset($_GET['sucesso']) && $_GET['sucesso'] == '1'): ?>
    <div class="container mt-3" style="max-width: 900px;">
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> Cadastro atualizado com sucesso!
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
<?php endif; ?>

<?php if (isset($_GET['excluido']) && $_GET['excluido'] == '1'): ?>
    <div class="container mt-3" style="max-width: 900px;">
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> Paciente excluído com sucesso.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
<?php endif; ?>
<?php if (isset($_GET['erro'])): ?>
    <div class="container mt-3" style="max-width: 900px;">
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars(is_string($_GET['erro']) ? $_GET['erro'] : 'Não foi possível atualizar o cadastro.', ENT_QUOTES, 'UTF-8'); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    </div>
<?php endif; ?>

<?php if (isset($_GET['erro_exclusao'])): ?>
    <div class="container mt-3" style="max-width: 900px;">
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($_GET['erro_exclusao']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
<?php endif; ?>

<div class="secao-config-agenda">

    <!-- ========================= BUSCA ========================= -->
    <form method="get" action="atualizar-cadastro.php" class="cartao-config-agenda">
        <h2 class="titulo-cartao-config">Buscar paciente</h2>

        <div class="campo-config campo-busca-paciente">
            <label for="buscaPaciente">Nome, CPF ou UID</label>
            <div class="linha-busca-paciente">
                <input type="text" id="buscaPaciente" name="busca" placeholder="Digite pra buscar..." value="<?php echo htmlspecialchars($termoBusca); ?>">
                <button type="submit" class="botao-buscar-paciente"><i class="bi bi-search"></i></button>
            </div>
        </div>
    </form>

    <?php if ($buscou && !empty($erroBusca)): ?>
        <div class="container mt-3" style="max-width: 900px;">
            <div class="alert alert-warning" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $erroBusca; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- ========================= LISTA DE RESULTADOS (quando há mais de um) ========================= -->
    <?php if ($buscou && count($pacientes) > 1 && !$paciente): ?>
        <div class="cartao-config-agenda">
            <h2 class="titulo-cartao-config">
                <?php echo count($pacientes); ?> pacientes encontrados — selecione um para editar
            </h2>

            <div class="lista-resultados-paciente">
                <?php foreach ($pacientes as $p): ?>
                    <a href="atualizar-cadastro.php?busca=<?php echo urlencode($termoBusca); ?>&id=<?php echo $p['id']; ?>"
                       class="item-resultado-paciente"
                       style="display:flex; justify-content:space-between; align-items:center; padding:12px 16px; border:1px solid #ddd; border-radius:8px; margin-bottom:10px; text-decoration:none; color:inherit;">
                        <div>
                            <strong><?php echo htmlspecialchars($p['nome']); ?></strong><br>
                            <small class="text-muted">
                                UID: <?php echo htmlspecialchars($p['uid']); ?>
                                &nbsp;|&nbsp; Doc: <?php echo htmlspecialchars($p['num_doc'] ?? '—'); ?>
                                &nbsp;|&nbsp; Nasc.: <?php echo !empty($p['data_nasc']) ? date('d/m/Y', strtotime($p['data_nasc'])) : '—'; ?>
                            </small>
                        </div>
                        <i class="bi bi-pencil-square"></i>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- ========================= FORMULÁRIO DE EDIÇÃO ========================= -->
    <?php if ($buscou && $paciente): ?>
        <?php if (count($pacientes) > 1): ?>
            <div class="container mt-2 mb-3" style="max-width: 900px;">
                <a href="atualizar-cadastro.php?busca=<?php echo urlencode($termoBusca); ?>">
                    <i class="bi bi-arrow-left"></i> Voltar para a lista de resultados
                </a>
            </div>
        <?php endif; ?>

        <form method="post" action="actions/salvar-atualizacao-paciente.php" class="cartao-config-agenda" enctype="multipart/form-data" onsubmit="return validarFormularioEdicao();">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="id" value="<?php echo (int)$paciente['id']; ?>">
            <?php if ($erroConvenios): ?>
                <div class="alert alert-danger" role="alert">
                    Não foi possível carregar os convênios. Atualize a página antes de salvar para evitar alterações incorretas no convênio.
                </div>
            <?php endif; ?>

            <div class="campo-config">
                <label>UID do paciente</label>
                <input type="text" value="<?php echo htmlspecialchars($paciente['uid']); ?>" disabled>
            </div>

            <h2 class="titulo-cartao-config">Editar dados de <?php echo htmlspecialchars($paciente['nome']); ?></h2>

            <div class="campo-config">
                <label for="nomeEditar">Nome completo</label>
                <input type="text" id="nomeEditar" name="nome" value="<?php echo htmlspecialchars($paciente['nome']); ?>" required>
            </div>

            <div class="linha-config-dupla">
                <div class="campo-config">
                    <label for="tipoDocEditar">Tipo de documento</label>
                    <select id="tipoDocEditar" name="tipo_documento">
                        <?php
                        $tipos = ['RG' => 'RG', 'CPF' => 'CPF', 'CNH' => 'CNH', 'Passaporte' => 'Passaporte', 'Certidao' => 'Certidão', 'Outro' => 'Outro documento oficial'];
                        foreach ($tipos as $valor => $label):
                        ?>
                            <option value="<?php echo htmlspecialchars($valor, ENT_QUOTES, 'UTF-8'); ?>" <?php echo (($paciente['tipo_documento'] && strcasecmp((string)$paciente['tipo_documento'], $valor) === 0) || (!$paciente['tipo_documento'] && $valor === 'CPF')) ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="campo-config">
                    <label for="documentoEditar">Número do Documento</label>
                    <input type="text" id="documentoEditar" name="documento" value="<?php echo htmlspecialchars($paciente['num_doc'] ?? ''); ?>" maxlength="60" required>
                </div>
            </div>

            <div class="campo-config">
                <label for="docComprovanteEditar">Comprovante do documento <span class="campo-opcional">(opcional)</span></label>
                <?php if (!empty($paciente['doc_comprovante'])): ?>
                    <p class="text-muted" style="font-size: 13px; margin-bottom: 6px;">
                        Arquivo atual: <a href="../../uploads/comprovantes/<?php echo rawurlencode(basename($paciente['doc_comprovante'])); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars(basename($paciente['doc_comprovante']), ENT_QUOTES, 'UTF-8'); ?></a>
                    </p>
                <?php endif; ?>
                <input type="file" id="docComprovanteEditar" name="doc_comprovante" accept=".pdf,.jpg,.jpeg,.png">
            </div>
            <?php if (!empty($paciente['doc_comprovante_resp'])): ?>
                <p class="text-muted" style="font-size:13px;">
                    Comprovante atual do responsável:
                    <a href="../../uploads/comprovantes/<?php echo rawurlencode(basename($paciente['doc_comprovante_resp'])); ?>" target="_blank" rel="noopener">
                        <?php echo htmlspecialchars(basename($paciente['doc_comprovante_resp']), ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                </p>
            <?php endif; ?>
            <div class="campo-config" id="comprovanteResponsavelEditar" style="<?php echo ($paciente['menor_idade'] === 'Y') ? '' : 'display:none;'; ?>">
                <label for="docComprovanteResponsavelEditar">Comprovante do documento do responsável <span class="campo-opcional">(opcional)</span></label>
                <input type="file" id="docComprovanteResponsavelEditar" name="doc_comprovante_resp" accept=".pdf,.jpg,.jpeg,.png">
            </div>

            <div class="campo-config">
                <label for="nascimentoEditar">Data de nascimento</label>
                <input type="date" id="nascimentoEditar" name="nascimento" value="<?php echo $paciente['data_nasc']; ?>" required onchange="verificarIdadeEdicao(this.value)">
            </div>

            <input type="hidden" id="menor_de_idade_editar" name="menor_de_idade" value="<?php echo ($paciente['menor_idade'] === 'Y') ? 'sim' : 'nao'; ?>">

            <div class="linha-config-dupla">
                <div class="campo-config">
                    <label for="telefoneEditar">Telefone <span class="campo-opcional">(opcional)</span></label>
                    <input type="tel" id="telefoneEditar" name="telefone" value="<?php echo htmlspecialchars($paciente['telefone'] ?? ''); ?>" maxlength="20">
                </div>
                <div class="campo-config">
                    <label for="celularEditar">Celular</label>
                    <input type="tel" id="celularEditar" name="celular" value="<?php echo htmlspecialchars($paciente['celular']); ?>" maxlength="15" required>
                </div>
            </div>

            <div class="campo-config">
                <label for="emailEditar">E-mail</label>
                <input type="email" id="emailEditar" name="email" value="<?php echo htmlspecialchars($paciente['email']); ?>" required>
            </div>

            <div class="campo-config">
                <label for="enderecoEditar">Endereço completo</label>
                <input type="text" id="enderecoEditar" name="endereco" value="<?php echo htmlspecialchars($paciente['endereco'] ?? ''); ?>">
            </div>

            <div class="campo-config">
                <label for="obsEditar">Observações <span class="campo-opcional">(opcional)</span></label>
                <textarea id="obsEditar" name="obs_paciente" rows="3"><?php echo htmlspecialchars($paciente['obs_paciente'] ?? ''); ?></textarea>
            </div>

            <!-- ========================= SEÇÃO DO RESPONSÁVEL ========================= -->
            <div id="secao-responsavel-editar" style="<?php echo ($paciente['menor_idade'] === 'Y') ? '' : 'display: none;'; ?> margin-top: 25px; padding-top: 20px; border-top: 2px dashed #0d948b;">
                <h2 class="titulo-cartao-config titulo-config-secundario" style="color: #0d948b;">Informações do Responsável</h2>

                <div class="campo-config">
                    <label for="nomeRespEditar">Nome completo do responsável</label>
                    <input type="text" id="nomeRespEditar" name="nome_responsavel" value="<?php echo htmlspecialchars($paciente['nome_resp'] ?? ''); ?>">
                </div>

                <div class="campo-config">
                    <label for="tipoDocRespEditar">Tipo de documento do responsável</label>
                    <select id="tipoDocRespEditar" name="tipo_documento_responsavel">
                        <?php foreach ($tipos as $valor => $label): ?>
                            <option value="<?php echo htmlspecialchars($valor, ENT_QUOTES, 'UTF-8'); ?>" <?php echo (($paciente['tipo_documento_resp'] && strcasecmp((string)$paciente['tipo_documento_resp'], $valor) === 0) || (!$paciente['tipo_documento_resp'] && $valor === 'CPF')) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="campo-config">
                    <label for="docRespEditar">Número do documento do responsável <span class="campo-opcional">(opcional)</span></label>
                    <input type="text" id="docRespEditar" name="num_doc_responsavel" value="<?php echo htmlspecialchars($paciente['num_doc_resp'] ?? ''); ?>" maxlength="30">
                </div>

                <div class="linha-config-dupla">
                    <div class="campo-config">
                        <label for="telefoneRespEditar">Telefone do responsável <span class="campo-opcional">(opcional)</span></label>
                        <input type="tel" id="telefoneRespEditar" name="telefone_responsavel" value="<?php echo htmlspecialchars($paciente['telefone_resp'] ?? ''); ?>" maxlength="15">
                    </div>
                    <div class="campo-config">
                        <label for="celularRespEditar">Celular do responsável</label>
                        <input type="tel" id="celularRespEditar" name="celular_responsavel" value="<?php echo htmlspecialchars($paciente['celular_resp'] ?? ''); ?>" maxlength="15">
                    </div>
                </div>

                <div class="campo-config">
                    <label for="emailRespEditar">E-mail do responsável <span class="campo-opcional">(opcional)</span></label>
                    <input type="email" id="emailRespEditar" name="email_responsavel" value="<?php echo htmlspecialchars($paciente['email_resp'] ?? ''); ?>">
                </div>

                <div class="campo-config">
                    <label for="nascimentoRespEditar">Data de nascimento do responsável</label>
                    <input type="date" id="nascimentoRespEditar" name="nascimento_responsavel" value="<?php echo $paciente['data_nasc_resp'] ?? ''; ?>">
                </div>
            </div>

            <h2 class="titulo-cartao-config titulo-config-secundario">Convênio</h2>

            <div class="linha-config-dupla">
                <div class="campo-config">
                    <label for="convenioEditar">Convênio</label>
                    <select id="convenioEditar" name="id_convenio" onchange="alternarCamposConvenioEditar(this.value)">
                        <option value="" <?php echo empty($paciente['id_convenio']) ? 'selected' : ''; ?>>Particular (sem convênio)</option>
                        <?php foreach ($listaConvenios as $conv): ?>
                            <option value="<?php echo (int)$conv['id']; ?>" <?php echo ((int)$paciente['id_convenio'] === (int)$conv['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($conv['nome'] . ($conv['status'] !== 'Ativo' ? ' (inativo - cadastro atual)' : ''), ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="campo-config">
                    <label for="carteirinhaEditar">Nº da carteirinha <span class="campo-opcional">(se houver)</span></label>
                    <input type="text" id="carteirinhaEditar" name="carteirinha" value="<?php echo htmlspecialchars($paciente['num_carteirinha'] ?? ''); ?>">
                </div>
            </div>

            <div class="linha-config-dupla" id="secao-plano-convenio-editar" style="<?php echo empty($paciente['id_convenio']) ? 'display: none;' : ''; ?>">
                <div class="campo-config">
                    <label for="planoConvenioEditar">Nome do plano <span class="campo-opcional">(se houver)</span></label>
                    <input type="text" id="planoConvenioEditar" name="plano_convenio" value="<?php echo htmlspecialchars($paciente['plano_convenio'] ?? ''); ?>">
                </div>
                <div class="campo-config">
                    <label for="validadeCarteirinhaEditar">Validade da carteirinha <span class="campo-opcional">(se houver)</span></label>
                    <input type="date" id="validadeCarteirinhaEditar" name="validade_carteirinha" value="<?php echo $paciente['validade_carteirinha'] ?? ''; ?>">
                </div>
            </div>

            <button type="submit" class="botao-entrar botao-config-salvar" <?php echo $erroConvenios ? 'disabled' : ''; ?>>Salvar alterações</button>
        </form>

        <!-- ========================= ZONA DE EXCLUSÃO ========================= -->
        <div class="cartao-config-agenda" style="border: 1px solid #dc3545; margin-top: 20px;">
            <h2 class="titulo-cartao-config" style="color: #dc3545;">
                <i class="bi bi-exclamation-octagon-fill"></i> Excluir paciente
            </h2>
            <p class="text-muted" style="font-size: 14px;">
                Esta ação é <strong>irreversível</strong> e vai apagar permanentemente o cadastro de
                <strong><?php echo htmlspecialchars($paciente['nome']); ?></strong>, junto com todo o histórico
                de consultas, prontuário, exames, receitas, atestados e convênios vinculados a ele.
            </p>

            <button type="button" class="botao-entrar" style="background-color: #dc3545;" onclick="abrirModalExclusao()">
                <i class="bi bi-trash-fill"></i> Excluir paciente
            </button>
        </div>

        <!-- Modal de confirmação -->
        <div id="modalExclusaoPaciente" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
            <div style="background:#fff; border-radius:10px; padding:24px; max-width:420px; width:90%;">
                <h3 style="color:#dc3545; margin-bottom:10px;">Confirmar exclusão</h3>
                <p>Para confirmar, digite o nome completo do paciente exatamente como está cadastrado:</p>
                <p style="font-weight:bold;"><?php echo htmlspecialchars($paciente['nome']); ?></p>

                <form method="post" action="actions/excluir-paciente.php" onsubmit="return validarExclusao();">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="id" value="<?php echo (int)$paciente['id']; ?>">
                    <input type="text" id="confirmacaoNomeExclusao" name="confirmacao_nome" class="form-control" placeholder="Digite o nome do paciente" required style="margin-bottom:15px; width:100%; padding:8px;">

                    <div style="display:flex; gap:10px; justify-content:flex-end;">
                        <button type="button" class="botao-entrar" style="background:#6c757d;" onclick="fecharModalExclusao()">Cancelar</button>
                        <button type="submit" class="botao-entrar" style="background:#dc3545;">Confirmar exclusão</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

</div>

<script>
function alternarCamposConvenioEditar(valor) {
    const secaoPlano = document.getElementById('secao-plano-convenio-editar');
    secaoPlano.style.display = valor ? 'flex' : 'none';
}

function calcularIdadeEditar(dataNasc) {
    if (!dataNasc) return 0;
    const partes = dataNasc.split('-').map(Number);
    const hoje = new Date();
    const nascimento = new Date(partes[0], partes[1] - 1, partes[2]);
    let idade = hoje.getFullYear() - nascimento.getFullYear();
    const m = hoje.getMonth() - nascimento.getMonth();
    if (m < 0 || (m === 0 && hoje.getDate() < nascimento.getDate())) {
        idade--;
    }
    return idade;
}

function verificarIdadeEdicao(dataNasc) {
    const idade = calcularIdadeEditar(dataNasc);
    const secaoResp = document.getElementById('secao-responsavel-editar');
    const inputMenor = document.getElementById('menor_de_idade_editar');
    const nomeResp = document.getElementById('nomeRespEditar');
    const celResp = document.getElementById('celularRespEditar');
    const nascResp = document.getElementById('nascimentoRespEditar');
    const comprovanteResp = document.getElementById('comprovanteResponsavelEditar');

    if (idade < 18 && dataNasc) {
        secaoResp.style.display = 'block';
        comprovanteResp.style.display = 'block';
        inputMenor.value = 'sim';
        nomeResp.setAttribute('required', 'required');
        celResp.setAttribute('required', 'required');
        nascResp.setAttribute('required', 'required');
    } else {
        secaoResp.style.display = 'none';
        comprovanteResp.style.display = 'none';
        inputMenor.value = 'nao';
        nomeResp.removeAttribute('required');
        celResp.removeAttribute('required');
        nascResp.removeAttribute('required');
    }

}

verificarIdadeEdicao(document.getElementById('nascimentoEditar').value);

function validarFormularioEdicao() {
    const inputMenor = document.getElementById('menor_de_idade_editar').value;
    if (inputMenor === 'sim') {
        const dataNascResp = document.getElementById('nascimentoRespEditar').value;
        const idadeResp = calcularIdadeEditar(dataNascResp);
        if (idadeResp < 18) {
            alert('O responsável cadastrado precisa ter pelo menos 18 anos de idade.');
            document.getElementById('nascimentoRespEditar').focus();
            return false;
        }
    }
    return true;
}

function abrirModalExclusao() {
    document.getElementById('modalExclusaoPaciente').style.display = 'flex';
    document.getElementById('confirmacaoNomeExclusao').focus();
}

function fecharModalExclusao() {
    document.getElementById('modalExclusaoPaciente').style.display = 'none';
    document.getElementById('confirmacaoNomeExclusao').value = '';
}

function validarExclusao() {
    const nomeDigitado = document.getElementById('confirmacaoNomeExclusao').value.trim().toLowerCase();
    const nomeReal = <?php echo json_encode(mb_strtolower(trim($paciente['nome'] ?? '')), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;

    if (nomeDigitado !== nomeReal) {
        alert('O nome digitado não confere com o nome do paciente. Exclusão cancelada.');
        return false;
    }
    return confirm('Tem certeza? Essa ação é irreversível e vai apagar todo o histórico do paciente.');
}
</script>

</body>
</html>