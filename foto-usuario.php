<?php
// foto-usuario.php (raiz do projeto)
// A pasta "perfis" fica fora da área pública, então essa pequena ponte lê o
// arquivo do disco e devolve ele pro navegador. Sem login, ninguém acessa.
session_start();

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403);
    exit('Acesso negado.');
}

// Fotos novas de médicos ficam em uploads; os demais perfis e fotos antigas
// continuam na pasta legada fora da área pública.
define('PASTA_PERFIS', __DIR__ . '/../perfis');
define('PASTA_UPLOADS', __DIR__ . '/uploads');

$arquivo = $_GET['arquivo'] ?? '';

// Só aceita caminhos no formato "perfil/nome-do-arquivo.ext" (ex: medico/MED002.jpg),
// pra evitar que alguém tente ler outro arquivo do disco via ../../ na URL.
if (!preg_match('#^(medicos|medico|recepcionista|gestor)/[A-Za-z0-9_\-]+\.(jpg|jpeg|png|webp)$#i', $arquivo)) {
    http_response_code(400);
    exit('Caminho de arquivo inválido.');
}

$pastaBase = strtolower(strtok($arquivo, '/')) === 'medicos'
    ? PASTA_UPLOADS
    : PASTA_PERFIS;
$caminhoCompleto = $pastaBase . '/' . $arquivo;

if (!is_file($caminhoCompleto)) {
    http_response_code(404);
    exit('Foto não encontrada.');
}

$mimes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
$extensao = strtolower(pathinfo($caminhoCompleto, PATHINFO_EXTENSION));

header('Content-Type: ' . ($mimes[$extensao] ?? 'application/octet-stream'));
header('Content-Length: ' . filesize($caminhoCompleto));
header('Cache-Control: private, max-age=86400');
readfile($caminhoCompleto);
exit();