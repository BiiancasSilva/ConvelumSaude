<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

// Carrega o Bootstrap e o Head
require_once 'includes/header.php';

// Carrega a barra de navegação
require_once 'includes/navbar.php';
?>

<!-- ========================= CONTEÚDO PRINCIPAL ========================= -->
<div id="secao-principal">

    <!-- LADO ESQUERDO -->
    <div class="conteudo-principal">
        <span id="subtitulo-clinica">CUIDADO E INOVAÇÃO</span>

        <h1 id="titulo-pagina">
            Bem-vindo ao portal da Convelum Saúde
        </h1>
        <p id="texto-descricao">
            Oferecemos uma estrutura moderna para a realização de check-ups, exames especializados e consultas médicas com foco no seu bem-estar completo.
        </p>

        <!-- BOTÕES PRINCIPAIS -->
        <div id="bloco-botoes">
            <a class="botao-servicos" href="servicos.php">Conhecer Serviços</a>
            <a class="botao-contato" href="index.php#secao-contato">Fale Conosco</a>
        </div>
    </div>

    <!-- LADO DIREITO -->
    <div class="area-imagem-principal">
        <img class="imagem-principal" src="imgs/logo.png" alt="Estrutura Convelum">
    </div>
</div>

<!-- ========================= CARTÕES DE INFORMAÇÕES ========================= -->
<div id="beneficios-clinica">

    <!-- PRIMEIRO CARTÃO -->
    <div id="beneficio-agendamento" class="card-beneficio">
        <div class="icone-beneficio-circulo">
            <img src="imgs/agenda.png" alt="agenda" class="icone-beneficio">
        </div>
        <p class="texto-beneficio">Agendamento rápido</p>
    </div>

    <!-- SEGUNDO CARTÃO -->
    <div id="beneficio-precos" class="card-beneficio">
        <div class="icone-beneficio-circulo">
            <img src="imgs/precos.png" alt="preços" class="icone-beneficio">
        </div>
        <p class="texto-beneficio">Preços acessíveis</p>
    </div>

    <!-- TERCEIRO CARTÃO -->
    <div id="beneficio-especialidades" class="card-beneficio">
        <div class="icone-beneficio-circulo">
            <img src="imgs/especil.png" alt="especialidades" class="icone-beneficio">
        </div>
        <p class="texto-beneficio">+20 especialidades</p>
    </div>

    <!-- QUARTO CARTÃO -->
    <div id="beneficio-localizacao" class="card-beneficio">
        <div class="icone-beneficio-circulo">
            <img src="imgs/coracao.png" alt="coração" class="icone-beneficio">
        </div>
        <p class="texto-beneficio">Localização</p>
    </div>
</div>

<!-- ========================= SEÇÃO DE ESPECIALIDADES ========================= -->
<div id="secao-especialidades">

    <h2 id="titulo-especialidades">Conheça nossas especialidades</h2>

    <div id="grid-especialidades">

        <?php
        // Conexão com o banco de dados
        require_once 'conexao.php'; 

        // Consulta as especialidades cadastradas
        $query = $pdo->query("SELECT nome, icone FROM especialidade ORDER BY nome ASC");
        $especialidades = $query->fetchAll(PDO::FETCH_ASSOC);

        // A coluna "icone" guarda só o NOME DO ARQUIVO enviado pelo gestor,
        // salvo em uploads/especialidades/ (veja gestor/actions/salvar-especialidade.php).
        $pastaIconesEspecialidade = __DIR__ . '/uploads/especialidades/';

        if (!empty($especialidades)):
            foreach ($especialidades as $esp):
                $nome = htmlspecialchars($esp['nome']);

                if (!empty($esp['icone']) && file_exists($pastaIconesEspecialidade . $esp['icone'])) {
                    $icone = 'uploads/especialidades/' . htmlspecialchars($esp['icone']);
                } else {
                    $icone = 'imgs/default.png';
                }
        ?>
                <div class="card-especialidade">
                    <img src="<?= $icone ?>" alt="<?= $nome ?>" class="icone-especialidade">
                    <p class="texto-especialidade"><?= $nome ?></p>
                </div>
        <?php 
            endforeach;
        else:
        ?>
            <p>Nenhuma especialidade cadastrada no momento.</p>
        <?php endif; ?>

    </div>

    <a href="especialidades.php" class="botao-ver-todos">Ver todos</a>
</div>

<!-- ========================= COMO PODEMOS TE AJUDAR ========================= -->
<div id="secao-ajuda">

    <h2 id="titulo-ajuda">Como podemos te ajudar hoje?</h2>

    <div id="grid-ajuda">

        <!-- CONSULTAS -->
        <div class="card-ajuda">
            <i class="bi bi-clipboard2-pulse icone-ajuda"></i>
            <p class="titulo-card-ajuda">Consultas</p>
            <p class="preco-card-ajuda">a partir de R$110,00</p>
            <a href="<?= $linkAgendamento ?>" class="botao-agendar-ajuda">Agendar <i class="bi bi-chevron-right"></i></a>
        </div>

        <!-- EXAMES -->
        <div class="card-ajuda">
            <i class="bi bi-droplet-half icone-ajuda"></i>
            <p class="titulo-card-ajuda">Exames</p>
            <p class="preco-card-ajuda">a partir de R$14,00</p>
            <a href="<?= $linkAgendamento ?>" class="botao-agendar-ajuda">Agendar <i class="bi bi-chevron-right"></i></a>
        </div>

        <!-- ULTRASSOM -->
        <div class="card-ajuda">
            <i class="bi bi-soundwave icone-ajuda"></i>
            <p class="titulo-card-ajuda">Ultrassom</p>
            <p class="preco-card-ajuda">a partir de R$100,00</p>
            <a href="<?= $linkAgendamento ?>" class="botao-agendar-ajuda">Agendar <i class="bi bi-chevron-right"></i></a>
        </div>

        <!-- CHECK-UP -->
        <div class="card-ajuda">
            <i class="bi bi-clipboard2-check icone-ajuda"></i>
            <p class="titulo-card-ajuda">Check-up</p>
            <p class="preco-card-ajuda">a partir de R$250,00</p>
            <a href="<?= $linkAgendamento ?>" class="botao-agendar-ajuda">Agendar <i class="bi bi-chevron-right"></i></a>
        </div>

    </div>
</div>

<!-- ========================= SEÇÃO SOBRE NÓS ========================= -->
<div id="secao-sobre">

    <!-- LADO ESQUERDO - IMAGEM -->
    <div class="area-imagem-sobre">
        <img class="imagem-sobre" src="imgs/equipe-convelum.jpg" alt="Equipe Convelum Saúde">
    </div>

    <!-- LADO DIREITO - TEXTO -->
    <div class="conteudo-sobre">
        <span id="subtitulo-sobre">SOBRE NÓS</span>

        <h2 id="titulo-sobre">
            Cuidado de você, essa é a nossa missão
        </h2>

        <p id="texto-sobre">
            A Convelum Saúde nasceu com o propósito de oferecer uma experiência
            médica completa, humana e acessível. Reunimos profissionais
            qualificados e uma estrutura moderna para cuidar de cada etapa
            da sua saúde — de check-ups de rotina a exames especializados.
        </p>

        <p id="texto-sobre">
            Acreditamos que cuidar bem começa com atenção, agilidade e
            transparência. Por isso, investimos constantemente em tecnologia
            e no bem-estar de cada paciente que passa por nós.
        </p>

        <a href="nossa-equipe.php" class="botao-sobre">Conheça nossa equipe</a>
    </div>
</div>

<!-- ========================= SEÇÃO DE CONTATO ========================= -->
<div id="secao-contato">

    <!-- LADO ESQUERDO - INFORMAÇÕES -->
    <div class="conteudo-contato">
        <h2 id="titulo-contato">Fale com a Convelum Saúde</h2>

        <p id="texto-contato">
            Estamos prontos para atender você.<br>
            Entre em contato ou venha nos visitar.
        </p>

        <ul id="lista-contato">
            <li>
                <i class="bi bi-geo-alt-fill icone-contato"></i>
                Rua Exemplo, 123 – Centro, São Paulo, SP
            </li>
            <li>
                <i class="bi bi-telephone-fill icone-contato"></i>
                (11) 99999-9999
            </li>
            <li>
                <i class="bi bi-whatsapp icone-contato"></i>
                (11) 1234-5678
            </li>
            <li>
                <i class="bi bi-envelope-fill icone-contato"></i>
                contato@convelumsaude.com.br
            </li>
            <li>
                <i class="bi bi-clock-fill icone-contato"></i>
                Seg a Sex – 8h às 18h
            </li>
        </ul>
    </div>

    <!-- LADO DIREITO - MAPA -->
    <div class="area-mapa-contato">
        <iframe
            src="https://www.google.com/maps?q=Rua+Exemplo,+123+-+Centro,+S%C3%A3o+Paulo,+SP&amp;output=embed"
            width="100%"
            height="100%"
            style="border:0;"
            allowfullscreen=""
            loading="lazy"
            referrerpolicy="no-referrer-when-downgrade">
        </iframe>
    </div>
</div>

<?php
// Rodapé
require_once 'includes/footer.php';
?>

</body>
</html>