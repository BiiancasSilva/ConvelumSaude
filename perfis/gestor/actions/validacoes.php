<?php
// gestor/actions/validacoes.php
// Funções de validação compartilhadas entre salvar-usuario.php e atualizar-usuario.php.

/**
 * Valida CPF (dígitos verificadores). Só deve ser chamada quando o tipo de
 * documento selecionado é de fato "CPF" — outros tipos de documento
 * não passam por essa checagem.
 */
function validarCpf(string $cpf): bool
{
    $cpf = preg_replace('/\D/', '', $cpf);

    if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }

    for ($t = 9; $t < 11; $t++) {
        $soma = 0;
        for ($i = 0; $i < $t; $i++) {
            $soma += $cpf[$i] * (($t + 1) - $i);
        }
        $digito = (($soma * 10) % 11) % 10;
        if ((int)$cpf[$t] !== $digito) {
            return false;
        }
    }

    return true;
}

/**
 * Valida e retorna a extensão (em minúsculas) de uma foto enviada por upload,
 * ou null se não houver arquivo válido. Verifica extensão E mime real do
 * arquivo (evita que um .php renomeado pra .jpg passe pela checagem).
 */
function extensaoFotoValida(array $arquivo): ?string
{
    $extensoesValidas = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
    $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
    $mimeReal = @mime_content_type($arquivo['tmp_name']);

    if (!isset($extensoesValidas[$extensao]) || $mimeReal !== $extensoesValidas[$extensao]) {
        return null;
    }

    return $extensao;
}