<?php
// gestor/actions/atualizar-convenio.php
session_start();
require_once '../../../conexao.php';

// Proteção de acesso: Apenas gestores
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_perfil'] !== 'gestor') {
    header("Location: ../../../auth/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../cadastrar-convenio.php");
    exit();
}

$id               = $_POST['id'] ?? null;
$nome             = trim($_POST['nome'] ?? '');
$registroAns      = trim($_POST['registro_ans'] ?? '');
$prazoFaturamento = ($_POST['prazo_faturamento'] ?? '30') . ' dias';
$status           = $_POST['status'] ?? 'ativo';
$planosJson       = $_POST['planos'] ?? '[]';

$planos = json_decode($planosJson, true);

if (empty($id) || !is_numeric($id) || empty($nome)) {
    header("Location: ../cadastrar-convenio.php?erro=" . urlencode('Preencha os campos obrigatórios.'));
    exit();
}

try {
    $pdo->beginTransaction();

    // 1. Atualiza os dados do convênio
    $stmt = $pdo->prepare("
        UPDATE convenios
        SET nome = ?, registro_ans = ?, prazo_faturamento = ?, status = ?
        WHERE id = ?
    ");
    $stmt->execute([$nome, $registroAns, $prazoFaturamento, $status, $id]);

    // 2. Refaz a lista de planos: apaga os antigos e insere os atuais
    $stmtDelPlanos = $pdo->prepare("DELETE FROM convenio_planos WHERE id_convenio = ?");
    $stmtDelPlanos->execute([$id]);

    if (is_array($planos) && count($planos) > 0) {
        $stmtPlano = $pdo->prepare("
            INSERT INTO convenio_planos (id_convenio, nome_plano, cobertura)
            VALUES (?, ?, ?)
        ");
        foreach ($planos as $plano) {
            if (!empty($plano['nome'])) {
                $stmtPlano->execute([$id, $plano['nome'], $plano['cobertura'] ?? '']);
            }
        }
    }

    $pdo->commit();

    header("Location: ../cadastrar-convenio.php?status=editado");
    exit();

} catch (PDOException $e) {
    $pdo->rollBack();
    header("Location: ../cadastrar-convenio.php?erro=" . urlencode('Não foi possível salvar as alterações.'));
    exit();
}