<?php
// gestor/actions/bloqueios-helpers.php
//
// Funções compartilhadas entre salvar-bloqueio.php, atualizar-bloqueio.php
// e excluir-bloqueio.php. Precisa que session_start() já tenha rodado antes
// de usar as funções de CSRF.

/**
 * Descobre o nome de exibição do alvo do bloqueio (nome_alvo),
 * a partir do tipo escolhido (medico/recepcionista/gestor/geral) e do uid.
 */
function resolverNomeAlvo(PDO $pdo, string $tipoAlvo, string $alvoId): string
{
    $tabelasPorTipo = [
        'medico'        => ['tabela' => 'medico',        'rotulo' => 'Médico',        'todos' => 'Todos os Médicos'],
        'recepcionista' => ['tabela' => 'recepcionistas', 'rotulo' => 'Recepcionista', 'todos' => 'Todos os Recepcionistas'],
        'gestor'        => ['tabela' => 'gestores',       'rotulo' => 'Gestor',        'todos' => 'Todos os Gestores'],
    ];

    if (!isset($tabelasPorTipo[$tipoAlvo])) {
        return 'Toda a Clínica';
    }

    $config = $tabelasPorTipo[$tipoAlvo];

    if ($alvoId === '0') {
        return $config['todos'];
    }

    $stmt = $pdo->prepare("SELECT nome FROM {$config['tabela']} WHERE uid = ?");
    $stmt->execute([$alvoId]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);

    return $res ? $res['nome'] . " ({$config['rotulo']})" : "{$config['rotulo']} {$alvoId}";
}

/**
 * Confere se o período do bloqueio faz sentido (fim não pode ser antes do início).
 * Retorna null se estiver tudo certo, ou uma mensagem de erro se não estiver.
 */
function validarPeriodoBloqueio(string $dataInicio, ?string $horaInicio, string $dataFim, ?string $horaFim): ?string
{
    $inicio = strtotime($dataInicio . ' ' . ($horaInicio ?: '00:00:00'));
    $fim    = strtotime($dataFim . ' ' . ($horaFim ?: '23:59:59'));

    if ($inicio === false || $fim === false) {
        return 'Data ou hora inválida.';
    }

    if ($fim < $inicio) {
        return 'A data/hora de término não pode ser antes do início.';
    }

    return null;
}

/**
 * Gera (ou reaproveita) o token CSRF salvo na sessão pra esse formulário.
 */
function gerarTokenCsrfBloqueio(): string
{
    if (empty($_SESSION['csrf_token_bloqueios'])) {
        $_SESSION['csrf_token_bloqueios'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token_bloqueios'];
}

/**
 * Confere se o token CSRF enviado no formulário bate com o da sessão.
 */
function validarTokenCsrfBloqueio(?string $tokenRecebido): bool
{
    return !empty($tokenRecebido)
        && !empty($_SESSION['csrf_token_bloqueios'])
        && hash_equals($_SESSION['csrf_token_bloqueios'], $tokenRecebido);
}