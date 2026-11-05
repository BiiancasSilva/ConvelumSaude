<!-- includes/navbar.php -->
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Descobre a profundidade do arquivo atual para gerar o caminho base correto ($baseUrl)
$scriptPath = $_SERVER['PHP_SELF'];

if (strpos($scriptPath, '/perfis/') !== false) {
    // Se estiver dentro de perfis/paciente/, perfis/medico/, etc., precisa voltar 2 níveis
    $baseUrl = '../../';
} elseif (strpos($scriptPath, '/auth/') !== false) {
    // Se estiver dentro de auth/, volta 1 nível
    $baseUrl = '../';
} else {
    // Se estiver na raiz (index.php, especialidades.php, etc.)
    $baseUrl = './';
}

// Verifica se há usuário logado
$usuarioLogado = $_SESSION['usuario_id'] ?? $_SESSION['id'] ?? $_SESSION['uid'] ?? $_SESSION['id_paciente'] ?? $_SESSION['id_medico'] ?? $_SESSION['id_gestor'] ?? null;
$nomeUsuario   = $_SESSION['usuario_nome'] ?? $_SESSION['nome'] ?? 'Usuário';
$nivelAcesso   = $_SESSION['nivel_acesso'] ?? $_SESSION['tipo_usuario'] ?? '';

// Mapeia os caminhos exatos conforme a estrutura de pastas da sua imagem
$cargo = 'Paciente';
$linkPainel = $baseUrl . 'perfis/paciente/painel.php';

if ($nivelAcesso == 4 || strtolower($nivelAcesso) === 'gestor') {
    $cargo = 'Gestor';
    $linkPainel = $baseUrl . 'perfis/gestor/painel.php';
} elseif ($nivelAcesso == 2 || strtolower($nivelAcesso) === 'medico') {
    $cargo = 'Médico';
    $linkPainel = $baseUrl . 'perfis/medico/painel.php';
} elseif ($nivelAcesso == 3 || strtolower($nivelAcesso) === 'recepcionista') {
    $cargo = 'Recepcionista';
    $linkPainel = $baseUrl . 'perfis/recepcionista/painel.php';
}

// Define para onde o botão "Agendar" envia a pessoa
$linkAgendamento = !empty($usuarioLogado) ? $baseUrl . 'perfis/paciente/agendar.php' : $baseUrl . 'auth/login.php';
?>

<nav class="navbar navbar-expand-lg bg-white navbar-convelum">
  <div class="container-fluid">

    <!-- LOGO DO SITE -->
    <a class="navbar-brand" href="<?= $baseUrl ?>index.php">
      <img src="<?= $baseUrl ?>imgs/logofrase.png" alt="Convelum Saúde" height="50" class="d-inline-block align-text-top">
    </a>

    <!-- BOTÃO HAMBÚRGUER (CELULAR) -->
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
            data-bs-target="#navbarNav" aria-controls="navbarNav"
            aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <!-- LINKS DO MENU -->
    <div class="collapse navbar-collapse" id="navbarNav">

      <ul class="navbar-nav mb-2 mb-lg-0">
        <li class="nav-item">
          <a class="nav-link active" href="<?= $baseUrl ?>index.php">Home</a>
        </li>

        <li class="nav-item">
          <a class="nav-link" href="<?= $baseUrl ?>index.php#secao-sobre">Sobre</a>
        </li>

        <li class="nav-item dropdown menu-servicos">
          <a class="nav-link" href="./servicos.php" role="button" data-bs-toggle="dropdown" aria-expanded="false">Serviços</a>
          <ul class="dropdown-menu border-0 shadow-sm rounded-3">
            <li><a class="dropdown-item" href="<?= $baseUrl ?>servicos-checkups.php">Check-ups</a></li>
            <li><a class="dropdown-item" href="<?= $baseUrl ?>servicos-consultas.php">Consultas</a></li>
            <li><a class="dropdown-item" href="<?= $baseUrl ?>servicos-exames.php">Exames</a></li>
          </ul>
        </li>

        <li class="nav-item">
          <a class="nav-link" href="<?= $baseUrl ?>especialidades.php">Especialidades</a>
        </li>

        <!-- LINK DINÂMICO DE AGENDAR -->
        <li class="nav-item">
          <a class="nav-link" href="<?= $linkAgendamento ?>">Agendar</a>
        </li>

        <li class="nav-item">
          <a class="nav-link" href="<?= $baseUrl ?>index.php#secao-contato">Contato</a>
        </li>
      </ul>

      <!-- BARRA DE PESQUISA E ÁREA DO USUÁRIO -->
      <div class="ms-auto d-flex align-items-center gap-3">

        <!-- FORMULÁRIO DE PESQUISA -->
        <form class="d-flex position-relative" action="<?= $baseUrl ?>busca.php" method="GET" role="search">
          <input 
              class="form-control rounded-3 input-busca" 
              type="search" 
              name="q"
              placeholder="Digite o que deseja buscar" 
              aria-label="Search"
              value="<?= isset($_GET['q']) ? htmlspecialchars($_GET['q']) : '' ?>"
              required
          >
          <button type="submit" style="background: none; border: none; padding: 0; position: absolute; right: 12px; top: 50%; transform: translateY(-50%); cursor: pointer;">
              <i class="bi bi-search icone-busca"></i>
          </button>
        </form>

        <!-- ÁREA DINÂMICA: PERFIL LOGADO OU PORTAL DE ACESSO -->
        <?php if (!empty($usuarioLogado)): ?>

            <!-- USUÁRIO LOGADO -->
            <div class="dropdown d-flex align-items-center">
                <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" id="menuUsuario" data-bs-toggle="dropdown" aria-expanded="false" style="color: inherit;">
                    <div class="icone-user-circle me-2" style="font-size: 1.5rem;">
                        <i class="bi bi-person-circle"></i>
                    </div>
                    <div class="d-flex flex-column text-start me-1">
                        <span style="font-weight: 600; font-size: 0.9rem; line-height: 1.1;">
                            <?= htmlspecialchars($nomeUsuario) ?>
                        </span>
                        <small style="font-size: 0.75rem; color: #666;">
                            <?= htmlspecialchars($cargo) ?>
                        </small>
                    </div>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="menuUsuario">
                    <li><a class="dropdown-item" href="<?= $linkPainel ?>"><i class="bi bi-speedometer2 me-2"></i>Meu Painel</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?= $baseUrl ?>auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Sair</a></li>
                </ul>
            </div>

        <?php else: ?>

            <!-- VISITANTE NÃO LOGADO -->
            <a href="<?= $baseUrl ?>auth/login.php"
               class="btn btn-outline-dark d-flex align-items-center px-3 py-2 rounded-3 botao-area-paciente text-nowrap">
              <i class="bi bi-person-circle me-2"></i>
              <span class="fw-medium">PORTAL DE ACESSO</span>
            </a>

        <?php endif; ?>

      </div>

    </div>
  </div>
</nav>