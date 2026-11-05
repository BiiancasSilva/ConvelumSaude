<?php
// gestor/actions/excluir-usuario.php
//
// Apesar do nome do arquivo (mantido assim pra não quebrar os links que já
// apontam pra cá), este script NÃO apaga o registro do banco. Ele só
// ALTERNA o status entre Ativo e Inativo.
//
// Motivo: medico, recepcionistas e paciente têm chaves estrangeiras com
// ON DELETE CASCADE em consultas, atendimento, prontuario, receitas,
// exames e atestados. Um DELETE de verdade apagaria junto todo o
// histórico clínico e de agendamento dessas pessoas.

session_start();
require_once '../../../conexao.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_perfil'] !== 'gestor') {
    header("Location: ../../../auth/login.php");
    exit();
}

$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$perfil = $_GET['perfil'] ?? '';
$origem = $_GET['origem'] ?? 'gerenciar'; // 'cadastro' (veio da tela de cadastro) ou 'gerenciar'

$paginaRetorno = $origem === 'cadastro' ? '../cadastrar-usuario.php' : '../gerenciar-usuarios.php';

$tabelas = [
    'medico'        => 'medico',
    'recepcionista' => 'recepcionistas',
    'gestor'        => 'gestores',
    'paciente'      => 'paciente',
];

if ($id <= 0 || !isset($tabelas[$perfil])) {
    header("Location: $paginaRetorno?erro=" . urlencode('Usuário inválido.'));
    exit();
}

$tabela = $tabelas[$perfil];

// Um gestor não pode inativar a própria conta (senão fica trancado pra fora)
if ($perfil === 'gestor' && $id === (int)$_SESSION['usuario_id']) {
    header("Location: $paginaRetorno?erro=" . urlencode('Você não pode inativar seu próprio usuário.'));
    exit();
}

try {
    $stmtStatus = $pdo->prepare("SELECT status FROM `$tabela` WHERE id = ?");
    $stmtStatus->execute([$id]);
    $statusAtual = $stmtStatus->fetchColumn();

    if ($statusAtual === false) {
        header("Location: $paginaRetorno?erro=" . urlencode('Usuário não encontrado.'));
        exit();
    }

    $novoStatus = ($statusAtual === 'Ativo') ? 'Inativo' : 'Ativo';

    $stmtUpdate = $pdo->prepare("UPDATE `$tabela` SET status = ? WHERE id = ?");
    $stmtUpdate->execute([$novoStatus, $id]);

    $acao = $novoStatus === 'Inativo' ? 'inativacao' : 'reativacao';
    header("Location: $paginaRetorno?status=sucesso&acao=$acao");
    exit();

} catch (PDOException $e) {
    // Provável causa: a coluna `status` ainda não existe na tabela
    // (rode migracao_status_usuarios.sql antes de usar esta tela).
    header("Location: $paginaRetorno?erro=" . urlencode('Não foi possível atualizar o status. Tente novamente.'));
    exit();
}