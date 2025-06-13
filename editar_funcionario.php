<?php
include 'config.php';

if (!isset($_GET['id'])) {
    header("Location: funcionarios.php");
    exit;
}

$id = $_GET['id'];

// Buscar funcionário
$stmt = $conn->prepare("SELECT * FROM funcionarios WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$funcionario = $result->fetch_assoc();

if (!$funcionario) {
    echo "Funcionário não encontrado!";
    exit;
}

// Atualizar funcionário
if (isset($_POST['atualizar'])) {
    $nome = $_POST['nome'];
    $data_admissao = $_POST['data_admissao'];
    $data_demissao = $_POST['data_demissao'];
    $cargo = $_POST['cargo'];
    $salario = $_POST['salario'];

    $stmt = $conn->prepare("UPDATE funcionarios SET nome=?, data_de_admissao=?, data_de_demissao=?, cargo=?, salario=? WHERE id=?");
    $stmt->bind_param("ssssdi", $nome, $data_admissao, $data_demissao, $cargo, $salario, $id);
    $stmt->execute();

    header("Location: funcionarios.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Editar Funcionário</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-5">
    <h2 class="mb-4">Editar Funcionário</h2>
    <a href="funcionarios.php" class="btn btn-secondary mb-3">← Voltar ao Painel</a>

    <div class="card">
        <div class="card-body">
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Nome</label>
                    <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($funcionario['nome']) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Data de Admissão</label>
                    <input type="date" name="data_admissao" class="form-control" value="<?= htmlspecialchars($funcionario['data_de_admissao']) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Data de Demissão</label>
                    <input type="date" name="data_demissao" class="form-control" value="<?= htmlspecialchars($funcionario['data_de_demissao']) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Cargo</label>
                    <input type="text" name="cargo" class="form-control" list="cargos" value="<?= htmlspecialchars($funcionario['cargo']) ?>" required>
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
                    <input type="number" step="0.01" name="salario" class="form-control" value="<?= htmlspecialchars($funcionario['salario']) ?>" required>
                </div>
                <button type="submit" name="atualizar" class="btn btn-primary">Atualizar</button>
                <a href="funcionarios.php" class="btn btn-secondary">Cancelar</a>
            </form>
        </div>
    </div>
</div>

</body>
</html>
