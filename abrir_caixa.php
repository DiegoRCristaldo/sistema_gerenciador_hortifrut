<?php
include 'verifica_login.php';
include 'config.php';

// Verifica se já há um caixa aberto
$operador_id = $_SESSION['usuario']; 
$stmt = $conn->prepare("SELECT id FROM caixas WHERE operador_id = ? AND data_fechamento IS NULL");
$stmt->bind_param("i", $operador_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo "<script>alert('Você já possui um caixa aberto!'); window.location.href='registrar_venda.php';</script>";
    exit;
}

// Processa o envio do formulário
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $valor_inicial = isset($_POST['valor_inicial']) ? (float)str_replace(',', '.', $_POST['valor_inicial']) : 0;

    $stmt = $conn->prepare("INSERT INTO caixas (operador_id, data_abertura, valor_inicial) VALUES (?, NOW(), ?)");
    $stmt->bind_param("id", $operador_id, $valor_inicial);
    $stmt->execute();

    echo "<script>alert('Caixa aberto com sucesso!'); window.location.href='registrar_venda.php';</script>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Abrir Caixa</title>
    <link rel="stylesheet" href="assets/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <h2>Abrir Caixa</h2>
    <form method="POST" class="mt-4">
        <div class="mb-3">
            <label for="valor_inicial" class="form-label">Valor Inicial em Caixa (R$)</label>
            <input type="number" step="0.01" min="0" name="valor_inicial" id="valor_inicial" class="form-control" required autofocus>
        </div>
        <button type="submit" class="btn btn-success">Abrir Caixa</button>
        <a href="index.php" class="btn btn-secondary ms-2">Voltar</a>
    </form>
</div>
</body>
</html>
