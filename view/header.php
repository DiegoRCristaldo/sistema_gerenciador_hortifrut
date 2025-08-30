<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <link rel="icon" href="assets/logo.png" type="image/png">
    <title>Hortifrut Quero Fruta</title>
    <link rel="stylesheet" href="assets/style.css">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- FontAwesome para os ícones -->
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <!-- Bootstrap Bundle JS (com suporte a Offcanvas) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</head>
<body class="bg-light">
    <header>
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
    </header>
    <main>
        <!-- Menu lateral Offcanvas -->
        <div class="offcanvas offcanvas-start text-bg-dark" tabindex="-1" id="menuLateral" aria-labelledby="menuLateralLabel">
            <div class="offcanvas-header">
                <h5 class="offcanvas-title" id="menuLateralLabel">Gerenciamento</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body">
                <ul class="list-group list-group-flush">
                    <?php foreach($lista_links as $link): ?>
                        <?php if ($link['exibir']): ?>
                            <?php
                                $extraAttr = '';
                                if ($link['texto'] === 'Duplicar Página') {
                                    $extraAttr = 'id="duplicarPagina"';
                                }
                            ?>
                            <li class="list-group-item bg-dark border-0">
                                <a <?= $extraAttr ?> class="text-white text-decoration-none" href="<?= $link['href'] ?>" target="<?= $link['target']?>" >
                                    <i class="<?= $link['icone'] ?> me-2"></i><?= $link['texto'] ?>
                                </a>
                            </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
