<?php
// gestor/actions/atualizar-perfil.php
session_start();
require_once '../../../conexao.php';
require_once 'validacoes.php';

// Proteção de acesso: Apenas gestores
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_perfil'] !== 'gestor') {
    header("Location: ../../../auth/login.php");
    exit();
}

$gestorId = $_SESSION['usuario_id'];

// Mesmo caminho usado em salvar-usuario.php/atualizar-usuario.php — AJUSTE JUNTO se mudar lá
define('PASTA_PERFIS', dirname(__DIR__, 4) . '/perfis');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome        = trim($_POST['nome'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $cpf         = trim($_POST['cpf'] ?? '');
    $celular     = trim($_POST['celular'] ?? '');
    $telefone    = trim($_POST['telefone'] ?? '');
    $cargo       = trim($_POST['cargo'] ?? '');
    $senhaAtual  = $_POST['senha_atual'] ?? '';
    $novaSenha   = $_POST['nova_senha'] ?? '';

    try {
        // 1. Busca os dados atuais do gestor para validar a senha
        $stmt = $pdo->prepare("SELECT * FROM gestores WHERE uid = ? OR id = ?");
        $stmt->execute([$gestorId, $gestorId]);
        $gestor = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$gestor) {
            header("Location: ../minha-conta.php?erro=nao_encontrado");
            exit();
        }

        // 2. Valida se a senha atual informada está correta
        if (!password_verify($senhaAtual, $gestor['senha'])) {
            header("Location: ../minha-conta.php?erro=senha_incorreta");
            exit();
        }

        // 3. Foto (opcional — só processa se o gestor escolheu um arquivo novo)
        $temFotoNova = isset($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE;
        $fotoRelativaNova = null;
        $caminhoFotoAbsNova = null;
        $caminhoFotoAntigaAbs = null;

        if ($temFotoNova) {
            if ($_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
                header("Location: ../minha-conta.php?erro=foto_envio");
                exit();
            }

            $tamanhoMaximo = 5 * 1024 * 1024; // 5 MB
            if ($_FILES['foto']['size'] > $tamanhoMaximo) {
                header("Location: ../minha-conta.php?erro=foto_tamanho");
                exit();
            }

            $extensaoFoto = extensaoFotoValida($_FILES['foto']);
            if ($extensaoFoto === null) {
                header("Location: ../minha-conta.php?erro=foto_formato");
                exit();
            }

            $pastaDestino = PASTA_PERFIS . '/gestor';
            if (!is_dir($pastaDestino) && !mkdir($pastaDestino, 0755, true)) {
                header("Location: ../minha-conta.php?erro=foto_pasta");
                exit();
            }

            // Reaproveita o UID já existente no nome do arquivo, com sufixo de tempo pra não colidir com o antigo
            $nomeArquivo        = $gestor['uid'] . '-' . time() . '.' . $extensaoFoto;
            $caminhoFotoAbsNova = $pastaDestino . '/' . $nomeArquivo;

            if (!move_uploaded_file($_FILES['foto']['tmp_name'], $caminhoFotoAbsNova)) {
                header("Location: ../minha-conta.php?erro=foto_salvar");
                exit();
            }

            $fotoRelativaNova = 'gestor/' . $nomeArquivo;

            if (!empty($gestor['foto'])) {
                $caminhoFotoAntigaAbs = PASTA_PERFIS . '/' . $gestor['foto'];
            }
        }

        // 4. Prepara a query de atualização básica
        $sql = "UPDATE gestores SET nome = ?, email = ?, cpf = ?, celular = ?, telefone = ?, cargo = ?";
        $params = [$nome, $email, $cpf, $celular, $telefone, $cargo];

        if ($temFotoNova) {
            $sql .= ", foto = ?";
            $params[] = $fotoRelativaNova;
        }

        // 5. Se o usuário preencheu a nova senha, valida e adiciona ao update
        if (!empty($novaSenha)) {
            if (strlen($novaSenha) < 6) {
                header("Location: ../minha-conta.php?erro=senha_curta");
                exit();
            }
            $hashSenha = password_hash($novaSenha, PASSWORD_DEFAULT);
            $sql .= ", senha = ?";
            $params[] = $hashSenha;
        }

        // Define a condição de qual registro atualizar (por ID ou UID)
        if (isset($gestor['id'])) {
            $sql .= " WHERE id = ?";
            $params[] = $gestor['id'];
        } else {
            $sql .= " WHERE uid = ?";
            $params[] = $gestor['uid'];
        }

        $updateStmt = $pdo->prepare($sql);
        $updateStmt->execute($params);

        // Atualiza também o nome na sessão caso tenha mudado
        $_SESSION['usuario_nome'] = $nome;

        // Só apaga a foto antiga depois que o UPDATE foi confirmado com sucesso
        if ($caminhoFotoAntigaAbs !== null && file_exists($caminhoFotoAntigaAbs)) {
            @unlink($caminhoFotoAntigaAbs);
        }

        header("Location: ../minha-conta.php?sucesso=1");
        exit();

    } catch (PDOException $e) {
        // Desfaz a foto nova já salva em disco, já que a atualização não foi concluída
        if ($caminhoFotoAbsNova !== null && file_exists($caminhoFotoAbsNova)) {
            @unlink($caminhoFotoAbsNova);
        }

        header("Location: ../minha-conta.php?erro=banco");
        exit();
    }
} else {
    header("Location: ../minha-conta.php");
    exit();
}