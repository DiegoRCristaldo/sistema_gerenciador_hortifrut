<?php
include 'verifica_login.php';
include 'config.php';
include 'funcoes_caixa.php';

$operador_id = $_SESSION['operador_id'] ?? $_SESSION['id'] ?? null;
if (!$operador_id) {
    $_SESSION['flash'] = 'Operador não identificado.';
    header("Location: registrar_venda.php");
    exit;
}

$caixa_id = getCaixaAberto($conn, $operador_id);
if (!$caixa_id) {
    $_SESSION['flash'] = 'Nenhum caixa aberto encontrado para este operador.';
    header("Location: registrar_venda.php");
    exit;
}

// soma vendas do caixa (coalesce para 0)
$sqlTotal = "SELECT COALESCE(SUM(total),0) as total_caixa FROM vendas WHERE caixa_id = ?";
$stmtTotal = $conn->prepare($sqlTotal);
$stmtTotal->bind_param("i", $caixa_id);
$stmtTotal->execute();
$total_caixa = $stmtTotal->get_result()->fetch_assoc()['total_caixa'] ?? 0;

// soma sangrias (se existir tabela sangrias)
$sqlSang = "SELECT COALESCE(SUM(valor),0) as total_sang FROM sangrias WHERE caixa_id = ?";
$stmtS = $conn->prepare($sqlSang);
$stmtS->bind_param("i", $caixa_id);
$stmtS->execute();
$total_sang = $stmtS->get_result()->fetch_assoc()['total_sang'] ?? 0;

// pegar valor_inicial do caixa
$stmt0 = $conn->prepare("SELECT valor_inicial FROM caixas WHERE id = ?");
$stmt0->bind_param("i", $caixa_id);
$stmt0->execute();
$valor_inicial = $stmt0->get_result()->fetch_assoc()['valor_inicial'] ?? 0.00;

$valor_fechamento = $valor_inicial + $total_caixa - $total_sang;

// atualiza fechamento
$sqlUpdate = "UPDATE caixas SET data_fechamento = NOW(), valor_fechamento = ? WHERE id = ?";
$stmtUpdate = $conn->prepare($sqlUpdate);
$stmtUpdate->bind_param("di", $valor_fechamento, $caixa_id);

if ($stmtUpdate->execute()) {
    // redireciona para relatório com o id do caixa fechado
    header("Location: relatorio_caixa.php?caixa_id=" . (int)$caixa_id);
    exit;
} else {
    $_SESSION['flash'] = 'Erro ao fechar o caixa: ' . $stmtUpdate->error;
    header("Location: registrar_venda.php");
    exit;
}
