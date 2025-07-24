<?php
include 'verifica_login.php';
include 'config.php';

$operador_id = $_SESSION['usuario'];

// Verifica se há um caixa aberto para esse operador
$sql = "SELECT * FROM caixas WHERE operador_id = ? AND data_fechamento IS NULL ORDER BY data_abertura DESC LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $operador_id);
$stmt->execute();
$result = $stmt->get_result();
$caixa = $result->fetch_assoc();

if (!$caixa) {
    echo "Nenhum caixa aberto encontrado para este operador.";
    exit();
}

$caixa_id = $caixa['id'];

// Calcula total de vendas associadas a esse caixa
$sqlTotal = "SELECT SUM(total_liquido) as total_caixa FROM vendas WHERE caixa_id = ?";
$stmtTotal = $conn->prepare($sqlTotal);
$stmtTotal->bind_param("i", $caixa_id);
$stmtTotal->execute();
$resultTotal = $stmtTotal->get_result();
$rowTotal = $resultTotal->fetch_assoc();
$total_caixa = $rowTotal['total_caixa'] ?? 0;

// Atualiza a tabela caixas com o valor final e data de fechamento
$sqlUpdate = "UPDATE caixas SET data_fechamento = NOW(), valor_fechamento = ? WHERE id = ?";
$stmtUpdate = $conn->prepare($sqlUpdate);
$stmtUpdate->bind_param("di", $total_caixa, $caixa_id);

if ($stmtUpdate->execute()) {
    echo "Caixa fechado com sucesso. Total no caixa: R$ " . number_format($total_caixa, 2, ',', '.');
} else {
    echo "Erro ao fechar o caixa: " . $stmtUpdate->error;
}
?>
