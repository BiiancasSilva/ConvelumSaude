<?php
// gestor/actions/alternar-status-especialidade.php
session_start();
require_once '../../../conexao.php';

// Proteção de acesso: Apenas gestores
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_perfil'] !== 'gestor') {
    header("Location: ../../../auth/login.php");
    exit();
}

$id = $_GET['id'] ?? null;

if (empty($id) || !is_numeric($id)) {
    header("Location: ../cadastrar-especialidade.php?erro=" . urlencode('Especialidade inválida.'));
    exit();
}

try {
    $stmtBusca = $pdo->prepare("SELECT status FROM especialidade WHERE id = ?");
    $stmtBusca->execute([$id]);
    $especialidade = $stmtBusca->fetch(PDO::FETCH_ASSOC);

    if (!$especialidade) {
        header("Location: ../cadastrar-especialidade.php?erro=" . urlencode('Especialidade não encontrada.'));
        exit();
    }

    $novoStatus = ($especialidade['status'] === 'Ativo') ? 'Inativo' : 'Ativo';

    $stmtUpdate = $pdo->prepare("UPDATE especialidade SET status = ? WHERE id = ?");
    $stmtUpdate->execute([$novoStatus, $id]);

    $acao = ($novoStatus === 'Ativo') ? 'ativacao' : 'inativacao';
    header("Location: ../cadastrar-especialidade.php?status=sucesso&acao=" . $acao);
    exit();

} catch (PDOException $e) {
    header("Location: ../cadastrar-especialidade.php?erro=" . urlencode('Não foi possível alterar o status. Tente novamente.'));
    exit();
}