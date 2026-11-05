<?php
// perfis/paciente/atestados.php
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
$atestados = [];
$erroAtestados = !isset($pdo);

// CONSULTA NO BANCO DE DADOS
if (isset($pdo)) {
    try {
        // O banco vincula diretamente o atestado ao paciente e ao médico.
        $sql = "SELECT 
                    a.id, 
                    a.tipo, 
                    a.data_emissao, 
                    a.periodo,
                    a.cid, 
                    a.observacoes,
                    a.arquivo_pdf,
                    m.nome AS medico_nome
                FROM atestados a
                LEFT JOIN medico m ON a.id_medico = m.id
                WHERE a.id_paciente = :paciente_id
                ORDER BY a.data_emissao DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':paciente_id' => $pacienteId]);
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($resultados as $row) {
            $atestados[] = [
                'id'      => $row['id'],
                'tipo'    => !empty($row['tipo']) ? $row['tipo'] : 'Atestado Médico',
                'medico'  => !empty($row['medico_nome']) ? 'Dr(a). ' . $row['medico_nome'] : 'Médico da Clínica',
                'emissao' => date('d/m/Y', strtotime($row['data_emissao'])),
                'periodo' => !empty($row['periodo']) ? $row['periodo'] : 'Não informado',
                'cid'     => $row['cid'],
                'arquivo' => $row['arquivo_pdf']
            ];
        }
    } catch (PDOException $e) {
        error_log('Erro ao buscar atestados do paciente: ' . $e->getMessage());
        $erroAtestados = true;
        $atestados = [];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atestados - Convelum Saúde</title>

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
        <h1 id="titulo-painel">Atestados</h1>
        <p id="texto-painel">Consulte e baixe seus atestados médicos.</p>
    </div>
</div>

<?php if ($erroAtestados): ?>
    <div class="alert alert-warning mx-3" role="alert">
        Não foi possível carregar seus atestados. Tente novamente mais tarde.
    </div>
<?php endif; ?>

<div class="secao-config-agenda">
    <?php if (empty($atestados) && $erroAtestados): ?>

        <div class="admin-vazio text-center py-5">
            <i class="bi bi-exclamation-circle fs-1 text-muted mb-2 d-block"></i>
            <p class="text-muted">Não foi possível carregar seus atestados.</p>
        </div>

    <?php elseif (empty($atestados)): ?>

        <div class="admin-vazio text-center py-5">
            <i class="bi bi-file-earmark-medical fs-1 text-muted mb-2 d-block"></i>
            <p class="text-muted">Você ainda não tem nenhum atestado.</p>
        </div>

    <?php else: ?>

        <?php foreach ($atestados as $atestado): ?>
            <div class="cartao-config-agenda cartao-receita-paciente mb-4">

                <div class="cabecalho-receita-paciente d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <p class="cabecalho-receita-tipo fw-bold mb-1"><?php echo htmlspecialchars($atestado['tipo']); ?></p>
                        <p class="cabecalho-receita-medico text-muted small mb-0">
                            <?php echo htmlspecialchars($atestado['medico']); ?> · Emitido em <?php echo $atestado['emissao']; ?>
                        </p>
                    </div>
                    <i class="bi bi-file-earmark-medical icone-atestado fs-3 text-primary"></i>
                </div>

                <div class="detalhes-atestado bg-light p-3 rounded mb-3">
                    <div class="linha-resumo d-flex justify-content-between mb-1">
                        <span class="text-muted">Período:</span>
                        <strong><?php echo htmlspecialchars($atestado['periodo']); ?></strong>
                    </div>
                    <?php if (!empty($atestado['cid'])): ?>
                        <div class="linha-resumo d-flex justify-content-between">
                            <span class="text-muted">CID-10:</span>
                            <strong><?php echo htmlspecialchars($atestado['cid']); ?></strong>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($atestado['arquivo'])): ?>
                    <a href="../../uploads/atestados/<?php echo rawurlencode(basename($atestado['arquivo'])); ?>" class="botao-entrar botao-baixar-receita" download>
                        <i class="bi bi-download"></i> Baixar atestado em PDF
                    </a>
                <?php endif; ?>

            </div>
        <?php endforeach; ?>

    <?php endif; ?>
</div>

</body>
</html>