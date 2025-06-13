<?php
include 'verifica_login.php';
include 'config.php';

$filtro = $_GET['filtro'] ?? 'diario';
$dataInicio = $_GET['data_inicio'] ?? null;
$dataFim = $_GET['data_fim'] ?? null;

if (!$dataInicio || !$dataFim) {
    switch ($filtro) {
        case 'semanal':
            $dataInicio = date('Y-m-d', strtotime('-7 days'));
            break;
        case 'mensal':
            $dataInicio = date('Y-m-d', strtotime('-30 days'));
            break;
        default:
            $dataInicio = date('Y-m-d');
    }
    $dataFim = date('Y-m-d');
}

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$query = "SELECT v.id, v.data, v.total, v.forma_pagamento, o.usuario AS operador 
          FROM vendas v
          LEFT JOIN operadores o ON v.operador_id = o.id
          WHERE DATE(v.data) BETWEEN '$dataInicio' AND '$dataFim'
          ORDER BY v.data DESC
          LIMIT $offset, $limit";

$resultado = $conn->query($query);

$countQuery = "SELECT COUNT(*) as total FROM vendas WHERE DATE(data) BETWEEN '$dataInicio' AND '$dataFim'";
$countResult = $conn->query($countQuery);
$totalRegistros = $countResult->fetch_assoc()['total'];
$totalPaginas = ceil($totalRegistros / $limit);

if (!$resultado) {
    echo "<div class='alert alert-danger'>Erro na consulta: " . $conn->error . "</div>";
}

$totalGeral = 0;
$resumo = [];
$labels = [];
$valores = [];

$consultaResumo = "SELECT forma_pagamento, SUM(total) as total
                   FROM vendas
                   WHERE DATE(data) BETWEEN '$dataInicio' AND '$dataFim'
                   GROUP BY forma_pagamento";
$resumoResult = $conn->query($consultaResumo);
while($r = $resumoResult->fetch_assoc()){
    $resumo[$r['forma_pagamento']] = $r['total'];
}

$consultaGrafico = "SELECT DATE(data) as dia, SUM(total) as total 
                    FROM vendas
                    WHERE DATE(data) BETWEEN '$dataInicio' AND '$dataFim'
                    GROUP BY dia
                    ORDER BY dia";
$graficoResult = $conn->query($consultaGrafico);
while($g = $graficoResult->fetch_assoc()){
    $labels[] = date('d/m', strtotime($g['dia']));
    $valores[] = $g['total'];
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Vendas</title>
    <link rel="stylesheet" href="assets/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body class="bg-light">

<div class="container py-4">
    <h2 class="mb-4">Relatório de Vendas</h2>

    <div class="no-print mb-3">
        <a href="index.php" class="btn btn-secondary">← Voltar ao Painel</a>
        <a href="?filtro=diario" class="btn btn-outline-primary <?= $filtro === 'diario' ? 'active' : '' ?>">Diário</a>
        <a href="?filtro=semanal" class="btn btn-outline-primary <?= $filtro === 'semanal' ? 'active' : '' ?>">Semanal</a>
        <a href="?filtro=mensal" class="btn btn-outline-primary <?= $filtro === 'mensal' ? 'active' : '' ?>">Mensal</a>
        <a href="relatorio_pdf.php?filtro=<?= $filtro ?>&data_inicio=<?= $dataInicio ?>&data_fim=<?= $dataFim ?>" class="btn btn-danger">📄 Exportar PDF</a>
        <button onclick="window.print()" class="btn btn-success float-end">🖨️ Imprimir</button>
    </div>

    <form method="get" class="row g-2 no-print mb-3">
        <input type="hidden" name="filtro" value="<?= $filtro ?>">
        <div class="col-auto">
            <label>De:</label>
            <input type="date" name="data_inicio" value="<?= $dataInicio ?>" class="form-control">
        </div>
        <div class="col-auto">
            <label>Até:</label>
            <input type="date" name="data_fim" value="<?= $dataFim ?>" class="form-control">
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary mt-4">Filtrar</button>
        </div>
    </form>

    <div class="alert alert-info">
        <h5>Resumo por Forma de Pagamento:</h5>
        <ul>
            <?php foreach($resumo as $forma => $total): ?>
                <li><?= ucfirst($forma) ?>: R$ <?= number_format($total, 2, ',', '.') ?></li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered bg-white">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Data</th>
                    <th>Total</th>
                    <th>Forma de Pagamento</th>
                    <th>Operador</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($resultado && $resultado->num_rows > 0): ?>
                    <?php while ($row = $resultado->fetch_assoc()): ?>
                        <?php $totalGeral += $row['total']; ?>
                        <tr>
                            <td><?= $row['id'] ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($row['data'])) ?></td>
                            <td>R$ <?= number_format($row['total'], 2, ',', '.') ?></td>
                            <td><?= ucfirst($row['forma_pagamento']) ?></td>
                            <td><?= htmlspecialchars($row['operador'] ?? '---') ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="text-center">Nenhuma venda encontrada para o período selecionado.</td></tr>
                <?php endif; ?>
            </tbody>
            <tfoot class="table-light">
                <tr>
                    <th colspan="2">Total Geral</th>
                    <th colspan="3">R$ <?= number_format($totalGeral, 2, ',', '.') ?></th>
                </tr>
            </tfoot>
        </table>
    </div>

    <nav class="no-print">
        <ul class="pagination justify-content-center">
            <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                    <a class="page-link" href="?filtro=<?= $filtro ?>&data_inicio=<?= $dataInicio ?>&data_fim=<?= $dataFim ?>&page=<?= $i ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>

    <h4 class="mt-5">Gráfico de Vendas</h4>
    <canvas id="graficoVendas"></canvas>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx = document.getElementById('graficoVendas').getContext('2d');
    const graficoVendas = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($labels) ?>,
            datasets: [{
                label: 'Total de Vendas',
                data: <?= json_encode($valores) ?>,
                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            scales: { y: { beginAtZero: true } }
        }
    });
</script>

</body>
</html>
