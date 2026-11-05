<?php
session_start();
require_once __DIR__ . '/../../../conexao.php';

if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'paciente') {
    header('Location: ../../../auth/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../configuracoes.php');
    exit();
}

$pacienteId = filter_var($_SESSION['usuario_id'], FILTER_VALIDATE_INT);
if ($pacienteId === false || $pacienteId < 1) {
    header('Location: ../../../auth/login.php');
    exit();
}

$senhaAtual = $_POST['senha_atual'] ?? '';
$senhaNova = $_POST['senha_nova'] ?? '';
$senhaNovaConfirmar = $_POST['senha_nova_confirmar'] ?? '';

if ($senhaAtual === '' || $senhaNova === '' || $senhaNovaConfirmar === '') {
    header('Location: ../configuracoes.php?erro=' . urlencode('Preencha todos os campos de senha.'));
    exit();
}

if ($senhaNova !== $senhaNovaConfirmar) {
    header('Location: ../configuracoes.php?erro=' . urlencode('As novas senhas não coincidem.'));
    exit();
}

if (strlen($senhaNova) < 6) {
    header('Location: ../configuracoes.php?erro=' . urlencode('A nova senha deve ter no mínimo 6 caracteres.'));
    exit();
}

try {
    $stmt = $pdo->prepare('SELECT senha FROM paciente WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $pacienteId]);
    $paciente = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$paciente) {
        header('Location: ../configuracoes.php?erro=' . urlencode('Usuário não encontrado.'));
        exit();
    }

    if (!password_verify($senhaAtual, $paciente['senha'])) {
        header('Location: ../configuracoes.php?erro=' . urlencode('A senha atual está incorreta.'));
        exit();
    }

    $novaSenhaHash = password_hash($senhaNova, PASSWORD_DEFAULT);
    $update = $pdo->prepare('UPDATE paciente SET senha = :senha WHERE id = :id');
    $update->execute([':senha' => $novaSenhaHash, ':id' => $pacienteId]);

    header('Location: ../configuracoes.php?sucesso=senha');
    exit();
} catch (PDOException $e) {
    error_log('Falha ao trocar senha do paciente: ' . $e->getMessage());
    header('Location: ../configuracoes.php?erro=' . urlencode('Não foi possível atualizar a senha. Tente novamente.'));
    exit();
}
