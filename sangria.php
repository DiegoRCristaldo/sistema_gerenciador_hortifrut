<?php
include 'verifica_login.php';
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $operador_id = $_SESSION['usuario'];
    $valor = floatval($_POST['valor']);
    $descricao = $_POST['descricao'] ?? '';

    // Busca o caixa aberto
    $sql = "SELECT id FROM caixas WHERE operador_id = ? AND data_fechamento IS NULL ORDER BY data_abertura DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $operador_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $caixa = $result->fetch_assoc();

    if (!$caixa) {
        echo "Nenhum caixa aberto para registrar a sangria.";
        exit();
    }

    $caixa_id = $caixa['id'];

    // Insere sangria
    $sqlInsert = "INSERT INTO sangrias (caixa_id, operador_id, valor, descricao, data_sangria) VALUES (?, ?, ?, ?, NOW())";
    $stmtInsert = $conn->prepare($sqlInsert);
    $stmtInsert->bind_param("iids", $caixa_id, $operador_id, $valor, $descricao);

    if ($stmtInsert->execute()) {
        echo "<script>alert('Sangria registrada com sucesso.');</script>";
    } else {
        echo "<script>alert('Erro ao registrar sangria: ');</script>" . $stmtInsert->error;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Registrar Sangria</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <!-- Formulário HTML -->
    <div class="container mt-5">
        <h2>Registrar Sangria</h2>
        <a href="registrar_venda.php" target="_blank" class="btn btn-outline-primary d-flex align-items-center gap-2">
            <i class="bi bi-files"></i> Voltar para o caixa
        </a>
        <form method="POST" class="mt-4">
            <label class="form-label">Valor da sangria:</label>
            <input type="number" name="valor" step="0.01" class="form-control" required autofocus>
            <br>
            <label class="form-label">Descrição (opcional):</label>
            <input type="text" name="descricao" class="form-control">
            <br>
            <button type="submit" class="btn btn-success">Registrar Sangria</button>
        </form>
    </div>    
</body>
</html>
