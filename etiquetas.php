<?php
require 'vendor/autoload.php'; // Picqer Barcode Generator

use Picqer\Barcode\BarcodeGeneratorPNG;

// Conexão com o banco de dados
include 'config.php';

// Busca os produtos
if (!isset($_GET['id'])) {
    die("Produto não especificado.");
}

$id = intval($_GET['id']);
$stmt = $conn->prepare("SELECT * FROM produtos WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Produto não encontrado.");
}

$produto = $result->fetch_assoc();

function gerarEAN13($numero_base) {
    // Preenche com zeros à esquerda até ter 12 dígitos
    $numero = str_pad($numero_base, 12, '0', STR_PAD_LEFT);

    // Calcula o dígito verificador (13º dígito)
    $soma = 0;
    for ($i = 0; $i < 12; $i++) {
        $soma += (int)$numero[$i] * ($i % 2 === 0 ? 1 : 3);
    }
    $resto = $soma % 10;
    $digito_verificador = ($resto === 0) ? 0 : (10 - $resto);

    return $numero . $digito_verificador;
}

// Se não tiver código de barras, gera e salva no banco
if (empty($produto['codigo_barras'])) {
    $codigo_gerado = gerarEAN13($produto['id']);

    $update = $conn->prepare("UPDATE produtos SET codigo_barras = ? WHERE id = ?");
    $update->bind_param("si", $codigo_gerado, $produto['id']);
    $update->execute();

    // Atualiza o array do produto
    $produto['codigo_barras'] = $codigo_gerado;
}

$generator = new BarcodeGeneratorPNG();

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="assets/etiquetas.css">
    <title>Etiquetas de Produtos</title>
</head>
<body>
    <div class="etiqueta">
        <p><strong><?= htmlspecialchars($produto['nome']) ?></strong></p>
        <p>Preço: R$ <?= number_format($produto['preco'], 2, ',', '.') ?></p>
        <?php
        $codigo = !empty($produto['codigo_barras']) 
            ? $produto['codigo_barras'] 
            : gerarEAN13($produto['id']);
        ?>

        <p class="codigo">Código: <?= htmlspecialchars($codigo) ?></p>
        <img src="data:image/png;base64,<?= base64_encode($generator->getBarcode($codigo, $generator::TYPE_EAN_13)) ?>" alt="Código de Barras">

    </div>
</body>
</html>
