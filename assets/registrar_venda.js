let produtosAdicionados = {};

// Função para formatar números para BRL
const formatarMoeda = (valor) => {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(valor);
};

function buscarProdutoPorCodigoBarras() {
    let codigoBarras = document.getElementById('codigo_barras').value;
    if (codigoBarras.trim() === '') {
        return;
    }

    // Caminho da API corrigido para a raiz
    fetch('buscar_produto.php?codigo_barras=' + encodeURIComponent(codigoBarras))
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success' && data.produto) {
                adicionarProdutoNaVenda(data.produto);
                document.getElementById('codigo_barras').value = ''; // Limpa o campo
            } else {
                alert(data.message || 'Produto não encontrado.');
            }
        })
        .catch(error => console.error('Erro na busca por código de barras:', error));
}

function buscarProdutoPorNome(nomeProduto) {
    if (nomeProduto.trim() === '') {
        return;
    }

    // Caminho da API corrigido para a raiz
    fetch('buscar_produto.php?nome=' + encodeURIComponent(nomeProduto))
        .then(response => response.json())
        .then(data => {
            const resultadosDiv = document.getElementById('resultados-busca');
            resultadosDiv.innerHTML = ''; // Limpa resultados anteriores

            if (data.status === 'success' && data.produtos && data.produtos.length > 0) {
                data.produtos.forEach(produtoEncontrado => {
                    const div = document.createElement('div');
                    div.className = 'd-flex justify-content-between align-items-center border p-2 mb-1 rounded';
                    div.innerHTML = `
                        <span class="p-2">${produtoEncontrado.nome} (R$ ${parseFloat(produtoEncontrado.preco).toFixed(2)} - ${produtoEncontrado.unidade_medida})</span>
                        <button type="button" class="btn btn-sm btn-success"
                            onclick='adicionarProdutoNaVenda(JSON.parse(this.dataset.product)); document.getElementById("resultados-busca").innerHTML = "";'
                            data-product='${JSON.stringify(produtoEncontrado)}'>Adicionar</button>
                    `;
                    resultadosDiv.appendChild(div);
                });
            } else {
                resultadosDiv.innerHTML = '<div class="alert alert-warning mt-2">Nenhum produto encontrado!</div>';
            }
        })
        .catch(error => {
            alert('Erro na busca por nome: ' + error.message);
            console.error('Erro na busca por nome:', error);
        });
}

function buscarProdutoPorId(idProduto) {
    if (idProduto === '' || isNaN(idProduto)) {
        return;
    }

    // Caminho da API corrigido para a raiz
    fetch('buscar_produto.php?id=' + encodeURIComponent(idProduto))
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success' && data.produto) {
                adicionarProdutoNaVenda(data.produto);
                document.getElementById('busca_produto_id').value = ''; // Limpa o campo
            } else {
                alert(data.message || 'Produto não encontrado com este ID.');
            }
        })
        .catch(error => console.error('Erro na busca por ID:', error));
}


function adicionarProdutoNaVenda(produto) {
    // Garante que a unidade_medida exista, ou usa uma string vazia como fallback
    produto.unidade_medida = produto.unidade_medida || '';

    if (produtosAdicionados[produto.id]) {
        // Se o produto já existe, apenas incrementa a quantidade
        const inputQuantidade = document.getElementById(`quantidade-${produto.id}`);
        if (inputQuantidade) {
            inputQuantidade.value = parseFloat(inputQuantidade.value) + 1;
            atualizarQuantidade(produto.id, inputQuantidade.value); // Atualiza imediatamente
        }
    } else {
        // Quantidade inicial para KG/LT deve ser 1, não 1.000
        let quantidadeInicial = 1;
        produtosAdicionados[produto.id] = { ...produto, quantidade: quantidadeInicial };
    }
    renderizarProdutosNaVenda();
    // Limpa os resultados de busca após adicionar um produto
    document.getElementById('resultados-busca').innerHTML = '';
}

function removerProdutoDaVenda(idProduto) {
    delete produtosAdicionados[idProduto];
    renderizarProdutosNaVenda();
}

function atualizarQuantidade(idProduto, novaQuantidade) {
    novaQuantidade = parseFloat(novaQuantidade); // Use parseFloat para lidar com decimais
    
    // Não remove o produto se a quantidade for 0 ou inválida, apenas define como 0
    if (isNaN(novaQuantidade) || novaQuantidade < 0) {
        novaQuantidade = 0;
    }
    
    produtosAdicionados[idProduto].quantidade = novaQuantidade;
    
    // Atualiza o subtotal do item
    const subtotalElement = document.getElementById(`subtotal-${idProduto}`);
    if (subtotalElement) {
        subtotalElement.textContent = formatarMoeda(produtosAdicionados[idProduto].preco * novaQuantidade);
    }
    
    atualizarTotal(); // Recalcula o total geral
}

function renderizarProdutosNaVenda() {
    let listaProdutosDiv = document.getElementById('lista-produtos');
    listaProdutosDiv.innerHTML = '';
    let totalGeral = 0;

    for (let id in produtosAdicionados) {
        let produto = produtosAdicionados[id];
        let subtotal = produto.preco * produto.quantidade;
        totalGeral += subtotal;

        // Determina o step baseado na unidade de medida, com fallback seguro
        let step = (produto.unidade_medida && (produto.unidade_medida.toUpperCase() === 'KG' || produto.unidade_medida.toUpperCase() === 'LT')) ? '0.001' : '1';
        let quantidadeExibida = produto.quantidade;
        
        // Se a quantidade for um número inteiro e a unidade for KG/LT, exibe como inteiro.
        // Se não for inteiro, ou não for KG/LT, exibe normalmente ou com casas decimais se já houver.
        if (step === '0.001' && quantidadeExibida % 1 !== 0) {
             quantidadeExibida = produto.quantidade.toFixed(3); // Exibe com 3 casas decimais se houver decimal
        } else {
             quantidadeExibida = produto.quantidade; // Exibe como inteiro se for inteiro
        }

        let div = document.createElement('div');
        div.id = `produto-${id}`; // Adiciona ID para facilitar a referência
        div.className = 'border p-2 rounded mb-2';

        div.innerHTML = `
            <input type="hidden" name="quantidade[${produto.id}]" value="${produto.quantidade}">
            <div class="row align-items-center g-2 mb-2">
                <div class="col-md-4">
                    <h5><strong>${produto.nome} (R$ ${parseFloat(produto.preco).toFixed(2)} ${produto.unidade_medida})</strong></h5>
                </div>

                <div class="col-md-3 d-flex align-items-center">
                    <span class="input-group-text px-2">Qtd</span>
                    <input type="number" name="quantidade[${id}]" id="quantidade-${id}" class="form-control form-control-sm" value="${quantidadeExibida}" min="0" step="${step}" oninput="atualizarQuantidade(${id}, this.value)">
                </div>

                <div class="col-md-3">
                    <h5><span>Subtotal: <span id="subtotal-${id}">${formatarMoeda(subtotal)}</span></span></h5>
                </div>

                <div class="col-md-2">
                    <button type="button" class="btn btn-sm btn-danger w-100 d-flex align-items-center justify-content-center" onclick="removerProdutoDaVenda(${id})">
                        <i class="bi bi-trash me-1"></i> Remover
                    </button>
                </div>
            </div>
        `;
        listaProdutosDiv.appendChild(div);
    }

    document.getElementById('total').textContent = formatarMoeda(totalGeral);
    atualizarTotal(); // Recalcula o total final após renderizar os produtos
}

function atualizarTotal() {
    let totalProdutos = 0;
    for (let id in produtosAdicionados) {
        let produto = produtosAdicionados[id];
        totalProdutos += produto.preco * produto.quantidade;
    }

    let desconto = parseFloat(document.getElementById('desconto').value) || 0;
    let totalFinal = totalProdutos - desconto;
    if (totalFinal < 0) totalFinal = 0; // Garante que o total não seja negativo

    document.getElementById('total').textContent = formatarMoeda(totalFinal);

    // Ajusta o valor inicial do campo de pagamento principal ao atualizar o total
    let formaPagamentoPrincipal = document.getElementById('forma_pagamento_principal');
    if (formaPagamentoPrincipal) {
        verificarMultipla(formaPagamentoPrincipal);
    }
    calcularTroco(); // Recalcula o troco/valor pago ao atualizar o total
}


function verificarMultipla(selectElement) {
    const pagamentosExtrasDiv = document.getElementById('pagamentos-extras');
    const botaoAdicionarDiv = document.getElementById('botao-adicionar');
    const valorPagoDiv = document.getElementById('valor-pago-div');
    const trocoDiv = document.getElementById('troco-div');
    const valorTotalContainer = document.getElementById('valor_total_container');
    const valorPagoInput = document.getElementById('valor-pago'); // O input dentro de valor-pago-div

    // Limpa e oculta tudo por padrão
    pagamentosExtrasDiv.innerHTML = '';
    pagamentosExtrasDiv.style.display = 'none';
    botaoAdicionarDiv.style.display = 'none';
    valorPagoDiv.style.display = 'none';
    trocoDiv.style.display = 'none';
    valorTotalContainer.style.display = 'none'; // Oculta o campo de valor do pagamento principal

    const forma = selectElement.value;
    const totalVendaNum = parseFloat(document.getElementById('total').textContent.replace('R$', '').replace('.', '').replace(',', '.'));


    if (forma === 'Múltipla') {
        pagamentosExtrasDiv.style.display = 'block';
        botaoAdicionarDiv.style.display = 'block';
        adicionarPagamento(true); // Adiciona a primeira linha de pagamento ao selecionar Múltipla
    } else if (forma === 'Dinheiro') {
        valorPagoDiv.style.display = 'block';
        trocoDiv.style.display = 'block';
        valorPagoInput.value = totalVendaNum.toFixed(2); // Preenche com o total da venda por padrão
        calcularTroco();
    } else {
        // Para Cartão de Crédito, Cartão de Débito, PIX
        valorTotalContainer.style.display = 'block'; // Mostra o container do valor
        // Garante que o input de valor pago principal tenha o name correto e um valor padrão
        const inputPrincipal = valorTotalContainer.querySelector('input[name="valor_pago[]"]');
        if (inputPrincipal) {
            inputPrincipal.value = totalVendaNum.toFixed(2); // Preenche com o total da venda
        }
    }
}


function adicionarPagamento(isFirst = false) {
    const pagamentosExtrasDiv = document.getElementById('pagamentos-extras');
    const novoIndex = pagamentosExtrasDiv.children.length; // Garante um índice único

    const div = document.createElement('div');
    div.className = 'd-flex align-items-center mb-2';
    div.innerHTML = `
        <select class="form-select me-2" name="forma_pagamento[]" required>
            <option value="Cartão de Crédito">Cartão de Crédito</option>
            <option value="Cartão de Débito">Cartão de Débito</option>
            <option value="PIX">PIX</option>
            <option value="Dinheiro">Dinheiro</option>
        </select>
        <input class="form-control me-2" type="text" name="valor_pago[]" placeholder="Valor pago (R$)" oninput="this.value = this.value.replace(/[^0-9,.]/g, '').replace(/,/g, '.'); calcularTrocoMultipla();">
        <button type="button" class="btn btn-danger btn-sm" onclick="removerPagamento(this)">Remover</button>
    `;
    pagamentosExtrasDiv.appendChild(div);

    // Se for o primeiro campo de pagamento em "Múltipla", preenche com o total
    if (isFirst) {
        const totalVendaNum = parseFloat(document.getElementById('total').textContent.replace('R$', '').replace('.', '').replace(',', '.'));
        const inputValor = div.querySelector('input[name="valor_pago[]"]');
        if (inputValor) {
            inputValor.value = totalVendaNum.toFixed(2);
        }
    }
    calcularTrocoMultipla();
}


function removerPagamento(buttonElement) {
    buttonElement.closest('.d-flex').remove();
    calcularTrocoMultipla();
}

function calcularTroco() {
    const totalVendaElement = document.getElementById('total');
    let totalVendaTexto = totalVendaElement.textContent.replace('R$', '').replace('.', '').replace(',', '.');
    const totalVenda = parseFloat(totalVendaTexto);

    const valorPagoInput = document.getElementById('valor-pago');
    let valorPagoTexto = valorPagoInput.value.replace(/,/g, '.');
    const valorPago = parseFloat(valorPagoTexto) || 0;

    const troco = valorPago - totalVenda;
    document.getElementById('troco').textContent = formatarMoeda(troco);
    document.getElementById('troco_final').value = troco.toFixed(2);
}

function calcularTrocoMultipla() {
    const totalVendaElement = document.getElementById('total');
    let totalVendaTexto = totalVendaElement.textContent.replace('R$', '').replace('.', '').replace(',', '.');
    const totalVenda = parseFloat(totalVendaTexto);

    let totalPago = 0;
    document.querySelectorAll('#pagamentos-extras input[name="valor_pago[]"]').forEach(input => {
        let valor = parseFloat(input.value.replace(/,/g, '.')) || 0;
        totalPago += valor;
    });

    const troco = totalPago - totalVenda;
    document.getElementById('troco').textContent = formatarMoeda(troco);
    document.getElementById('troco_final').value = troco.toFixed(2);
    
    // Mostra/oculta div de troco se estiver no modo múltiplo
    const trocoDiv = document.getElementById('troco-div');
    if (totalPago > totalVenda) {
        trocoDiv.style.display = 'block';
    } else {
        trocoDiv.style.display = 'none';
    }
}


function confirmarFinalizacao() {
    if (Object.keys(produtosAdicionados).length === 0) {
        alert('Adicione ao menos um produto para finalizar a venda.');
        return;
    }

    const formaPagamentoPrincipal = document.getElementById('forma_pagamento_principal').value;
    const totalVendaNum = parseFloat(document.getElementById('total').textContent.replace('R$', '').replace('.', '').replace(',', '.'));
    let totalPagoVerificacao = 0;

    if (formaPagamentoPrincipal === 'Múltipla') {
        document.querySelectorAll('#pagamentos-extras input[name="valor_pago[]"]').forEach(input => {
            totalPagoVerificacao += parseFloat(input.value.replace(/,/g, '.')) || 0;
        });
    } else if (formaPagamentoPrincipal === 'Dinheiro') {
        totalPagoVerificacao = parseFloat(document.getElementById('valor-pago').value.replace(/,/g, '.')) || 0;
    } else { // Cartão de Crédito, Débito, PIX (pagamento único)
        const inputPrincipal = document.getElementById('valor_total_container').querySelector('input[name="valor_pago[]"]');
        if (inputPrincipal) {
            totalPagoVerificacao = parseFloat(inputPrincipal.value.replace(/,/g, '.')) || 0;
        }
    }

    if (totalPagoVerificacao < totalVendaNum && formaPagamentoPrincipal !== 'Múltipla') {
        alert('O valor pago é menor que o total da venda. Ajuste o pagamento.');
        return;
    }

    // Confirmação final antes de enviar
    const confirmacao = confirm('Deseja finalizar a venda?');
    if (confirmacao) {
        document.querySelector('form').submit();
    }
}


function handleKeyPress(event) {
    if (event.key === 'Enter') {
        event.preventDefault(); // Impede o envio do formulário padrão
        buscarProdutoPorCodigoBarras();
    }
}

function handleKeyPressBuscaNome(event) {
    if (event.key === 'Enter') {
        event.preventDefault();
        buscarProdutoPorNome(document.getElementById('busca_produto').value);
    }
}

function handleKeyPressBuscaId(event) {
    if (event.key === 'Enter') {
        event.preventDefault();
        buscarProdutoPorId(document.getElementById('busca_produto_id').value);
    }
}


// Event listeners para garantir que a atualização ocorra ao carregar a página
document.addEventListener('DOMContentLoaded', () => {
    renderizarProdutosNaVenda();
    // Garante que o estado inicial do select de pagamento seja processado
    const formaPagamentoPrincipal = document.getElementById('forma_pagamento_principal');
    if (formaPagamentoPrincipal) {
        verificarMultipla(formaPagamentoPrincipal);
    }
});
