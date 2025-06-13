<?php
include 'verifica_login.php';
include 'config.php';

// Verifica se é administrador
if ($_SESSION['tipo'] !== 'admin') {
    header('Location: index.php');
    exit();
}

// Cadastro ou Edição
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = $_POST['usuario'];
    $tipo = $_POST['tipo'];

    if (isset($_POST['id'])) { // Edição
        $id = intval($_POST['id']);
        $stmt = $conn->prepare("UPDATE operadores SET usuario = ?, tipo = ? WHERE id = ?");
        $stmt->bind_param("ssi", $usuario, $tipo, $id);
    } else { // Cadastro
        $senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO operadores (usuario, senha, tipo) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $usuario, $senha, $tipo);
    }

    $stmt->execute();
    $stmt->close();
    header("Location: usuarios.php");
    exit();
}

// Exclusão
if (isset($_GET['excluir'])) {
    $id = intval($_GET['excluir']);
    $conn->query("DELETE FROM operadores WHERE id = $id");
    header("Location: usuarios.php");
    exit();
}

// Carregar dados do usuário para edição
$editar = null;
if (isset($_GET['editar'])) {
    $id = intval($_GET['editar']);
    $res = $conn->query("SELECT * FROM operadores WHERE id = $id");
    $editar = $res->fetch_assoc();
}

// Listagem
$result = $conn->query("SELECT * FROM operadores");
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Gerenciar Usuários</title>
    <link rel="stylesheet" href="assets/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-4">
    <h2><?= $editar ? 'Editar Usuário' : 'Cadastrar Novo Usuário' ?></h2>

    <form method="post" class="mb-4">
        <?php if ($editar): ?>
            <input type="hidden" name="id" value="<?= $editar['id'] ?>">
        <?php endif; ?>
        <div class="mb-2">
            <label>Usuário:</label>
            <input type="text" name="usuario" class="form-control" required value="<?= $editar['usuario'] ?? '' ?>">
        </div>
        <?php if (!$editar): ?>
        <div class="mb-2">
            <label>Senha:</label>
            <input type="password" name="senha" class="form-control" required>
        </div>
        <?php endif; ?>
        <div class="mb-2">
            <label>Tipo de Acesso:</label>
            <select name="tipo" class="form-control">
                <option value="vendedor" <?= ($editar['tipo'] ?? '') === 'vendedor' ? 'selected' : '' ?>>Somente Vendas</option>
                <option value="admin" <?= ($editar['tipo'] ?? '') === 'admin' ? 'selected' : '' ?>>Administrador</option>
            </select>
        </div>
        <button class="btn btn-primary"><?= $editar ? 'Atualizar' : 'Cadastrar' ?></button>
        <a href="usuarios.php" class="btn btn-secondary">Cancelar</a>
    </form>

    <h4>Usuários Cadastrados</h4>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Usuário</th>
                <th>Tipo</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['usuario']) ?></td>
                <td><?= $row['tipo'] ?></td>
                <td>
                    <a href="?editar=<?= $row['id'] ?>" class="btn btn-sm btn-warning">Editar</a>
                    <a href="?excluir=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Excluir este usuário?')">Excluir</a>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>

    <a href="index.php" class="btn btn-secondary">← Voltar</a>
</body>
</html>
