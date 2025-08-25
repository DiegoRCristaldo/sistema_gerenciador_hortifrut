<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'verifica_login.php';
include 'config.php';

header('Content-Type: application/json');

// Alterado para esperar 'codigo_barras' em vez de 'codigo'
if (isset($_GET['codigo_barras'])) {
    $codigo = $conn->real_escape_string($_GET['codigo_barras']);
    $sql = "SELECT id, nome, preco, unidade_medida, ncm, cfop, codigo_barras FROM produtos WHERE codigo_barras = '$codigo'";
    $result = $conn->query($sql);

    if ($result) {
        $produto = $result->fetch_assoc();
        // Retorna um objeto com status e produto, para facilitar o tratamento no JS
        echo json_encode($produto ? ['status' => 'success', 'produto' => $produto] : ['status' => 'error', 'message' => 'Produto não encontrado.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }
    exit;
}

if (isset($_GET['id'])) {
    $id = (int) $_GET['id'];
    $sql = "SELECT id, nome, preco, unidade_medida, ncm, cfop, codigo_barras FROM produtos WHERE id = $id";
    $result = $conn->query($sql);

    if ($result) {
        $produto = $result->fetch_assoc();
        // Retorna um objeto com status e produto
        echo json_encode($produto ? ['status' => 'success', 'produto' => $produto] : ['status' => 'error', 'message' => 'Produto não encontrado.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }
    exit;
}

if (isset($_GET['nome'])) {
    $nome = $conn->real_escape_string($_GET['nome'] ?? '');
    $sql = "SELECT id, nome, preco, unidade_medida, ncm, cfop, codigo_barras FROM produtos WHERE nome LIKE '%$nome%'";
    
    $result = $conn->query($sql);

    if ($result) {
        $produtos = [];
        while ($row = $result->fetch_assoc()) {
            $produtos[] = $row;
        }
        // Retorna um objeto com status e lista de produtos
        echo json_encode($produtos ? ['status' => 'success', 'produtos' => $produtos] : ['status' => 'error', 'message' => 'Nenhum produto encontrado.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }
    exit;
}

// Se não houver parâmetros válidos, retorna uma mensagem de erro
echo json_encode(['status' => 'error', 'message' => 'Parâmetros de busca inválidos.']);
?>
