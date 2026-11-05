<?php
// recepcionista/salvar-atualizacao-paciente.php
// Recebe o POST do formulário de edição em atualizar-cadastro.php e salva.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: atualizar-cadastro.php');
    exit();
}

// Coleta e limpa os dados enviados
$nome       = trim($_POST['nome'] ?? '');
$documento  = trim($_POST['documento'] ?? '');
$nascimento = trim($_POST['nascimento'] ?? '');
$celular    = trim($_POST['celular'] ?? '');
$email      = trim($_POST['email'] ?? '');
$endereco   = trim($_POST['endereco'] ?? '');
$convenio   = trim($_POST['convenio'] ?? '');

// Validação básica — troque/complete pelas regras reais do seu sistema
$erros = [];

if ($nome === '') {
    $erros[] = 'nome';
}
if ($celular === '') {
    $erros[] = 'celular';
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $erros[] = 'email';
}

if (!empty($erros)) {
    // Volta pro formulário com os erros, sem perder o que o usuário já preencheu
    header('Location: atualizar-cadastro.php?busca=' . urlencode($nome) . '&erro=1');
    exit();
}

// ================================================================
// AQUI ENTRA A ATUALIZAÇÃO REAL NO BANCO. Exemplo com PDO:
//
// $stmt = $pdo->prepare(
//     'UPDATE pacientes
//      SET nome = ?, documento = ?, nascimento = ?, celular = ?,
//          email = ?, endereco = ?, convenio = ?, atualizado_em = NOW()
//      WHERE id = ?'
// );
// $stmt->execute([$nome, $documento, $nascimento, $celular, $email,
//                 $endereco, $convenio, $_POST['paciente_id']]);
// ================================================================

// Redireciona de volta pra busca, já mostrando o paciente atualizado
// e um aviso de sucesso.
header('Location: atualizar-cadastro.php?busca=' . urlencode($nome) . '&atualizado=1');
exit();