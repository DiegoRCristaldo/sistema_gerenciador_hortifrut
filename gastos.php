<?php
include 'verifica_login.php';
include 'config.php';

// Só permite acesso se for administrador
if ($_SESSION['tipo'] !== 'admin') {
    header('Location: registrar_venda.php');
    exit();
}

// Definindo filtro
$filtro = $_GET['filtro'] ?? 'todos';
$where = '';

switch ($filtro) {
    case 'diario':
        $where = "WHERE data = CURDATE()";
        break;
    case 'semanal':
        $where = "WHERE YEARWEEK(data, 1) = YEARWEEK(CURDATE(), 1)";
        break;
    case 'mensal':
        $where = "WHERE MONTH(data) = MONTH(CURDATE()) AND YEAR(data) = YEAR(CURDATE())";
        break;
}

// Deletar gasto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['excluir_id'])) {
    $id = intval($_POST['excluir_id']);
    $stmt = $conn->prepare("DELETE FROM gastos WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    header("Location: gastos.php?filtro=$filtro");
    exit;
}

// Inserir gasto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['categoria'])) {
    $categoria = $conn->real_escape_string($_POST['categoria']);
    $valor = floatval($_POST['valor']);
    $data = $_POST['data'];
    $descricao = $conn->real_escape_string($_POST['descricao']);

    $conn->query("INSERT INTO gastos (categoria, valor, data, descricao) VALUES ('$categoria', $valor, '$data', '$descricao')");
    header("Location: gastos.php?filtro=$filtro");
    exit;
}

// Buscar gastos com filtro
$resultado = $conn->query("SELECT * FROM gastos $where ORDER BY data DESC");

require 'lista_links_principal.php';
require 'view/header.php';
?>

<div class="container py-5">
    <h2 class="mb-4">Controle de Gastos da Empresa</h2>
    <a href="index.php" class="btn btn-secondary mb-4">← Voltar ao Painel</a>

    <!-- Filtro de Período -->
    <form method="get" class="mb-4">
        <div class="row g-2 align-items-center">
            <div class="col-auto">
                <label class="form-label fw-bold mb-0">Filtrar por:</label>
            </div>
            <div class="col-auto">
                <select name="filtro" class="form-select" onchange="this.form.submit()">
                    <option value="todos" <?= $filtro === 'todos' ? 'selected' : '' ?>>Todos</option>
                    <option value="diario" <?= $filtro === 'diario' ? 'selected' : '' ?>>Diário</option>
                    <option value="semanal" <?= $filtro === 'semanal' ? 'selected' : '' ?>>Semanal</option>
                    <option value="mensal" <?= $filtro === 'mensal' ? 'selected' : '' ?>>Mensal</option>
                </select>
            </div>
        </div>
    </form>

    <!-- Formulário -->
    <form method="post" class="card p-4 mb-5 shadow-sm bg-white">
        <div class="row mb-3">
            <div class="col-md-4">
                <label class="form-label">Categoria</label>
                <select name="categoria" class="form-select" required>
                    <option value="">Selecione</option>
                    <option>Funcionários</option>
                    <option>Aluguel</option>
                    <option>Aquisição de Mercadorias</option>
                    <option>Água</option>
                    <option>Luz</option>
                    <option>Internet</option>
                    <option>Propaganda</option>
                    <option>Segurança</option>
                    <option>Outros</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Valor</label>
                <input type="number" step="0.01" name="valor" class="form-control" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Data</label>
                <input type="date" name="data" class="form-control" required>
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label">Descrição</label>
            <textarea name="descricao" class="form-control" rows="2"></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Registrar Gasto</button>
    </form>

    <!-- Lista de gastos -->
    <h4 class="mb-3">Gastos Registrados <?= $filtro !== 'todos' ? '(' . ucfirst($filtro) . ')' : '' ?></h4>
    <table class="table table-bordered bg-white table-hover">
        <thead class="table-dark">
            <tr>
                <th>Data</th>
                <th>Categoria</th>
                <th>Valor</th>
                <th>Descrição</th>
                <th>Ação</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $total = 0;
        if ($resultado && $resultado->num_rows > 0):
            while ($row = $resultado->fetch_assoc()):
                $total += $row['valor'];
        ?>
            <tr>
                <td><?= date('d/m/Y', strtotime($row['data'])) ?></td>
                <td><?= htmlspecialchars($row['categoria']) ?></td>
                <td>R$ <?= number_format($row['valor'], 2, ',', '.') ?></td>
                <td><?= htmlspecialchars($row['descricao']) ?></td>
                <td>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Tem certeza que deseja excluir este gasto?');">
                        <input type="hidden" name="excluir_id" value="<?= $row['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger ms-2">Excluir</button>
                    </form>
                </td>
            </tr>
        <?php endwhile; ?>
            <tr class="fw-bold bg-light">
                <td colspan="2">Total</td>
                <td colspan="2">R$ <?= number_format($total, 2, ',', '.') ?></td>
            </tr>
        <?php else: ?>
            <tr><td colspan="4">Nenhum gasto encontrado.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    <div class="col-auto">
        <button type="button" onclick="window.print()" class="btn btn-outline-primary">🖨️ Imprimir</button>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
