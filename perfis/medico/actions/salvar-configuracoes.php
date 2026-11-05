<?php
session_start();
require_once '../../../conexao.php';
require_once '../../gestor/actions/validacoes.php';

if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'medico') {
    header('Location: ../../../auth/login.php');
    exit();
}

function voltarConfiguracoesMedico(string $mensagem, string $tipo = 'config_erro'): void
{
    $_SESSION[$tipo] = $mensagem;
    header('Location: ../configuracoes.php');
    exit();
}

class ErroFotoMedico extends RuntimeException
{
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../configuracoes.php');
    exit();
}
if (!isset($_POST['csrf_token'], $_SESSION['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
    voltarConfiguracoesMedico('Sua sessão expirou. Atualize a página e tente novamente.');
}

$medicoId = filter_var($_SESSION['usuario_id'], FILTER_VALIDATE_INT);
$nome = trim((string)($_POST['nome'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$celular = trim((string)($_POST['celular'] ?? ''));
$telefone = trim((string)($_POST['telefone'] ?? ''));
$digitosCelular = preg_replace('/\D/', '', $celular);
$digitosTelefone = preg_replace('/\D/', '', $telefone);

if (!$medicoId || $nome === '' || mb_strlen($nome) > 100
    || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100
    || !in_array(strlen($digitosCelular), [10, 11], true) || mb_strlen($celular) > 15
    || !in_array(strlen($digitosTelefone), [10, 11], true) || mb_strlen($telefone) > 15) {
    voltarConfiguracoesMedico('Informe nome, e-mail válido, telefone e celular completos.');
}

$caminhoFotoAbsNova = null;
$caminhoFotoAntigaAbs = null;

try {
    $stmtAtual = $pdo->prepare('SELECT id, uid, foto FROM medico WHERE id = ? LIMIT 1');
    $stmtAtual->execute([$medicoId]);
    $medicoAtual = $stmtAtual->fetch(PDO::FETCH_ASSOC);
    if (!$medicoAtual) {
        voltarConfiguracoesMedico('Não foi possível localizar esta conta médica.');
    }

    $stmtCheck = $pdo->prepare('SELECT id FROM medico WHERE email = ? AND id <> ? LIMIT 1');
    $stmtCheck->execute([$email, $medicoId]);
    if ($stmtCheck->fetchColumn()) {
        voltarConfiguracoesMedico('Este e-mail já está em uso por outra conta médica.');
    }

    $fotoRelativaNova = null;
    $temFotoNova = isset($_FILES['foto']) && (int)$_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE;
    if ($temFotoNova) {
        $arquivo = $_FILES['foto'];
        if ($arquivo['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($arquivo['tmp_name'])
            || $arquivo['size'] < 1 || $arquivo['size'] > 5 * 1024 * 1024) {
            throw new ErroFotoMedico('A foto deve ser enviada corretamente e ter até 5 MB.');
        }

        $extensao = extensaoFotoValida($arquivo);
        if ($extensao === null) {
            throw new ErroFotoMedico('Formato de foto inválido. Use JPG, PNG ou WEBP.');
        }

        $pastaDestino = dirname(__DIR__, 4) . '/perfis/medico';
        if (!is_dir($pastaDestino) && !mkdir($pastaDestino, 0755, true) && !is_dir($pastaDestino)) {
            throw new ErroFotoMedico('Não foi possível preparar a pasta de fotos.');
        }

        $nomeArquivo = preg_replace('/[^A-Za-z0-9_-]/', '', (string)$medicoAtual['uid'])
            . '-' . bin2hex(random_bytes(8)) . '.' . $extensao;
        $caminhoFotoAbsNova = $pastaDestino . '/' . $nomeArquivo;
        if (!move_uploaded_file($arquivo['tmp_name'], $caminhoFotoAbsNova)) {
            throw new ErroFotoMedico('Não foi possível salvar a foto enviada.');
        }
        $fotoRelativaNova = 'medico/' . $nomeArquivo;

        if (!empty($medicoAtual['foto'])
            && preg_match('#^medico/[A-Za-z0-9_-]+\.(jpg|jpeg|png|webp)$#i', (string)$medicoAtual['foto'])) {
            $caminhoFotoAntigaAbs = dirname(__DIR__, 4) . '/perfis/' . $medicoAtual['foto'];
        }
    }

    if ($temFotoNova) {
        $stmtUpdate = $pdo->prepare('UPDATE medico SET nome = ?, email = ?, celular = ?, telefone = ?, foto = ? WHERE id = ?');
        $stmtUpdate->execute([$nome, $email, $celular, $telefone, $fotoRelativaNova, $medicoId]);
    } else {
        $stmtUpdate = $pdo->prepare('UPDATE medico SET nome = ?, email = ?, celular = ?, telefone = ? WHERE id = ?');
        $stmtUpdate->execute([$nome, $email, $celular, $telefone, $medicoId]);
    }

    $_SESSION['usuario_nome'] = $nome;

    if ($caminhoFotoAntigaAbs !== null && is_file($caminhoFotoAntigaAbs)
        && !unlink($caminhoFotoAntigaAbs)) {
        error_log('Não foi possível remover foto antiga do médico ID ' . $medicoId);
    }

    voltarConfiguracoesMedico('Dados pessoais atualizados com sucesso.', 'config_sucesso');
} catch (ErroFotoMedico $e) {
    if ($caminhoFotoAbsNova !== null && is_file($caminhoFotoAbsNova) && !unlink($caminhoFotoAbsNova)) {
        error_log('Não foi possível remover foto temporária do médico ID ' . $medicoId);
    }
    voltarConfiguracoesMedico($e->getMessage());
} catch (Throwable $e) {
    if ($caminhoFotoAbsNova !== null && is_file($caminhoFotoAbsNova) && !unlink($caminhoFotoAbsNova)) {
        error_log('Não foi possível remover foto temporária do médico ID ' . $medicoId);
    }
    error_log('Falha ao salvar configurações do médico: ' . $e->getMessage());
    voltarConfiguracoesMedico('Não foi possível salvar seus dados. Confira se o e-mail já está em uso e tente novamente.');
}
