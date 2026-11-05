<?php
// gestor/actions/excluir-definitivo.php
//
// Este script SIM apaga o registro de verdade (DELETE), diferente de
// excluir-usuario.php (que só inativa). Use com cuidado: se as tabelas
// consultas, atendimento, prontuario, receitas, exames e atestados
// tiverem ON DELETE CASCADE apontando pra medico/recepcionistas/paciente,
// o histórico clínico e de agendamento dessa pessoa é apagado junto e
// não tem como desfazer.

session_start();
require_once '../../../conexao.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_perfil'] !== 'gestor') {
    header("Location: ../../../auth/login.php");
    exit();
}

$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$perfil = $_GET['perfil'] ?? '';

$tabelas = [
    'medico'        => 'medico',
    'recepcionista' => 'recepcionistas',
    'gestor'        => 'gestores',
    'paciente'      => 'paciente',
];

if ($id <= 0 || !isset($tabelas[$perfil])) {
    header("Location: ../gerenciar-usuarios.php?erro=" . urlencode('Usuário inválido.'));
    exit();
}

$tabela = $tabelas[$perfil];

// Um gestor não pode excluir a própria conta
if ($perfil === 'gestor' && $id === (int)$_SESSION['usuario_id']) {
    header("Location: ../gerenciar-usuarios.php?erro=" . urlencode('Você não pode excluir seu próprio usuário.'));
    exit();
}

try {
    $stmtCheck = $pdo->prepare("SELECT nome FROM `$tabela` WHERE id = ?");
    $stmtCheck->execute([$id]);
    if (!$stmtCheck->fetch()) {
        header("Location: ../gerenciar-usuarios.php?erro=" . urlencode('Usuário não encontrado.'));
        exit();
    }

    $stmtDelete = $pdo->prepare("DELETE FROM `$tabela` WHERE id = ?");
    $stmtDelete->execute([$id]);

    header("Location: ../gerenciar-usuarios.php?status=sucesso&acao=exclusao");
    exit();

} catch (PDOException $e) {
    // Provável causa: existe histórico vinculado (consultas, prontuário,
    // receitas, exames, atestados) e a foreign key não permite apagar
    // sem antes remover ou desvincular esses registros.
    header("Location: ../gerenciar-usuarios.php?erro=" . urlencode('Não foi possível excluir: existem registros vinculados a este usuário (consultas, prontuário, etc). Inative o usuário em vez de excluir.'));
    exit();
}