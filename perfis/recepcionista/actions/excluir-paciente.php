<?php
// recepcionista/actions/excluir-paciente.php
session_start();
require_once '../../../conexao.php';

// Validação de segurança: apenas recepcionistas logados
if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'recepcionista') {
    header("Location: ../../../auth/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../atualizar-cadastro.php");
    exit();
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (
    !is_string($csrfToken) ||
    empty($_SESSION['csrf_atualizar_paciente']) ||
    !hash_equals($_SESSION['csrf_atualizar_paciente'], $csrfToken)
) {
    header("Location: ../atualizar-cadastro.php?erro_exclusao=" . urlencode('Sua sessão expirou. Atualize a página e tente novamente.'));
    exit();
}

$rawId = $_POST['id'] ?? null;
$id = is_string($rawId)
    ? filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
$confirmacaoNome = isset($_POST['confirmacao_nome']) && is_string($_POST['confirmacao_nome'])
    ? trim($_POST['confirmacao_nome'])
    : '';

if (!$id) {
    header("Location: ../atualizar-cadastro.php?erro_exclusao=" . urlencode('Paciente inválido.'));
    exit();
}

try {
    // Busca o paciente pra validar que ele existe e conferir o nome digitado na confirmação
    $stmt = $pdo->prepare("SELECT id, nome, uid FROM paciente WHERE id = ?");
    $stmt->execute([$id]);
    $paciente = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$paciente) {
        header("Location: ../atualizar-cadastro.php?erro_exclusao=" . urlencode('Paciente não encontrado.'));
        exit();
    }

    // Confirmação extra: o nome digitado precisa bater com o nome do paciente
    if (mb_strtolower(trim($confirmacaoNome)) !== mb_strtolower(trim($paciente['nome']))) {
        header("Location: ../atualizar-cadastro.php?busca=" . urlencode($paciente['uid']) . "&erro_exclusao=" . urlencode('Nome de confirmação não confere. Nenhum dado foi excluído.'));
        exit();
    }

    $stmt = $pdo->prepare("DELETE FROM paciente WHERE id = ?");
    $stmt->execute([$id]);
    if ($stmt->rowCount() !== 1) {
        header("Location: ../atualizar-cadastro.php?erro_exclusao=" . urlencode('O paciente não foi excluído. Atualize a página e tente novamente.'));
        exit();
    }

    header("Location: ../atualizar-cadastro.php?excluido=1");
    exit();

} catch (PDOException $e) {
    error_log('Falha ao excluir paciente #' . $id . ': ' . $e->getMessage());
    header("Location: ../atualizar-cadastro.php?erro_exclusao=" . urlencode('Não foi possível excluir o paciente. Verifique se há dados vinculados.'));
    exit();
}