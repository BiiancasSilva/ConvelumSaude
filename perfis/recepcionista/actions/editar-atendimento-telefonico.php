<?php
session_start();
require_once __DIR__ . '/../../../conexao.php';

if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'recepcionista') {
    header('Location: ../../../auth/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../atendimento-telefonico.php');
    exit();
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!isset($_SESSION['csrf_atendimento_telefonico']) || !is_string($csrfToken)
    || !hash_equals($_SESSION['csrf_atendimento_telefonico'], $csrfToken)) {
    header('Location: ../atendimento-telefonico.php?erro=' . urlencode('A sessão do formulário expirou. Atualize a página e tente novamente.'));
    exit();
}

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
$paciente = isset($_POST['paciente']) && is_string($_POST['paciente']) ? trim($_POST['paciente']) : '';
$telefoneInput = isset($_POST['telefone']) && is_string($_POST['telefone']) ? trim($_POST['telefone']) : '';
$telefone = preg_replace('/\D/', '', $telefoneInput);
$assunto = isset($_POST['assunto']) && is_string($_POST['assunto']) ? $_POST['assunto'] : '';
$resumo = isset($_POST['resumo']) && is_string($_POST['resumo']) ? trim($_POST['resumo']) : '';
$assuntosPermitidos = ['agendamento', 'reagendamento', 'cancelamento', 'duvida', 'exame', 'outro'];
$erro = null;

if ($id === false || $id < 1) {
    $erro = 'Registro de atendimento inválido.';
} elseif ($paciente === '' || $resumo === '') {
    $erro = 'Preencha o nome do paciente e o resumo da ligação.';
} else {
    $quantidadeNome = preg_match_all('/./us', $paciente, $matches);
    $quantidadeResumo = preg_match_all('/./us', $resumo, $matches);
    if ($quantidadeNome === false || $quantidadeNome > 200) {
        $erro = 'O nome do paciente deve ter no máximo 200 caracteres.';
    } elseif ($quantidadeResumo === false || $quantidadeResumo > 5000) {
        $erro = 'O resumo deve ter no máximo 5000 caracteres.';
    } elseif (!in_array($assunto, $assuntosPermitidos, true)) {
        $erro = 'Selecione um assunto válido.';
    } elseif ($telefoneInput !== '' && !in_array(strlen($telefone), [10, 11], true)) {
        $erro = 'Informe um telefone válido com DDD.';
    }
}

if ($erro !== null) {
    header('Location: ../atendimento-telefonico.php?erro=' . urlencode($erro));
    exit();
}

try {
    $stmt = $pdo->prepare(
        'UPDATE atendimento_telefonico
         SET paciente = ?, telefone = ?, assunto = ?, resumo = ?
         WHERE id = ?'
    );
    $stmt->execute([$paciente, $telefone !== '' ? $telefone : null, $assunto, $resumo, $id]);
    if ($stmt->rowCount() === 0) {
        $stmtExiste = $pdo->prepare('SELECT id FROM atendimento_telefonico WHERE id = ? LIMIT 1');
        $stmtExiste->execute([$id]);
        if (!$stmtExiste->fetchColumn()) {
            header('Location: ../atendimento-telefonico.php?erro=' . urlencode('O atendimento não foi encontrado.'));
            exit();
        }
    }

    header('Location: ../atendimento-telefonico.php?sucesso=editado');
    exit();
} catch (PDOException $e) {
    error_log('Falha ao editar atendimento telefônico: ' . $e->getMessage());
    header('Location: ../atendimento-telefonico.php?erro=' . urlencode('Não foi possível atualizar o atendimento. Tente novamente.'));
    exit();
}
