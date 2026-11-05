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
$linkAgendamento = !empty($usuarioLogado) ? $baseUrl . 'perfis/paciente/agendar.php' : $baseUrl . 'auth/login.php';

// Conexão com o banco de dados
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=convelumsaude;charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Busca as especialidades diretamente do banco ordenadas pelo nome
    $stmt = $pdo->query("SELECT nome, preco, icone FROM especialidade WHERE status = 'Ativo' ORDER BY nome ASC");
    $especialidades = $stmt->fetchAll();

} catch (PDOException $e) {
    $especialidades = [];
}
?>

<div id="secao-servico-topo">
    <span class="subtitulo-servico">ATENDIMENTO MÉDICO</span>
    <h1 class="titulo-servico">Consultas</h1>
    <p class="texto-servico">Presencial ou por teleconsulta, com os melhores especialistas.</p>
</div>

<!-- TIPOS DE ATENDIMENTO -->
<div class="secao-tipos-consulta" style="display: flex; gap: 20px; justify-content: center; flex-wrap: wrap; margin-bottom: 50px;">
    
    <!-- Card Consulta Presencial -->
    <div class="card-tipo-consulta" style="display: flex; flex-direction: column; justify-content: space-between; flex: 1; min-width: 280px; max-width: 450px;">
        <div>
            <i class="bi bi-hospital"></i>
            <p class="card-tipo-consulta-titulo">Consulta presencial</p>
            <p class="card-tipo-consulta-texto">Atendimento na clínica, com toda a estrutura pra exames complementares no mesmo dia.</p>
        </div>
        <div style="margin-top: 20px;">
            <a href="<?php echo $linkAgendamento; ?>" class="pacote-botao-publico">Agendar Presencial</a>
        </div>
    </div>

    <!-- Card Teleconsulta -->
    <div class="card-tipo-consulta" style="display: flex; flex-direction: column; justify-content: space-between; flex: 1; min-width: 280px; max-width: 450px;">
        <div>
            <i class="bi bi-camera-video"></i>
            <p class="card-tipo-consulta-titulo">Teleconsulta</p>
            <p class="card-tipo-consulta-texto">Atendimento por videochamada, sem sair de casa, com a mesma qualidade.</p>
        </div>
        <div style="margin-top: 20px;">
            <a href="<?php echo $linkAgendamento; ?>" class="pacote-botao-publico">Agendar Teleconsulta</a>
        </div>
    </div>

</div>

<!-- ESPECIALIDADES E PREÇOS -->
<div id="secao-especialidades" class="secao-especialidades-servico">
    <h2 id="titulo-especialidades">Especialidades e valores</h2>

    <!-- BARRA DE PESQUISA -->
    <div class="container-pesquisa-especialidade" style="max-width: 500px; margin: 0 auto 30px auto; padding: 0 15px;">
        <div class="input-group">
            <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
            <input type="text" id="inputPesquisaEspecialidade" class="form-control border-start-0 shadow-none" placeholder="Pesquisar especialidade..." onkeyup="filtrarEspecialidades()">
        </div>
    </div>

    <div id="grid-especialidades">
        <?php if (!empty($especialidades)): ?>
            <?php foreach ($especialidades as $esp): ?>
                <div class="card-especialidade" data-nome="<?php echo strtolower($esp['nome']); ?>" style="display: flex; flex-direction: column; justify-content: space-between;">
                    <div>
                        <?php if (!empty($esp['icone'])): ?>
                            <img src="<?php echo htmlspecialchars($esp['icone']); ?>" alt="<?php echo htmlspecialchars($esp['nome']); ?>" class="icone-especialidade">
                        <?php endif; ?>
                        <p class="texto-especialidade"><?php echo htmlspecialchars($esp['nome']); ?></p>
                        <p class="preco-especialidade-servico">a partir de R$<?php echo number_format($esp['preco'], 2, ',', '.'); ?></p>
                    </div>
                    <div style="margin-top: 15px;">
                        <a href="<?php echo $linkAgendamento; ?>" class="btn btn-sm btn-primary w-100" style="border-radius: 6px; padding: 6px 12px; font-weight: 500;">Agendar</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p id="sem-resultados" style="grid-column: 1 / -1; text-align: center;">Nenhuma especialidade cadastrada no momento.</p>
        <?php endif; ?>
        <p id="pesquisa-vazia" style="display: none; grid-column: 1 / -1; text-align: center; color: #666;">Nenhuma especialidade encontrada para esta busca.</p>
    </div>
</div>

<!-- SCRIPT DE FILTRAGEM RÁPIDA -->
<script>
function filtrarEspecialidades() {
    let input = document.getElementById('inputPesquisaEspecialidade').value.toLowerCase();
    let cards = document.querySelectorAll('.card-especialidade');
    let encontrou = false;

    cards.forEach(card => {
        let nome = card.getAttribute('data-nome');
        if (nome.includes(input)) {
            card.style.display = "flex";
            encontrou = true;
        } else {
            card.style.display = "none";
        }
    });

    let avisoVazio = document.getElementById('pesquisa-vazia');
    if (avisoVazio) {
        avisoVazio.style.display = encontrou ? "none" : "block";
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>

</body>
</html>