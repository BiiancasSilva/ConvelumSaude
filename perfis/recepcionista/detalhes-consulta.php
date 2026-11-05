<?php
// recepcionista/detalhes-consulta.php
// Detalhes de um horário da agenda geral, visto pela recepção.
session_start();
require_once '../../conexao.php';

if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'recepcionista') {
    header("Location: ../../auth/login.php");
    exit();
}

$csrfToken = $_SESSION['csrf_consultas_detalhes'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_consultas_detalhes'] = $csrfToken;

$rawConsultaId = $_GET['id'] ?? $_POST['id'] ?? null;
$consultaId = is_string($rawConsultaId) || is_int($rawConsultaId)
    ? filter_var($rawConsultaId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
if ($consultaId <= 0) {
    header("Location: painel.php");
    exit();
}

// Mensagens mostradas no topo depois de cada ação (a chave vem por ?msg=)
$mensagensFlash = [
    'confirmada'       => ['success', 'Consulta confirmada com sucesso.'],
    'checkin'          => ['success', 'Chegada do paciente registrada.'],
    'checkin_invalido' => ['warning', 'Não foi possível registrar a chegada. Ela só pode ser registrada no dia da consulta e uma única vez.'],
    'cancelada'        => ['success', 'Consulta cancelada. O horário foi liberado na agenda.'],
    'remarcada'        => ['success', 'Consulta reagendada com sucesso.'],
    'sem_alteracao'    => ['warning', 'Nada foi alterado. A consulta pode já ter sido atualizada por outra pessoa.'],
    'csrf_invalido'    => ['danger', 'Sua sessão expirou. Atualize a página e tente novamente.'],
    'erro_acao'        => ['danger', 'Não foi possível atualizar a consulta. Tente novamente mais tarde.'],
];

// ================== AÇÕES (POST) ==================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tokenEnviado = $_POST['csrf_token'] ?? '';
    if (
        !is_string($tokenEnviado) ||
        empty($_SESSION['csrf_consultas_detalhes']) ||
        !hash_equals($_SESSION['csrf_consultas_detalhes'], $tokenEnviado)
    ) {
        header("Location: detalhes-consulta.php?id=" . $consultaId . "&msg=csrf_invalido");
        exit();
    }

    $acao = isset($_POST['acao']) && is_string($_POST['acao']) ? $_POST['acao'] : '';
    $flash = 'sem_alteracao';

    try {
        if ($acao === 'confirmar') {
            // Só confirma o que está pendente
            $st = $pdo->prepare("UPDATE consultas SET status = 'confirmada' WHERE id = ? AND status = 'pendente'");
            $st->execute([$consultaId]);
            if ($st->rowCount() > 0) {
                $flash = 'confirmada';
            }

        } elseif ($acao === 'checkin') {
            // Chegada: só no dia da consulta, uma vez, e só se a consulta está ativa.
            // Paciente que chegou conta como confirmado.
            $st = $pdo->prepare("
                UPDATE consultas
                SET checkin_em = NOW(), status = 'confirmada'
                WHERE id = ?
                  AND status IN ('pendente', 'confirmada')
                  AND checkin_em IS NULL
                  AND DATE(data_hora) = CURDATE()
            ");
            $st->execute([$consultaId]);
            $flash = ($st->rowCount() > 0) ? 'checkin' : 'checkin_invalido';

        } elseif ($acao === 'cancelar') {
            // Só cancela o que ainda está ativo (não mexe em consulta já realizada/cancelada)
            $st = $pdo->prepare("UPDATE consultas SET status = 'cancelada' WHERE id = ? AND status IN ('pendente', 'confirmada')");
            $st->execute([$consultaId]);
            if ($st->rowCount() > 0) {
                $flash = 'cancelada';
            }
        }
    } catch (PDOException $e) {
        error_log('Falha ao atualizar consulta #' . $consultaId . ': ' . $e->getMessage());
        $flash = 'erro_acao';
    }

    header("Location: detalhes-consulta.php?id=" . $consultaId . "&msg=" . $flash);
    exit();
}

// ================== BUSCA A CONSULTA ==================
try {
    $stmt = $pdo->prepare("
        SELECT
            c.id,
            c.data_hora,
            c.tipo_atendimento,
            c.status,
            c.is_encaixe,
            c.agendado_por,
            c.checkin_em,
            p.nome            AS paciente,
            p.celular         AS paciente_celular,
            p.telefone        AS paciente_telefone,
            cvc.nome          AS convenio,
            COALESCE(pl.nome_plano, p.plano_convenio) AS plano_convenio,
            m.nome            AS medico,
            e.nome            AS especialidade,
            e.preco           AS preco,
            s.nome            AS sala
        FROM consultas c
        INNER JOIN paciente        p   ON p.id   = c.id_paciente
        INNER JOIN medico          m   ON m.id   = c.id_medico
        INNER JOIN especialidade   e   ON e.id   = c.id_especialidade
        LEFT  JOIN salas           s   ON s.id   = c.id_sala
        LEFT  JOIN convenios       cvc ON cvc.id = COALESCE(c.id_convenio, c.id_convenios, p.id_convenio)
        LEFT  JOIN convenio_planos pl  ON pl.id  = c.id_plano
        WHERE c.id = ?
        LIMIT 1
    ");
    $stmt->execute([$consultaId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Falha ao carregar consulta #' . $consultaId . ': ' . $e->getMessage());
    http_response_code(500);
    exit('Não foi possível carregar os detalhes da consulta. Tente novamente mais tarde.');
}

if (!$row) {
    header("Location: painel.php");
    exit();
}

$dt = new DateTime($row['data_hora']);

$telefone = $row['paciente_celular'] ?: ($row['paciente_telefone'] ?: 'Sem telefone');

if (!empty($row['convenio'])) {
    $convenioTexto = $row['convenio'] . (!empty($row['plano_convenio']) ? ' - ' . $row['plano_convenio'] : '');
} else {
    $convenioTexto = 'Particular (sem convênio)';
}

$consulta = [
    'id'            => (int)$row['id'],
    'paciente'      => $row['paciente'],
    'telefone'      => $telefone,
    'convenio'      => $convenioTexto,
    'medico'        => $row['medico'],
    'especialidade' => $row['especialidade'],
    'preco'         => $row['preco'],
    'data'          => $dt->format('d/m/Y'),
    'hora'          => $dt->format('H:i'),
    'sala'          => $row['sala'] ?: 'Não se aplica',
    'tipo'          => $row['tipo_atendimento'],
    'status'        => $row['status'],
    'encaixe'       => (int)$row['is_encaixe'] === 1,
    'agendado_por'  => $row['agendado_por'],
    'checkin_em'    => $row['checkin_em'],
];

$rotulosStatus = [
    'confirmada' => 'Confirmada',
    'pendente'   => 'Aguardando confirmação',
    'cancelada'  => 'Cancelada',
    'realizada'  => 'Realizada',
];

$classeSelo = 'pendente';
if ($consulta['status'] === 'confirmada' || $consulta['status'] === 'realizada') {
    $classeSelo = 'aprovada';
} elseif ($consulta['status'] === 'cancelada') {
    $classeSelo = 'negada';
}

// Só dá pra confirmar / registrar chegada / remarcar / cancelar enquanto a consulta está ativa
$consultaAtiva = in_array($consulta['status'], ['pendente', 'confirmada'], true);
$ehHoje        = ($dt->format('Y-m-d') === date('Y-m-d'));
$jaChegou      = !empty($consulta['checkin_em']);

$chaveFlash = isset($_GET['msg']) && is_string($_GET['msg']) ? $_GET['msg'] : '';
$flash = $mensagensFlash[$chaveFlash] ?? null;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes da Consulta - Convelum Saúde</title>

    <link href="../../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/responsivo.css">
    <style>
        /* Botões de ação: usam os botões do Bootstrap (já carregado) com layout em grade */
        .acoes-detalhe {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .acoes-detalhe > * {
            flex: 1 1 210px;
            margin: 0;
        }
        .acoes-detalhe form {
            display: flex;
        }
        .acoes-detalhe .btn {
            width: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 16px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 15px;
            line-height: 1.2;
        }
        .acoes-detalhe .btn i {
            font-size: 17px;
        }
        .dica-acoes {
            margin: 12px 0 0;
            font-size: 13px;
            color: #6c757d;
        }
        .botao-salvar-remarcar {
            width: 100%;
            margin-top: 8px;
            padding: 12px 16px;
            border-radius: 10px;
            font-weight: 600;
        }
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
        <a href="painel.php" class="topo-painel-sair">
            <i class="bi bi-arrow-left"></i> Voltar ao painel
        </a>
    </div>
</div>

<!-- ========================= CABEÇALHO ========================= -->
<div id="painel-boasvindas">
    <div>
        <span id="subtitulo-painel">AGENDA GERAL</span>
        <h1 id="titulo-painel">Detalhes da consulta</h1>
        <p id="texto-painel"><?php echo $consulta['data']; ?> às <?php echo $consulta['hora']; ?></p>
    </div>
    <span class="selo-solicitacao-<?php echo $classeSelo; ?> selo-status-detalhe">
        <?php echo $rotulosStatus[$consulta['status']] ?? htmlspecialchars($consulta['status']); ?>
    </span>
</div>

<div class="secao-config-agenda">

    <?php if ($flash): ?>
        <div class="alert alert-<?php echo $flash[0]; ?>" role="alert">
            <?php echo htmlspecialchars($flash[1]); ?>
        </div>
    <?php endif; ?>

    <!-- ========================= DADOS DO PACIENTE ========================= -->
    <div class="cartao-config-agenda">
        <h2 class="titulo-cartao-config">Paciente</h2>

        <div class="cabecalho-paciente cabecalho-paciente-detalhe">
            <div class="cabecalho-paciente-avatar">
                <i class="bi bi-person-fill"></i>
            </div>
            <div>
                <p class="cabecalho-paciente-nome"><?php echo htmlspecialchars($consulta['paciente']); ?></p>
                <p class="cabecalho-paciente-detalhes">
                    <?php echo htmlspecialchars($consulta['telefone']); ?> · <?php echo htmlspecialchars($consulta['convenio']); ?>
                </p>
            </div>
        </div>
    </div>

    <!-- ========================= DADOS DA CONSULTA ========================= -->
    <div class="cartao-config-agenda">
        <h2 class="titulo-cartao-config">Consulta</h2>

        <div class="resumo-agendamento">
            <div class="linha-resumo">
                <span>Médico</span>
                <strong><?php echo htmlspecialchars($consulta['medico']); ?></strong>
            </div>
            <div class="linha-resumo">
                <span>Especialidade</span>
                <strong><?php echo htmlspecialchars($consulta['especialidade']); ?></strong>
            </div>
            <div class="linha-resumo">
                <span>Tipo</span>
                <strong><?php echo htmlspecialchars($consulta['tipo']); ?><?php echo $consulta['encaixe'] ? ' (encaixe)' : ''; ?></strong>
            </div>
            <div class="linha-resumo">
                <span>Sala</span>
                <strong><?php echo $consulta['tipo'] === 'Presencial' ? htmlspecialchars($consulta['sala']) : 'Não se aplica (teleconsulta)'; ?></strong>
            </div>
            <div class="linha-resumo">
                <span>Data</span>
                <strong><?php echo $consulta['data']; ?></strong>
            </div>
            <div class="linha-resumo">
                <span>Horário</span>
                <strong><?php echo $consulta['hora']; ?></strong>
            </div>
            <?php if ($consulta['preco'] !== null): ?>
            <div class="linha-resumo">
                <span>Valor da consulta</span>
                <strong>R$ <?php echo number_format((float)$consulta['preco'], 2, ',', '.'); ?></strong>
            </div>
            <?php endif; ?>
            <div class="linha-resumo">
                <span>Agendado por</span>
                <strong><?php echo htmlspecialchars($consulta['agendado_por']); ?></strong>
            </div>
            <div class="linha-resumo">
                <span>Chegada do paciente</span>
                <strong><?php echo $jaChegou ? 'Registrada às ' . (new DateTime($consulta['checkin_em']))->format('H:i') : 'Ainda não chegou'; ?></strong>
            </div>
        </div>

        <div class="alert alert-info d-flex align-items-start gap-2 mt-3 mb-0" role="alert">
            <i class="bi bi-cash-coin"></i>
            <div>
                <strong>Pagamento somente na recepção.</strong><br>
                O pagamento da consulta é feito presencialmente na recepção da clínica.
            </div>
        </div>
    </div>

    <!-- ========================= AÇÕES ========================= -->
    <?php if ($consultaAtiva): ?>
    <div class="cartao-config-agenda">
        <h2 class="titulo-cartao-config">Ações</h2>

        <div class="acoes-detalhe">

            <?php if ($consulta['status'] === 'pendente'): ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="acao" value="confirmar">
                    <input type="hidden" name="id" value="<?php echo $consulta['id']; ?>">
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle"></i> Confirmar consulta
                    </button>
                </form>
            <?php endif; ?>

            <?php if ($jaChegou): ?>
                <button type="button" class="btn btn-outline-success" disabled>
                    <i class="bi bi-check-circle-fill"></i> Chegada registrada
                </button>
            <?php elseif ($ehHoje): ?>
                <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="acao" value="checkin">
                    <input type="hidden" name="id" value="<?php echo $consulta['id']; ?>">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-person-check"></i> Registrar chegada
                    </button>
                </form>
            <?php else: ?>
                <button type="button" class="btn btn-outline-secondary" disabled
                        title="A chegada só pode ser registrada no dia da consulta">
                    <i class="bi bi-person-check"></i> Registrar chegada
                </button>
            <?php endif; ?>

            <a href="reagendar-consulta.php?id=<?php echo $consulta['id']; ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-repeat"></i> Remarcar
            </a>

            <form method="post" id="formCancelarConsulta">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="acao" value="cancelar">
                <input type="hidden" name="id" value="<?php echo $consulta['id']; ?>">
                <button type="button" class="btn btn-outline-danger" onclick="cancelarConsulta()">
                    <i class="bi bi-x-circle"></i> Cancelar consulta
                </button>
            </form>
        </div>

        <?php if (!$jaChegou && !$ehHoje): ?>
            <p class="dica-acoes"><i class="bi bi-info-circle"></i> A chegada do paciente só pode ser registrada no dia da consulta.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>

<script>
function cancelarConsulta() {
    if (confirm('Tem certeza que quer cancelar essa consulta? O horário será liberado na agenda.')) {
        document.getElementById('formCancelarConsulta').submit();
    }
}
</script>

</body>
</html>