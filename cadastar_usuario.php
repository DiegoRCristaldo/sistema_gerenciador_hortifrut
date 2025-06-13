<?php
include 'verifica_login.php'; // Verifica se o usuário está logado
include 'config.php'; // Conexão com o banco

// Só permite acesso se for administrador
session_start();
if ($_SESSION['tipo'] !== 'admin') {
    header('Location: registrar_venda.php');
    exit();
}

// Processar formulário
$mensagem = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nome = $_POST['nome'];
    $usuario = $_POST['usuario'];
    $senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);
    $tipo = $_POST['tipo'];

    $stmt = $conn->prepare("INSERT INTO usuarios (nome, usuario, senha, tipo) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $nome, $usuario, $senha, $tipo);

    if ($stmt->execute()) {
        $mensagem = "Usuário cadastrado com sucesso!";
    } else {
        $mensagem = "Erro ao cadastrar usuário: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Cadastrar Novo Usuário</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <h2>Cadastrar Novo Usuário</h2>

    <?php if ($mensagem): ?>
        <div class="alert alert-info"><?= $mensagem ?></div>
    <?php endif; ?>

    <form method="POST" class="mt-4">
        <div class="mb-3">
            <label for="nome" class="form-label">Nome:</label>
            <input type="text" name="nome" id="nome" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="usuario" class="form-label">Usuário:</label>
            <input type="text" name="usuario" id="usuario" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="senha" class="form-label">Senha:</label>
            <input type="password" name="senha" id="senha" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="tipo" class="form-label">Tipo de Acesso:</label>
            <select name="tipo" id="tipo" class="form-select">
                <option value="restrito">Restrito (apenas registrar venda)</option>
                <option value="admin">Administrador (acesso total)</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Cadastrar</button>
        <a href="index.php" class="btn btn-secondary">Voltar</a>
    </form>
</div>
</body>
</html>
