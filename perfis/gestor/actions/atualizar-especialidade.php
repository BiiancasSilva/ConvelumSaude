<?php
// gestor/actions/atualizar-especialidade.php
session_start();
require_once '../../../conexao.php';

// Proteção de acesso: Apenas gestores
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_perfil'] !== 'gestor') {
    header("Location: ../../../auth/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../cadastrar-especialidade.php");
    exit();
}

$id        = (int)($_POST['id'] ?? 0);
$nome      = trim($_POST['nome'] ?? '');
$preco     = str_replace(',', '.', $_POST['preco'] ?? '0');
$descricao = trim($_POST['descricao'] ?? '');
$status    = ($_POST['status'] ?? 'Ativo') === 'Inativo' ? 'Inativo' : 'Ativo';

if ($id <= 0 || empty($nome)) {
    header("Location: ../editar-especialidade.php?id=" . $id . "&erro=" . urlencode('Nome inválido.'));
    exit();
}

try {
    // Busca o registro atual pra saber o ícone salvo antes de trocar
    $stmtAtual = $pdo->prepare("SELECT icone FROM especialidade WHERE id = ?");
    $stmtAtual->execute([$id]);
    $atual = $stmtAtual->fetch(PDO::FETCH_ASSOC);

    if (!$atual) {
        header("Location: ../cadastrar-especialidade.php?erro=" . urlencode('Especialidade não encontrada.'));
        exit();
    }

    $nomeIcone = $atual['icone'];

    // Upload de um novo ícone (opcional — só troca se o gestor enviar um arquivo novo)
    if (isset($_FILES['icone']) && $_FILES['icone']['error'] === UPLOAD_ERR_OK) {
        $extensao = strtolower(pathinfo($_FILES['icone']['name'], PATHINFO_EXTENSION));
        $extensoesPermitidas = ['jpg', 'jpeg', 'png', 'svg'];

        if (in_array($extensao, $extensoesPermitidas)) {
            $novoNomeIcone = uniqid('esp_') . '.' . $extensao;
            $diretorioDestino = '../../../uploads/especialidades/';

            if (!is_dir($diretorioDestino)) {
                mkdir($diretorioDestino, 0777, true);
            }

            if (move_uploaded_file($_FILES['icone']['tmp_name'], $diretorioDestino . $novoNomeIcone)) {
                // Remove o ícone antigo do disco, se existir
                if (!empty($nomeIcone) && file_exists($diretorioDestino . $nomeIcone)) {
                    unlink($diretorioDestino . $nomeIcone);
                }
                $nomeIcone = $novoNomeIcone;
            }
        }
    }

    $stmt = $pdo->prepare("
        UPDATE especialidade
        SET nome = ?, preco = ?, icone = ?, descricao = ?, status = ?
        WHERE id = ?
    ");
    $stmt->execute([$nome, $preco, $nomeIcone, $descricao, $status, $id]);

    header("Location: ../cadastrar-especialidade.php?status=sucesso&acao=edicao");
    exit();

} catch (PDOException $e) {
    header("Location: ../editar-especialidade.php?id=" . $id . "&erro=" . urlencode($e->getMessage()));
    exit();
}