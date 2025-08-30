<?php
include 'verifica_login.php';
include 'config.php';
include 'funcoes_caixa.php';

$operador_id = $_SESSION['operador_id'] ?? $_SESSION['id'] ?? null;
if (!$operador_id) {
    echo "Erro: operador não identificado.";
    exit;
}

// se já tem caixa aberto, redireciona para registrar_venda
$caixa_aberto = getCaixaAberto($conn, $operador_id);
if ($caixa_aberto) {
    $_SESSION['flash'] = 'Você já possui um caixa aberto!';
    header("Location: registrar_venda.php");
    exit;
}

$erro = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $valor_inicial = isset($_POST['valor_inicial']) ? (float)str_replace(',', '.', $_POST['valor_inicial']) : 0.00;

    $stmt = $conn->prepare("INSERT INTO caixas (operador_id, data_abertura, valor_inicial) VALUES (?, NOW(), ?)");
    $stmt->bind_param("id", $operador_id, $valor_inicial);
    if ($stmt->execute()) {
        $_SESSION['flash'] = 'Caixa aberto com sucesso!';
        header("Location: registrar_venda.php");
        exit;
    } else {
        $erro = "Erro ao abrir caixa: " . $stmt->error;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <link rel="icon" href="assets/logo.png" type="image/png">
    <title>Abrir Caixa</title>
    <link rel="stylesheet" href="assets/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <h2>Abrir Caixa</h2>
    <?php if (!empty($erro)): ?>
        <div class="alert alert-danger"><?= $erro ?></div>
    <?php endif; ?>
    <form method="POST" class="mt-4">
        <div class="mb-3">
            <label for="valor_inicial" class="form-label">Valor Inicial em Caixa (R$)</label>
            <input type="number" step="0.01" min="0" name="valor_inicial" id="valor_inicial" class="form-control" required autofocus>
        </div>
        <button type="submit" class="btn btn-success">Abrir Caixa</button>
        <a href="logout.php" class="btn btn-secondary ms-2">Sair</a>
        <a href="index.php" class="btn btn-secondary ms-2">Voltar</a>
    </form>
</div>
</body>
</html>
