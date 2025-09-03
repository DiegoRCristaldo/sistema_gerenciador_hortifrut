<?php
include 'verifica_login.php';
include 'config.php';

if (!isset($_GET['venda_id'])) {
    header('HTTP/1.0 400 Bad Request');
    echo 'ID da venda não especificado';
    exit;
}

$venda_id = intval($_GET['venda_id']);
$arquivo = __DIR__ . '/danfes/venda_' . $venda_id . '_danfe.pdf';

// Verifica se o arquivo existe
if (!file_exists($arquivo)) {
    header('HTTP/1.0 404 Not Found');
    echo 'Arquivo não encontrado';
    exit;
}

// Define se é para visualizar ou baixar
if (isset($_GET['download'])) {
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="venda_' . $venda_id . '_danfe.pdf"');
} else {
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="venda_' . $venda_id . '_danfe.pdf"');
}

header('Content-Length: ' . filesize($arquivo));
header('Cache-Control: must-revalidate');
header('Pragma: public');

// Lê e exibe o arquivo
readfile($arquivo);
exit;