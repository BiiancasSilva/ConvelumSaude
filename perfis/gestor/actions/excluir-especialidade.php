<?php
// gestor/actions/excluir-especialidade.php
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
    $pdo->beginTransaction();

    // Busca o ícone antes de excluir, pra remover o arquivo do disco depois
    $stmtBusca = $pdo->prepare("SELECT icone, nome FROM especialidade WHERE id = ?");
    $stmtBusca->execute([$id]);
    $especialidade = $stmtBusca->fetch(PDO::FETCH_ASSOC);

    if (!$especialidade) {
        $pdo->rollBack();
        header("Location: ../cadastrar-especialidade.php?erro=" . urlencode('Especialidade não encontrada.'));
        exit();
    }

    // Remove primeiro os vínculos com médicos (evita erro de FK)
    $stmtVinculos = $pdo->prepare("DELETE FROM medico_especialidades WHERE id_especialidade = ?");
    $stmtVinculos->execute([$id]);

    // Remove a especialidade em si
    $stmtDelete = $pdo->prepare("DELETE FROM especialidade WHERE id = ?");
    $stmtDelete->execute([$id]);

    $pdo->commit();

    // Remove o arquivo de ícone do disco, se existir
    if (!empty($especialidade['icone'])) {
        $caminhoIcone = "../../../uploads/especialidades/" . $especialidade['icone'];
        if (file_exists($caminhoIcone)) {
            unlink($caminhoIcone);
        }
    }

    header("Location: ../cadastrar-especialidade.php?status=sucesso&acao=exclusao");
    exit();

} catch (PDOException $e) {
    $pdo->rollBack();
    header("Location: ../cadastrar-especialidade.php?erro=" . urlencode('Não foi possível excluir. Tente novamente.'));
    exit();
}