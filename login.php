<?php
include 'config.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = $_POST['usuario'];
    $senha = $_POST['senha'];

    $result = $conn->query("SELECT * FROM operadores WHERE usuario = '$usuario'");
    $op = $result ? $result->fetch_assoc() : null;

    if ($op && password_verify($senha, $op['senha'])) {
        $_SESSION['usuario'] = $op['usuario'];
        $_SESSION['tipo'] = $op['tipo'];
        $_SESSION['id'] = $op['id'];
        $_SESSION['operador_id'] = $op['id'];
        header("Location: index.php");
        exit;
    } else {
        $erro = "Login inválido!";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <link rel="icon" href="assets/logo.png" type="image/png">
    <title>Login</title>
    <link rel="stylesheet" href="assets/login.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body>
    <div class="login-card">
        <h3 class="text-center mb-4">Login do Operador</h3>

        <?php if (isset($erro)) echo "<div class='alert alert-danger'>$erro</div>"; ?>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Usuário</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
                    <input type="text" name="usuario" class="form-control" placeholder="Digite seu usuário" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Senha</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                    <input type="password" name="senha" class="form-control" placeholder="Digite sua senha" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100 mt-3">Entrar</button>
        </form>
    </div>
</body>
</html>
