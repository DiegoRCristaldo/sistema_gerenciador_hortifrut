<?php
$dados = include('dados.php');

$servidor = $dados['servidor'];
$usuario = $dados['usuario'];
$senha = $dados['senha'];
$banco = $dados['banco'];
$cnpjQueroFruta = $dados['cnpj'];
$configJsonCaminho = $dados['configJson'];
$certificadoPfx = $dados['certificadoPfx'];
$senhaPfx = $dados['senhaPfx'];
$razaoSocial = $dados['razaoSocial'];

$conn = new mysqli("$servidor", "$usuario", "$senha", "$banco");
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("Erro de conexão: " . $conn->connect_error);
}

// Ajustar timezone no MySQL
$conn->query("SET time_zone = '-03:00'");
?>