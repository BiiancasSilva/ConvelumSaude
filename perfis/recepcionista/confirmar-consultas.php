<?php
// recepcionista/confirmar-consultas.php
session_start();
require_once __DIR__ . '/../../conexao.php';

// Validação de sessão da recepcionista
if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'recepcionista') {
    header("Location: ../../auth/login.php");
    exit();
}

if (empty($_SESSION['csrf_consultas'])) {
    $_SESSION['csrf_consultas'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_consultas'];

$consultasPendentes = [];
$erroCarregamento = false;

try {
    // Busca consultas pendentes unindo com pacientes e médicos
    // OBS: isso NÃO inclui a tabela `encaixes`, que hoje é separada de `consultas`.
    // Se um "encaixe" foi cadastrado direto na tabela `encaixes`, ele não vai
    // aparecer aqui. O ideal é que o cadastro de encaixe grave em `consultas`
    // com is_encaixe = 1 (a coluna já existe pra isso).
    $stmt = $pdo->prepare("
        SELECT c.id, c.data_hora, c.is_encaixe,
               p.nome as paciente_nome, p.celular as paciente_telefone, p.email as paciente_email,
               m.nome as medico_nome
        FROM consultas c
        JOIN paciente p ON c.id_paciente = p.id
        JOIN medico m ON c.id_medico = m.id
        WHERE c.status = 'pendente'
        ORDER BY c.data_hora ASC
    ");
    $stmt->execute();
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($resultados as $row) {
        $consultasPendentes[] = [
            'id'         => (int)$row['id'],
            'paciente'   => $row['paciente_nome'],
            'medico'     => $row['medico_nome'] ?? 'Médico',
            'data'       => date('d/m/Y', strtotime($row['data_hora'])),
            'hora'       => date('H:i', strtotime($row['data_hora'])),
            'telefone'   => $row['paciente_telefone'] ?? '',
            'email'      => $row['paciente_email'] ?? '',
            'is_encaixe' => (bool)$row['is_encaixe'],
        ];
    }
} catch (PDOException $e) {
    error_log('Falha ao carregar consultas pendentes para confirmação: ' . $e->getMessage());
    $erroCarregamento = true;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmar Consultas - Convelum Saúde</title>

    <link href="../../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/responsivo.css">
</head>
<body>

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
        <span id="subtitulo-painel">CONFIRMAÇÃO DE CONSULTAS</span>
        <h1 id="titulo-painel">Consultas pendentes</h1>
        <p id="texto-painel">Confirme com o paciente por WhatsApp, e-mail, SMS ou ligação.</p>
    </div>
</div>

<div class="secao-lista-painel">
    <?php if (isset($_GET['erro'])): ?>
        <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($_GET['erro'], ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if ($erroCarregamento): ?>
        <div class="alert alert-danger" role="alert">
            Não foi possível carregar as consultas pendentes. Tente novamente mais tarde.
        </div>
    <?php elseif (empty($consultasPendentes)): ?>
        <div class="admin-vazio" style="padding: 40px; text-align: center; color: #666;">
            <i class="bi bi-check2-circle" style="font-size: 2rem;"></i>
            <p class="mt-2">Nenhuma consulta pendente de confirmação no momento.</p>
        </div>
    <?php else: ?>
        <div class="lista-itens-painel">
            <?php foreach ($consultasPendentes as $c): ?>
                <div class="linha-item-painel status-pendente linha-confirmacao" data-id="<?php echo (int)$c['id']; ?>">
                    <div class="info-confirmacao">
                        <span class="item-painel-principal">
                            <?php echo htmlspecialchars($c['paciente'], ENT_QUOTES, 'UTF-8'); ?>
                            <?php if ($c['is_encaixe']): ?>
                                <span class="badge-encaixe" style="background:#ffc107;color:#000;font-size:.7rem;padding:2px 6px;border-radius:4px;margin-left:6px;">ENCAIXE</span>
                            <?php endif; ?>
                        </span>
                        <span class="item-painel-submedico">
                            <?php echo htmlspecialchars($c['medico'], ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars($c['data'], ENT_QUOTES, 'UTF-8'); ?> às <?php echo htmlspecialchars($c['hora'], ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    </div>

                    <div class="canais-confirmacao">
                        <?php if (!empty($c['telefone'])): ?>
                            <a href="https://wa.me/55<?php echo rawurlencode(preg_replace('/\D/', '', $c['telefone'])); ?>" target="_blank" rel="noopener noreferrer" class="botao-canal-confirmacao canal-whatsapp" title="WhatsApp">
                                <i class="bi bi-whatsapp"></i>
                            </a>
                            <a href="tel:<?php echo rawurlencode(preg_replace('/\D/', '', $c['telefone'])); ?>" class="botao-canal-confirmacao canal-ligacao" title="Ligação">
                                <i class="bi bi-telephone-fill"></i>
                            </a>
                        <?php endif; ?>

                        <?php if (!empty($c['email'])): ?>
                            <a href="mailto:<?php echo htmlspecialchars($c['email'], ENT_QUOTES, 'UTF-8'); ?>" class="botao-canal-confirmacao canal-email" title="E-mail">
                                <i class="bi bi-envelope-fill"></i>
                            </a>
                        <?php endif; ?>
                    </div>

                    <div class="acoes-confirmacao" style="display:flex; gap:8px;">
                        <button type="button" class="item-painel-botao" onclick="atualizarStatusConsulta(this, <?php echo (int)$c['id']; ?>, 'confirmada')">Confirmar</button>
                        <button type="button" class="item-painel-botao" style="background:#dc3545;" onclick="atualizarStatusConsulta(this, <?php echo (int)$c['id']; ?>, 'cancelada')">Cancelar</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
const csrfConsultas = <?php echo json_encode($csrfToken); ?>;

function atualizarStatusConsulta(botao, idConsulta, novoStatus) {
    const linha = botao.closest('.linha-item-painel');
    const botoes = linha.querySelectorAll('button');

    // Evita duplo clique enquanto a requisição está em andamento
    botoes.forEach(b => b.disabled = true);

    fetch('actions/atualizar-status-consulta.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + encodeURIComponent(idConsulta)
            + '&status=' + encodeURIComponent(novoStatus)
            + '&csrf_token=' + encodeURIComponent(csrfConsultas)
    })
    .then(response => response.json().then(data => {
        if (!response.ok) {
            throw new Error(data.erro || 'Não foi possível atualizar a consulta.');
        }
        return data;
    }))
    .then(data => {
        if (data.sucesso) {
            linha.classList.remove('status-pendente');
            linha.classList.add('status-' + novoStatus);

            if (novoStatus === 'confirmada') {
                botao.textContent = 'Confirmada ✓';
                botoes.forEach(b => b.disabled = true);
            } else {
                // Some com a linha após cancelar
                linha.style.opacity = '0.5';
                setTimeout(() => linha.remove(), 600);
                return;
            }

            botao.style.opacity = '0.6';
        } else {
            alert(data.erro || 'Erro ao atualizar status.');
            botoes.forEach(b => b.disabled = false);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert(error.message || 'Erro de comunicação com o servidor. Tente novamente.');
        botoes.forEach(b => b.disabled = false);
    });
}
</script>

</body>
</html>