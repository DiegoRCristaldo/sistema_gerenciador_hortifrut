<?php
include 'verifica_login.php';
include 'config.php';

if (!isset($_GET['id_venda'])) {
    echo "Venda não encontrada.";
    exit;
}

$id_venda = intval($_GET['id_venda']);

// Busca informações da venda
$sql = "SELECT v.*, o.usuario AS operador FROM vendas v 
        LEFT JOIN operadores o ON v.operador_id = o.id 
        WHERE v.id = $id_venda";

$result = $conn->query($sql);

if (!$result) {
    die("Erro na consulta: " . $conn->error);
}

$venda = $result->fetch_assoc();

if (!$venda) {
    echo "Venda inválida.";
    exit;
}

// Itens da venda
$itens = $conn->query("SELECT iv.*, p.nome, p.preco FROM itens_venda iv
                       JOIN produtos p ON iv.produto_id = p.id
                       WHERE iv.venda_id = $id_venda");
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Comprovante de Venda</title>
    <link rel="stylesheet" href="assets/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5 bg-white p-4 rounded shadow">
    <img class="banner" src="assets/banner.jpeg" alt="Banner escrito Hortifrut Quero Fruta" srcset="">
    <h3 class="text-center">Comprovante de Venda</h3>
    <hr>
    <p><strong>ID da Venda:</strong> <?= $venda['id'] ?></p>
    <p><strong>Data:</strong> <?= date('d/m/Y H:i', strtotime($venda['data'])) ?></p>
    <p><strong>Forma de Pagamento:</strong> <?= htmlspecialchars($venda['forma_pagamento']) ?></p>
    <p><strong>Operador:</strong> <?= htmlspecialchars($venda['operador']) ?></p>

    <table class="table table-bordered mt-4">
        <thead class="table-dark">
            <tr>
                <th>Produto</th>
                <th>Qtd</th>
                <th>Preço Unitário</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($item = $itens->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($item['nome']) ?></td>
                <td><?= $item['quantidade'] ?></td>
                <td>R$ <?= number_format($item['preco'], 2, ',', '.') ?></td>
                <td>R$ <?= number_format($item['quantidade'] * $item['preco'], 2, ',', '.') ?></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
        <tfoot>
            <tr class="table-light">
                <th colspan="3" class="text-end">Total da Venda</th>
                <th>R$ <?= number_format($venda['total'], 2, ',', '.') ?></th>
            </tr>
        </tfoot>
    </table>

    <div class="text-center mt-4">
        <a href="registrar_venda.php" class="btn btn-primary">Nova Venda</a>
        <a href="index.php" class="btn btn-secondary">← Voltar ao Painel</a>
        <button onclick="window.print()" class="btn btn-secondary">Imprimir Comprovante</button>
    </div>
</div>

</body>
</html>
