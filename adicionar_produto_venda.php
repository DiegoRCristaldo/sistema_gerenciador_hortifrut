<?php
header('Content-Type: application/json');

try {
    // Conexão com o banco
    $pdo = new PDO('mysql:host=localhost;dbname=sistema_caixa', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $dados = json_decode(file_get_contents('php://input'), true);
    $produtoId = $dados['id'] ?? null;
    $quantidade = $dados['quantidade'] ?? 1;  // se quiser passar quantidade do JS

    if (!$produtoId) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'ID do produto não informado.']);
        exit;
    }

    // Defina a venda atual (ex.: via sessão ou última aberta)
    $vendaId = 1;  // ajustar conforme sua lógica

    // Valida se o produto existe
    $stmt = $pdo->prepare("SELECT id, nome, preco FROM produtos WHERE id = :id");
    $stmt->execute(['id' => $produtoId]);
    $produto = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$produto) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Produto não encontrado.']);
        exit;
    }

    // Insere o item na venda
    $stmt = $pdo->prepare("
        INSERT INTO itens_venda (venda_id, produto_id, quantidade, preco_unitario) 
        VALUES (:venda_id, :produto_id, :quantidade, :preco)
    ");
    $stmt->execute([
        'venda_id' => $vendaId,
        'produto_id' => $produtoId,
        'quantidade' => $quantidade,
        'preco' => $produto['preco']
    ]);

    echo json_encode(['sucesso' => true]);

} catch (Exception $e) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro: ' . $e->getMessage()]);
}
?>
