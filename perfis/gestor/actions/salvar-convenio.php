<?php
// gestor/actions/salvar-convenio.php
session_start();
require_once '../../../conexao.php';

// Proteção de acesso: Apenas gestores
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_perfil'] !== 'gestor') {
    header("Location: ../../../auth/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome             = trim($_POST['nome'] ?? '');
    $registroAns      = trim($_POST['registro_ans'] ?? '');
    $prazoFaturamento = ($_POST['prazo_faturamento'] ?? '30') . ' dias';
    $status           = $_POST['status'] ?? 'Ativo';
    $planosJson       = $_POST['planos'] ?? '[]';

    $planos = json_decode($planosJson, true);

    if (!empty($nome)) {
        try {
            $pdo->beginTransaction();

            // 1. Inserir o Convênio (Tabela correta: convenios)
            $stmt = $pdo->prepare("
                INSERT INTO convenios (nome, registro_ans, prazo_faturamento, status) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$nome, $registroAns, $prazoFaturamento, $status]);
            $idConvenio = $pdo->lastInsertId();

            // 2. Inserir os Planos vinculados (Tabela correta do seu banco: convenio_planos)
            if (is_array($planos) && count($planos) > 0) {
                $stmtPlano = $pdo->prepare("
                    INSERT INTO convenio_planos (id_convenio, nome_plano, cobertura) 
                    VALUES (?, ?, ?)
                ");
                foreach ($planos as $plano) {
                    if (!empty($plano['nome'])) {
                        $stmtPlano->execute([$idConvenio, $plano['nome'], $plano['cobertura'] ?? '']);
                    }
                }
            }

            $pdo->commit();

            header("Location: ../cadastrar-convenio.php?status=sucesso");
            exit();

        } catch (PDOException $e) {
            $pdo->rollBack();
            header("Location: ../cadastrar-convenio.php?erro=" . urlencode($e->getMessage()));
            exit();
        }
    } else {
        header("Location: ../cadastrar-convenio.php?erro=Nome_invalido");
        exit();
    }
} else {
    header("Location: ../cadastrar-convenio.php");
    exit();
}