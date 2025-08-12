<?php
include 'config.php';

function getPeriodoFiltro($periodo) {
    switch ($periodo) {
        case 'diario':
            return "DATE(data) = CURDATE()";
        case 'semanal':
            return "YEARWEEK(data, 1) = YEARWEEK(CURDATE(), 1)";
        case 'mensal':
            return "MONTH(data) = MONTH(CURDATE()) AND YEAR(data) = YEAR(CURDATE())";
        default:
            return "1=1";
    }
}

$periodo = $_GET['periodo'] ?? '';
$data_inicio = $_GET['data_inicio'] ?? '';
$data_fim = $_GET['data_fim'] ?? '';

if ($data_inicio && $data_fim) {
    $filtroSQL = "DATE(data) BETWEEN '$data_inicio' AND '$data_fim'";
} elseif ($periodo) {
    $filtroSQL = getPeriodoFiltro($periodo);
} else {
    // Padrão: exibe tudo
    $filtroSQL = "1=1";
}

// Vendas
$vendas = $conn->query("SELECT SUM(total) as total FROM vendas WHERE $filtroSQL");
if (!$vendas) {
    die("Erro na consulta de vendas: " . $conn->error);
}
$total_vendas = $vendas->fetch_assoc()['total'] ?? 0;

// Gastos
$gastos = $conn->query("SELECT categoria, SUM(valor) as total FROM gastos WHERE $filtroSQL GROUP BY categoria");
if (!$gastos) {
    die("Erro na consulta de gastos: " . $conn->error);
}
$valores_gastos = [];
$total_gastos = 0;
while ($row = $gastos->fetch_assoc()) {
    $valores_gastos[$row['categoria']] = $row['total'];
    $total_gastos += $row['total'];
}

// Lucro ou prejuízo
$lucro = $total_vendas - $total_gastos;
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Balanço da Empresa</title>
    <style>
        #containerGrafico {
            max-width: 600px;
            margin: 0 auto;
        }

        canvas {
            width: 100% !important;
            height: auto !important;
            max-height: 400px;
        }
    </style>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php 
        $lucros_7_dias = [];
        $datas_7_dias = [];

        for ($i = 6; $i >= 0; $i--) {
            $dia = date('Y-m-d', strtotime("-$i days"));
            $datas_7_dias[] = date('d/m', strtotime($dia));

            // Vendas do dia
            $v_result = $conn->query("SELECT SUM(total) as total FROM vendas WHERE DATE(data) = '$dia'");
            $v_total = $v_result ? ($v_result->fetch_assoc()['total'] ?? 0) : 0;

            // Gastos do dia
            $g_result = $conn->query("SELECT SUM(valor) as total FROM gastos WHERE DATE(data) = '$dia'");
            $g_total = $g_result ? ($g_result->fetch_assoc()['total'] ?? 0) : 0;

            $lucros_7_dias[] = $v_total - $g_total;
        }
?>
</head>
<body class="bg-light">

<div class="container py-4">
    <h2 class="mb-4">Balanço Financeiro</h2>

    <form class="mb-4" method="get">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="periodo" class="form-label">Período Rápido:</label>
                <select name="periodo" id="periodo" class="form-select" onchange="this.form.submit()">
                    <option value="">Selecionar</option>
                    <option value="diario" <?= $periodo === 'diario' ? 'selected' : '' ?>>Hoje</option>
                    <option value="semanal" <?= $periodo === 'semanal' ? 'selected' : '' ?>>Esta Semana</option>
                    <option value="mensal" <?= $periodo === 'mensal' ? 'selected' : '' ?>>Este Mês</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="data_inicio" class="form-label">Data Inicial:</label>
                <input type="date" id="data_inicio" name="data_inicio" value="<?= $_GET['data_inicio'] ?? '' ?>" class="form-control">
            </div>
            <div class="col-md-3">
                <label for="data_fim" class="form-label">Data Final:</label>
                <input type="date" id="data_fim" name="data_fim" value="<?= $_GET['data_fim'] ?? '' ?>" class="form-control">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100">Aplicar Filtro</button>
            </div>
        </div>
    </form>


    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card border-success">
                <div class="card-header bg-success text-white">Total em Vendas</div>
                <div class="card-body">
                    <h4>R$ <?= number_format($total_vendas, 2, ',', '.') ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-danger">
                <div class="card-header bg-danger text-white">Total em Gastos</div>
                <div class="card-body">
                    <h4>R$ <?= number_format($total_gastos, 2, ',', '.') ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-<?= $lucro >= 0 ? 'success' : 'danger' ?>">
                <div class="card-header bg-<?= $lucro >= 0 ? 'success' : 'danger' ?> text-white">
                    <?= $lucro >= 0 ? 'Lucro' : 'Prejuízo' ?>
                </div>
                <div class="card-body">
                    <h4>R$ <?= number_format($lucro, 2, ',', '.') ?></h4>
                </div>
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label">Selecionar Gráfico:</label>
            <select class="form-select w-auto" id="seletorGrafico">
                <option value="grafico">Comparativo</option>
                <option value="graficoPizza">Distribuição de Gastos</option>
                <option value="graficoLinha">Lucro ou Prejuízo - Últimos 7 dias</option>
            </select>
        </div>

        <div id="containerGrafico">
            <canvas id="grafico" style="display: block;"></canvas>
            <canvas id="graficoPizza" style="display: none;"></canvas>
            <canvas id="graficoLinha" style="display: none;"></canvas>
        </div>

    <button onclick="window.print()" class="btn btn-secondary mt-4">Imprimir</button>

    <?php
    // Dados para gráfico de linha: últimos 7 dias
    $lucrosDias = [];
    $datasDias = [];

    $datasDias = [];
    $lucrosDias = [];

    if ($data_inicio && $data_fim) {
        $start = new DateTime($data_inicio);
        $end = new DateTime($data_fim);
        while ($start <= $end) {
            $data = $start->format('Y-m-d');
            $label = $start->format('d/m');
            $datasDias[] = $label;

            $sqlV = $conn->query("SELECT SUM(total) as total FROM vendas WHERE DATE(data) = '$data'");
            $v = $sqlV ? $sqlV->fetch_assoc()['total'] ?? 0 : 0;

            $sqlG = $conn->query("SELECT SUM(valor) as total FROM gastos WHERE DATE(data) = '$data'");
            $g = $sqlG ? $sqlG->fetch_assoc()['total'] ?? 0 : 0;

            $lucrosDias[] = $v - $g;

            $start->modify('+1 day');
        }
    }
    ?>

</div>

<script>
document.getElementById('seletorGrafico').addEventListener('change', function () {
    const todos = ['grafico', 'graficoPizza', 'graficoLinha'];
    todos.forEach(id => {
        document.getElementById(id).style.display = (this.value === id) ? 'block' : 'none';
    });
});

// Gráfico Comparativo
new Chart(document.getElementById('grafico'), {
    type: 'bar',
    data: {
        labels: ['Vendas', ...<?= json_encode(array_keys($valores_gastos)) ?>],
        datasets: [{
            label: 'R$',
            data: [<?= $total_vendas ?>, ...<?= json_encode(array_values($valores_gastos)) ?>],
            backgroundColor: <?= json_encode(array_merge(['green'], array_fill(0, count($valores_gastos), 'red'))) ?>,
        }]
    },
    options: { scales: { y: { beginAtZero: true } } }
});

// Gráfico de Pizza
new Chart(document.getElementById('graficoPizza'), {
    type: 'pie',
    data: {
        labels: <?= json_encode(array_keys($valores_gastos)) ?>,
        datasets: [{
            label: 'Gastos',
            data: <?= json_encode(array_values($valores_gastos)) ?>,
            backgroundColor: ['#f44336', '#e91e63', '#9c27b0', '#2196f3', '#ff9800', '#4caf50'],
        }]
    }
});

// Gráfico de Linha (Lucro 7 dias)
new Chart(document.getElementById('graficoLinha'), {
    type: 'line',
    data: {
        labels: <?= json_encode($datas_7_dias) ?>,
        datasets: [{
            label: 'Lucro / Prejuízo',
            data: <?= json_encode($lucros_7_dias) ?>,
            fill: false,
            borderColor: 'blue',
            tension: 0.1
        }]
    },
    options: { scales: { y: { beginAtZero: true } } }
});
</script>

</body>
</html>