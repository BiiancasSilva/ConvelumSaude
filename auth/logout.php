<?php
// Inicia a sessão para poder acessá-la
session_start();

// Limpa todas as variáveis da sessão
$_SESSION = array();

// Se desejar destruir completamente a sessão, apague também o cookie da sessão
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
        $params["httponly"]
    );
}

// Destrói a sessão no servidor
session_destroy();

// Redireciona o usuário de volta para a página inicial
header("Location: ../index.php");
exit;
?>