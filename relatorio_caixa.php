<?php
include 'verifica_login.php';
include 'config.php';
include 'funcoes_caixa.php';

$operador_id = $_SESSION['operador_id'] ?? $_SESSION['id'] ?? null;
$caixa_id = isset($_GET['caixa_id']) ? (int) $_GET['caixa_id'] : null;

if (!$caixa_id) {
    // pega último caixa fechado desse operador
    $sql = "SELECT * FROM caixas WHERE operador_id = ? AND data_fechamento IS NOT NULL ORDER BY data_fechamento DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $operador_id);
    $stmt->execute();
    $caixa = $stmt->get_result()->fetch_assoc();
    if (!$caixa) {
        echo "Nenhum caixa fechado encontrado.";
        exit();
    }
} else {
    $stmt = $conn->prepare("SELECT * FROM caixas WHERE id = ?");
    $stmt->bind_param("i", $caixa_id);
    $stmt->execute();
    $caixa = $stmt->get_result()->fetch_assoc();
    if (!$caixa) {
        echo "Caixa não encontrado.";
        exit();
    }
    $caixa_id = $caixa['id'];
}

// Totais (vendas e sangrias)
$vendas = getTotalVendas($conn, $caixa_id);
$sangrias = getTotalSangrias($conn, $caixa_id);

$total_final = $caixa['valor_inicial'] + ($vendas['total'] ?? 0) - ($sangrias['total'] ?? 0);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Relatório do Caixa</title>
    <link rel="stylesheet" href="assets/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <h2>Relatório do Caixa</h2>
        <div class="d-flex flex-wrap gap-2 mb-3">
            <a href="logout.php" class="btn btn-outline-secondary d-flex align-items-center gap-2">
                <i class="bi bi-box-arrow-right"></i> Sair
            </a>
            <a href="abrir_caixa.php" class="btn btn-outline-primary d-flex align-items-center gap-2">
                <i class="bi bi-cash-coin"></i> Abrir um novo caixa
            </a>
        </div>
        <p><strong>Nome do operador(a):</strong> <?= htmlspecialchars($_SESSION['usuario'] ?? '') ?></p>
        <p><strong>Data de Abertura:</strong> <?= $caixa['data_abertura'] ?></p>
        <p><strong>Data de Fechamento:</strong> <?= $caixa['data_fechamento'] ?></p>
        <p><strong>Valor de Abertura:</strong> R$ <?= number_format($caixa['valor_inicial'], 2, ',', '.') ?></p>
        <p><strong>Total de Vendas:</strong> R$ <?= number_format($vendas['total'] ?? 0, 2, ',', '.') ?></p>
        <p><strong>Total de Sangrias:</strong> R$ <?= number_format($sangrias['total'] ?? 0, 2, ',', '.') ?></p>
        <p><strong>Valor Final em Caixa:</strong> <span style="color: green">R$ <?= number_format($total_final, 2, ',', '.') ?></span></p>
        <button onclick="window.print()" class="btn btn-success">🖨️ Imprimir</button>
    </div>
</body>
</html>
