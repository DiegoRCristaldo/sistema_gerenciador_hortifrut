<?php
require 'vendor/autoload.php'; // Dompdf
include 'verifica_login.php';
include 'config.php';

use Dompdf\Dompdf;

$filtro = $_GET['filtro'] ?? 'diario';
$dataInicio = $_GET['data_inicio'] ?? date('Y-m-d');
$dataFim = $_GET['data_fim'] ?? date('Y-m-d');

$query = "SELECT v.id, v.data, v.total, v.forma_pagamento, o.usuario AS operador 
          FROM vendas v
          LEFT JOIN operadores o ON v.operador_id = o.id
          WHERE DATE(v.data) BETWEEN '$dataInicio' AND '$dataFim'
          ORDER BY v.data DESC";

$resultado = $conn->query($query);

$html = "<h2>Relatório de Vendas</h2><table border='1' cellpadding='5'><tr><th>ID</th><th>Data</th><th>Total</th><th>Forma</th><th>Operador</th></tr>";

$totalGeral = 0;

while ($row = $resultado->fetch_assoc()) {
    $totalGeral += $row['total'];
    $html .= "<tr>
                <td>{$row['id']}</td>
                <td>".date('d/m/Y H:i', strtotime($row['data']))."</td>
                <td>R$ ".number_format($row['total'], 2, ',', '.')."</td>
                <td>".ucfirst($row['forma_pagamento'])."</td>
                <td>".htmlspecialchars($row['operador'] ?? '---')."</td>
              </tr>";
}

$html .= "<tr><td colspan='2'>Total Geral</td><td colspan='3'>R$ ".number_format($totalGeral, 2, ',', '.')."</td></tr></table>";

$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream("relatorio_vendas.pdf");
?>
