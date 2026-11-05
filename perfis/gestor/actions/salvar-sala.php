<?php
// gestor/actions/salvar-sala.php
session_start();
require_once '../../../conexao.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_perfil'] !== 'gestor') {
    header("Location: ../../../auth/login.php");
    exit();
}

$acao = $_POST['acao'] ?? '';
$msg = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ================= CRIAR NOVA SALA =================
    if ($acao === 'criar') {
        $nome = trim($_POST['nome'] ?? '');
        $idMedico = trim($_POST['id_medico'] ?? '');
        $idMedico = ($idMedico === '') ? null : (int) $idMedico;

        if ($nome === '') {
            $erro = 'Informe um nome para a sala.';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO salas (nome, id_medico, status) VALUES (?, ?, 'livre')");
                $stmt->execute([$nome, $idMedico]);
                $msg = 'Sala cadastrada com sucesso!';
            } catch (PDOException $e) {
                $erro = 'Erro ao cadastrar sala.';
            }
        }
    }

    // ================= EDITAR SALA EXISTENTE =================
    elseif ($acao === 'editar') {
        $id = (int) ($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $idMedico = trim($_POST['id_medico'] ?? '');
        $idMedico = ($idMedico === '') ? null : (int) $idMedico;

        if ($id <= 0 || $nome === '') {
            $erro = 'Preencha os campos obrigatórios.';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE salas SET nome = ?, id_medico = ? WHERE id = ?");
                $stmt->execute([$nome, $idMedico, $id]);
                $msg = 'Sala atualizada com sucesso!';
            } catch (PDOException $e) {
                $erro = 'Erro ao atualizar sala.';
            }
        }
    }

    // ================= ALTERAR STATUS (livre / bloqueada) =================
    elseif ($acao === 'alterar_status') {
        $id = (int) ($_POST['id'] ?? 0);
        $novoStatus = $_POST['novo_status'] ?? '';

        if ($id > 0 && in_array($novoStatus, ['livre', 'bloqueada'], true)) {
            try {
                $stmt = $pdo->prepare("UPDATE salas SET status = ? WHERE id = ?");
                $stmt->execute([$novoStatus, $id]);
                $msg = 'Status da sala atualizado!';
            } catch (PDOException $e) {
                $erro = 'Erro ao atualizar status.';
            }
        } else {
            $erro = 'Dados inválidos para atualizar status.';
        }
    }

    // ================= EXCLUIR SALA =================
    elseif ($acao === 'excluir') {
        $id = (int) ($_POST['id'] ?? 0);

        if ($id > 0) {
            try {
                // Consultas e bloqueios que apontam pra essa sala ficam com id_sala = NULL (ON DELETE SET NULL)
                $stmt = $pdo->prepare("DELETE FROM salas WHERE id = ?");
                $stmt->execute([$id]);
                $msg = 'Sala removida com sucesso!';
            } catch (PDOException $e) {
                $erro = 'Erro ao remover sala.';
            }
        } else {
            $erro = 'Sala inválida.';
        }
    }

    else {
        $erro = 'Ação não reconhecida.';
    }
}

// Volta para a tela de cadastro, levando a mensagem via GET
$params = [];
if ($msg)  $params['msg']  = $msg;
if ($erro) $params['erro'] = $erro;

$queryString = http_build_query($params);
header("Location: ../cadastrar-salas.php" . ($queryString ? "?$queryString" : ""));
exit();