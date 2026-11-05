<?php
// medico/agenda-configurar.php
session_start();
require_once '../../conexao.php';

// Validação de sessão para o perfil de médico
if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'medico') {
    header("Location: ../../auth/login.php");
    exit();
}

$medicoId = filter_var($_SESSION['usuario_id'], FILTER_VALIDATE_INT);
if (!$medicoId) {
    header("Location: ../../auth/login.php");
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ================= COLETANDO DADOS REAIS DO BANCO =================
$erroCarregamento = '';
try {
    $stmtConfig = $pdo->prepare("SELECT dias_trabalho, hora_inicio, hora_fim, duracao_consulta, intervalo_consulta FROM medico WHERE id = ? LIMIT 1");
    $stmtConfig->execute([$medicoId]);
    $config = $stmtConfig->fetch(PDO::FETCH_ASSOC);
    if (!$config) {
        throw new RuntimeException('Conta médica não encontrada.');
    }

    $diasAtivos = !empty($config['dias_trabalho']) ? explode(',', $config['dias_trabalho']) : ['seg', 'ter', 'qua', 'qui', 'sex'];
    $horaInicio = $config['hora_inicio'] ?? '08:00';
    $horaFim    = $config['hora_fim'] ?? '18:00';
    $duracao    = $config['duracao_consulta'] ?? '30';
    $intervalo  = $config['intervalo_consulta'] ?? '10';

    $ausencias = [];
    $bloqueios = [];
    $stmtAusencias = $pdo->prepare("SELECT id, tipo, data_inicio, data_fim, motivo FROM medico_ausencias WHERE id_medico = ? ORDER BY data_inicio DESC");
    $stmtAusencias->execute([$medicoId]);
    foreach ($stmtAusencias->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $dataInicio = date('d/m/Y', strtotime($row['data_inicio']));
        $dataFim = date('d/m/Y', strtotime($row['data_fim']));
        $ausencias[] = [
            'id' => (int)$row['id'],
            'origem' => 'medico_ausencias',
            'tipo' => $row['tipo'],
            'periodo' => $dataInicio === $dataFim ? $dataInicio : $dataInicio . ' a ' . $dataFim,
            'motivo' => (string)($row['motivo'] ?? ''),
        ];
    }

    $stmtBloqueios = $pdo->prepare("SELECT id, data_bloqueio, hora_inicio, hora_fim, motivo FROM medico_bloqueios WHERE id_medico = ? ORDER BY data_bloqueio DESC, hora_inicio DESC");
    $stmtBloqueios->execute([$medicoId]);
    foreach ($stmtBloqueios->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $bloqueios[] = [
            'id' => (int)$row['id'],
            'origem' => 'medico_bloqueios',
            'data' => date('d/m/Y', strtotime($row['data_bloqueio'])),
            'horario' => substr((string)$row['hora_inicio'], 0, 5) . ' às ' . substr((string)$row['hora_fim'], 0, 5),
            'motivo' => (string)($row['motivo'] ?? ''),
        ];
    }

    // Keep displaying records created by older versions that used bloqueio_agenda.
    $stmtLegado = $pdo->prepare("SELECT id, data_inicio, data_fim, motivo FROM bloqueio_agenda WHERE id_medico = ? ORDER BY data_inicio DESC");
    $stmtLegado->execute([$medicoId]);
    foreach ($stmtLegado->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (preg_match('/^\[(.+?)\]\s*(.*)$/', (string)$row['motivo'], $m)) {
            $dataInicio = date('d/m/Y', strtotime($row['data_inicio']));
            $dataFim = date('d/m/Y', strtotime($row['data_fim']));
            $ausencias[] = [
                'id' => (int)$row['id'],
                'origem' => 'bloqueio_agenda',
                'tipo' => $m[1],
                'periodo' => $dataInicio === $dataFim ? $dataInicio : $dataInicio . ' a ' . $dataFim,
                'motivo' => $m[2],
            ];
        } else {
            $bloqueios[] = [
                'id' => (int)$row['id'],
                'origem' => 'bloqueio_agenda',
                'data' => date('d/m/Y', strtotime($row['data_inicio'])),
                'horario' => date('H:i', strtotime($row['data_inicio'])) . ' às ' . date('H:i', strtotime($row['data_fim'])),
                'motivo' => (string)$row['motivo'],
            ];
        }
    }

} catch (Throwable $e) {
    error_log('Erro ao carregar configuração da agenda médica: ' . $e->getMessage());
    $erroCarregamento = 'Não foi possível carregar sua agenda. Atualize a página ou tente novamente mais tarde.';
    $diasAtivos = ['seg', 'ter', 'qua', 'qui', 'sex'];
    $horaInicio = '08:00';
    $horaFim    = '18:00';
    $duracao    = '30';
    $intervalo  = '10';
    $ausencias  = [];
    $bloqueios  = [];
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurar Agenda - Convelum Saúde</title>

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
        <a href="agenda.php" class="topo-painel-sair">
            <i class="bi bi-arrow-left"></i> Voltar à agenda
        </a>
    </div>
</div>

<!-- ========================= CABEÇALHO ========================= -->
<div id="painel-boasvindas">
    <div>
        <span id="subtitulo-painel">CONFIGURAÇÕES</span>
        <h1 id="titulo-painel">Configurar agenda</h1>
        <p id="texto-painel">Defina seus dias e horários de atendimento, ausências e bloqueios.</p>
    </div>
</div>

<?php if (isset($_GET['sucesso']) && $_GET['sucesso'] == '1'): ?>
    <div class="alert alert-success alert-dismissible fade show m-4" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i> Configurações da agenda salvas com sucesso!
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (isset($_GET['sucesso']) && $_GET['sucesso'] === 'ausencia'): ?>
    <div class="alert alert-success alert-dismissible fade show m-4" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i> Ausência registrada com sucesso!
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (isset($_GET['sucesso']) && $_GET['sucesso'] === 'bloqueio'): ?>
    <div class="alert alert-success alert-dismissible fade show m-4" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i> Bloqueio de agenda criado com sucesso!
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (isset($_GET['sucesso']) && $_GET['sucesso'] === 'removido'): ?>
    <div class="alert alert-success alert-dismissible fade show m-4" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i> Registro removido com sucesso.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
    </div>
<?php endif; ?>

<?php if ($erroCarregamento !== ''): ?>
    <div class="alert alert-danger m-4" role="alert"><?php echo htmlspecialchars($erroCarregamento, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<?php
$errosAgenda = [
    'csrf' => 'Sua sessão expirou. Atualize a página e tente novamente.',
    'campos' => 'Confira os campos informados.',
    'agenda' => 'Os horários de atendimento não são válidos.',
    'dias' => 'Selecione pelo menos um dia de atendimento.',
    'datas' => 'Confira o período informado. As datas devem ser válidas e futuras.',
    'bloqueio' => 'Confira a data e o intervalo do bloqueio.',
    'conflito' => 'A alteração conflita com consulta ou encaixe já agendado.',
    'ausencia-conflito' => 'Há consultas ou encaixes agendados dentro desse período; reprograme-os antes de registrar a ausência.',
    'bloqueio-conflito' => 'Há consultas, encaixes ou outro bloqueio dentro desse período.',
    'falha' => 'Não foi possível salvar a alteração. Tente novamente.',
    'registro' => 'O registro selecionado não foi encontrado.',
];
if (isset($_GET['erro']) && isset($errosAgenda[(string)$_GET['erro']])): ?>
    <div class="alert alert-danger m-4" role="alert"><?php echo htmlspecialchars($errosAgenda[(string)$_GET['erro']], ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<?php if ($erroCarregamento === ''): ?>
<div class="secao-config-agenda">

    <!-- DIAS E HORÁRIOS DE ATENDIMENTO -->
    <form method="post" action="actions/salvar-config-agenda.php" class="cartao-config-agenda">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
        <h2 class="titulo-cartao-config">Dias e horários de atendimento</h2>

        <div class="campo-config">
            <label>Dias de trabalho</label>
            <div class="grid-dias-semana">
                <?php
                $dias = ['dom' => 'Dom', 'seg' => 'Seg', 'ter' => 'Ter', 'qua' => 'Qua', 'qui' => 'Qui', 'sex' => 'Sex', 'sab' => 'Sáb'];
                foreach ($dias as $chave => $rotulo):
                    $ativo = in_array($chave, $diasAtivos) ? 'ativo' : '';
                ?>
                    <button type="button" class="dia-semana-opcao <?php echo $ativo; ?>" data-dia="<?php echo $chave; ?>">
                        <?php echo $rotulo; ?>
                    </button>
                <?php endforeach; ?>
                <input type="hidden" name="dias" id="diasSelecionados" value="<?php echo htmlspecialchars(implode(',', $diasAtivos), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
        </div>

        <div class="linha-config-dupla">
            <div class="campo-config">
                <label for="horaInicio">Horário de início</label>
                <input type="time" id="horaInicio" name="hora_inicio" value="<?php echo htmlspecialchars((string)$horaInicio, ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
            <div class="campo-config">
                <label for="horaFim">Horário de término</label>
                <input type="time" id="horaFim" name="hora_fim" value="<?php echo htmlspecialchars((string)$horaFim, ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
        </div>

        <div class="linha-config-dupla">
            <div class="campo-config">
                <label for="duracaoConsulta">Duração da consulta</label>
                <select id="duracaoConsulta" name="duracao">
                    <option value="15" <?php echo ($duracao == '15') ? 'selected' : ''; ?>>15 minutos</option>
                    <option value="20" <?php echo ($duracao == '20') ? 'selected' : ''; ?>>20 minutos</option>
                    <option value="30" <?php echo ($duracao == '30') ? 'selected' : ''; ?>>30 minutos</option>
                    <option value="45" <?php echo ($duracao == '45') ? 'selected' : ''; ?>>45 minutos</option>
                    <option value="60" <?php echo ($duracao == '60') ? 'selected' : ''; ?>>60 minutos</option>
                </select>
            </div>
            <div class="campo-config">
                <label for="intervaloConsulta">Intervalo entre consultas</label>
                <select id="intervaloConsulta" name="intervalo">
                    <option value="0" <?php echo ($intervalo == '0') ? 'selected' : ''; ?>>Sem intervalo</option>
                    <option value="5" <?php echo ($intervalo == '5') ? 'selected' : ''; ?>>5 minutos</option>
                    <option value="10" <?php echo ($intervalo == '10') ? 'selected' : ''; ?>>10 minutos</option>
                    <option value="15" <?php echo ($intervalo == '15') ? 'selected' : ''; ?>>15 minutos</option>
                </select>
            </div>
        </div>

        <button type="submit" class="botao-entrar botao-config-salvar">Salvar horários</button>
    </form>

    <!-- AUSÊNCIAS: FÉRIAS, LICENÇAS, AUSÊNCIAS TEMPORÁRIAS -->
    <div class="cartao-config-agenda">
        <h2 class="titulo-cartao-config">Férias, licenças e ausências</h2>

        <form method="post" action="actions/registrar-ausencia.php" class="form-inline-config">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
            <div class="campo-config">
                <label for="tipoAusencia">Tipo</label>
                <select id="tipoAusencia" name="tipo">
                    <option value="ferias">Férias</option>
                    <option value="licenca">Licença</option>
                    <option value="ausencia">Ausência temporária</option>
                </select>
            </div>
            <div class="campo-config">
                <label for="ausenciaInicio">Data início</label>
                <input type="date" id="ausenciaInicio" name="data_inicio" min="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="campo-config">
                <label for="ausenciaFim">Data fim</label>
                <input type="date" id="ausenciaFim" name="data_fim" min="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="campo-config campo-config-largo">
                <label for="ausenciaMotivo">Motivo (opcional)</label>
                <input type="text" id="ausenciaMotivo" name="motivo" maxlength="255" placeholder="Ex: férias anuais, congresso...">
            </div>
            <button type="submit" class="botao-config-adicionar">
                <i class="bi bi-plus-lg"></i> Registrar
            </button>
        </form>

        <div class="lista-config-registros">
            <?php if (!empty($ausencias)): ?>
                <?php foreach ($ausencias as $item): ?>
                    <div class="registro-config">
                        <div>
                            <span class="registro-config-tipo"><?php echo htmlspecialchars($item['tipo'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="registro-config-periodo"><?php echo htmlspecialchars($item['periodo'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="registro-config-motivo"><?php echo htmlspecialchars($item['motivo'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <form method="post" action="actions/remover-registro-agenda.php" onsubmit="return confirm('Remover esta ausência?');">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="id" value="<?php echo (int)$item['id']; ?>">
                            <input type="hidden" name="origem" value="<?php echo htmlspecialchars($item['origem'], ENT_QUOTES, 'UTF-8'); ?>">
                            <button type="submit" class="registro-config-remover" title="Remover"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted p-2">Nenhuma ausência registrada.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- BLOQUEIOS DE AGENDA -->
    <div class="cartao-config-agenda">
        <h2 class="titulo-cartao-config">Bloqueios de agenda</h2>

        <form method="post" action="actions/criar-bloqueio.php" class="form-inline-config">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
            <div class="campo-config">
                <label for="bloqueioData">Data</label>
                <input type="date" id="bloqueioData" name="data" min="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="campo-config">
                <label for="bloqueioInicio">Das</label>
                <input type="time" id="bloqueioInicio" name="hora_inicio" required>
            </div>
            <div class="campo-config">
                <label for="bloqueioFim">Às</label>
                <input type="time" id="bloqueioFim" name="hora_fim" required>
            </div>
            <div class="campo-config campo-config-largo">
                <label for="bloqueioMotivo">Motivo (opcional)</label>
                <input type="text" id="bloqueioMotivo" name="motivo" maxlength="255" placeholder="Ex: reunião, procedimento externo...">
            </div>
            <button type="submit" class="botao-config-adicionar">
                <i class="bi bi-plus-lg"></i> Criar bloqueio
            </button>
        </form>

        <div class="lista-config-registros">
            <?php if (!empty($bloqueios)): ?>
                <?php foreach ($bloqueios as $item): ?>
                    <div class="registro-config">
                        <div>
                            <span class="registro-config-tipo"><?php echo htmlspecialchars($item['data'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="registro-config-periodo"><?php echo htmlspecialchars($item['horario'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="registro-config-motivo"><?php echo htmlspecialchars($item['motivo'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <form method="post" action="actions/remover-registro-agenda.php" onsubmit="return confirm('Remover este bloqueio?');">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="id" value="<?php echo (int)$item['id']; ?>">
                            <input type="hidden" name="origem" value="<?php echo htmlspecialchars($item['origem'], ENT_QUOTES, 'UTF-8'); ?>">
                            <button type="submit" class="registro-config-remover" title="Remover"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted p-2">Nenhum bloqueio cadastrado.</p>
            <?php endif; ?>
        </div>
    </div>

</div>
<?php endif; ?>

<script>
document.querySelectorAll('.dia-semana-opcao').forEach(function (botao) {
    botao.addEventListener('click', function () {
        botao.classList.toggle('ativo');
        const selecionados = [...document.querySelectorAll('.dia-semana-opcao.ativo')]
            .map(function (b) { return b.dataset.dia; });
        document.getElementById('diasSelecionados').value = selecionados.join(',');
    });
});
</script>

</body>
</html>