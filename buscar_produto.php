<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'verifica_login.php';
include 'config.php';

header('Content-Type: application/json');

if (isset($_GET['codigo'])) {
    $codigo = $conn->real_escape_string($_GET['codigo']);
    $sql = "SELECT id, nome, preco, unidade_medida FROM produtos WHERE codigo_barras = '$codigo'";
    $result = $conn->query($sql);

    if ($result) {
        $produto = $result->fetch_assoc();
        echo json_encode($produto ? $produto : []);
    } else {
        echo json_encode(['error' => $conn->error]);
    }
    exit;
}

if (isset($_GET['id'])) {
    $id = (int) $_GET['id'];
    $sql = "SELECT id, nome, preco, unidade_medida FROM produtos WHERE id = $id";
    $result = $conn->query($sql);

    if ($result) {
        $produto = $result->fetch_assoc();
        echo json_encode($produto ? $produto : []);
    } else {
        echo json_encode(['error' => $conn->error]);
    }
    exit;
}

if (isset($_GET['nome'])) {
    $nome = $conn->real_escape_string($_GET['nome'] ?? '');
    $sql = "SELECT id, nome, preco, unidade_medida FROM produtos WHERE nome LIKE '%$nome%'";
    
    $result = $conn->query($sql);

    if ($result) {
        $produtos = [];
        while ($row = $result->fetch_assoc()) {
            $produtos[] = $row;
        }
        echo json_encode($produtos);
    } else {
        echo json_encode(['error' => $conn->error]);
    }
    exit;
}

// Se não houver parâmetros válidos
echo json_encode([]);
?>
