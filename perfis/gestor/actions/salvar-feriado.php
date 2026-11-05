<?php
// gestor/actions/salvar-feriado.php
session_start();
require_once '../../../conexao.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_perfil'] !== 'gestor') {
    header("Location: ../../../auth/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome       = trim($_POST['nome'] ?? '');
    $data       = $_POST['data'] ?? '';
    $tipo       = $_POST['tipo'] ?? 'Nacional';
    $recorrente = isset($_POST['recorrente']) ? (int)$_POST['recorrente'] : 1;

    if (!empty($nome) && !empty($data)) {
        try {
            // Verifica se já existe um feriado cadastrado nessa mesma data
            $stmtCheck = $pdo->prepare("SELECT id FROM feriados WHERE data = ?");
            $stmtCheck->execute([$data]);

            if ($stmtCheck->fetch()) {
                header("Location: ../configurar-feriados.php?erro=" . urlencode('Já existe um feriado cadastrado nessa data.'));
                exit();
            }

            $stmt = $pdo->prepare("INSERT INTO feriados (nome, data, tipo, recorrente) VALUES (?, ?, ?, ?)");
            $stmt->execute([$nome, $data, $tipo, $recorrente]);

            // Redireciona corretamente para a tela de listagem com aviso de sucesso
            header("Location: ../configurar-feriados.php?sucesso=cadastrado");
            exit();
        } catch (PDOException $e) {
            header("Location: ../configurar-feriados.php?erro=" . urlencode('Não foi possível salvar o feriado. Tente novamente.'));
            exit();
        }
    }
}

header("Location: ../configurar-feriados.php");
exit();