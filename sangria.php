<?php
include 'verifica_login.php';
include 'config.php';
include 'funcoes_caixa.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $operador_id = $_SESSION['operador_id'] ?? $_SESSION['id'] ?? null;
    $valor = floatval($_POST['valor']);
    $descricao = $_POST['descricao'] ?? '';

    // Busca o caixa aberto
    $sql = "SELECT id FROM caixas WHERE operador_id = ? AND data_fechamento IS NULL ORDER BY data_abertura DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $operador_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $caixa = $result->fetch_assoc();

    if (!$caixa) {
        echo "Nenhum caixa aberto para registrar a sangria.";
        exit();
    }

    $caixa_id = $caixa['id'];

    // Insere sangria
    $sqlInsert = "INSERT INTO sangrias (caixa_id, operador_id, valor, descricao, data_sangria) VALUES (?, ?, ?, ?, NOW())";
    $stmtInsert = $conn->prepare($sqlInsert);
    $stmtInsert->bind_param("iids", $caixa_id, $operador_id, $valor, $descricao);

    if ($stmtInsert->execute()) {
        echo "<script>alert('Sangria registrada com sucesso.');</script>";
    } else {
        echo "<script>alert('Erro ao registrar sangria: ');</script>" . $stmtInsert->error;
    }
}

$lista_links = [
    ['href' => 'registrar_venda.php','icone' => 'bi bi-arrow-left', 'texto' => 'Voltar para o caixa', 'exibir' => true, 'target' => ''],
    ['href' => 'fechar_caixa.php', 'icone' => 'bi bi-cash-coin', 'texto' => 'Fechar Caixa', 'exibir' => true, 'target' => ''],
    ['href' => 'logout.php', 'icone' => 'bi bi-box-arrow-right', 'texto' => 'Sair', 'exibir' => true, 'target' => '']
];
require "view/header.php";
?>

    <!-- Formulário HTML -->
    <div class="container mt-5">
        <h2>Registrar Sangria</h2>
        <form method="POST" class="mt-4">
            <label class="form-label">Valor da sangria:</label>
            <input type="number" name="valor" step="0.01" class="form-control" required autofocus>
            <br>
            <label class="form-label">Descrição (opcional):</label>
            <input type="text" name="descricao" class="form-control">
            <br>
            <button type="submit" class="btn btn-success">Registrar Sangria</button>
        </form>
    </div>    
</main>
</body>
</html>
