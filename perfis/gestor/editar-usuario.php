<?php
// gestor/editar-usuario.php
session_start();
require_once '../../conexao.php'; // Ajuste o caminho do conexao.php se necessário

// Proteção de acesso: Apenas gestores
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_perfil'] !== 'gestor') {
    header("Location: ../../auth/login.php");
    exit();
}

$perfisValidos = ['medico', 'recepcionista', 'gestor', 'paciente'];
$id     = (int)($_GET['id'] ?? 0);
$perfil = $_GET['perfil'] ?? '';

if ($id <= 0 || !in_array($perfil, $perfisValidos, true)) {
    header("Location: gerenciar-usuarios.php?erro=" . urlencode('Usuário inválido para edição.'));
    exit();
}

$tabelas = ['medico' => 'medico', 'recepcionista' => 'recepcionistas', 'gestor' => 'gestores', 'paciente' => 'paciente'];
$rotulosPerfil = ['medico' => 'Médico', 'gestor' => 'Gestor', 'recepcionista' => 'Recepcionista', 'paciente' => 'Paciente'];

// Busca o usuário na tabela correspondente ao perfil
try {
    $stmt = $pdo->prepare("SELECT * FROM {$tabelas[$perfil]} WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $usuario = false;
}

if (!$usuario) {
    header("Location: gerenciar-usuarios.php?erro=" . urlencode('Usuário não encontrado.'));
    exit();
}

// Especialidades: lista completa + as que já estão marcadas pro médico em edição
$listaEspecialidades = [];
$idsEspecialidadesAtuais = [];
if ($perfil === 'medico') {
    try {
        $stmtEsp = $pdo->query("SELECT id, nome FROM especialidade ORDER BY nome ASC");
        $listaEspecialidades = $stmtEsp->fetchAll(PDO::FETCH_ASSOC);

        $stmtEspAtual = $pdo->prepare("SELECT id_especialidade FROM medico_especialidades WHERE id_medico = ?");
        $stmtEspAtual->execute([$id]);
        $idsEspecialidadesAtuais = array_column($stmtEspAtual->fetchAll(PDO::FETCH_ASSOC), 'id_especialidade');
    } catch (PDOException $e) {
        $listaEspecialidades = [];
    }
}

// Caminho relativo salvo no banco (ex: "medico/MED002.jpg"), só existe pra medico/recepcionista/gestor
$fotoAtual = $usuario['foto'] ?? null;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Usuário - Convelum Saúde</title>

    <link href="../../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/responsivo.css">
    <style>
        .foto-atual-preview { display:flex; align-items:center; gap:12px; margin-bottom:10px; }
        .foto-atual-preview img { width:64px; height:64px; border-radius:50%; object-fit:cover; }
        .foto-atual-preview span { opacity:.75; font-size:.9rem; }

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

<div class="topo-painel">
    <div class="topo-painel-marca">
        <a href="../../index.php">
            <img src="../../imgs/logofrase.png" alt="Convelum Saúde">
        </a>
    </div>
    <div class="topo-painel-perfil">
        <a href="gerenciar-usuarios.php" class="topo-painel-sair">
            <i class="bi bi-arrow-left"></i> Voltar ao painel
        </a>
    </div>
</div>

<div id="painel-boasvindas">
    <div>
        <span id="subtitulo-painel">CONFIGURAÇÕES DO SISTEMA</span>
        <h1 id="titulo-painel">Editar usuário</h1>
        <p id="texto-painel">Atualize os dados de <?php echo htmlspecialchars($usuario['nome']); ?> (<?php echo $rotulosPerfil[$perfil]; ?>).</p>
    </div>
</div>

<div class="secao-config-agenda">

<?php if (isset($_GET['erro'])): ?>
    <div class="alert alert-danger alert-dismissible w-100 mb-4" role="alert" id="alertaUsuarioErro">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> Erro ao salvar: <?php echo htmlspecialchars($_GET['erro']); ?>
        <button type="button" class="btn-close" aria-label="Fechar" onclick="document.getElementById('alertaUsuarioErro').remove();"></button>
    </div>
<?php endif; ?>

    <form method="post" action="actions/atualizar-usuario.php" class="cartao-config-agenda" enctype="multipart/form-data">
        <h2 class="titulo-cartao-config">Dados pessoais</h2>

        <input type="hidden" name="id" value="<?php echo $usuario['id']; ?>">
        <input type="hidden" name="perfil" value="<?php echo htmlspecialchars($perfil); ?>">

        <div class="campo-config">
            <label>Perfil de acesso</label>
            <input type="text" value="<?php echo $rotulosPerfil[$perfil]; ?>" disabled>
        </div>

        <div class="campo-config">
            <label for="nomeUsuario">Nome completo</label>
            <input type="text" id="nomeUsuario" name="nome" value="<?php echo htmlspecialchars($usuario['nome']); ?>" required>
        </div>

        <?php if ($perfil !== 'paciente'): ?>
            <!-- Foto: médico/recepcionista/gestor. Trocar é opcional mesmo pro médico, já que ele já tem uma foto salva. -->
            <div class="campo-config">
                <label for="fotoUsuario">Foto de perfil</label>
                <?php if (!empty($fotoAtual)): ?>
                    <div class="foto-atual-preview">
                        <img src="../../foto-usuario.php?arquivo=<?php echo urlencode($fotoAtual); ?>" alt="Foto atual">
                        <span>Foto atual — envie um arquivo abaixo só se quiser substituí-la.</span>
                    </div>
                <?php else: ?>
                    <p class="text-muted" style="margin-bottom:8px;">Este usuário ainda não tem foto cadastrada.</p>
                <?php endif; ?>
                <input type="file" id="fotoUsuario" name="foto" accept="image/png, image/jpeg, image/webp">
                <small class="texto-ajuda-campo">Formatos aceitos: JPG, PNG ou WEBP. Deixe em branco pra manter a foto atual.</small>
            </div>
        <?php endif; ?>

        <div class="campo-config">
            <label for="cpfUsuario"><?php echo $perfil === 'paciente' ? 'Documento' : 'CPF'; ?></label>
            <input type="text" id="cpfUsuario" name="cpf" value="<?php echo htmlspecialchars($usuario['num_doc'] ?? $usuario['cpf'] ?? ''); ?>" required>
        </div>

        <?php if ($perfil === 'paciente'): ?>
            <div class="campo-config">
                <label for="tipoDocPaciente">Tipo de documento</label>
                <select id="tipoDocPaciente" name="tipo_doc">
                    <?php foreach (['CPF', 'RG', 'Passaporte', 'Certidao', 'Outro'] as $tipo): ?>
                        <option value="<?php echo $tipo; ?>" <?php echo ($usuario['tipo_documento'] ?? '') === $tipo ? 'selected' : ''; ?>>
                            <?php echo $tipo === 'Certidao' ? 'Certidão de nascimento' : $tipo; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="campo-config">
                <label for="dataNascPaciente">Data de nascimento</label>
                <input type="date" id="dataNascPaciente" name="data_nasc" value="<?php echo htmlspecialchars($usuario['data_nasc'] ?? ''); ?>" required>
            </div>
        <?php else: ?>
            <?php if ($perfil !== 'gestor'): // gestores não tem data_inicio no banco ?>
                <div class="campo-config">
                    <label for="dataInicioUsuario">Data de início</label>
                    <input type="date" id="dataInicioUsuario" name="data_inicio" value="<?php echo htmlspecialchars($usuario['data_inicio'] ?? ''); ?>">
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="campo-config">
            <label for="emailUsuario">E-mail</label>
            <input type="email" id="emailUsuario" name="email" value="<?php echo htmlspecialchars($usuario['email']); ?>" required>
        </div>

        <div class="linha-config-dupla">
            <div class="campo-config">
                <label for="telefoneUsuario">Telefone</label>
                <input type="tel" id="telefoneUsuario" name="telefone" value="<?php echo htmlspecialchars($usuario['telefone'] ?? ''); ?>" maxlength="14">
            </div>
            <div class="campo-config">
                <label for="celularUsuario">Celular</label>
                <input type="tel" id="celularUsuario" name="celular" value="<?php echo htmlspecialchars($usuario['celular'] ?? ''); ?>" maxlength="16" required>
            </div>
        </div>

        <?php if ($perfil === 'medico'): ?>
            <h2 class="titulo-cartao-config titulo-config-secundario">Dados profissionais</h2>

            <div class="campo-config">
                <label for="crmMedico">CRM</label>
                <input type="text" id="crmMedico" name="crm" value="<?php echo htmlspecialchars($usuario['crm'] ?? ''); ?>">
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
                <input type="hidden" name="especialidades" id="especialidadesSelecionadas"
                       value="<?php echo htmlspecialchars(implode(',', $idsEspecialidadesAtuais)); ?>">
            </div>
        <?php endif; ?>

        <?php if ($perfil === 'paciente'): ?>
            <h2 class="titulo-cartao-config titulo-config-secundario">Dados do paciente</h2>

            <div class="campo-config">
                <label for="menorIdadePaciente">Paciente é menor de idade?</label>
                <select id="menorIdadePaciente" name="menor_idade" onchange="alternarCamposResponsavel()">
                    <option value="N" <?php echo ($usuario['menor_idade'] ?? 'N') === 'N' ? 'selected' : ''; ?>>Não</option>
                    <option value="Y" <?php echo ($usuario['menor_idade'] ?? 'N') === 'Y' ? 'selected' : ''; ?>>Sim</option>
                </select>
            </div>

            <div id="camposResponsavel" style="<?php echo ($usuario['menor_idade'] ?? 'N') === 'Y' ? '' : 'display:none;'; ?>">
                <h2 class="titulo-cartao-config titulo-config-secundario">Dados do responsável</h2>

                <div class="campo-config">
                    <label for="nomeResp">Nome do responsável</label>
                    <input type="text" id="nomeResp" name="nome_resp" value="<?php echo htmlspecialchars($usuario['nome_resp'] ?? ''); ?>">
                </div>

                <div class="campo-config">
                    <label for="tipoDocResp">Tipo de documento do responsável</label>
                    <select id="tipoDocResp" name="tipo_doc_resp">
                        <?php foreach (['CPF', 'RG', 'Passaporte', 'Outro'] as $tipo): ?>
                            <option value="<?php echo $tipo; ?>" <?php echo ($usuario['tipo_documento_resp'] ?? '') === $tipo ? 'selected' : ''; ?>><?php echo $tipo; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="campo-config">
                    <label for="cpfResp">Documento do responsável</label>
                    <input type="text" id="cpfResp" name="num_doc_resp" value="<?php echo htmlspecialchars($usuario['num_doc_resp'] ?? ''); ?>">
                </div>

                <div class="campo-config">
                    <label for="dataNascResp">Data de nascimento do responsável</label>
                    <input type="date" id="dataNascResp" name="data_nasc_resp" value="<?php echo htmlspecialchars($usuario['data_nasc_resp'] ?? ''); ?>">
                </div>

                <div class="linha-config-dupla">
                    <div class="campo-config">
                        <label for="telefoneResp">Telefone do responsável</label>
                        <input type="tel" id="telefoneResp" name="telefone_resp" value="<?php echo htmlspecialchars($usuario['telefone_resp'] ?? ''); ?>" maxlength="14">
                    </div>
                    <div class="campo-config">
                        <label for="celularResp">Celular do responsável</label>
                        <input type="tel" id="celularResp" name="celular_resp" value="<?php echo htmlspecialchars($usuario['celular_resp'] ?? ''); ?>" maxlength="16">
                    </div>
                </div>

                <div class="campo-config">
                    <label for="emailResp">E-mail do responsável</label>
                    <input type="email" id="emailResp" name="email_resp" value="<?php echo htmlspecialchars($usuario['email_resp'] ?? ''); ?>">
                </div>
            </div>
        <?php endif; ?>

        <button type="submit" class="botao-entrar botao-config-salvar">Salvar alterações</button>
    </form>

</div>

<script>
const listaEspecialidadesDisponiveis = <?php echo json_encode(array_map(fn($e) => ['id' => (int)$e['id'], 'nome' => $e['nome']], $listaEspecialidades)); ?>;
let especialidadesEscolhidas = listaEspecialidadesDisponiveis.filter(function (e) {
    return <?php echo json_encode(array_map('intval', $idsEspecialidadesAtuais)); ?>.includes(e.id);
});

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

const campoSugestoesEspecialidade = document.getElementById('sugestoesEspecialidade');
const campoEspecialidadesSelecionadas = document.getElementById('especialidadesSelecionadasLista');

if (campoSugestoesEspecialidade && campoEspecialidadesSelecionadas) {
    campoSugestoesEspecialidade.addEventListener('click', function (e) {
        const item = e.target.closest('.sugestao-especialidade-item');
        if (item) selecionarEspecialidadePorId(parseInt(item.dataset.id, 10));
    });

    campoEspecialidadesSelecionadas.addEventListener('click', function (e) {
        const icone = e.target.closest('[data-id]');
        if (icone) removerEspecialidadePorId(parseInt(icone.dataset.id, 10));
    });

    // Fecha a lista de sugestões ao clicar fora dela
    document.addEventListener('click', function (e) {
        if (!e.target.closest('.campo-especialidade-busca')) {
            campoSugestoesEspecialidade.style.display = 'none';
        }
    });

    // Mostra as especialidades já vinculadas assim que a página carrega
    renderizarEspecialidadesSelecionadas();
}

function alternarCamposResponsavel() {
    const menor = document.getElementById('menorIdadePaciente');
    if (!menor) return;
    document.getElementById('camposResponsavel').style.display = (menor.value === 'Y') ? 'block' : 'none';
}

// Máscaras (mesmas do cadastro)
function aplicarMascaraCpf(e) {
    let v = e.target.value.replace(/\D/g, '');
    if (v.length > 11) v = v.slice(0, 11);
    v = v.replace(/(\d{3})(\d)/, '$1.$2');
    v = v.replace(/(\d{3})(\d)/, '$1.$2');
    v = v.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
    e.target.value = v;
}
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

document.getElementById('cpfUsuario').addEventListener('input', function(e) {
    <?php echo $perfil !== 'paciente' ? 'aplicarMascaraCpf(e);' : "if (document.getElementById('tipoDocPaciente').value === 'CPF') aplicarMascaraCpf(e);"; ?>
});
document.getElementById('telefoneUsuario').addEventListener('input', aplicarMascaraTelefoneFixo);
document.getElementById('celularUsuario').addEventListener('input', aplicarMascaraCelular);

const telefoneResp = document.getElementById('telefoneResp');
const celularResp = document.getElementById('celularResp');
if (telefoneResp) telefoneResp.addEventListener('input', aplicarMascaraTelefoneFixo);
if (celularResp) celularResp.addEventListener('input', aplicarMascaraCelular);
</script>

</body>
</html>