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
$itens = $conn->query("SELECT iv.*, p.nome, p.preco, p.unidade_medida FROM itens_venda iv
                       JOIN produtos p ON iv.produto_id = p.id
                       WHERE iv.venda_id = $id_venda");

$pagamentos = $conn->query("SELECT * FROM pagamentos WHERE venda_id = $id_venda");
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Comprovante de Venda</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/comprovante.css">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<div class="content p-3">
    <img src="assets/banner.jpeg" alt="Banner escrito Hortifrut Quero Fruta" class="banner">

    <h2>Comprovante de Venda</h2>
    <p><strong>ID da Venda:</strong> <?= $venda['id'] ?></p>
    <p><strong>Data:</strong> <?= date('d/m/Y H:i', strtotime($venda['data'])) ?></p>
    <p><strong>Formas de Pagamento:</strong></p>
    <ul>
        <?php while ($pg = $pagamentos->fetch_assoc()): ?>
            <li><?= htmlspecialchars($pg['forma_pagamento']) ?>: R$ <?= number_format($pg['valor_pago'], 2, ',', '.') ?></li>
        <?php endwhile; ?>
    </ul>
    <p><strong>Operador:</strong> <?= htmlspecialchars($venda['operador']) ?></p>

    <hr>

    <h6>Itens da Venda:</h6>
    <span>| Produto | Qtd | Unit | Total |</span>
    <?php
    mysqli_data_seek($itens, 0); // Garante que o ponteiro volte ao início
    while ($item = $itens->fetch_assoc()):
        $qtd = rtrim(rtrim(number_format((float)$item['quantidade'], 3, ',', '.'), '0'), ',');
        $unit = number_format($item['preco'], 2, ',', '.');
        $total = number_format($item['preco'] * $item['quantidade'], 2, ',', '.');
    ?>
        <div class="linha-produto">
            <strong><?= htmlspecialchars(mb_strimwidth($item['nome'], 0, 20, '...')) ?> (<?= $item['unidade_medida'] ?>)</strong><br>
            <span class="d-flex justify-content-end"><?= $qtd ?> (Qtd) <?= $unit ?> (<?= $item['unidade_medida'] ?>) R$ <?= $total ?></span>
        </div>
    <?php endwhile; ?>

    <p class="d-flex justify-content-end total"><strong>TOTAL: R$ <?= number_format($venda['total'], 2, ',', '.') ?></strong></p>

    <div class="text-center mt-3 d-print-none">
        <a href="registrar_venda.php" class="btn btn-primary">Nova Venda</a>
        <a href="index.php" class="btn btn-secondary">← Voltar ao Painel</a>
        <button onclick="window.print()" class="btn btn-secondary">Imprimir Comprovante</button>
    </div>
</div>

</body>
</html>
