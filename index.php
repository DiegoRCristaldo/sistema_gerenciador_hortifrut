<?php
include 'verifica_login.php';
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Hortifrut Quero Fruta</title>
    <link rel="stylesheet" href="assets/index.css">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome para os ícones -->
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <!-- Bootstrap Bundle JS (com suporte a Offcanvas) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</head>
<body class="bg-light">

    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <!-- Botão para abrir o menu lateral -->
            <button class="btn btn-outline-light me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#menuLateral" aria-controls="menuLateral">
            <i class="fas fa-bars"></i>
            </button>

            <!-- Título do sistema -->
            <span class="navbar-brand mb-0 h1">Hortifrut Quero Fruta</span>

            <!-- Botão de sair -->
            <a href="logout.php" class="btn btn-outline-light">Sair</a>
        </div>
    </nav>

    <!-- Menu lateral Offcanvas -->
    <div class="offcanvas offcanvas-start text-bg-dark" tabindex="-1" id="menuLateral" aria-labelledby="menuLateralLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="menuLateralLabel">Gerenciamento</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <ul class="list-group list-group-flush">
                <li class="list-group-item bg-dark border-0"><a class="text-white text-decoration-none" href="registrar_venda.php"><i class="fas fa-cash-register me-2"></i>Registrar Venda</a></li>
                <li class="list-group-item bg-dark border-0"><a class="text-white text-decoration-none" href="gastos.php"><i class="fas fa-money-bill-wave me-2"></i>Registrar Despesas</a></li>
                <li class="list-group-item bg-dark border-0"><a class="text-white text-decoration-none" href="produtos.php"><i class="fas fa-boxes me-2"></i>Gerenciar Produtos</a></li>
                <li class="list-group-item bg-dark border-0"><a class="text-white text-decoration-none" href="usuarios.php"><i class="fas fa-users-cog me-2"></i>Gerenciar Usuários</a></li>
                <li class="list-group-item bg-dark border-0"><a class="text-white text-decoration-none" href="funcionarios.php"><i class="fas fa-users-cog me-2"></i>Gerenciar Funcionários</a></li>
                <li class="list-group-item bg-dark border-0"><a class="text-white text-decoration-none" href="etiquetas.php"><i class="fas fa-users-cog me-2"></i>Imprimir Etiquetas</a></li>
                <li class="list-group-item bg-dark border-0"><a class="text-white text-decoration-none" href="relatorio.php"><i class="fas fa-chart-line me-2"></i>Relatórios</a></li>
            </ul>
        </div>
    </div>

    <?php include 'balanco.php' ?>

</body>
</html>
