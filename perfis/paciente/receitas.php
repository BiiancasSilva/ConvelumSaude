<?php
// perfis/paciente/receitas.php
session_start();

$caminhoConexao = __DIR__ . '/../../conexao.php';
if (file_exists($caminhoConexao)) {
    require_once $caminhoConexao;
}

if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'paciente') {
    header("Location: ../../auth/login.php");
    exit();
}

$pacienteId = $_SESSION['usuario_id'];
$receitas = [];
$erroReceitas = !isset($pdo);

// CONSULTA DE DADOS NO BANCO DE DADOS
if (isset($pdo)) {
    try {
        // O vínculo com o paciente é feito pela coluna id_paciente.
        $sql = "SELECT 
                    r.id, 
                    r.tipo, 
                    r.data_emissao, 
                    r.status, 
                    r.arquivo_pdf,
                    m.nome AS medico_nome
                FROM receitas r
                LEFT JOIN medico m ON r.id_medico = m.id
                WHERE r.id_paciente = :paciente_id
                ORDER BY r.data_emissao DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':paciente_id' => $pacienteId]);
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($resultados as $row) {
            // Busca os medicamentos vinculados à receita
            $sqlMed = "SELECT nome, posologia, duracao FROM receita_medicamentos WHERE id_receita = :id_receita";
            $stmtMed = $pdo->prepare($sqlMed);
            $stmtMed->execute([':id_receita' => $row['id']]);
            $medicamentos = $stmtMed->fetchAll(PDO::FETCH_ASSOC);

            $statusLimpo = strtolower($row['status']);

            $receitas[] = [
                'id'           => $row['id'],
                'data'         => date('d/m/Y', strtotime($row['data_emissao'])),
                'medico'       => !empty($row['medico_nome']) ? 'Dr(a). ' . $row['medico_nome'] : 'Médico da Clínica',
                'tipo'         => !empty($row['tipo']) ? $row['tipo'] : 'Receita Simples',
                'status'       => $statusLimpo,
                'arquivo'      => $row['arquivo_pdf'],
                'medicamentos' => $medicamentos
            ];
        }
    } catch (PDOException $e) {
        error_log('Erro ao buscar receitas do paciente: ' . $e->getMessage());
        $erroReceitas = true;
        $receitas = [];
    }
}

$rotulosStatus = [
    'ativa'    => 'Ativa',
    'expirada' => 'Expirada',
    'usada'    => 'Utilizada'
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receitas Digitais - Convelum Saúde</title>

    <link href="../../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/responsivo.css">
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

<!-- ========================= CABEÇALHO ========================= -->
<div id="painel-boasvindas">
    <div>
        <span id="subtitulo-painel">SEU HISTÓRICO</span>
        <h1 id="titulo-painel">Receitas digitais</h1>
        <p id="texto-painel">Consulte suas receitas e baixe pra levar na farmácia.</p>
    </div>
</div>

<?php if ($erroReceitas): ?>
    <div class="alert alert-warning mx-3" role="alert">
        Não foi possível carregar suas receitas. Tente novamente mais tarde.
    </div>
<?php endif; ?>

<div class="secao-config-agenda">
    <?php if (empty($receitas) && $erroReceitas): ?>

        <div class="admin-vazio text-center py-5">
            <i class="bi bi-exclamation-circle fs-1 text-muted mb-2 d-block"></i>
            <p class="text-muted">Não foi possível carregar suas receitas.</p>
        </div>

    <?php elseif (empty($receitas)): ?>

        <div class="admin-vazio text-center py-5">
            <i class="bi bi-prescription2 fs-1 text-muted mb-2 d-block"></i>
            <p class="text-muted">Você ainda não tem nenhuma receita digital.</p>
        </div>

    <?php else: ?>

        <?php foreach ($receitas as $receita): ?>
            <div class="cartao-config-agenda cartao-receita-paciente mb-4">

                <div class="cabecalho-receita-paciente d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <p class="cabecalho-receita-tipo fw-bold mb-1"><?php echo htmlspecialchars($receita['tipo']); ?></p>
                        <p class="cabecalho-receita-medico text-muted small mb-0">
                            <?php echo htmlspecialchars($receita['medico']); ?> · <?php echo $receita['data']; ?>
                        </p>
                    </div>
                    <span class="selo-receita-<?php echo $receita['status']; ?> badge bg-<?php echo $receita['status'] === 'ativa' ? 'success' : 'secondary'; ?>">
                        <?php echo $rotulosStatus[$receita['status']] ?? ucfirst($receita['status']); ?>
                    </span>
                </div>

                <div class="lista-medicamentos mb-3">
                    <?php if (!empty($receita['medicamentos'])): ?>
                        <?php foreach ($receita['medicamentos'] as $med): ?>
                            <div class="registro-config registro-medicamento p-2 bg-light rounded mb-2">
                                <div>
                                    <span class="registro-config-tipo fw-semibold"><?php echo htmlspecialchars($med['nome']); ?></span>
                                    <p class="registro-medicamento-detalhes small text-muted mb-0">
                                        <?php echo htmlspecialchars($med['posologia']); ?> · <?php echo htmlspecialchars($med['duracao']); ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="small text-muted italic">Verifique os medicamentos descritos no PDF anexado.</p>
                    <?php endif; ?>
                </div>

                <?php if (!empty($receita['arquivo'])): ?>
                    <a href="../../uploads/receitas/<?php echo rawurlencode(basename($receita['arquivo'])); ?>" class="botao-entrar botao-baixar-receita" download>
                        <i class="bi bi-download"></i> Baixar receita em PDF
                    </a>
                <?php endif; ?>

            </div>
        <?php endforeach; ?>

    <?php endif; ?>
</div>

</body>
</html>