<?php
include 'config.php';
require_once 'vendor/autoload.php';

use Picqer\Barcode\BarcodeGeneratorPNG;
$generator = new BarcodeGeneratorPNG();

$etiquetas = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['produtos'])) {
    foreach ($_POST['produtos'] as $id_produto) {
        $quantidade = max(1, (int)($_POST['quantidade'][$id_produto] ?? 1));

        $stmt = $conn->prepare("SELECT * FROM produtos WHERE id = ?");
        $stmt->bind_param("i", $id_produto);
        $stmt->execute();
        $produto = $stmt->get_result()->fetch_assoc();

        // Gerar código de barras se estiver vazio (sua função)
        if (empty($produto['codigo_barras'])) {
            function gerarEAN13() {
                $codigo = str_pad(mt_rand(100000000000, 999999999999), 12, '0', STR_PAD_LEFT);
                $soma = 0;
                for ($i = 0; $i < 12; $i++) {
                    $soma += ($i % 2 === 0 ? 1 : 3) * (int)$codigo[$i];
                }
                $digito = (10 - ($soma % 10)) % 10;
                return $codigo . $digito;
            }

            do {
                $novoCodigo = gerarEAN13();
                $check = $conn->prepare("SELECT id FROM produtos WHERE codigo_barras = ?");
                $check->bind_param("s", $novoCodigo);
                $check->execute();
                $check->store_result();
            } while ($check->num_rows > 0);

            $update = $conn->prepare("UPDATE produtos SET codigo_barras = ? WHERE id = ?");
            $update->bind_param("si", $novoCodigo, $id_produto);
            $update->execute();

            $produto['codigo_barras'] = $novoCodigo;
        }

        for ($i = 0; $i < $quantidade; $i++) {
            $etiquetas[] = $produto;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Imprimir Etiquetas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/etiqueta.css">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="bg-light">

<div class="no-print">
    <h3>Imprimir Etiquetas</h3>
    <a href="index.php" class="btn btn-secondary">← Voltar ao Painel</a>

    <form method="POST" id="form-etiquetas" class="no-print mb-4">
        <div class="input-group mb-3">
            <input type="text" class="form-control" id="busca-produto" placeholder="Digite o nome do produto">
            <button class="btn btn-primary" type="button" id="btn-pesquisar">Pesquisar</button>
        </div>

        <!-- Área onde resultados da busca aparecerão -->
        <div id="resultado-pesquisa" class="mb-3"></div>

        <!-- Lista dos produtos que o usuário escolheu para gerar etiquetas -->
        <div id="lista-produtos"></div>

        <button class="btn btn-success mt-2" type="submit">Gerar Etiquetas</button>
        <?php if ($etiquetas): ?>
            <button class="btn btn-secondary mt-2 ms-2" type="button" onclick="window.print()">Imprimir</button>
        <?php endif; ?>
    </form>
</div>

<?php if ($etiquetas): ?>
    <div class="sheet mt-4">
        <?php foreach ($etiquetas as $etiqueta): ?>
            <div class="etiqueta">
                <strong><?= htmlspecialchars(mb_strimwidth($etiqueta['nome'], 0, 35, '...')) ?></strong>
                <small><?= number_format($etiqueta['preco'], 2, ',', '.') ?> R$ (<?= $etiqueta['unidade_medida'] ?>)</small><br>
                <img src="data:image/png;base64,<?= base64_encode($generator->getBarcode($etiqueta['codigo_barras'], $generator::TYPE_EAN_13)) ?>" alt="Código de barras"><br>
                <small><?= $etiqueta['codigo_barras'] ?></small>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
let produtosAdicionados = {}; // Objeto para evitar duplicações e controlar quantidade

// Função para mostrar produtos encontrados
function mostrarResultados(produtos) {
    const resultado = document.getElementById('resultado-pesquisa');
    resultado.innerHTML = '';

    if (produtos.length === 0) {
        resultado.innerHTML = '<div class="alert alert-warning">Nenhum produto encontrado!</div>';
        return;
    }

    produtos.forEach(produto => {
        const div = document.createElement('div');
        div.className = 'd-flex justify-content-between align-items-center border p-2 mb-1 rounded';
        div.innerHTML = `
            <span>${produto.nome} (R$ ${parseFloat(produto.preco).toFixed(2)} - ${produto.unidade_medida})</span>
            <button class="btn btn-sm btn-success" type="button" onclick="adicionarProduto(${produto.id}, '${produto.nome}', ${produto.preco}, '${produto.unidade_medida}')">Adicionar</button>
        `;
        resultado.appendChild(div);
    });
}

// Função para buscar produtos via AJAX
function buscarProduto() {
    const nome = document.getElementById('busca-produto').value.trim();
    if (!nome) return;

    fetch('buscar_produto.php?nome=' + encodeURIComponent(nome))
        .then(res => res.json())
        .then(data => {
            if (data.error) {
                alert('Erro: ' + data.error);
                return;
            }
            mostrarResultados(data);
        })
        .catch(err => alert('Erro na busca: ' + err));
}

// Função para adicionar produto selecionado na lista para impressão
function adicionarProduto(id, nome, preco, unidade) {
    if (produtosAdicionados[id]) {
        // Incrementa quantidade se já existe
        produtosAdicionados[id].quantidade++;
        atualizarQuantidade(id);
        return;
    }

    produtosAdicionados[id] = { nome, preco, unidade, quantidade: 1 };

    // Criar elemento na lista de produtos adicionados
    const lista = document.getElementById('lista-produtos');

    const div = document.createElement('div');
    div.className = 'input-group mb-2';
    div.id = 'produto-' + id;

    div.innerHTML = `
        <span class="input-group-text flex-grow-1">${nome} (R$ ${preco.toFixed(2)} - ${unidade})</span>
        <input type="number" min="1" value="1" class="form-control quantidade-produto" name="quantidade[${id}]" aria-label="Quantidade para ${nome}" onchange="atualizarQuantidade(${id})" />
        <input type="hidden" name="produtos[]" value="${id}" />
        <button class="btn btn-danger" type="button" onclick="removerProduto(${id})">&times;</button>
    `;

    lista.appendChild(div);
}

// Atualiza o objeto e o input quantidade
function atualizarQuantidade(id) {
    const input = document.querySelector(`#produto-${id} input.quantidade-produto`);
    const val = parseInt(input.value);
    if (val < 1 || isNaN(val)) {
        input.value = 1;
        produtosAdicionados[id].quantidade = 1;
    } else {
        produtosAdicionados[id].quantidade = val;
    }
}

// Remove produto da lista e do objeto
function removerProduto(id) {
    delete produtosAdicionados[id];
    const div = document.getElementById('produto-' + id);
    if (div) div.remove();
}

// Eventos
document.getElementById('btn-pesquisar').addEventListener('click', buscarProduto);

document.getElementById('busca-produto').addEventListener('keydown', function(event) {
    if (event.key === 'Enter') {
        event.preventDefault();
        buscarProduto();
    }
});
</script>

</body>
</html>
