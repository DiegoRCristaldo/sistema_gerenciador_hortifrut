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