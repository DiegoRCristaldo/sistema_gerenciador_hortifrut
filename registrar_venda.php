<?php
include 'verifica_login.php';
include 'config.php';
include 'funcoes_caixa.php';

$operador_id = $_SESSION['usuario']; 
$caixa_id = getCaixaAberto($conn, $operador_id);

if (!$caixa_id) {
    echo "<script>alert('Nenhum caixa aberto foi encontrado. Abra um caixa antes de registrar vendas.'); window.location.href = 'abrir_caixa.php';</script>";
    exit;
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $quantidades = $_POST['quantidade'] ?? [];
    $desconto = isset($_POST['desconto']) ? (float)$_POST['desconto'] : 0;
    $forma = $conn->real_escape_string($_POST['pagamento']);
    $total = 0;

    // Verificação
    $totalItens = 0;
    foreach ($quantidades as $id => $qtd) {
        $qtd = (float)$qtd;
        if ($qtd > 0) {
            $totalItens += $qtd;
        }
    }

    if ($totalItens == 0) {
        echo "<script>alert('Não é possível finalizar a venda sem nenhum item!'); window.history.back();</script>";
        exit;
    }

    $stmt_produto = $conn->prepare("SELECT preco, unidade_medida FROM produtos WHERE id = ?");
    $stmt_item = $conn->prepare("INSERT INTO itens_venda (venda_id, produto_id, quantidade, unidade_medida) VALUES (?, ?, ?, ?)");

    foreach ($quantidades as $id => $qtd) {
        $id = (int)$id;
        $qtd = (float)$qtd;

        if ($qtd > 0) {
            $stmt_produto->bind_param("i", $id);
            $stmt_produto->execute();
            $result = $stmt_produto->get_result();
            $produto = $result->fetch_assoc();

            $preco = $produto['preco'];
            $total += $preco * $qtd;
        }
    }

    $total -= $desconto;
    if($total < 0) $total = 0;

    $stmt_venda = $conn->prepare("INSERT INTO vendas (total, forma_pagamento, desconto, caixa_id) VALUES (?, ?, ?, ?)");
    $stmt_venda->bind_param("dssi", $total, $forma, $desconto, $caixa_id);
    $stmt_venda->execute();
    $venda_id = $stmt_venda->insert_id;

    foreach ($quantidades as $id => $qtd) {
        $id = (int)$id;
        $qtd = (float)$qtd;

        if ($qtd > 0) {
            $stmt_produto->bind_param("i", $id);
            $stmt_produto->execute();
            $result = $stmt_produto->get_result();
            $produto = $result->fetch_assoc();

            $unidade = $produto['unidade_medida'];

            $stmt_item->bind_param("iids", $venda_id, $id, $qtd, $unidade);
            $stmt_item->execute();
        }
    }

    header("Location: comprovante.php?id_venda=$venda_id");
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Registrar Venda</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
    <?php
    require 'view/header.php';
    ?>
<div class="container mt-4">
    <h2>Registrar Venda</h2>

    <div class="d-flex flex-wrap gap-2 mb-3">
        <a href="fechar_caixa.php" class="btn btn-outline-warning d-flex align-items-center gap-2">
            <i class="bi bi-cash-coin"></i> Fechar Caixa
        </a>

        <a href="sangria.php" class="btn btn-outline-danger d-flex align-items-center gap-2">
            <i class="bi bi-arrow-down-circle"></i> Fazer Sangria
        </a>

        <a href="relatorio_caixa.php" class="btn btn-outline-info d-flex align-items-center gap-2">
            <i class="bi bi-clipboard-data"></i> Relatório do Caixa
        </a>

        <a href="logout.php" class="btn btn-outline-danger d-flex align-items-center gap-2">
            <i class="bi bi-box-arrow-right"></i> Sair
        </a>

        <a href="index.php" class="btn btn-outline-secondary d-flex align-items-center gap-2">
            <i class="bi bi-arrow-left"></i> Voltar ao Painel
        </a>

        <a href="<?php echo $_SERVER['REQUEST_URI']; ?>" target="_blank" class="btn btn-outline-primary d-flex align-items-center gap-2">
            <i class="bi bi-files"></i> Duplicar Página
        </a>

        <a href="produtos.php" class="btn btn-outline-success d-flex align-items-center gap-2">
            <i class="bi bi-pencil-square"></i> Editar Produtos
        </a>
    </div>

    <form method="POST">
        <div class="d-flex flex-row justify-content-between align-items-center border p-1">
            <div class="mb-4 p-1">
                <label class="form-label">Buscar por Código de Barras</label>
                <input type="text" id="codigo_barras" class="form-control" onkeypress="handleKeyPress(event)" autofocus autocomplete="off" placeholder="Escaneie ou digite o código de barras">
            </div>

            <div class="mb-4 p-1">
                <label class="form-label">Buscar Produto por nome</label>
                <input type="text" id="busca_produto" class="form-control" placeholder="Digite o nome ou descrição" onkeypress="handleKeyPressBuscaNome(event)">
            </div>

            <div class="mb-4 p-1">
                <label class="form-label">Buscar Produto por ID</label>
                <input type="number" id="busca_produto_id" class="form-control" placeholder="Digite o ID do produto" onkeypress="handleKeyPressBuscaId(event)">
            </div>
        </div>

        <div id="resultados-busca" class="mb-3"></div>

        <h5 class="d-flex align-items-center">Produtos na Venda</h5>
        <div id="lista-produtos" class="mb-3"></div>

        <div class="mb-3">
            <label class="form-label">Desconto Total (R$)</label>
            <input type="number" step="0.01" min="0" name="desconto" id="desconto" class="form-control" value="0" oninput="atualizarTotal()">
        </div>

        <div class="mt-3">
            <h3><strong>Total: R$ <span id="total">0.00</span></strong></h3>
        </div>

        <div class="mb-3 mt-3">
            <label class="form-label">Forma de Pagamento</label>
            <select name="pagamento" class="form-select" required>
                <option value="Cartão de Crédito">Cartão de Crédito</option>
                <option value="Cartão de Débito">Cartão de Débito</option>
                <option value="PIX">PIX</option>
                <option value="Dinheiro">Dinheiro</option>
            </select>
            <div class="mb-3 mt-3" id="valor-pago-div" style="display:none;">
                <label class="form-label">Valor Pago (R$)</label>
                <input type="number" step="0.01" min="0" id="valor-pago" class="form-control" placeholder="Digite o valor pago" oninput="calcularTroco()">
            </div>

            <div class="mb-3 mt-3" id="troco-div" style="display:none;">
                <strong>Troco: R$ <span id="troco">0.00</span></strong>
            </div>
        </div>

        <button type="button" class="btn btn-success" onclick="confirmarFinalizacao()">Finalizar Venda</button>
    </form>
</div>

<script>
let produtosAdicionados = {};  // id: preco

document.getElementById('codigo_barras').addEventListener('keydown', function(event) {
    if (event.key === "Enter") {
        event.preventDefault();
        const codigo = this.value.trim();
        if (codigo !== '') {
            adicionarProduto(codigo);
        }
    }
});


function handleKeyPressBuscaNome(event) {
    if (event.key === "Enter") {
        event.preventDefault();
        buscarProduto();
    }
}

function handleKeyPressBuscaId(event) {
    if (event.key === "Enter") {
        event.preventDefault();
        buscarProdutoPorId();
    }
}

function adicionarProduto(codigo) {
    // Garante que o código tenha 13 dígitos, adicionando zeros à esquerda se necessário
    if (codigo.length < 13) {
        codigo = codigo.padStart(13, '0');
    }

    fetch('buscar_produto.php?codigo=' + encodeURIComponent(codigo))
        .then(res => res.json())
        .then(produto => {
            if (!produto || !produto.id) {
                alert("Produto não encontrado!");
                return;
            }
            incluirProdutoNaVenda(produto);
            document.getElementById('codigo_barras').value = '';
        });
}

function incluirProdutoNaVenda(produto) {
    const id = produto.id;

    if (produtosAdicionados[id]) {
        const input = document.getElementById('quantidade-' + id);
        input.value = parseFloat(input.value) + 1;
    } else {
        const container = document.getElementById('lista-produtos');
        const div = document.createElement('div');
        div.id = 'produto-' + id;
        div.className = 'border p-2 rounded mb-2';

        let step = (produto.unidade_medida === 'KG' || produto.unidade_medida === 'LT') ? '0.001' : '1';

        div.innerHTML = `
            <div class="row align-items-center g-2 mb-2">
                <div class="col-md-4">
                    <h5><strong>${produto.nome} (R$ ${parseFloat(produto.preco).toFixed(2)} ${produto.unidade_medida})</strong></h5>
                </div>

                <div class="col-md-3 d-flex align-items-center">
                    <span class="input-group-text px-2">Qtd</span>
                    <input type="number" name="quantidade[${id}]" id="quantidade-${id}" class="form-control form-control-sm" value="1" step="${step}" oninput="atualizarTotal()">
                </div>

                <div class="col-md-3">
                    <h5><span>Subtotal: R$ <span id="subtotal-${id}">${parseFloat(produto.preco).toFixed(2)}</span></span></h5>
                </div>

                <div class="col-md-2">
                    <button type="button" class="btn btn-sm btn-danger w-100 d-flex align-items-center justify-content-center" onclick="removerProduto(${id})">
                        <i class="bi bi-trash me-1"></i> Remover
                    </button>
                </div>
            </div>
        `;
        container.appendChild(div);
        produtosAdicionados[id] = parseFloat(produto.preco);
    }

    atualizarTotal();
}

function removerProduto(id) {
    delete produtosAdicionados[id];
    const div = document.getElementById('produto-' + id);
    if (div) div.remove();
    atualizarTotal();
}

function atualizarTotal() {
    let total = 0;
    for (const id in produtosAdicionados) {
        const preco = produtosAdicionados[id];
        const qtd = parseFloat(document.getElementById('quantidade-' + id).value) || 0;

        const subtotal = preco * qtd;
        document.getElementById('subtotal-' + id).innerText = subtotal.toFixed(2);

        if (qtd > 0) {
            total += subtotal;
        }
    }
    const desconto = parseFloat(document.getElementById('desconto').value) || 0;
    total = total - desconto;
    if (total < 0) total = 0;
    document.getElementById('total').innerText = total.toFixed(2);
}

function buscarProduto() {
    const nome = document.getElementById('busca_produto').value.trim();
    if (nome === '') return;

    fetch('buscar_produto.php?nome=' + encodeURIComponent(nome))
        .then(res => res.json())
        .then(produtos => {
            const resultados = document.getElementById('resultados-busca');
            resultados.innerHTML = '';

            if (!produtos.length) {
                resultados.innerHTML = '<div class="alert alert-warning">Nenhum produto encontrado!</div>';
                return;
            }

            produtos.forEach(produto => {
                const div = document.createElement('div');
                div.className = 'd-flex justify-content-between align-items-center border p-2 mb-1 rounded';
                div.innerHTML = `
                    <span class="p-2">${produto.nome} (R$ ${parseFloat(produto.preco).toFixed(2)} - ${produto.unidade_medida})</span>
                    <button type="button" class="btn btn-sm btn-success" onclick="adicionarProdutoPorId(${produto.id})">Adicionar</button>
                `;
                resultados.appendChild(div);
            });
        })
        .catch(err => {
            alert('Erro: ' + err.message);
            console.error(err);
        });
}

function adicionarProdutoPorId(id) {
    fetch('buscar_produto.php?id=' + id)
        .then(res => res.json())
        .then(produto => {
            if (!produto || !produto.id) {
                alert("Produto não encontrado!");
                return;
            }
            incluirProdutoNaVenda(produto);
            document.getElementById('resultados-busca').innerHTML = ''; // <<< Limpa a lista
        })
        .catch(err => {
            alert('Erro: ' + err.message);
            console.error(err);
        });
}

function buscarProdutoPorId() {
    const id = document.getElementById('busca_produto_id').value.trim();
    if (id === '') return;

    fetch('buscar_produto.php?id=' + encodeURIComponent(id))
        .then(res => res.json())
        .then(produto => {
            const resultados = document.getElementById('resultados-busca');
            resultados.innerHTML = '';

            if (!produto || !produto.id) {
                resultados.innerHTML = '<div class="alert alert-warning">Produto não encontrado!</div>';
                return;
            }

            const div = document.createElement('div');
            div.className = 'd-flex justify-content-between align-items-center border p-2 mb-1 rounded';
            div.innerHTML = `
                <span>${produto.nome} (R$ ${parseFloat(produto.preco).toFixed(2)} - ${produto.unidade_medida})</span>
                <button type="button" class="btn btn-sm btn-success" onclick="adicionarProdutoPorId(${produto.id})">Adicionar</button>
            `;
            resultados.appendChild(div);
        })
        .catch(err => {
            alert('Erro: ' + err.message);
            console.error(err);
        });
}

function confirmarFinalizacao() {
    if (confirm("Tem certeza que deseja finalizar a venda?")) {
        document.querySelector('form').submit();
    }
}
</script>

<script>
document.querySelector('select[name="pagamento"]').addEventListener('change', function() {
    const valorPagoDiv = document.getElementById('valor-pago-div');
    const trocoDiv = document.getElementById('troco-div');
    if (this.value === 'Dinheiro') {
        valorPagoDiv.style.display = 'block';
        trocoDiv.style.display = 'block';
    } else {
        valorPagoDiv.style.display = 'none';
        trocoDiv.style.display = 'none';
    }
    calcularTroco();  // Atualiza o troco se necessário
});

function calcularTroco() {
    const valorPago = parseFloat(document.getElementById('valor-pago').value) || 0;
    const total = parseFloat(document.getElementById('total').innerText) || 0;
    let troco = valorPago - total;
    if (troco < 0) troco = 0;
    document.getElementById('troco').innerText = troco.toFixed(2);
}
</script>

</body>
</html>
