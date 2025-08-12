<?php
include 'verifica_login.php';
include 'config.php';
include 'funcoes_caixa.php';

$caixa_id = getCaixaAberto($conn, $operador_id);

if (!$caixa_id) {
    echo "<script>alert('Nenhum caixa aberto foi encontrado. Abra um caixa antes de registrar vendas.'); window.location.href = 'abrir_caixa.php';</script>";
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $quantidades = $_POST['quantidade'] ?? [];
    $desconto = isset($_POST['desconto']) ? (float)$_POST['desconto'] : 0;
    $formas_pagamento = $_POST['forma_pagamento'] ?? [];
    $valores_pagamento = $_POST['valor_pago'] ?? [];
    $total = 0;

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

    // Se for uma única forma de pagamento (não dividida)
    if (count($formas_pagamento) == 1) {
        $forma = $conn->real_escape_string($formas_pagamento[0]);
        $stmt_venda = $conn->prepare("INSERT INTO vendas (total, forma_pagamento, desconto, caixa_id, operador_id) VALUES (?, ?, ?, ?, ?)");
        $stmt_venda->bind_param("dssii", $total, $forma, $desconto, $caixa_id, $operador_id);
        $stmt_venda->execute();
        $venda_id = $stmt_venda->insert_id;
    } else {
        $stmt_venda = $conn->prepare("INSERT INTO vendas (total, forma_pagamento, desconto, caixa_id, operador_id) VALUES (?, 'Multipla', ?, ?, ?)");
        $stmt_venda->bind_param("dsii", $total, $desconto, $caixa_id, $operador_id);
        $stmt_venda->execute();
        $venda_id = $stmt_venda->insert_id;

        $stmt_pagamento = $conn->prepare("INSERT INTO pagamentos (venda_id, forma_pagamento, valor_pago) VALUES (?, ?, ?)");
        foreach ($formas_pagamento as $i => $forma_pagamento) {
            $valor_pago = isset($valores_pagamento[$i]) ? (float)str_replace(',', '.', $valores_pagamento[$i]) : 0.0;
            $forma_pagamento = $conn->real_escape_string($forma_pagamento);
            $stmt_pagamento->bind_param("isd", $venda_id, $forma_pagamento, $valor_pago);
            $stmt_pagamento->execute();
        }
    }

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

$uri = $_SERVER['REQUEST_URI'];
$uri .= (strpos($uri, '?') !== false) ? '&duplicado=' . time() : '?duplicado=' . time();

$lista_links = [
    ['href' => $uri, 'icone' => 'bi bi-files', 'texto' => 'Duplicar Página', 'exibir' => true, 'target' => 'blank'],
    ['href' => 'produtos.php', 'icone' => 'bi bi-pencil-square', 'texto' => 'Editar Produtos', 'exibir' => true, 'target' => ''],
    ['href' => 'sangria.php', 'icone' => 'bi bi-arrow-down-circle', 'texto' => 'Fazer Sangria', 'exibir' => true, 'target' => ''],
    ['href' => 'fechar_caixa.php', 'icone' => 'bi bi-cash-coin', 'texto' => 'Fechar Caixa', 'exibir' => true, 'target' => ''],
    ['href' => 'index.php', 'icone' => 'bi bi-arrow-left', 'texto' => 'Voltar ao Painel', 'exibir' => $usuario_tipo === 'admin', 'target' => ''],
    ['href' => 'logout.php', 'icone' => 'bi bi-box-arrow-right', 'texto' => 'Sair', 'exibir' => true, 'target' => ''],
];

require 'view/header.php';

    ?>
<div class="container mt-4">
    <h2>Registrar Venda</h2>

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
            <select class=form-select name="forma_pagamento[]" id="forma_pagamento_principal" required onchange="verificarMultipla(this)">
                <option value="Cartão de Crédito">Cartão de Crédito</option>
                <option value="Cartão de Débito">Cartão de Débito</option>
                <option value="PIX">PIX</option>
                <option value="Dinheiro">Dinheiro</option>
                <option value="Múltipla">Dividir pagamento</option>
            </select>
            </div>

            <!-- Campo de valor (oculto inicialmente) -->
            <div class="mb-3 mt-3" id="valor_total_container" style="margin-top: 10px; display: none;">
                <label class="form-label">Valor total dessa forma:</label>
                <input class="form-control" type="text" name="valor_pago[]" placeholder="Valor pago (R$)">
            </div>

            <!-- Bloco onde outras formas de pagamento serão adicionadas -->
            <div id="pagamentos-extras" style="margin-top: 20px;"></div>

            <!-- Botão para adicionar mais formas -->
            <div class="mb-3 mt-3" id="botao-adicionar" style="display: none; margin-top: 10px;">
                <button type="button" class="btn btn-primary" onclick="adicionarPagamento()">Adicionar Forma de Pagamento</button>
            </div>
            <div class="mb-3 mt-3" id="valor-pago-div" style="display:none;">
                <label class="form-label">Valor Pago (R$)</label>
                <input type="number" step="0.01" min="0" id="valor-pago" class="form-control" placeholder="Digite o valor pago" oninput="calcularTroco()">
            </div>

            <div class="mb-3 mt-3" id="troco-div" style="display:none;">
                <h5><strong>Troco: R$ <span id="troco">0.00</span></strong></h5>
            </div>

            <button type="button" class="btn btn-success" onclick="confirmarFinalizacao()">Finalizar Venda</button>
        </div>

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

//Adiciona mais de uma formas de pagamento
function verificarMultipla(select) {
  const valorContainer = document.getElementById('valor_total_container');
  const botaoAdicionar = document.getElementById('botao-adicionar');

  if (select.value === 'Múltipla') {
    valorContainer.style.display = 'none';
    botaoAdicionar.style.display = 'block';
  } else {
    valorContainer.style.display = 'none'; // escondido porque não precisa informar valor
    botaoAdicionar.style.display = 'none';

    // Limpa os pagamentos extras
    document.getElementById('pagamentos-extras').innerHTML = '';
  }
}

document.querySelector('select[name="forma_pagamento[]"]').addEventListener('change', verificarDinheiroSelecionado);
function verificarDinheiroSelecionado() {
  const selects = document.querySelectorAll('select[name="forma_pagamento[]"]');
  let temDinheiro = false;

  selects.forEach(sel => {
    if (sel.value === 'Dinheiro') {
      temDinheiro = true;
    }
  });

  const valorPagoDiv = document.getElementById('valor-pago-div');
  const trocoDiv = document.getElementById('troco-div');

  if (temDinheiro) {
    valorPagoDiv.style.display = 'block';
    trocoDiv.style.display = 'block';
  } else {
    valorPagoDiv.style.display = 'none';
    trocoDiv.style.display = 'none';
  }

  calcularTroco();
}

function calcularTroco() {
    const valorPago = parseFloat(document.getElementById('valor-pago').value) || 0;
    const total = parseFloat(document.getElementById('total').innerText) || 0;
    let troco = valorPago - total;
    if (troco < 0) troco = 0;
    document.getElementById('troco').innerText = troco.toFixed(2);
}

function adicionarPagamento() {
  const container = document.getElementById('pagamentos-extras');

  const div = document.createElement('div');
  div.classList.add('pagamento-extra');
  div.style.marginTop = '10px';

  div.innerHTML = `
    <div class="d-flex flex-row mb-3 mt-3">
        <select class="form-select" name="forma_pagamento[]" required>
            <option value="Cartão de Crédito">Cartão de Crédito</option>
            <option value="Cartão de Débito">Cartão de Débito</option>
            <option value="PIX">PIX</option>
            <option value="Dinheiro">Dinheiro</option>
        </select>
        <input type="text" name="valor_pago[]" placeholder="Valor pago (R$)" class="form-control" required>
        <button class="btn btn-danger" type="button" onclick="removerPagamento(this)">Remover</button>
    </div>
  `;

  container.appendChild(div);

  // Adiciona o event listener ao novo select
  const novoSelect = div.querySelector('select');
  novoSelect.addEventListener('change', verificarDinheiroSelecionado);
}

function removerPagamento(btn) {
  btn.parentElement.remove();
}

//Duplicar página
document.addEventListener("DOMContentLoaded", function () {
    const botaoDuplicar = document.getElementById("duplicarPagina");
    if (botaoDuplicar) {
        botaoDuplicar.addEventListener("click", function (e) {
            e.preventDefault(); // evita o comportamento padrão do link

            const urlBase = window.location.href.split('?')[0]; // remove parâmetros
            const novaUrl = urlBase + '?duplicado=' + new Date().getTime();
            window.open(novaUrl, '_blank'); // abre nova aba sempre
        });
    }
});

</script>

</body>
</html>
