<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/header.php';
require_once 'includes/navbar.php';

// Descobre a profundidade do arquivo para calcular a URL base ($baseUrl)
$scriptPath = $_SERVER['PHP_SELF'];
if (strpos($scriptPath, '/perfis/') !== false) {
    $baseUrl = '../../';
} elseif (strpos($scriptPath, '/auth/') !== false) {
    $baseUrl = '../';
} else {
    $baseUrl = './';
}

// Verifica se há usuário logado
$usuarioLogado = $_SESSION['usuario_id'] ?? $_SESSION['id'] ?? $_SESSION['uid'] ?? $_SESSION['id_paciente'] ?? $_SESSION['id_medico'] ?? $_SESSION['id_gestor'] ?? null;

// Define a rota dinâmica para agendamento
$linkAgendamentoPacote = !empty($usuarioLogado) ? $baseUrl . 'perfis/paciente/agendar.php' : $baseUrl . 'auth/login.php';

$pacotes = [
    [
        'nome'   => 'Básico',
        'preco'  => '180',
        'exames' => ['Hemograma completo', 'Glicemia em jejum', 'Colesterol total e frações', 'Avaliação clínica geral'],
        'destaque' => false,
    ],
    [
        'nome'   => 'Intermediário',
        'preco'  => '320',
        'exames' => ['Tudo do pacote Básico', 'Eletrocardiograma', 'Exame de urina', 'Função renal e hepática'],
        'destaque' => true,
    ],
    [
        'nome'   => 'Completo',
        'preco'  => '550',
        'exames' => ['Tudo do pacote Intermediário', 'Ultrassom abdominal', 'Avaliação cardiológica', 'Exame oftalmológico', 'Consulta com nutricionista'],
        'destaque' => false,
    ],
];
?>

<div id="secao-servico-topo">
    <span class="subtitulo-servico">CUIDADO COMPLETO</span>
    <h1 class="titulo-servico">Pacotes de Check-up</h1>
    <p class="texto-servico">Escolha o pacote ideal pra cuidar da sua saúde de forma completa e preventiva.</p>
</div>

<div class="secao-pacotes-checkup-publico">
    <div class="grid-pacotes-checkup-publico">
        <?php foreach ($pacotes as $pacote): ?>
            <div class="card-pacote-checkup-publico <?php echo $pacote['destaque'] ? 'pacote-destaque-publico' : ''; ?>">

                <?php if ($pacote['destaque']): ?>
                    <span class="selo-pacote-popular">Mais popular</span>
                <?php endif; ?>

                <p class="pacote-nome-publico"><?php echo $pacote['nome']; ?></p>

                <p class="pacote-preco-publico">
                    <span class="pacote-preco-moeda-publico">R$</span><?php echo $pacote['preco']; ?>
                </p>

                <ul class="pacote-lista-exames-publico">
                    <?php foreach ($pacote['exames'] as $exame): ?>
                        <li><i class="bi bi-check-circle-fill"></i> <?php echo htmlspecialchars($exame); ?></li>
                    <?php endforeach; ?>
                </ul>

                <!-- LINK DINÂMICO DE AGENDAMENTO -->
                <a href="<?= $linkAgendamentoPacote ?>" class="pacote-botao-publico">Agendar este pacote</a>

            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

</body>
</html>