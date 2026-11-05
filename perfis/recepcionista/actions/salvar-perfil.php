<?php
session_start();
require_once __DIR__ . '/../../../conexao.php';
require_once __DIR__ . '/../../gestor/actions/validacoes.php';

if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'recepcionista') {
    header('Location: ../../../auth/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../meu-perfil.php');
    exit();
}

$idRecepcionista = filter_var($_SESSION['usuario_id'], FILTER_VALIDATE_INT);
if ($idRecepcionista === false || $idRecepcionista < 1) {
    header('Location: ../../../auth/login.php');
    exit();
}

$voltarComErro = static function (string $codigo): void {
    header('Location: ../meu-perfil.php?erro=' . urlencode($codigo));
    exit();
};

if (isset($_POST['apenas_senha'])) {
    $senhaAtual = $_POST['senha_atual'] ?? '';
    $senhaNova = $_POST['senha_nova'] ?? '';
    $senhaConfirma = $_POST['senha_confirma'] ?? '';

    if ($senhaAtual === '' || $senhaNova === '' || $senhaConfirma === '') {
        $voltarComErro('campos');
    }
    if ($senhaNova !== $senhaConfirma) {
        $voltarComErro('confirmacao');
    }
    if (strlen($senhaNova) < 6) {
        $voltarComErro('senha_curta');
    }
    if (strlen($senhaNova) > 72) {
        $voltarComErro('limite');
    }

    try {
        $stmt = $pdo->prepare('SELECT senha FROM recepcionistas WHERE id = ? LIMIT 1');
        $stmt->execute([$idRecepcionista]);
        $registro = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$registro) {
            $voltarComErro('1');
        }
        if (!password_verify($senhaAtual, $registro['senha'])) {
            $voltarComErro('senha');
        }

        $novoHash = password_hash($senhaNova, PASSWORD_DEFAULT);
        $stmtUpdate = $pdo->prepare('UPDATE recepcionistas SET senha = ? WHERE id = ?');
        $stmtUpdate->execute([$novoHash, $idRecepcionista]);

        header('Location: ../meu-perfil.php?sucesso=1');
        exit();
    } catch (PDOException $e) {
        error_log('Falha ao trocar senha da recepcionista: ' . $e->getMessage());
        $voltarComErro('1');
    }
}

$nome = trim($_POST['nome'] ?? '');
$email = trim($_POST['email'] ?? '');
$telefone = trim($_POST['telefone'] ?? '');
$celular = trim($_POST['celular'] ?? '');
$tamanho = static function (string $valor): int {
    $quantidade = preg_match_all('/./us', $valor, $matches);
    return $quantidade === false ? PHP_INT_MAX : $quantidade;
};

if ($nome === '' || $email === '' || $celular === '') {
    $voltarComErro('campos');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $voltarComErro('email');
}
if ($tamanho($nome) > 100 || $tamanho($email) > 100 || $tamanho($telefone) > 15 || $tamanho($celular) > 15) {
    $voltarComErro('limite');
}

$caminhoFotoAbsNova = null;
try {
    $stmtAtual = $pdo->prepare('SELECT id, foto FROM recepcionistas WHERE id = ? LIMIT 1');
    $stmtAtual->execute([$idRecepcionista]);
    $recepcionistaAtual = $stmtAtual->fetch(PDO::FETCH_ASSOC);
    if (!$recepcionistaAtual) {
        $voltarComErro('1');
    }

    $stmtEmail = $pdo->prepare('SELECT id FROM recepcionistas WHERE email = ? AND id <> ? LIMIT 1');
    $stmtEmail->execute([$email, $idRecepcionista]);
    if ($stmtEmail->fetchColumn()) {
        $voltarComErro('email_duplicado');
    }

    $temFotoNova = isset($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE;
    $fotoRelativaNova = null;
    $caminhoFotoAntigaAbs = null;
    if ($temFotoNova) {
        if ($_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
            $voltarComErro('foto');
        }
        if ($_FILES['foto']['size'] <= 0 || $_FILES['foto']['size'] > 5 * 1024 * 1024) {
            $voltarComErro('foto');
        }

        $extensaoFoto = extensaoFotoValida($_FILES['foto']);
        if ($extensaoFoto === null) {
            $voltarComErro('foto');
        }

        $pastaPerfis = dirname(__DIR__, 4) . '/perfis';
        $pastaDestino = $pastaPerfis . '/recepcionista';
        if (!is_dir($pastaDestino) && !mkdir($pastaDestino, 0755, true) && !is_dir($pastaDestino)) {
            $voltarComErro('foto');
        }

        $nomeArquivo = 'REC' . $idRecepcionista . '-' . bin2hex(random_bytes(8)) . '.' . $extensaoFoto;
        $caminhoFotoAbsNova = $pastaDestino . '/' . $nomeArquivo;
        if (!move_uploaded_file($_FILES['foto']['tmp_name'], $caminhoFotoAbsNova)) {
            $caminhoFotoAbsNova = null;
            $voltarComErro('foto');
        }
        $fotoRelativaNova = 'recepcionista/' . $nomeArquivo;

        if (!empty($recepcionistaAtual['foto'])
            && preg_match('#^recepcionista/[A-Za-z0-9_-]+\.(jpg|jpeg|png|webp)$#i', $recepcionistaAtual['foto'])) {
            $caminhoFotoAntigaAbs = $pastaPerfis . '/' . $recepcionistaAtual['foto'];
        }
    }

    $stmtUpdate = $pdo->prepare(
        'UPDATE recepcionistas
         SET nome = ?, email = ?, telefone = ?, celular = ?' .
         ($temFotoNova ? ', foto = ?' : '') . '
         WHERE id = ?'
    );
    $parametros = [$nome, $email, $telefone, $celular];
    if ($temFotoNova) {
        $parametros[] = $fotoRelativaNova;
    }
    $parametros[] = $idRecepcionista;
    $stmtUpdate->execute($parametros);

    $_SESSION['usuario_nome'] = $nome;
    if ($caminhoFotoAntigaAbs !== null && is_file($caminhoFotoAntigaAbs)
        && !unlink($caminhoFotoAntigaAbs)) {
        error_log('Não foi possível remover a foto anterior da recepcionista: ' . $caminhoFotoAntigaAbs);
    }
    header('Location: ../meu-perfil.php?sucesso=1');
    exit();
} catch (PDOException $e) {
    if ($caminhoFotoAbsNova !== null && is_file($caminhoFotoAbsNova) && !unlink($caminhoFotoAbsNova)) {
        error_log('Não foi possível remover a nova foto após falha ao salvar o perfil.');
    }
    error_log('Falha ao salvar perfil da recepcionista: ' . $e->getMessage());
    $voltarComErro('1');
}
