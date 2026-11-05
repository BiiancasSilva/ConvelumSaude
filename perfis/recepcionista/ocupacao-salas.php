<?php
// recepcionista/ocupacao-salas.php
session_start();
require_once '../../conexao.php';

// Validação de sessão da recepcionista
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_perfil'] !== 'recepcionista') {
    header("Location: ../../auth/login.php");
    exit();
}

$salas = [];
$dataHoje = date('Y-m-d');
$agora = date('Y-m-d H:i:s');

try {
    // A tabela 'consultas' guarda apenas 'data_hora' (sem horário de término separado),
    // então consideramos que cada consulta ocupa a sala por 1 hora a partir do início.
    $stmt = $pdo->prepare("
        SELECT s.id, s.nome, s.status, s.id_medico AS id_medico_fixo,
               mf.nome AS medico_fixo_nome,
               m.nome as medico_nome, p.nome as paciente_nome, c.data_hora, c.is_encaixe
        FROM salas s
        LEFT JOIN medico mf ON s.id_medico = mf.id
        LEFT JOIN consultas c ON s.id = c.id_sala 
               AND DATE(c.data_hora) = ? 
               AND ? BETWEEN c.data_hora AND DATE_ADD(c.data_hora, INTERVAL 1 HOUR)
        LEFT JOIN medico m ON c.id_medico = m.id
        LEFT JOIN paciente p ON c.id_paciente = p.id
        ORDER BY s.id ASC
    ");
    $stmt->execute([$dataHoje, $agora]);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($resultados) {
        foreach ($resultados as $row) {
            $status = !empty($row['data_hora']) ? 'ocupada' : ($row['status'] ?? 'livre');
            $ocupante = null;
            $ate = null;

            if ($status === 'ocupada' && !empty($row['medico_nome'])) {
                $pacienteInfo = !empty($row['paciente_nome']) ? $row['paciente_nome'] : ($row['is_encaixe'] ? 'encaixe' : '');
                $ocupante = $row['medico_nome'] . ' — ' . $pacienteInfo;
                $ate = date('H:i', strtotime($row['data_hora'] . ' +1 hour'));
            } elseif ($status === 'bloqueada') {
                $ocupante = 'Manutenção';
            }

            $salas[] = [
                'nome'            => $row['nome'],
                'status'          => $status,
                'ocupante'        => $ocupante,
                'ate'             => $ate,
                'medico_fixo'     => $row['medico_fixo_nome'] ?? null,
            ];
        }
    }
} catch (PDOException $e) {
    // Mantém vazio em caso de erro ou se preferir não usar dados estáticos
    $salas = [];
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ocupação de Salas - Convelum Saúde</title>

    <link href="../../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/responsivo.css">

    <style>
        .selo-sala-fixa {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 12px;
            padding: 2px 8px;
            border-radius: 12px;
            background: #eef1ff;
            color: #3b4a9c;
            margin-top: 4px;
        }
        .selo-sala-compartilhada {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 12px;
            padding: 2px 8px;
            border-radius: 12px;
            background: #f1f1f1;
            color: #666;
            margin-top: 4px;
        }
    </style>
</head>
<body>

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
        <span id="subtitulo-painel">INFRAESTRUTURA</span>
        <h1 id="titulo-painel">Ocupação de salas</h1>
        <p id="texto-painel">Veja em tempo real quais salas estão livres, ocupadas ou bloqueadas, e se são fixas de algum médico.</p>
    </div>
</div>

<div class="secao-config-agenda">
    <?php if (empty($salas)): ?>
        <div class="admin-vazio" style="padding: 50px; text-align: center; color: #666;">
            <i class="bi bi-door-closed" style="font-size: 2.5rem; display: block; margin-bottom: 10px;"></i>
            <p>Nenhuma sala cadastrada ou encontrada no momento.</p>
        </div>
    <?php else: ?>
        <div class="grid-salas">
            <?php foreach ($salas as $sala): ?>
                <div class="card-sala status-sala-<?php echo $sala['status']; ?>">
                    <div class="card-sala-topo">
                        <span class="card-sala-nome"><?php echo htmlspecialchars($sala['nome']); ?></span>
                        <span class="selo-sala">
                            <?php
                            echo $sala['status'] === 'livre' ? 'Livre' : ($sala['status'] === 'ocupada' ? 'Ocupada' : 'Bloqueada');
                            ?>
                        </span>
                    </div>

                    <?php if ($sala['medico_fixo']): ?>
                        <span class="selo-sala-fixa">
                            <i class="bi bi-pin-angle-fill"></i> Fixa de <?php echo htmlspecialchars($sala['medico_fixo']); ?>
                        </span>
                    <?php else: ?>
                        <span class="selo-sala-compartilhada">
                            <i class="bi bi-people"></i> Compartilhada
                        </span>
                    <?php endif; ?>

                    <?php if ($sala['ocupante']): ?>
                        <p class="card-sala-ocupante"><?php echo htmlspecialchars($sala['ocupante']); ?></p>
                    <?php else: ?>
                        <p class="card-sala-ocupante card-sala-vazia">Disponível pra uso</p>
                    <?php endif; ?>

                    <?php if ($sala['ate']): ?>
                        <p class="card-sala-ate"><i class="bi bi-clock"></i> Até <?php echo $sala['ate']; ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>