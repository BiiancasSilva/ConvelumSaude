<?php
// gestor/actions/salvar-especialidade.php
session_start();
require_once '../../../conexao.php';

// Proteção de acesso: Apenas gestores
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_perfil'] !== 'gestor') {
    header("Location: ../../../auth/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome      = trim($_POST['nome'] ?? '');
    $preco     = str_replace(',', '.', $_POST['preco'] ?? '0');
    $descricao = trim($_POST['descricao'] ?? '');
    $nomeIcone = null;

    // Upload da imagem/ícone
    if (isset($_FILES['icone']) && $_FILES['icone']['error'] === UPLOAD_ERR_OK) {
        $extensao = strtolower(pathinfo($_FILES['icone']['name'], PATHINFO_EXTENSION));
        $extensoesPermitidas = ['jpg', 'jpeg', 'png', 'svg'];

        if (in_array($extensao, $extensoesPermitidas)) {
            $nomeIcone = uniqid('esp_') . '.' . $extensao;
            $diretorioDestino = '../../../uploads/especialidades/';

            if (!is_dir($diretorioDestino)) {
                mkdir($diretorioDestino, 0777, true);
            }

            move_uploaded_file($_FILES['icone']['tmp_name'], $diretorioDestino . $nomeIcone);
        }
    }

    if (!empty($nome)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO especialidade (nome, preco, icone, descricao) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$nome, $preco, $nomeIcone, $descricao]);

            // Redireciona com parâmetro de sucesso
            header("Location: ../cadastrar-especialidade.php?status=sucesso");
            exit();
        } catch (PDOException $e) {
            header("Location: ../cadastrar-especialidade.php?erro=" . urlencode($e->getMessage()));
            exit();
        }
    } else {
        header("Location: ../cadastrar-especialidade.php?erro=Nome_invalido");
        exit();
    }
} else {
    header("Location: ../cadastrar-especialidade.php");
    exit();
}