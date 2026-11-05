<?php
// recepcionista/atendimento-telefonico.php
session_start();
require_once __DIR__ . '/../../conexao.php';

// Validação de sessão da recepcionista
if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'recepcionista') {
    header("Location: ../../auth/login.php");
    exit();
}

if (empty($_SESSION['csrf_atendimento_telefonico'])) {
    $_SESSION['csrf_atendimento_telefonico'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_atendimento_telefonico'];

$atendimentosHoje = [];
$dataHoje = date('Y-m-d');
$erroCarregamento = false;

try {
    // Busca os atendimentos telefônicos registrados na data de hoje
    $stmt = $pdo->prepare("
        SELECT id, paciente, telefone, assunto, resumo, criado_em
        FROM atendimento_telefonico 
        WHERE criado_em >= ? AND criado_em < DATE_ADD(?, INTERVAL 1 DAY)
        ORDER BY criado_em DESC
    ");
    $stmt->execute([$dataHoje . ' 00:00:00', $dataHoje . ' 00:00:00']);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($resultados as $row) {
        $atendimentosHoje[] = [
            'id'       => (int)$row['id'],
            'paciente' => $row['paciente'],
            'telefone' => $row['telefone'] ?? '',
            'assunto'  => $row['assunto'] ?? 'outro',
            'resumo'   => $row['resumo'],
            'hora'     => $row['criado_em'] ? date('H:i', strtotime($row['criado_em'])) : ''
        ];
    }
} catch (PDOException $e) {
    error_log('Falha ao carregar atendimentos telefônicos do dia: ' . $e->getMessage());
    $erroCarregamento = true;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atendimento Telefônico - Convelum Saúde</title>

    <link href="../../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/responsivo.css">
</head>
<body class="paginas-arrumar">

<!-- ========================= TOPO DO PAINEL ========================= -->
<div class="topo-painel">
    <div class="topo-painel-marca">
        <a href="../../index.php">
        <img src="../../imgs/logofrase.png" alt="Convelum Saúde">
        </a>
    </div>
    <div class="topo-painel-perfil">
        <a href="painel.php" class="topo-painel-sair">
            <i class="bi bi-arrow-left"></i> Voltar ao painel
        </a>
    </div>
</div>

<div id="painel-boasvindas">
    <div>
        <span id="subtitulo-painel">TRIAGEM E CADASTRO</span>
        <h1 id="titulo-painel">Atendimento telefônico</h1>
        <p id="texto-painel">Registre o que foi tratado numa ligação com o paciente.</p>
    </div>
</div>

<?php if (isset($_GET['sucesso']) && in_array($_GET['sucesso'], ['1', 'editado', 'excluido'], true)): ?>
    <div class="container mt-3" style="max-width: 900px;">
        <div class="alert alert-success" role="alert" id="alertaSucesso" style="display: flex; align-items: center; justify-content: space-between;">
            <span><i class="bi bi-check-circle-fill me-2"></i>
                <?php
                    $mensagensSucesso = [
                        '1' => 'Atendimento telefônico registrado com sucesso!',
                        'editado' => 'Atendimento telefônico atualizado com sucesso!',
                        'excluido' => 'Atendimento telefônico excluído com sucesso.',
                    ];
                    echo htmlspecialchars($mensagensSucesso[$_GET['sucesso']] ?? $mensagensSucesso['1'], ENT_QUOTES, 'UTF-8');
                ?>
            </span>
            <button type="button" onclick="document.getElementById('alertaSucesso').remove()" style="background:none;border:none;color:inherit;font-size:1.2rem;cursor:pointer;line-height:1;">&times;</button>
        </div>
    </div>
    <script>
        setTimeout(function () {
            const alerta = document.getElementById('alertaSucesso');
            if (alerta) alerta.remove();
        }, 5000);
    </script>
<?php elseif (isset($_GET['erro'])): ?>
    <div class="container mt-3" style="max-width: 900px;">
        <div class="alert alert-danger" role="alert" id="alertaErro" style="display: flex; align-items: center; justify-content: space-between;">
            <span><i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($_GET['erro'], ENT_QUOTES, 'UTF-8'); ?></span>
            <button type="button" onclick="document.getElementById('alertaErro').remove()" style="background:none;border:none;color:inherit;font-size:1.2rem;cursor:pointer;line-height:1;">&times;</button>
        </div>
    </div>
    <script>
        setTimeout(function () {
            const alerta = document.getElementById('alertaErro');
            if (alerta) alerta.remove();
        }, 5000);
    </script>
<?php endif; ?>

<div class="secao-config-agenda">

    <!-- Apontando para actions/salvar-atendimento-telefonico.php -->
    <form method="post" action="actions/salvar-atendimento-telefonico.php" class="cartao-config-agenda">
        <h2 class="titulo-cartao-config">Novo registro</h2>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

        <div class="campo-config">
            <label for="pacienteTelefone">Paciente</label>
            <input type="text" id="pacienteTelefone" name="paciente" maxlength="200" placeholder="Nome do paciente (ou 'Não identificado')" required>
        </div>

        <div class="campo-config">
            <label for="telefoneOrigem">Telefone de origem</label>
            <input type="tel" id="telefoneOrigem" name="telefone" placeholder="(00) 0000-0000 ou (00) 00000-0000" maxlength="16">
        </div>

        <div class="campo-config">
            <label for="assuntoTelefone">Assunto</label>
            <select id="assuntoTelefone" name="assunto">
                <option value="agendamento">Agendamento</option>
                <option value="reagendamento">Reagendamento</option>
                <option value="cancelamento">Cancelamento</option>
                <option value="duvida">Dúvida geral</option>
                <option value="exame">Exame/resultado</option>
                <option value="outro">Outro</option>
            </select>
        </div>

        <div class="campo-config">
            <label for="resumoTelefone">Resumo da ligação</label>
            <textarea id="resumoTelefone" name="resumo" rows="3" maxlength="5000" placeholder="Descreva rapidamente o que foi tratado..." required></textarea>
        </div>

        <button type="submit" class="botao-entrar botao-config-salvar">Registrar atendimento</button>
    </form>

    <div class="cartao-config-agenda">
        <h2 class="titulo-cartao-config">Registrados hoje</h2>

        <?php if (empty($atendimentosHoje)): ?>
            <?php if (!$erroCarregamento): ?>
                <p class="historico-vazio" style="color: #666; padding: 15px 0;">Nenhum atendimento telefônico registrado hoje ainda.</p>
            <?php endif; ?>
        <?php else: ?>
            <div class="lista-config-registros">
                <?php foreach ($atendimentosHoje as $a): ?>
                    <div class="registro-config">
                        <div>
                            <span class="registro-config-tipo"><?php echo htmlspecialchars($a['paciente'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="registro-config-periodo"><?php echo htmlspecialchars($a['hora'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php if ($a['telefone'] !== ''): ?>
                                <p class="registro-medicamento-detalhes">Telefone: <?php echo htmlspecialchars($a['telefone'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <?php endif; ?>
                            <p class="registro-medicamento-detalhes">
                                <?php echo htmlspecialchars(ucfirst($a['assunto']) . ': ' . $a['resumo'], ENT_QUOTES, 'UTF-8'); ?>
                            </p>
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="alternarEdicaoAtendimento(<?php echo $a['id']; ?>)">
                                    <i class="bi bi-pencil"></i> Editar
                                </button>
                                <form method="post" action="actions/excluir-atendimento-telefonico.php" onsubmit="return confirm('Tem certeza de que deseja excluir este atendimento?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="id" value="<?php echo $a['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-trash"></i> Excluir
                                    </button>
                                </form>
                            </div>
                            <form method="post" action="actions/editar-atendimento-telefonico.php" id="editarAtendimento<?php echo $a['id']; ?>" class="mt-3" style="display:none;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="id" value="<?php echo $a['id']; ?>">
                                <div class="campo-config">
                                    <label for="paciente<?php echo $a['id']; ?>">Paciente</label>
                                    <input type="text" id="paciente<?php echo $a['id']; ?>" name="paciente" maxlength="200" value="<?php echo htmlspecialchars($a['paciente'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                </div>
                                <div class="campo-config">
                                    <label for="telefone<?php echo $a['id']; ?>">Telefone de origem</label>
                                    <input type="tel" id="telefone<?php echo $a['id']; ?>" name="telefone" maxlength="20" value="<?php echo htmlspecialchars($a['telefone'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="(00) 0000-0000">
                                </div>
                                <div class="campo-config">
                                    <label for="assunto<?php echo $a['id']; ?>">Assunto</label>
                                    <select id="assunto<?php echo $a['id']; ?>" name="assunto" required>
                                        <?php foreach ([
                                            'agendamento' => 'Agendamento',
                                            'reagendamento' => 'Reagendamento',
                                            'cancelamento' => 'Cancelamento',
                                            'duvida' => 'Dúvida geral',
                                            'exame' => 'Exame/resultado',
                                            'outro' => 'Outro',
                                        ] as $valorAssunto => $rotuloAssunto): ?>
                                            <option value="<?php echo $valorAssunto; ?>" <?php echo $a['assunto'] === $valorAssunto ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($rotuloAssunto, ENT_QUOTES, 'UTF-8'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="campo-config">
                                    <label for="resumo<?php echo $a['id']; ?>">Resumo da ligação</label>
                                    <textarea id="resumo<?php echo $a['id']; ?>" name="resumo" rows="3" maxlength="5000" required><?php echo htmlspecialchars($a['resumo'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                                </div>
                                <button type="submit" class="btn btn-sm btn-primary">Salvar alterações</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="alternarEdicaoAtendimento(<?php echo $a['id']; ?>)">Cancelar</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($erroCarregamento): ?>
            <div class="container mt-3" style="max-width: 900px;">
                <div class="alert alert-danger" role="alert">Não foi possível carregar os atendimentos telefônicos. Tente novamente mais tarde.</div>
            </div>
        <?php endif; ?>
    </div>

</div>

<script>
    function alternarEdicaoAtendimento(id) {
        const formulario = document.getElementById('editarAtendimento' + id);
        if (formulario) {
            formulario.style.display = formulario.style.display === 'none' ? 'block' : 'none';
        }
    }

    // Máscara de telefone que aceita tanto FIXO (10 dígitos, (00) 0000-0000)
    // quanto CELULAR (11 dígitos, (00) 00000-0000) — o "telefone de origem"
    // de uma ligação pode ser qualquer um dos dois, então detecta pela
    // quantidade de dígitos digitados em vez de assumir sempre celular.
    document.getElementById('telefoneOrigem').addEventListener('input', function (e) {
        let v = e.target.value.replace(/\D/g, '');
        if (v.length > 11) v = v.slice(0, 11);

        if (v.length <= 10) {
            // Formato FIXO: (00) 0000-0000
            if (v.length > 6) {
                v = v.replace(/^(\d{2})(\d{4})(\d{0,4}).*$/, '($1) $2-$3');
            } else if (v.length > 2) {
                v = v.replace(/^(\d{2})(\d{0,4})/, '($1) $2');
            } else if (v.length > 0) {
                v = v.replace(/^(\d*)/, '($1');
            }
        } else {
            // Formato CELULAR: (00) 00000-0000 (11º dígito digitado)
            v = v.replace(/^(\d{2})(\d{5})(\d{0,4}).*$/, '($1) $2-$3');
        }
        e.target.value = v;
    });
</script>

</body>
</html>