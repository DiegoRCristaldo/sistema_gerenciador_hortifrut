<?php
header('Content-Type: application/json');

try {
    $pdo = new PDO('mysql:host=localhost;dbname=sistema_caixa', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Defina a venda atual
    $vendaId = 1;  // ajustar conforme a sua lógica

    // Consulta os itens
    $stmt = $pdo->prepare("
        SELECT p.id, p.nome, iv.quantidade, iv.preco_unitario, 
               (iv.quantidade * iv.preco_unitario) AS total
        FROM itens_venda iv
        JOIN produtos p ON iv.produto_id = p.id
        WHERE iv.venda_id = :venda_id
    ");
    $stmt->execute(['venda_id' => $vendaId]);

    $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($itens);

} catch (Exception $e) {
    echo json_encode(['erro' => 'Erro ao listar o carrinho: ' . $e->getMessage()]);
}
?>
