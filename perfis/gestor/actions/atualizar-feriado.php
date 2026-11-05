<?php
// gestor/actions/atualizar-feriado.php
session_start();
require_once '../../../conexao.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_perfil'] !== 'gestor') {
    header("Location: ../../../auth/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../configurar-feriados.php");
    exit();
}

$id         = $_POST['id'] ?? null;
$nome       = trim($_POST['nome'] ?? '');
$data       = $_POST['data'] ?? '';
$tipo       = $_POST['tipo'] ?? 'Nacional';
$recorrente = isset($_POST['recorrente']) ? (int)$_POST['recorrente'] : 1;

if (empty($id) || !is_numeric($id) || empty($nome) || empty($data)) {
    header("Location: ../configurar-feriados.php?erro=" . urlencode('Preencha os campos obrigatórios.'));
    exit();
}

try {
    // Verifica se já existe OUTRO feriado (id diferente) cadastrado nessa mesma data
    $stmtCheck = $pdo->prepare("SELECT id FROM feriados WHERE data = ? AND id != ?");
    $stmtCheck->execute([$data, $id]);

    if ($stmtCheck->fetch()) {
        header("Location: ../configurar-feriados.php?erro=" . urlencode('Já existe outro feriado cadastrado nessa data.'));
        exit();
    }

    $stmt = $pdo->prepare("UPDATE feriados SET nome = ?, data = ?, tipo = ?, recorrente = ? WHERE id = ?");
    $stmt->execute([$nome, $data, $tipo, $recorrente, $id]);

    header("Location: ../configurar-feriados.php?sucesso=editado");
    exit();

} catch (PDOException $e) {
    header("Location: ../configurar-feriados.php?erro=" . urlencode('Não foi possível salvar. Tente novamente.'));
    exit();
}