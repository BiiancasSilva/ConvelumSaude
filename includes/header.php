<!-- includes/header.php -->
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Convelum Saúde - Clínica Médica</title>

    <!-- 1º: Link do Bootstrap (CSS) -->
    <link href="css/bootstrap.min.css" rel="stylesheet">

    <!-- 2º: Seu CSS personalizado (pc e celular) (para sobrescrever o Bootstrap quando precisar) -->
        <link rel="stylesheet" href="css/style.css?v=2">
        <link rel="stylesheet" href="css/responsivo.css">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</head>
<body>

<script src="js/bootstrap.bundle.min.js"></script>

<!-- Script para abrir o dropdown de Serviços ao passar o mouse -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const menuServicos = document.querySelector('.menu-servicos');
    if (menuServicos) {
        const dropdownToggle = menuServicos.querySelector('.nav-link');
        const dropdownMenu = menuServicos.querySelector('.dropdown-menu');

        menuServicos.addEventListener('mouseenter', function () {
            dropdownMenu.classList.add('show');
            dropdownToggle.setAttribute('aria-expanded', 'true');
        });

        menuServicos.addEventListener('mouseleave', function () {
            dropdownMenu.classList.remove('show');
            dropdownToggle.setAttribute('aria-expanded', 'false');
        });
    }
});
</script>