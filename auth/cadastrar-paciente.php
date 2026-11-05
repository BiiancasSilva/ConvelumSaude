<?php
// auth/cadastrar-paciente.php

require_once '../conexao.php'; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Recebe os dados da Etapa 1
    $nome       = filter_input(INPUT_POST, 'nome', FILTER_SANITIZE_SPECIAL_CHARS);
    $email      = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $telefone   = !empty($_POST['telefone']) ? $_POST['telefone'] : null;
    $celular    = !empty($_POST['celular']) ? $_POST['celular'] : null;
    $nascimento = !empty($_POST['nascimento']) ? $_POST['nascimento'] : null;

    // Recebe e criptografa a senha (Etapa 3)
    $senhaRaw   = $_POST['senha'] ?? '';
    $senhaHash  = password_hash($senhaRaw, PASSWORD_DEFAULT);

    // Identifica se é menor de idade
    $menorInput = $_POST['menor_de_idade'] ?? 'nao';
    $menorIdade = ($menorInput === 'sim') ? 'Y' : 'N';

    // Recebe os dados do Responsável (Etapa 2 - se preenchidos)
    $nomeResp       = !empty($_POST['nome_responsavel']) ? $_POST['nome_responsavel'] : null;
    $telResp        = !empty($_POST['telefone_responsavel']) ? $_POST['telefone_responsavel'] : null;
    $celResp        = !empty($_POST['celular_responsavel']) ? $_POST['celular_responsavel'] : null;
    $emailResp      = !empty($_POST['email_responsavel']) ? $_POST['email_responsavel'] : null;
    $nascimentoResp = !empty($_POST['nascimento_responsavel']) ? $_POST['nascimento_responsavel'] : null;

    if (!$nome || !$email || !$senhaRaw || !$nascimento || !$celular) {
        die("Por favor, preencha todos os campos obrigatórios.");
    }

    // Validação obrigatória: senha com no mínimo 6 dígitos
    if (strlen($senhaRaw) < 6) {
        die("A senha deve ter no mínimo 6 dígitos.");
    }

    // Gera o UID sequencial no padrão PAC001, PAC002, etc. (igual auth/cadastro.php)
    $queryUltimo = $pdo->query("SELECT id FROM paciente ORDER BY id DESC LIMIT 1");
    $ultimoId    = $queryUltimo->fetchColumn();
    $proximoId   = $ultimoId ? ((int)$ultimoId + 1) : 1;
    $uid         = 'PAC' . str_pad($proximoId, 3, '0', STR_PAD_LEFT);

    try {
        // TABELA NO SINGULAR: paciente
        $sql = "INSERT INTO paciente (
                    uid, nome, email, telefone, celular, data_nasc, 
                    senha, menor_idade, nome_resp, telefone_resp, 
                    celular_resp, email_resp, data_nasc_resp, nivel_acesso
                ) VALUES (
                    :uid, :nome, :email, :telefone, :celular, :data_nasc, 
                    :senha, :menor_idade, :nome_resp, :telefone_resp, 
                    :celular_resp, :email_resp, :data_nasc_resp, 1
                )";

        $stmt = $pdo->prepare($sql);
        
        $stmt->execute([
            ':uid'            => $uid,
            ':nome'           => $nome,
            ':email'          => strtolower(trim($email)),
            ':telefone'       => $telefone,
            ':celular'        => $celular,
            ':data_nasc'      => $nascimento,
            ':senha'          => $senhaHash,
            ':menor_idade'    => $menorIdade,
            ':nome_resp'      => $nomeResp,
            ':telefone_resp'  => $telResp,
            ':celular_resp'   => $celResp,
            ':email_resp'     => $emailResp ? strtolower(trim($emailResp)) : null,
            ':data_nasc_resp' => $nascimentoResp
        ]);

        header("Location: login.php?status=cadastro_sucesso");
        exit;

    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            die("Erro: Este e-mail já está cadastrado no sistema.");
        } else {
            die("Erro ao cadastrar no banco de dados: " . $e->getMessage());
        }
    }
} else {
    header("Location: cadastro.php");
    exit;
}