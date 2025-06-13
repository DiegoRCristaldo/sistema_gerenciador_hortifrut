<?php
$dados = include('dados.php');

$servidor = $dados['servidor'];
$usuario = $dados['usuario'];
$senha = $dados['senha'];
$banco = $dados['banco'];

$conn = new mysqli("$servidor", "$usuario", "$senha", "$banco");
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("Erro de conexão: " . $conn->connect_error);
}

?>
<?php