<?php
session_start();
require_once '../../../conexao.php';

if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'medico') {
    header('Location: ../../../auth/login.php');
    exit();
}

function voltarSenhaMedico(string $mensagem, string $tipo = 'config_erro'): void
{
    $_SESSION[$tipo] = $mensagem;
    header('Location: ../configuracoes.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../configuracoes.php');
    exit();
}
if (!isset($_POST['csrf_token'], $_SESSION['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
    voltarSenhaMedico('Sua sessão expirou. Atualize a página e tente novamente.');
}

$medicoId = filter_var($_SESSION['usuario_id'], FILTER_VALIDATE_INT);
$senhaAtual = (string)($_POST['senha_atual'] ?? '');
$senhaNova = (string)($_POST['senha_nova'] ?? '');
$senhaConfirmar = (string)($_POST['senha_nova_confirmar'] ?? '');

if (!$medicoId || $senhaAtual === '' || $senhaNova === '' || $senhaConfirmar === '') {
    voltarSenhaMedico('Preencha todos os campos de senha.');
}
if (mb_strlen($senhaNova) < 6 || strlen($senhaNova) > 72) {
    voltarSenhaMedico('A nova senha deve ter pelo menos 6 caracteres e no máximo 72 bytes.');
}
if ($senhaNova !== $senhaConfirmar) {
    voltarSenhaMedico('A nova senha e a confirmação não coincidem.');
}

try {
    $stmt = $pdo->prepare('SELECT senha FROM medico WHERE id = ? LIMIT 1');
    $stmt->execute([$medicoId]);
    $senhaHash = $stmt->fetchColumn();
    if (!$senhaHash || !password_verify($senhaAtual, (string)$senhaHash)) {
        voltarSenhaMedico('Senha atual incorreta.');
    }

    $novaSenhaHash = password_hash($senhaNova, PASSWORD_DEFAULT);
    if ($novaSenhaHash === false) {
        throw new RuntimeException('Falha ao gerar hash da senha.');
    }

    $stmtUpdate = $pdo->prepare('UPDATE medico SET senha = ? WHERE id = ?');
    $stmtUpdate->execute([$novaSenhaHash, $medicoId]);

    voltarSenhaMedico('Senha atualizada com sucesso.', 'config_sucesso');
} catch (Throwable $e) {
    error_log('Falha ao trocar senha do médico ID ' . $medicoId . ': ' . $e->getMessage());
    voltarSenhaMedico('Não foi possível atualizar a senha. Tente novamente.');
}
