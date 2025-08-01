<?php

// Último caixa fechado
$sql = "SELECT * FROM caixas WHERE operador_id = ? AND data_fechamento IS NOT NULL ORDER BY data_fechamento DESC LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $operador_id);
$stmt->execute();
$result = $stmt->get_result();
$caixa = $result->fetch_assoc();

if (!$caixa) {
    echo "Nenhum caixa fechado encontrado.";
    exit();
}

$caixa_id = $caixa['id'];

// Total de vendas
$sqlVendas = "SELECT SUM(total) AS total FROM vendas WHERE caixa_id = ?";
$stmtVendas = $conn->prepare($sqlVendas);
$stmtVendas->bind_param("i", $caixa_id);
$stmtVendas->execute();
$resultVendas = $stmtVendas->get_result();
$vendas = $resultVendas->fetch_assoc();

// Total de sangrias
$sqlSangrias = "SELECT SUM(valor) AS total FROM sangrias WHERE caixa_id = ?";
$stmtSangrias = $conn->prepare($sqlSangrias);
$stmtSangrias->bind_param("i", $caixa_id);
$stmtSangrias->execute();
$resultSangrias = $stmtSangrias->get_result();
$sangrias = $resultSangrias->fetch_assoc();

// Total final em caixa
$total_final = $caixa['valor_inicial'] + $vendas['total'] - $sangrias['total'];

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Relatório do Caixa</title>
    <link rel="stylesheet" href="assets/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <h2>Relatório do Caixa</h2>
        <div class="d-flex flex-wrap gap-2 mb-3">
            <a href="index.php" class="btn btn-outline-secondary d-flex align-items-center gap-2">
                <i class="bi bi-files"></i> Voltar para o menu
            </a>
            <a href="abrir_caixa.php" class="btn btn-outline-primary d-flex align-items-center gap-2">
                <i class="bi bi-files"></i> Abrir um novo caixa
            </a>
        </div>
        <p><strong>Nome do operador(a):</strong> <?= $_SESSION['usuario'] ?></p>
        <p><strong>Data de Abertura:</strong> <?= $caixa['data_abertura'] ?></p>
        <p><strong>Data de Fechamento:</strong> <?= $caixa['data_fechamento'] ?></p>
        <p><strong>Valor de Abertura:</strong> R$ <?= number_format($caixa['valor_inicial'], 2, ',', '.') ?></p>
        <p><strong>Total de Vendas:</strong> R$ <?= number_format($vendas['total'], 2, ',', '.') ?></p>
        <p><strong>Total de Sangrias:</strong> R$ <?= number_format($sangrias['total'], 2, ',', '.') ?></p>
        <p><strong>Valor Final em Caixa:</strong> <span style="color: green">R$ <?= number_format($total_final, 2, ',', '.') ?></span></p>
    </div>
</body>
</html>