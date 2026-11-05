<?php
// recepcionista/cadastrar-paciente.php
session_start();
require_once '../../conexao.php';

// Validação de sessão da recepcionista
if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'recepcionista') {
    header("Location: ../../auth/login.php");
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Busca convênios reais cadastrados no sistema
try {
    $stmtConv = $pdo->query("SELECT id, nome FROM convenios WHERE status = 'Ativo' ORDER BY nome ASC");
    $listaConvenios = $stmtConv->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $listaConvenios = [];
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastrar Paciente - Convelum Saúde</title>

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
        <h1 id="titulo-painel">Cadastrar paciente</h1>
        <p id="texto-painel">Cadastro presencial ou por telefone.</p>
    </div>
</div>

<?php if (isset($_GET['status']) && $_GET['status'] === 'sucesso'): ?>
    <div class="container mt-3" style="max-width: 900px;">
        <div class="alert alert-success alert-dismissible w-100" role="alert" id="alertaPaciente">
            <i class="bi bi-check-circle-fill me-2"></i> Paciente cadastrado com sucesso no banco de dados!
            <button type="button" class="btn-close" aria-label="Fechar" onclick="document.getElementById('alertaPaciente').remove();"></button>
        </div>
    </div>
<?php elseif (isset($_GET['erro'])): ?>
    <div class="container mt-3" style="max-width: 900px;">
        <div class="alert alert-danger alert-dismissible w-100" role="alert" id="alertaPacienteErro">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> Erro ao cadastrar: <?php echo htmlspecialchars((string)$_GET['erro'], ENT_QUOTES, 'UTF-8'); ?>
            <button type="button" class="btn-close" aria-label="Fechar" onclick="document.getElementById('alertaPacienteErro').remove();"></button>
        </div>
    </div>
<?php endif; ?>

<div class="secao-config-agenda">

    <form method="post" action="actions/salvar-paciente.php" class="cartao-config-agenda" enctype="multipart/form-data" onsubmit="return validarFormulario();">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
        <h2 class="titulo-cartao-config">Dados pessoais</h2>

        <div class="campo-config">
            <label for="nomePaciente">Nome completo</label>
            <input type="text" id="nomePaciente" name="nome" maxlength="200" placeholder="Digite o nome completo" required>
        </div>

        <div class="linha-config-dupla">
            <div class="campo-config">
                <label for="tipoDocPaciente">Tipo de documento</label>
                <select id="tipoDocPaciente" name="tipo_documento">
                    <option value="RG">RG</option>
                    <option value="CPF">CPF</option>
                    <option value="CNH">CNH</option>
                    <option value="Passaporte">Passaporte</option>
                    <option value="Certidao">Certidão de nascimento</option>
                    <option value="Outro">Outro documento oficial</option>
                </select>
            </div>
            <div class="campo-config">
                <label for="numeroDocPaciente">Número do documento</label>
                <input type="text" id="numeroDocPaciente" name="numero_documento" placeholder="Digite o número" maxlength="60" required>
            </div>
        </div>

        <div class="campo-config">
            <label for="docComprovante">Comprovante do documento <span class="campo-opcional">(opcional)</span></label>
            <input type="file" id="docComprovante" name="doc_comprovante" accept=".pdf,.jpg,.jpeg,.png">
        </div>

        <div class="campo-config">
            <label for="nascimentoPaciente">Data de nascimento</label>
            <input type="date" id="nascimentoPaciente" name="nascimento" max="<?php echo date('Y-m-d'); ?>" required onchange="verificarIdade(this.value)">
        </div>

        <!-- Input hidden para indicar ao backend se é menor de idade -->
        <input type="hidden" id="menor_de_idade" name="menor_de_idade" value="nao">

        <div class="linha-config-dupla">
            <div class="campo-config">
                <label for="telefonePaciente">Telefone <span class="campo-opcional">(opcional)</span></label>
                <input type="tel" id="telefonePaciente" name="telefone" placeholder="(00) 0000-0000" maxlength="14">
            </div>
            <div class="campo-config">
                <label for="celularPaciente">Celular</label>
                <input type="tel" id="celularPaciente" name="celular" placeholder="(00) 00000-0000" maxlength="15" required>
            </div>
        </div>

        <div class="campo-config">
            <label for="emailPaciente">E-mail <span class="campo-opcional">(opcional)</span></label>
            <input type="email" id="emailPaciente" name="email" maxlength="100" placeholder="Digite o e-mail">
        </div>

        <div class="campo-config">
            <label for="enderecoPaciente">Endereço completo</label>
            <input type="text" id="enderecoPaciente" name="endereco" placeholder="Rua, número, bairro, cidade">
        </div>

        <div class="campo-config">
            <label for="obsPaciente">Observações <span class="campo-opcional">(opcional)</span></label>
            <textarea id="obsPaciente" name="obs_paciente" rows="3" placeholder="Alergias, condições relevantes, observações gerais..."></textarea>
        </div>

        <!-- ========================= SEÇÃO DO RESPONSÁVEL (Dinâmica) ========================= -->
        <div id="secao-responsavel" style="display: none; margin-top: 25px; padding-top: 20px; border-top: 2px dashed #0d948b;">
            <h2 class="titulo-cartao-config titulo-config-secundario" style="color: #0d948b;">Informações do Responsável</h2>
            <p class="text-muted" style="font-size: 14px; margin-bottom: 15px;">Como o paciente é menor de idade, preencha os dados do responsável abaixo:</p>

            <div class="campo-config">
                <label for="nomeResponsavel">Nome completo do responsável</label>
                <input type="text" id="nomeResponsavel" name="nome_responsavel" maxlength="100" placeholder="Digite o nome do responsável">
            </div>

            <div class="campo-config">
                <label for="docNumResponsavel">Número do documento do responsável <span class="campo-opcional">(opcional)</span></label>
                <input type="text" id="docNumResponsavel" name="num_doc_responsavel" placeholder="CPF, RG, etc." maxlength="30">
            </div>

            <div class="campo-config">
                <label for="tipoDocResponsavel">Tipo de documento do responsável</label>
                <select id="tipoDocResponsavel" name="tipo_documento_responsavel">
                    <option value="">Selecione</option>
                    <option value="RG">RG</option>
                    <option value="CPF">CPF</option>
                    <option value="CNH">CNH</option>
                    <option value="Passaporte">Passaporte</option>
                    <option value="Outro">Outro documento oficial</option>
                </select>
            </div>

            <div class="linha-config-dupla">
                <div class="campo-config">
                    <label for="telefoneResponsavel">Telefone do responsável <span class="campo-opcional">(opcional)</span></label>
                    <input type="tel" id="telefoneResponsavel" name="telefone_responsavel" placeholder="(00) 0000-0000" maxlength="14">
                </div>
                <div class="campo-config">
                    <label for="celularResponsavel">Celular do responsável</label>
                    <input type="tel" id="celularResponsavel" name="celular_responsavel" placeholder="(00) 00000-0000" maxlength="15">
                </div>
            </div>

            <div class="campo-config">
                <label for="emailResponsavel">E-mail do responsável <span class="campo-opcional">(opcional)</span></label>
                <input type="email" id="emailResponsavel" name="email_responsavel" maxlength="100" placeholder="Digite o e-mail do responsável">
            </div>

            <div class="campo-config">
                <label for="nascimentoResponsavel">Data de nascimento do responsável</label>
                <input type="date" id="nascimentoResponsavel" name="nascimento_responsavel" max="<?php echo date('Y-m-d'); ?>">
            </div>

            <div class="campo-config">
                <label for="docComprovanteResponsavel">Comprovante do documento do responsável <span class="campo-opcional">(opcional)</span></label>
                <input type="file" id="docComprovanteResponsavel" name="doc_comprovante_responsavel" accept=".pdf,.jpg,.jpeg,.png">
            </div>
        </div>

        <h2 class="titulo-cartao-config titulo-config-secundario">Convênio</h2>

        <div class="linha-config-dupla">
            <div class="campo-config">
                <label for="convenioPaciente">Convênio</label>
                <select id="convenioPaciente" name="id_convenio" onchange="alternarCamposConvenio(this.value)">
                    <option value="">Particular (sem convênio)</option>
                    <?php foreach ($listaConvenios as $conv): ?>
                        <option value="<?php echo $conv['id']; ?>"><?php echo htmlspecialchars($conv['nome']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo-config">
                <label for="carteirinhaPaciente">Nº da carteirinha <span class="campo-opcional">(se houver)</span></label>
                <input type="text" id="carteirinhaPaciente" name="carteirinha" maxlength="50" placeholder="Número da carteirinha">
            </div>
        </div>

        <div class="linha-config-dupla" id="secao-plano-convenio" style="display: none;">
            <div class="campo-config">
                <label for="planoConvenio">Nome do plano <span class="campo-opcional">(se houver)</span></label>
                <input type="text" id="planoConvenio" name="plano_convenio" maxlength="100" placeholder="Ex.: Básico, Premium...">
            </div>
            <div class="campo-config">
                <label for="validadeCarteirinha">Validade da carteirinha <span class="campo-opcional">(se houver)</span></label>
                <input type="date" id="validadeCarteirinha" name="validade_carteirinha">
            </div>
        </div>

        <button type="submit" class="botao-entrar botao-config-salvar">Cadastrar paciente</button>
    </form>

</div>

<script>
// Exibe os campos de plano/validade só quando um convênio é selecionado
function alternarCamposConvenio(valor) {
    const secaoPlano = document.getElementById('secao-plano-convenio');
    secaoPlano.style.display = valor ? 'flex' : 'none';
    if (!valor) {
        document.getElementById('planoConvenio').value = '';
        document.getElementById('validadeCarteirinha').value = '';
    }
}

// Função para calcular a idade com base em uma data
function calcularIdade(dataNasc) {
    if (!dataNasc) return 0;
    const hoje = new Date();
    const partes = dataNasc.split('-').map(Number);
    const nascimento = new Date(partes[0], partes[1] - 1, partes[2]);
    let idade = hoje.getFullYear() - nascimento.getFullYear();
    const m = hoje.getMonth() - nascimento.getMonth();

    if (m < 0 || (m === 0 && hoje.getDate() < nascimento.getDate())) {
        idade--;
    }
    return idade;
}

// Exibição dinâmica da seção do responsável com base na idade do paciente
function verificarIdade(dataNasc) {
    let idade = calcularIdade(dataNasc);

    const secaoResp = document.getElementById('secao-responsavel');
    const inputMenor = document.getElementById('menor_de_idade');
    
    const nomeResp = document.getElementById('nomeResponsavel');
    const celResp = document.getElementById('celularResponsavel');
    const nascResp = document.getElementById('nascimentoResponsavel');
    const tipoDocResp = document.getElementById('tipoDocResponsavel');

    if (idade < 18 && dataNasc) {
        secaoResp.style.display = 'block';
        inputMenor.value = 'sim';
        nomeResp.setAttribute('required', 'required');
        celResp.setAttribute('required', 'required');
        nascResp.setAttribute('required', 'required');
        tipoDocResp.setAttribute('required', 'required');
    } else {
        secaoResp.style.display = 'none';
        inputMenor.value = 'nao';
        nomeResp.removeAttribute('required');
        celResp.removeAttribute('required');
        nascResp.removeAttribute('required');
        tipoDocResp.removeAttribute('required');
        
        nomeResp.value = '';
        document.getElementById('telefoneResponsavel').value = '';
        celResp.value = '';
        document.getElementById('emailResponsavel').value = '';
        nascResp.value = '';
        document.getElementById('docNumResponsavel').value = '';
        document.getElementById('tipoDocResponsavel').value = '';
        document.getElementById('docComprovanteResponsavel').value = '';
    }
}

// Validação final antes de enviar o formulário (Garante que o responsável tem 18 anos ou mais)
function validarFormulario() {
    const inputMenor = document.getElementById('menor_de_idade').value;
    
    if (inputMenor === 'sim') {
        const dataNascResp = document.getElementById('nascimentoResponsavel').value;
        const idadeResp = calcularIdade(dataNascResp);

        if (idadeResp < 18) {
            alert('O responsável cadastrado precisa ter pelo menos 18 anos de idade.');
            document.getElementById('nascimentoResponsavel').focus();
            return false;
        }
    }
    return true;
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

// Aplicando máscaras nos inputs de telefonia existentes
document.getElementById('telefonePaciente').addEventListener('input', aplicarMascaraTelefoneFixo);
document.getElementById('celularPaciente').addEventListener('input', aplicarMascaraCelular);
document.getElementById('telefoneResponsavel').addEventListener('input', aplicarMascaraTelefoneFixo);
document.getElementById('celularResponsavel').addEventListener('input', aplicarMascaraCelular);
</script>

</body>
</html>