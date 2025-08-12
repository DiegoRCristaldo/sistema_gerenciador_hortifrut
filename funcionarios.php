<?php
include 'verifica_login.php';
include 'config.php';

// Adicionar funcionário
if (isset($_POST['adicionar'])) {
    $nome = $_POST['nome'];
    $data_admissao = $_POST['data_admissao'];
    $cargo = $_POST['cargo'];
    $salario = $_POST['salario'];

    $stmt = $conn->prepare("INSERT INTO funcionarios (nome, data_de_admissao, cargo, salario) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sssd", $nome, $data_admissao, $cargo, $salario);
    $stmt->execute();
    header("Location: funcionarios.php");
    exit;
}

// Excluir funcionário
if (isset($_GET['excluir'])) {
    $id = $_GET['excluir'];
    $conn->query("DELETE FROM funcionarios WHERE id = $id");
    header("Location: funcionarios.php");
    exit;
}

// Listar funcionários
$result = $conn->query("SELECT * FROM funcionarios");

require 'lista_links_principal.php';
require "view/header.php";
?>

<div class="container py-5">
    <h2 class="mb-4">Gerenciar Funcionários</h2>
    <a href="index.php" class="btn btn-secondary mb-3">← Voltar ao Painel</a>

    <!-- Formulário de Adição -->
    <div class="card mb-4">
        <div class="card-header">Adicionar Funcionário</div>
        <div class="card-body">
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Nome</label>
                    <input type="text" name="nome" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Data de Admissão</label>
                    <input type="date" name="data_admissao" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Cargo</label>
                    <input type="text" name="cargo" class="form-control" list="cargos" required>
                    <datalist id="cargos">
                        <option value="Gerente">
                        <option value="Analista">
                        <option value="Auxiliar">
                        <option value="Técnico">
                        <option value="Estagiário">
                    </datalist>
                </div>
                <div class="mb-3">
                    <label class="form-label">Salário</label>
                    <input type="number" step="0.01" name="salario" class="form-control" required>
                </div>
                <button type="submit" name="adicionar" class="btn btn-success">Adicionar</button>
            </form>
        </div>
    </div>

    <!-- Tabela de Funcionários -->
    <div class="card">
        <div class="card-header">Lista de Funcionários</div>
        <div class="card-body">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Nome</th>
                        <th>Admissão</th>
                        <th>Demissão</th>
                        <th>Cargo</th>
                        <th>Salário</th>
                        <th>Custo Total</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                <?php while($f = $result->fetch_assoc()): ?>
                    <?php
                        $salario = $f['salario'];
                        $inss_patronal = ($salario / 100) * 20;
                        $fgts = ($salario / 100) * 8;
                        $decimo_terceiro = $salario / 12;
                        $ferias = $salario / 3;
                        $custo_total = $salario + $inss_patronal + $fgts + $decimo_terceiro + $ferias;
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($f['nome']) ?></td>
                        <td><?= htmlspecialchars($f['data_de_admissao']) ?></td>
                        <td><?= htmlspecialchars($f['data_de_demissao'] ?? 'Ativo') ?></td>
                        <td><?= htmlspecialchars($f['cargo']) ?></td>
                        <td>R$ <?= number_format($salario, 2, ',', '.') ?></td>
                        <td>R$ <?= number_format($custo_total, 2, ',', '.') ?></td>
                        <td>
                            <a href="editar_funcionario.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-primary">Editar</a>
                            <a href="funcionarios.php?excluir=<?= $f['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Deseja realmente excluir este funcionário?')">Excluir</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</main>
</body>
</html>
