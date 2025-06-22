<?php
header('Content-Type: application/json');
include 'config.php'; // conexão $conn com MySQLi

function calcularDigitoEAN13($codigo) {
    $soma = 0;
    for ($i = 0; $i < 12; $i++) {
        $digito = (int)$codigo[$i];
        $soma += ($i % 2 === 0) ? $digito : $digito * 3;
    }
    $resto = $soma % 10;
    return ($resto === 0) ? 0 : (10 - $resto);
}

if (!isset($_POST['id']) || empty($_POST['id'])) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'ID não informado']);
    exit;
}

$id = intval($_POST['id']);

// Verifica se o produto já tem código de barras
$sql = "SELECT codigo_barras FROM produtos WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$produto = $result->fetch_assoc();

if (!$produto) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Produto não encontrado']);
    exit;
}

if (!empty($produto['codigo_barras'])) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Este produto já possui um código de barras']);
    exit;
}

// Gera código com prefixo + aleatório + parte do ID, totalizando 12 dígitos antes do verificador
do {
    $prefixo = '789'; // padrão Brasil
    $randLength = 9 - strlen($id); // espaço restante para gerar número aleatório
    $parteAleatoria = str_pad(mt_rand(0, pow(10, $randLength) - 1), $randLength, '0', STR_PAD_LEFT);
    $codigoBase = $prefixo . $parteAleatoria . $id;

    $digitoVerificador = calcularDigitoEAN13($codigoBase);
    $codigoFinal = $codigoBase . $digitoVerificador;

    // Verifica se esse código já existe
    $check = $conn->prepare("SELECT id FROM produtos WHERE codigo_barras = ?");
    $check->bind_param("s", $codigoFinal);
    $check->execute();
    $check->store_result();
} while ($check->num_rows > 0);

// Atualiza o produto com o novo código
$update = $conn->prepare("UPDATE produtos SET codigo_barras = ? WHERE id = ?");
$update->bind_param("si", $codigoFinal, $id);
if ($update->execute()) {
    echo json_encode(['status' => 'sucesso', 'mensagem' => 'Código de barras gerado com sucesso: ' . $codigoFinal]);
} else {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao salvar o código de barras']);
}
