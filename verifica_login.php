<?php
session_start();

$is_api_request = false;

// Detecta se é requisição AJAX
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    $is_api_request = true;
}

// OU se quiser, pode verificar se o script atual é buscar_produto.php
if (basename($_SERVER['PHP_SELF']) === 'buscar_produto.php') {
    $is_api_request = true;
}

if (!isset($_SESSION['usuario'])) {
    if ($is_api_request) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['erro' => 'Usuário não autenticado']);
    } else {
        header('Location: login.php');
    }
    exit();
}

// Restrição para vendedores: só podem acessar registrar_venda.php
$current = basename($_SERVER['PHP_SELF']);
$permitido_vendedor = ['registrar_venda.php', 'comprovante.php'];

if ($_SESSION['tipo'] === 'vendedor' && !in_array($current, $permitido_vendedor)) {
    header('Location: registrar_venda.php');
    exit();
}
?>
