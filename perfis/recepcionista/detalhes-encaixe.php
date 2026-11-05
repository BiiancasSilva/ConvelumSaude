<?php
// recepcionista/detalhes-encaixe.php
// Detalhes de um encaixe (tabela "encaixes"), visto pela recepção.
session_start();
require_once '../../conexao.php';

if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'recepcionista') {
    header("Location: ../../auth/login.php");
    exit();
}

$csrfToken = $_SESSION['csrf_encaixes'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_encaixes'] = $csrfToken;

$rawEncaixeId = $_GET['id'] ?? $_POST['id'] ?? null;
$encaixeId = is_string($rawEncaixeId) || is_int($rawEncaixeId)
    ? filter_var($rawEncaixeId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
if ($encaixeId <= 0) {
    header("Location: painel.php");
    exit();
}

// Mensagens mostradas no topo depois de cada ação (a chave vem por ?msg=)
$mensagensFlash = [
    'confirmado'        => ['success', 'Encaixe confirmado com sucesso.'],
    'checkin'           => ['success', 'Chegada do paciente registrada.'],
    'checkin_invalido'  => ['warning', 'Não foi possível registrar a chegada. Ela só pode ser registrada no dia do encaixe e uma única vez.'],
    'cancelado'         => ['success', 'Encaixe cancelado. O horário foi liberado.'],
    'remarcado'         => ['success', 'Encaixe remarcado com sucesso.'],
    'editado'           => ['success', 'Dados do encaixe atualizados com sucesso.'],
    'dados_invalidos'   => ['danger',  'Confira os dados informados e tente novamente.'],
    'remarcar_invalido' => ['danger',  'Data ou horário inválido para remarcar.'],
    'csrf_invalido'     => ['danger',  'Sua sessão expirou. Atualize a página e tente novamente.'],
    'conflito_medico'   => ['danger',  'Esse médico já tem uma consulta ou encaixe nesse horário. Escolha outro horário.'],
    'conflito_paciente' => ['danger',  'Esse paciente já tem uma consulta ou encaixe nesse horário. Escolha outro horário.'],
    'sem_alteracao'     => ['warning', 'Nada foi alterado. O encaixe pode já ter sido atualizado por outra pessoa.'],
];

// ================== AÇÕES (POST) ==================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tokenEnviado = $_POST['csrf_token'] ?? '';
    if (
        !is_string($tokenEnviado) ||
        empty($_SESSION['csrf_encaixes']) ||
        !hash_equals($_SESSION['csrf_encaixes'], $tokenEnviado)
    ) {
        header("Location: detalhes-encaixe.php?id=" . $encaixeId . "&msg=csrf_invalido");
        exit();
    }

    $acao  = $_POST['acao'] ?? '';
    $flash = 'sem_alteracao';

    try {
        if ($acao === 'editar_dados') {
            $novoPaciente = isset($_POST['paciente']) && is_string($_POST['paciente']) ? trim($_POST['paciente']) : '';
            $novoTipo = isset($_POST['tipo']) && is_string($_POST['tipo']) ? $_POST['tipo'] : '';
            $novoMotivo = isset($_POST['motivo']) && is_string($_POST['motivo']) ? trim($_POST['motivo']) : '';
            if (
                $novoPaciente === '' ||
                strlen($novoPaciente) > 200 ||
                !in_array($novoTipo, ['presencial', 'tele'], true) ||
                strlen($novoMotivo) > 5000
            ) {
                header("Location: detalhes-encaixe.php?id=" . $encaixeId . "&msg=dados_invalidos");
                exit();
            }

            $pdo->beginTransaction();
            $stAtual = $pdo->prepare("
                SELECT paciente, medico, data_encaixe, horario, status
                FROM encaixes WHERE id = ? FOR UPDATE
            ");
            $stAtual->execute([$encaixeId]);
            $atual = $stAtual->fetch(PDO::FETCH_ASSOC);
            if (!$atual || !in_array($atual['status'], ['pendente', 'confirmada'], true)) {
                $pdo->rollBack();
                header("Location: detalhes-encaixe.php?id=" . $encaixeId . "&msg=sem_alteracao");
                exit();
            }

            $stMed = $pdo->prepare("SELECT id, duracao_consulta FROM medico WHERE nome = ? LIMIT 1");
            $stMed->execute([$atual['medico']]);
            $medicoAtual = $stMed->fetch(PDO::FETCH_ASSOC);
            $duracao = max(1, (int)($medicoAtual['duracao_consulta'] ?? 30));
            $inicio = new DateTime($atual['data_encaixe'] . ' ' . $atual['horario']);
            $fim = (clone $inicio)->modify('+' . $duracao . ' minutes');
            $inicioSql = $inicio->format('Y-m-d H:i:s');
            $fimSql = $fim->format('Y-m-d H:i:s');

            $stConflito = $pdo->prepare("
                SELECT COUNT(*) FROM consultas c
                INNER JOIN paciente p ON p.id = c.id_paciente
                INNER JOIN medico m ON m.id = c.id_medico
                WHERE p.nome = ? AND c.status <> 'cancelada'
                  AND c.data_hora < ?
                  AND TIMESTAMPADD(MINUTE, COALESCE(m.duracao_consulta, 30), c.data_hora) > ?
            ");
            $stConflito->execute([$novoPaciente, $fimSql, $inicioSql]);
            $conflitoPaciente = (int)$stConflito->fetchColumn() > 0;

            if (!$conflitoPaciente) {
                $stConflito = $pdo->prepare("
                    SELECT COUNT(*) FROM encaixes
                    WHERE id <> ? AND paciente = ? AND status <> 'cancelada'
                      AND data_encaixe = ?
                      AND CAST(CONCAT(data_encaixe, ' ', LEFT(horario, 5), ':00') AS DATETIME) < ?
                      AND TIMESTAMPADD(MINUTE, ?, CAST(CONCAT(data_encaixe, ' ', LEFT(horario, 5), ':00') AS DATETIME)) > ?
                ");
                $stConflito->execute([
                    $encaixeId,
                    $novoPaciente,
                    $atual['data_encaixe'],
                    $fimSql,
                    $duracao,
                    $inicioSql
                ]);
                $conflitoPaciente = (int)$stConflito->fetchColumn() > 0;
            }

            if ($conflitoPaciente) {
                $pdo->rollBack();
                header("Location: detalhes-encaixe.php?id=" . $encaixeId . "&msg=conflito_paciente");
                exit();
            }

            $stAtualiza = $pdo->prepare("
                UPDATE encaixes
                SET paciente = ?, tipo_atendimento = ?, motivo = ?
                WHERE id = ? AND status IN ('pendente', 'confirmada')
            ");
            $stAtualiza->execute([$novoPaciente, $novoTipo, $novoMotivo, $encaixeId]);
            if ($stAtualiza->rowCount() === 0) {
                $pdo->rollBack();
                header("Location: detalhes-encaixe.php?id=" . $encaixeId . "&msg=sem_alteracao");
                exit();
            }
            $pdo->commit();
            $flash = 'editado';

        } elseif ($acao === 'confirmar') {
            $st = $pdo->prepare("UPDATE encaixes SET status = 'confirmada' WHERE id = ? AND status = 'pendente'");
            $st->execute([$encaixeId]);
            if ($st->rowCount() > 0) {
                $flash = 'confirmado';
            }

        } elseif ($acao === 'checkin') {
            // Chegada: só no dia do encaixe, uma vez, e só se está ativo. Quem chegou conta como confirmado.
            $st = $pdo->prepare("
                UPDATE encaixes
                SET checkin_em = NOW(), status = 'confirmada'
                WHERE id = ?
                  AND status IN ('pendente', 'confirmada')
                  AND checkin_em IS NULL
                  AND data_encaixe = CURDATE()
            ");
            $st->execute([$encaixeId]);
            $flash = ($st->rowCount() > 0) ? 'checkin' : 'checkin_invalido';

        } elseif ($acao === 'cancelar') {
            $st = $pdo->prepare("UPDATE encaixes SET status = 'cancelada' WHERE id = ? AND status IN ('pendente', 'confirmada')");
            $st->execute([$encaixeId]);
            if ($st->rowCount() > 0) {
                $flash = 'cancelado';
            }

        } elseif ($acao === 'remarcar') {
            $novaData = isset($_POST['nova_data']) && is_string($_POST['nova_data']) ? $_POST['nova_data'] : '';
            $novoHorario = isset($_POST['novo_horario']) && is_string($_POST['novo_horario']) ? $_POST['novo_horario'] : '';
            $inicioDt = DateTime::createFromFormat('!Y-m-d H:i', $novaData . ' ' . $novoHorario);
            $dateErrors = DateTime::getLastErrors();
            if (
                !$inicioDt ||
                ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0)) ||
                $inicioDt->format('Y-m-d H:i') !== $novaData . ' ' . $novoHorario ||
                !preg_match('/^\d{2}:\d{2}$/', $novoHorario) ||
                $novaData < date('Y-m-d') ||
                ($novaData === date('Y-m-d') && $inicioDt < new DateTime())
            ) {
                header("Location: detalhes-encaixe.php?id=" . $encaixeId . "&msg=remarcar_invalido");
                exit();
            }

            // Dados atuais do encaixe (paciente e médico são guardados pelo nome)
            $stAtual = $pdo->prepare("SELECT paciente, medico, status FROM encaixes WHERE id = ? LIMIT 1");
            $stAtual->execute([$encaixeId]);
            $atual = $stAtual->fetch(PDO::FETCH_ASSOC);

            if (!$atual || !in_array($atual['status'], ['pendente', 'confirmada'], true)) {
                header("Location: detalhes-encaixe.php?id=" . $encaixeId . "&msg=sem_alteracao");
                exit();
            }

            $stMed = $pdo->prepare("
                SELECT id, nome, dias_trabalho, hora_inicio, hora_fim, duracao_consulta, intervalo_consulta
                FROM medico WHERE nome = ? LIMIT 1
            ");
            $stMed->execute([$atual['medico']]);
            $med = $stMed->fetch(PDO::FETCH_ASSOC);
            if (!$med) {
                header("Location: detalhes-encaixe.php?id=" . $encaixeId . "&msg=sem_alteracao");
                exit();
            }

            $duracao = (int)$med['duracao_consulta'];
            if ($duracao <= 0) {
                $duracao = 30;
            }
            $mapaDias = [1 => 'seg', 2 => 'ter', 3 => 'qua', 4 => 'qui', 5 => 'sex', 6 => 'sab', 7 => 'dom'];
            $diasTrabalho = array_map('trim', explode(',', strtolower($med['dias_trabalho'] ?? '')));
            if (!in_array($mapaDias[(int)$inicioDt->format('N')], $diasTrabalho, true)) {
                header("Location: detalhes-encaixe.php?id=" . $encaixeId . "&msg=remarcar_invalido");
                exit();
            }
            $intervalo = max(0, (int)($med['intervalo_consulta'] ?? 0));
            $horaInicio = new DateTime($novaData . ' ' . $med['hora_inicio']);
            $horaFim = new DateTime($novaData . ' ' . $med['hora_fim']);
            $inicioMinutos = (int)$inicioDt->format('H') * 60 + (int)$inicioDt->format('i');
            $inicioJornadaMinutos = (int)$horaInicio->format('H') * 60 + (int)$horaInicio->format('i');
            $fimDt = (clone $inicioDt)->modify('+' . $duracao . ' minutes');
            if (
                $inicioMinutos < $inicioJornadaMinutos ||
                ($inicioMinutos - $inicioJornadaMinutos) % ($duracao + $intervalo) !== 0 ||
                $fimDt > $horaFim
            ) {
                header("Location: detalhes-encaixe.php?id=" . $encaixeId . "&msg=remarcar_invalido");
                exit();
            }

            $qAusencia = $pdo->prepare("
                SELECT COUNT(*) FROM medico_ausencias
                WHERE id_medico = ? AND ? BETWEEN data_inicio AND data_fim
            ");
            $qAusencia->execute([$med['id'], $novaData]);
            if ((int)$qAusencia->fetchColumn() > 0) {
                header("Location: detalhes-encaixe.php?id=" . $encaixeId . "&msg=remarcar_invalido");
                exit();
            }

            $qBloqueio = $pdo->prepare("
                SELECT data_inicio, data_fim FROM bloqueio_agenda
                WHERE id_medico = ? AND ? BETWEEN DATE(data_inicio) AND DATE(data_fim)
            ");
            $qBloqueio->execute([$med['id'], $novaData]);
            foreach ($qBloqueio->fetchAll(PDO::FETCH_ASSOC) as $bloqueio) {
                if (new DateTime($bloqueio['data_inicio']) < $fimDt && new DateTime($bloqueio['data_fim']) > $inicioDt) {
                    header("Location: detalhes-encaixe.php?id=" . $encaixeId . "&msg=remarcar_invalido");
                    exit();
                }
            }

            $qBloqueioHora = $pdo->prepare("
                SELECT hora_inicio, hora_fim FROM medico_bloqueios
                WHERE id_medico = ? AND data_bloqueio = ?
            ");
            $qBloqueioHora->execute([$med['id'], $novaData]);
            foreach ($qBloqueioHora->fetchAll(PDO::FETCH_ASSOC) as $bloqueio) {
                $inicioBloqueio = new DateTime($novaData . ' ' . $bloqueio['hora_inicio']);
                $fimBloqueio = new DateTime($novaData . ' ' . $bloqueio['hora_fim']);
                if ($inicioBloqueio < $fimDt && $fimBloqueio > $inicioDt) {
                    header("Location: detalhes-encaixe.php?id=" . $encaixeId . "&msg=remarcar_invalido");
                    exit();
                }
            }

            $inicio = $inicioDt->format('Y-m-d H:i:s');
            $fim    = (clone $inicioDt)->modify('+' . $duracao . ' minutes')->format('Y-m-d H:i:s');

            $pdo->beginTransaction();

            // Trava o médico até o fim da transação (evita dois agendamentos simultâneos no mesmo horário)
            $stTrava = $pdo->prepare("SELECT id FROM medico WHERE id = ? FOR UPDATE");
            $stTrava->execute([$med['id']]);
            $stTrava->fetchAll();

            // Sobreposição: início_existente < fim_novo E fim_existente > início_novo. Cancelados não contam.
            // 1) Médico com consulta no novo horário
            $q = $pdo->prepare("
                SELECT COUNT(*) FROM consultas
                WHERE id_medico = ? AND status <> 'cancelada'
                  AND data_hora < ? AND TIMESTAMPADD(MINUTE, ?, data_hora) > ?
            ");
            $q->execute([$med['id'], $fim, $duracao, $inicio]);
            $conflitoMedico = $q->fetchColumn() > 0;

            // 2) Médico com OUTRO encaixe no novo horário (ignora este próprio encaixe)
            if (!$conflitoMedico) {
                $q = $pdo->prepare("
                    SELECT COUNT(*) FROM encaixes
                    WHERE id <> ? AND medico = ? AND status <> 'cancelada' AND data_encaixe = ?
                      AND CAST(CONCAT(data_encaixe, ' ', LEFT(horario, 5), ':00') AS DATETIME) < ?
                      AND TIMESTAMPADD(MINUTE, ?, CAST(CONCAT(data_encaixe, ' ', LEFT(horario, 5), ':00') AS DATETIME)) > ?
                ");
                $q->execute([$encaixeId, $med['nome'], $novaData, $fim, $duracao, $inicio]);
                $conflitoMedico = $q->fetchColumn() > 0;
            }

            // 3) Paciente (pelo nome) com consulta no novo horário
            $conflitoPaciente = false;
            if (!$conflitoMedico) {
                $q = $pdo->prepare("
                    SELECT COUNT(*) FROM consultas c
                    INNER JOIN paciente p ON p.id = c.id_paciente
                    INNER JOIN medico   m ON m.id = c.id_medico
                    WHERE p.nome = ? AND c.status <> 'cancelada'
                      AND c.data_hora < ?
                      AND TIMESTAMPADD(MINUTE, COALESCE(m.duracao_consulta, 30), c.data_hora) > ?
                ");
                $q->execute([$atual['paciente'], $fim, $inicio]);
                $conflitoPaciente = $q->fetchColumn() > 0;
            }

            // 4) Paciente (pelo nome) com OUTRO encaixe no novo horário
            if (!$conflitoMedico && !$conflitoPaciente) {
                $q = $pdo->prepare("
                    SELECT COUNT(*) FROM encaixes
                    WHERE id <> ? AND paciente = ? AND status <> 'cancelada' AND data_encaixe = ?
                      AND CAST(CONCAT(data_encaixe, ' ', LEFT(horario, 5), ':00') AS DATETIME) < ?
                      AND TIMESTAMPADD(MINUTE, ?, CAST(CONCAT(data_encaixe, ' ', LEFT(horario, 5), ':00') AS DATETIME)) > ?
                ");
                $q->execute([$encaixeId, $atual['paciente'], $novaData, $fim, $duracao, $inicio]);
                $conflitoPaciente = $q->fetchColumn() > 0;
            }

            if ($conflitoMedico || $conflitoPaciente) {
                $pdo->rollBack();
                header("Location: detalhes-encaixe.php?id=" . $encaixeId . "&msg=" . ($conflitoMedico ? 'conflito_medico' : 'conflito_paciente'));
                exit();
            }

            // Livre: remarca e zera a chegada (paciente ainda não veio no novo horário)
            $up = $pdo->prepare("UPDATE encaixes SET data_encaixe = ?, horario = ?, checkin_em = NULL WHERE id = ?");
            $up->execute([$novaData, $novoHorario, $encaixeId]);
            $pdo->commit();

            $flash = 'remarcado';
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Falha ao atualizar encaixe #' . $encaixeId . ': ' . $e->getMessage());
        header("Location: detalhes-encaixe.php?id=" . $encaixeId . "&msg=dados_invalidos");
        exit();
    }

    header("Location: detalhes-encaixe.php?id=" . $encaixeId . "&msg=" . $flash);
    exit();
}

// ================== BUSCA O ENCAIXE ==================
try {
    $stmt = $pdo->prepare("
        SELECT id, paciente, medico, data_encaixe, horario, tipo_atendimento, motivo, criado_em, status, checkin_em
        FROM encaixes
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$encaixeId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Falha ao carregar encaixe #' . $encaixeId . ': ' . $e->getMessage());
    http_response_code(500);
    exit('Não foi possível carregar os detalhes do encaixe. Tente novamente mais tarde.');
}

if (!$row) {
    header("Location: painel.php");
    exit();
}

// O encaixe guarda paciente e médico só pelo NOME, então buscamos o resto pelo nome.
// Se não achar (nome digitado que não existe no cadastro), a página continua funcionando.
$telefone = null;
$pacienteCadastrado = false;
$especialidades = null;
$medicoId = 0;

try {
    $stmtPac = $pdo->prepare("SELECT celular, telefone FROM paciente WHERE nome = ? LIMIT 1");
    $stmtPac->execute([$row['paciente']]);
    $pac = $stmtPac->fetch(PDO::FETCH_ASSOC);
    if ($pac) {
        $pacienteCadastrado = true;
        $telefone = $pac['celular'] ?: ($pac['telefone'] ?: null);
    }

    $stmtEsp = $pdo->prepare("
        SELECT m.id, GROUP_CONCAT(e.nome SEPARATOR ', ') AS especialidades
        FROM medico m
        LEFT JOIN medico_especialidades me ON me.id_medico = m.id
        LEFT JOIN especialidade e ON e.id = me.id_especialidade
        WHERE m.nome = ?
        GROUP BY m.id
        LIMIT 1
    ");
    $stmtEsp->execute([$row['medico']]);
    $med = $stmtEsp->fetch(PDO::FETCH_ASSOC);
    if ($med) {
        $medicoId = (int)$med['id'];
        $especialidades = $med['especialidades'] ?: null;
    }
} catch (PDOException $e) {
    error_log('Falha ao carregar dados complementares do encaixe #' . $encaixeId . ': ' . $e->getMessage());
}

$dataEncaixe = new DateTime($row['data_encaixe']);
$hora = substr($row['horario'], 0, 5);

$tipoBruto = strtolower(trim($row['tipo_atendimento'] ?? ''));
$tipoLabel = ($tipoBruto === 'tele' || $tipoBruto === 'teleconsulta') ? 'Teleconsulta' : 'Presencial';

$registradoEm = $row['criado_em'] ? (new DateTime($row['criado_em']))->format('d/m/Y H:i') : null;
$motivo = trim($row['motivo'] ?? '');

$status = $row['status'];
$rotulosStatus = [
    'pendente'   => 'Aguardando confirmação',
    'confirmada' => 'Confirmado',
    'cancelada'  => 'Cancelado',
    'realizada'  => 'Realizado',
];
$classeSelo = 'pendente';
if ($status === 'confirmada' || $status === 'realizada') {
    $classeSelo = 'aprovada';
} elseif ($status === 'cancelada') {
    $classeSelo = 'negada';
}

$encaixeAtivo = in_array($status, ['pendente', 'confirmada'], true);
$ehHoje       = ($dataEncaixe->format('Y-m-d') === date('Y-m-d'));
$jaChegou     = !empty($row['checkin_em']);

$chaveFlash = isset($_GET['msg']) && is_string($_GET['msg']) ? $_GET['msg'] : '';
$flash = $mensagensFlash[$chaveFlash] ?? null;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes do Encaixe - Convelum Saúde</title>

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
        #gridHorariosRemarcar {
            max-height: 260px;
            overflow-y: auto;
            padding-right: 4px;
        }
    </style>
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
        <h1 id="titulo-painel">Detalhes do encaixe</h1>
        <p id="texto-painel"><?php echo $dataEncaixe->format('d/m/Y'); ?> às <?php echo $hora; ?></p>
    </div>
    <span class="selo-solicitacao-<?php echo $classeSelo; ?> selo-status-detalhe">
        <?php echo $rotulosStatus[$status] ?? htmlspecialchars($status); ?>
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
                <p class="cabecalho-paciente-nome"><?php echo htmlspecialchars($row['paciente']); ?></p>
                <p class="cabecalho-paciente-detalhes">
                    <?php if ($pacienteCadastrado): ?>
                        <?php echo htmlspecialchars($telefone ?: 'Sem telefone'); ?>
                    <?php else: ?>
                        Nome não encontrado entre os pacientes cadastrados
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>

    <!-- ========================= DADOS DO ENCAIXE ========================= -->
    <div class="cartao-config-agenda">
        <h2 class="titulo-cartao-config">Encaixe</h2>

        <div class="resumo-agendamento">
            <div class="linha-resumo">
                <span>Médico</span>
                <strong><?php echo htmlspecialchars($row['medico']); ?></strong>
            </div>
            <?php if ($especialidades): ?>
            <div class="linha-resumo">
                <span>Especialidade</span>
                <strong><?php echo htmlspecialchars($especialidades); ?></strong>
            </div>
            <?php endif; ?>
            <div class="linha-resumo">
                <span>Tipo</span>
                <strong><?php echo $tipoLabel; ?> (encaixe)</strong>
            </div>
            <div class="linha-resumo">
                <span>Data</span>
                <strong><?php echo $dataEncaixe->format('d/m/Y'); ?></strong>
            </div>
            <div class="linha-resumo">
                <span>Horário</span>
                <strong><?php echo $hora; ?></strong>
            </div>
            <?php if ($registradoEm): ?>
            <div class="linha-resumo">
                <span>Registrado em</span>
                <strong><?php echo $registradoEm; ?></strong>
            </div>
            <?php endif; ?>
            <div class="linha-resumo">
                <span>Chegada do paciente</span>
                <strong><?php echo $jaChegou ? 'Registrada às ' . (new DateTime($row['checkin_em']))->format('H:i') : 'Ainda não chegou'; ?></strong>
            </div>
        </div>

        <p class="aviso-recuperacao aviso-motivo-consulta">
            <i class="bi bi-info-circle"></i>
            <?php echo $motivo !== '' ? htmlspecialchars($motivo) : 'Motivo não informado'; ?>
        </p>

        <div class="alert alert-info d-flex align-items-start gap-2 mt-3 mb-0" role="alert">
            <i class="bi bi-cash-coin"></i>
            <div>
                <strong>Pagamento somente na recepção.</strong><br>
                O pagamento do atendimento é feito presencialmente na recepção da clínica.
            </div>
        </div>
    </div>

    <!-- ========================= AÇÕES ========================= -->
    <?php if ($encaixeAtivo): ?>
    <div class="cartao-config-agenda">
        <h2 class="titulo-cartao-config">Ações</h2>

        <div class="acoes-detalhe">

            <?php if ($status === 'pendente'): ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="acao" value="confirmar">
                    <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle"></i> Confirmar encaixe
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
                    <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-person-check"></i> Registrar chegada
                    </button>
                </form>
            <?php else: ?>
                <button type="button" class="btn btn-outline-secondary" disabled
                        title="A chegada só pode ser registrada no dia do encaixe">
                    <i class="bi bi-person-check"></i> Registrar chegada
                </button>
            <?php endif; ?>

            <button type="button" class="btn btn-outline-primary" onclick="alternarEditarDados()">
                <i class="bi bi-pencil-square"></i> Editar dados
            </button>
            <?php if ($medicoId > 0): ?>
                <button type="button" class="btn btn-outline-secondary" onclick="alternarRemarcar()">
                    <i class="bi bi-calendar2-week"></i> Editar data e horário
                </button>
            <?php endif; ?>

            <form method="post" id="formCancelarEncaixe">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="acao" value="cancelar">
                <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                <button type="button" class="btn btn-outline-danger" onclick="cancelarEncaixe()">
                    <i class="bi bi-x-circle"></i> Cancelar encaixe
                </button>
            </form>
        </div>

        <?php if (!$jaChegou && !$ehHoje): ?>
            <p class="dica-acoes"><i class="bi bi-info-circle"></i> A chegada do paciente só pode ser registrada no dia do encaixe.</p>
        <?php endif; ?>
    </div>

    <form method="post" class="cartao-config-agenda" id="cartaoEditarDados" style="display:none;" onsubmit="return validarEdicaoDados();">
        <h2 class="titulo-cartao-config">Editar dados do encaixe</h2>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="acao" value="editar_dados">
        <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">

        <div class="campo-config">
            <label for="editarPacienteEncaixe">Paciente</label>
            <input type="text" id="editarPacienteEncaixe" name="paciente" maxlength="200" required
                   value="<?php echo htmlspecialchars($row['paciente'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="campo-config">
            <label for="editarTipoEncaixe">Tipo de atendimento</label>
            <select id="editarTipoEncaixe" name="tipo" required>
                <option value="presencial" <?php echo ($tipoBruto !== 'tele' && $tipoBruto !== 'teleconsulta') ? 'selected' : ''; ?>>Presencial</option>
                <option value="tele" <?php echo ($tipoBruto === 'tele' || $tipoBruto === 'teleconsulta') ? 'selected' : ''; ?>>Teleconsulta</option>
            </select>
        </div>
        <div class="campo-config">
            <label for="editarMotivoEncaixe">Motivo</label>
            <textarea id="editarMotivoEncaixe" name="motivo" rows="3" maxlength="5000"><?php echo htmlspecialchars($row['motivo'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary botao-salvar-remarcar">Salvar alterações</button>
    </form>

    <!-- ========================= REMARCAR ========================= -->
    <?php if ($medicoId > 0): ?>
    <form method="post" class="cartao-config-agenda" id="cartaoRemarcar" style="display:none;" onsubmit="return validarRemarcar();">
        <h2 class="titulo-cartao-config">Remarcar encaixe</h2>

        <input type="hidden" name="acao" value="remarcar">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">

        <div class="campo-config">
            <label for="novaData">Nova data</label>
            <input type="date" id="novaData" name="nova_data" min="<?php echo date('Y-m-d'); ?>" onchange="buscarHorariosRemarcar()">
        </div>

        <div class="campo-config">
            <label>Novo horário</label>
            <p id="avisoRemarcar" class="text-muted" style="font-size:14px;">
                <i class="bi bi-info-circle"></i> Escolha a nova data para ver os horários disponíveis.
            </p>
            <div class="grid-horarios" id="gridHorariosRemarcar" style="display:none;"></div>
            <input type="hidden" name="novo_horario" id="novoHorario" value="">
        </div>

        <button type="submit" class="btn btn-primary botao-salvar-remarcar" id="botaoSalvarRemarcar" disabled>Confirmar novo horário</button>
    </form>
    <?php endif; ?>
    <?php endif; ?>

</div>

<script>
function cancelarEncaixe() {
    if (confirm('Tem certeza que quer cancelar esse encaixe? O horário será liberado na agenda.')) {
        document.getElementById('formCancelarEncaixe').submit();
    }
}

function alternarEditarDados() {
    const cartao = document.getElementById('cartaoEditarDados');
    cartao.style.display = (cartao.style.display === 'none') ? 'block' : 'none';
    if (cartao.style.display === 'block') {
        cartao.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function validarEdicaoDados() {
    const paciente = document.getElementById('editarPacienteEncaixe').value.trim();
    if (!paciente || paciente.length > 200) {
        alert('Informe o nome do paciente (até 200 caracteres).');
        return false;
    }
    return true;
}

<?php if ($encaixeAtivo && $medicoId > 0): ?>
const medicoIdEncaixe = <?php echo $medicoId; ?>;
let requisicaoHorariosRemarcar = 0;

function alternarRemarcar() {
    const cartao = document.getElementById('cartaoRemarcar');
    cartao.style.display = (cartao.style.display === 'none') ? 'block' : 'none';
    if (cartao.style.display === 'block') {
        cartao.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function buscarHorariosRemarcar() {
    const requisicaoAtual = ++requisicaoHorariosRemarcar;
    const data = document.getElementById('novaData').value;
    const grid = document.getElementById('gridHorariosRemarcar');
    const aviso = document.getElementById('avisoRemarcar');
    const inputHorario = document.getElementById('novoHorario');
    const botao = document.getElementById('botaoSalvarRemarcar');

    grid.innerHTML = '';
    inputHorario.value = '';
    botao.disabled = true;

    if (!data) {
        grid.style.display = 'none';
        aviso.style.display = 'block';
        aviso.textContent = 'Escolha a nova data para ver os horários disponíveis.';
        return;
    }

    grid.style.display = 'none';
    aviso.style.display = 'block';
    aviso.textContent = 'Buscando horários disponíveis...';

    fetch('actions/horarios-disponiveis-encaixe.php?id_medico=' + encodeURIComponent(medicoIdEncaixe) + '&data=' + encodeURIComponent(data))
        .then(function (resp) { return resp.json(); })
        .then(function (dados) {
            if (requisicaoAtual !== requisicaoHorariosRemarcar) {
                return;
            }
            if (dados.erro) {
                aviso.textContent = dados.erro;
                return;
            }
            if (!dados.horarios || dados.horarios.length === 0) {
                aviso.textContent = 'Esse médico não tem horários livres nessa data. Tente outra data.';
                return;
            }

            dados.horarios.forEach(function (item) {
                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'horario-opcao' + (item.disponivel ? '' : ' horario-ocupado');
                b.textContent = item.hora;

                if (!item.disponivel) {
                    b.disabled = true;
                    b.title = 'Horário já ocupado';
                } else {
                    b.addEventListener('click', function () {
                        grid.querySelectorAll('.horario-opcao').forEach(function (x) { x.classList.remove('ativo'); });
                        b.classList.add('ativo');
                        inputHorario.value = item.hora;
                        botao.disabled = false;
                    });
                }
                grid.appendChild(b);
            });

            aviso.style.display = 'none';
            grid.style.display = 'grid';
        })
        .catch(function () {
            if (requisicaoAtual !== requisicaoHorariosRemarcar) {
                return;
            }
            aviso.textContent = 'Erro ao buscar horários. Tente novamente.';
        });
}

function validarRemarcar() {
    if (!document.getElementById('novaData').value || !document.getElementById('novoHorario').value) {
        alert('Escolha a nova data e um horário disponível.');
        return false;
    }
    return true;
}

// Enter num campo não envia o formulário sem querer
document.getElementById('cartaoRemarcar').addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && e.target.tagName !== 'BUTTON' && e.target.tagName !== 'TEXTAREA') {
        e.preventDefault();
    }
});
<?php endif; ?>
const cartaoEditarDados = document.getElementById('cartaoEditarDados');
if (cartaoEditarDados) {
    cartaoEditarDados.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && e.target.tagName !== 'BUTTON' && e.target.tagName !== 'TEXTAREA') {
        e.preventDefault();
    }
    });
}
</script>

</body>
</html>