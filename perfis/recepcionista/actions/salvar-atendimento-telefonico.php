<?php
// recepcionista/actions/salvar-atendimento-telefonico.php
session_start();
require_once __DIR__ . '/../../../conexao.php';

if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'recepcionista') {
    header("Location: ../../../auth/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../atendimento-telefonico.php');
    exit();
}

if (!isset($_SESSION['csrf_atendimento_telefonico'], $_POST['csrf_token'])
    || !is_string($_POST['csrf_token'])
    || !hash_equals($_SESSION['csrf_atendimento_telefonico'], $_POST['csrf_token'])) {
    header('Location: ../atendimento-telefonico.php?erro=' . urlencode('A sessão do formulário expirou. Atualize a página e tente novamente.'));
    exit();
}

$textoPost = static function (string $campo): string {
    $valor = $_POST[$campo] ?? '';
    return is_string($valor) ? trim($valor) : '';
};

$paciente = $textoPost('paciente');
$telefoneInput = $textoPost('telefone');
$telefone = preg_replace('/\D/', '', $telefoneInput);
$resumo = $textoPost('resumo');
$assuntoInput = $textoPost('assunto');
$assuntosPermitidos = ['agendamento', 'reagendamento', 'cancelamento', 'duvida', 'exame', 'outro'];

if ($paciente === '' || $resumo === '') {
    header('Location: ../atendimento-telefonico.php?erro=' . urlencode('Preencha o nome do paciente e o resumo da ligação.'));
    exit();
}

$quantidadeCaracteresNome = preg_match_all('/./us', $paciente, $matches);
if ($quantidadeCaracteresNome === false || $quantidadeCaracteresNome > 200) {
    header('Location: ../atendimento-telefonico.php?erro=' . urlencode('O nome do paciente deve ter no máximo 200 caracteres.'));
    exit();
}

$quantidadeCaracteresResumo = preg_match_all('/./us', $resumo, $matches);
if ($quantidadeCaracteresResumo === false || $quantidadeCaracteresResumo > 5000) {
    header('Location: ../atendimento-telefonico.php?erro=' . urlencode('O resumo deve ter no máximo 5000 caracteres.'));
    exit();
}

if (!in_array($assuntoInput, $assuntosPermitidos, true)) {
    header('Location: ../atendimento-telefonico.php?erro=' . urlencode('Selecione um assunto válido.'));
    exit();
}

if ($telefoneInput !== '' && !in_array(strlen($telefone), [10, 11], true)) {
    header('Location: ../atendimento-telefonico.php?erro=' . urlencode('Informe um telefone válido com DDD.'));
    exit();
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO atendimento_telefonico (paciente, telefone, assunto, resumo, criado_em)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$paciente, $telefone !== '' ? $telefone : null, $assuntoInput, $resumo]);

    header('Location: ../atendimento-telefonico.php?sucesso=1');
    exit();
} catch (PDOException $e) {
    error_log('Falha ao registrar atendimento telefônico: ' . $e->getMessage());
    header('Location: ../atendimento-telefonico.php?erro=' . urlencode('Não foi possível salvar o atendimento. Tente novamente.'));
    exit();
}