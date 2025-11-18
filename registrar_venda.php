<?php
use NFePHP\NFe\Tools;
use NFePHP\Common\Certificate;
use NFePHP\NFe\Common\Standardize;
use NFePHP\DA\NFe\Danfce;

include 'verifica_login.php';
include 'config.php'; 
require 'nfe_service.php';
include 'funcoes_caixa.php'; 

// Coloque esta função NO INÍCIO do arquivo, antes de qualquer código
function carregarCertificadoPem($pemContent, $password) {
    // Pattern corrigido
    $privateKeyPattern = '/-----BEGIN PRIVATE KEY-----[\s\S]*?-----END PRIVATE KEY-----/';
    $certificatePattern = '/-----BEGIN CERTIFICATE-----[\s\S]*?-----END CERTIFICATE-----/';
    
    if (!preg_match($privateKeyPattern, $pemContent, $privateMatches) ||
        !preg_match($certificatePattern, $pemContent, $certMatches)) {
        throw new Exception("Não foi possível extrair componentes do PEM");
    }
    
    $privateKey = trim($privateMatches[0]);
    $certificate = trim($certMatches[0]);
    
    $pfxContent = '';
    if (!openssl_pkcs12_export($certificate, $pfxContent, $privateKey, $password)) {
        throw new Exception("Falha ao criar PFX: " . openssl_error_string());
    }
    
    return Certificate::readPfx($pfxContent, $password);
}

// Define o fuso horário padrão do PHP. Essencial para dhEmi e dhSaiEnt no XML.
date_default_timezone_set('America/Sao_Paulo'); 

// Tenta obter o ID do operador logado, com fallbacks caso não esteja na sessão.
$operador_id = function_exists('getOperadorId') ? getOperadorId($conn, $_SESSION['usuario']) : ($_SESSION['operador_id'] ?? $_SESSION['id'] ?? null);
$caixaObj = getCaixaAberto($conn, $operador_id); // Obtém informações do caixa aberto para o operador
$caixa_id = is_array($caixaObj) ? $caixaObj['id'] : $caixaObj; // Extrai o ID do caixa

// Verifica se há um caixa aberto, caso contrário, redireciona.
if (!$caixa_id) {
    echo "<script>alert('Nenhum caixa aberto foi encontrado. Abra um caixa antes de registrar vendas.'); window.location.href = 'abrir_caixa.php';</script>";
    exit;
}

// Gera token único para o formulário
if (empty($_SESSION['form_token'])) {
    $_SESSION['form_token'] = bin2hex(random_bytes(32));
}

// Processamento da requisição POST para registrar a venda.
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Processamento POST com validações anti-duplicação
    // Valida token
    $token = $_POST['form_token'] ?? '';
    if (empty($token) || $token !== $_SESSION['form_token']) {
        error_log("TENTATIVA DE DUPLICAÇÃO: Token inválido");
        die("Venda já processada. Não recarregue a página.");
    }
    
    // Gera hash único da venda
    $hashVenda = md5(json_encode($_POST) . time() . $operador_id);
    
    // Verifica duplicidade imediata
    $stmt = $conn->prepare("SELECT id FROM vendas WHERE hash_venda = ?");
    $stmt->bind_param("s", $hashVenda);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        die("Venda duplicada detectada.");
    }
    
    // Inicia transação
    $conn->begin_transaction();

    $quantidades = $_POST['quantidade'] ?? []; // Quantidades de cada produto vendido
    $desconto = isset($_POST['desconto']) ? (float)$_POST['desconto'] : 0; // Desconto total na venda
    $formas_pagamento = $_POST['forma_pagamento'] ?? []; // Formas de pagamento
    $valores_pagamento = $_POST['valor_pagamento'] ?? []; // Valores pagos por cada forma
    $total = 0; // Inicializa o total da venda

    $troco_final = isset($_POST['troco_final']); // Troco calculado

    $totalItens = 0; // Contagem de itens para verificar se a venda está vazia
    foreach ($quantidades as $id => $qtd) {
        $qtd = (float)$qtd;
        if ($qtd > 0) {
            $totalItens += $qtd;
        }
    }

    // Impede a finalização da venda se não houver itens.
    if ($totalItens == 0) {
        echo "<script>alert('Não é possível finalizar a venda sem nenhum item!'); window.history.back();</script>";
        exit;
    }

    // Prepara as consultas para produtos e itens de venda.
    $stmt_produto = $conn->prepare("SELECT id, nome, preco, unidade_medida, ncm, cfop, codigo_barras FROM produtos WHERE id = ?");
    $stmt_item = $conn->prepare("INSERT INTO itens_venda (venda_id, produto_id, quantidade, unidade_medida) VALUES (?, ?, ?, ?)");

    // Calcula o total da venda antes pelo desconto.
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

    $total -= $desconto; // Aplica o desconto
    if($total < 0) $total = 0; // Garante que o total não seja negativo

    // Insere a venda no banco de dados.
    if (count($formas_pagamento) == 1) { // Caso seja uma única forma de pagamento
        $forma = $conn->real_escape_string($formas_pagamento[0]);
        $stmt_venda = $conn->prepare("INSERT INTO vendas (total, forma_pagamento, desconto, caixa_id, operador_id, hash_venda) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt_venda->bind_param("dssiis", $total, $forma, $desconto, $caixa_id, $operador_id, $hashVenda);
        $stmt_venda->execute();
        $venda_id = $stmt_venda->insert_id;
    } else { // Caso seja pagamento múltiplo
        $stmt_venda = $conn->prepare("INSERT INTO vendas (total, forma_pagamento, desconto, caixa_id, operador_id, hash_venda) VALUES (?, 'Multipla', ?, ?, ?, ?)");
        $stmt_venda->bind_param("dsiis", $total, $desconto, $caixa_id, $operador_id, $hashVenda);
        $stmt_venda->execute();
        $venda_id = $stmt_venda->insert_id;

        $stmt_pagamento = $conn->prepare("INSERT INTO pagamentos (venda_id, forma_pagamento, valor_pago) VALUES (?, ?, ?)");
        foreach ($formas_pagamento as $i => $forma_pagamento) {
            $valor_pag = isset($valores_pagamento[$i]) ? (float)str_replace(',', '.', $valores_pagamento[$i]) : 0.0;
            $forma_pagamento = $conn->real_escape_string($forma_pagamento);
            $stmt_pagamento->bind_param("isd", $venda_id, $forma_pagamento, $valor_pag);
            $stmt_pagamento->execute();
        }
    }

    // Insere os itens da venda no banco de dados.
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

    // ========== CORREÇÃO CRÍTICA: CÁLCULO DOS VALORES PARA XML ==========
    
    $itensParaXml = [];
    $valorTotalProdutos = 0;
    $descontoFinal = 0;

    // PRIMEIRO PASSO: Calcular valor total dos produtos (SEM desconto)
    foreach ($quantidades as $id => $qtd) {
        $id = (int)$id;
        $qtd = (float)$qtd;
        if ($qtd <= 0) continue;

        $stmt_produto->bind_param("i", $id);
        $stmt_produto->execute();
        $res = $stmt_produto->get_result();
        $produto = $res->fetch_assoc();

        $preco = (float)$produto['preco'];
        
        // CORREÇÃO: vProd deve ser o valor BRUTO (vUnCom × qCom)
        $valorBruto = $preco * $qtd;
        $valorTotalProdutos += $valorBruto;
    }

    // SEGUNDO PASSO: Distribuir desconto proporcionalmente
    $somaDescontosItens = 0;
    $ultimoIndex = null;

    foreach ($quantidades as $id => $qtd) {
        $id = (int)$id;
        $qtd = (float)$qtd;
        if ($qtd <= 0) continue;

        $stmt_produto->bind_param("i", $id);
        $stmt_produto->execute();
        $res = $stmt_produto->get_result();
        $produto = $res->fetch_assoc();

        $preco = (float)$produto['preco'];
        $valorBruto = $preco * $qtd;

        // Calcula o desconto proporcional para este item
        $descontoItem = 0;
        if ($desconto > 0 && $valorTotalProdutos > 0) {
            $percentualItem = $valorBruto / $valorTotalProdutos;
            $descontoItem = round($desconto * $percentualItem, 2);
        }

        $somaDescontosItens += $descontoItem;
        
        // CORREÇÃO CRÍTICA: vProd é o valor BRUTO, vDesc é o desconto individual
        $itensParaXml[] = [
            'id' => $produto['id'],
            'cProd' => $produto['id'],
            'cEAN' => $produto['codigo_barras'] ?? 'SEM GTIN',
            'xProd' => $produto['nome'], 
            'NCM' => $produto['ncm'] ?? '07099990',
            'CFOP' => $produto['cfop'] ?? '5102',
            'uCom' => $produto['unidade_medida'] ?? 'UN',
            'qCom' => $qtd,
            'vUnCom' => $preco,
            'vProd' => round($valorBruto, 2), // VALOR BRUTO (vUnCom × qCom)
            'vDesc' => round($descontoItem, 2), // Desconto individual
            'cEANTrib' => $produto['codigo_barras'] ?? 'SEM GTIN',
            'uTrib' => $produto['unidade_medida'] ?? 'UN',
            'qTrib' => $qtd,
            'vUnTrib' => $preco,
            'indTot' => 1
        ];
        
        $ultimoIndex = count($itensParaXml) - 1;
    }

    // TERCEIRO PASSO: Ajustar diferenças de arredondamento
    $diferenca = round($desconto - $somaDescontosItens, 2);

    if (abs($diferenca) > 0.001 && $ultimoIndex !== null && count($itensParaXml) > 0) {
        // Ajusta a diferença no último item
        $itensParaXml[$ultimoIndex]['vDesc'] = round(
            $itensParaXml[$ultimoIndex]['vDesc'] + $diferenca, 
            2
        );
        
        // IMPORTANTE: NÃO altera o vProd - ele deve permanecer como valor bruto
        // O vProd NÃO deve ser recalculado como (vUnCom × qCom) - vDesc
    }

    // QUARTO PASSO: Recalcular totais finais
    $valorTotalProdutosFinal = 0;
    $descontoFinal = 0;

    foreach ($itensParaXml as $item) {
        // CORREÇÃO: vProd deve permanecer como valor BRUTO
        $valorBrutoItem = $item['vProd'];
        $valorTotalProdutosFinal += $valorBrutoItem;
        $descontoFinal += $item['vDesc'];
    }

    // Garante que o total da NF está correto
    $totalNF = round($valorTotalProdutosFinal - $descontoFinal, 2);

    // VERIFICAÇÃO DE CONSISTÊNCIA CRÍTICA - VALIDAÇÃO 629
    $errosValidacao = [];
    foreach ($itensParaXml as $index => $item) {
        $vProdCalculado = round($item['vUnCom'] * $item['qCom'], 2);
        $vProdInformado = round($item['vProd'], 2);
        
        // Verifica se a diferença é maior que 0.01 (tolerância SEFAZ)
        if (abs($vProdCalculado - $vProdInformado) > 0.01) {
            $errosValidacao[] = "Item {$index}: vProd calculado ({$vProdCalculado}) ≠ vProd informado ({$vProdInformado})";
        }
    }

    if (!empty($errosValidacao)) {
        error_log("ERRO VALIDAÇÃO 629: " . implode("; ", $errosValidacao));
        // Corrige automaticamente os valores problemáticos
        foreach ($itensParaXml as &$item) {
            $vProdCalculado = round($item['vUnCom'] * $item['qCom'], 2);
            $item['vProd'] = $vProdCalculado; // Força o valor correto
        }
        // Recalcula totais após correção
        $valorTotalProdutosFinal = 0;
        foreach ($itensParaXml as $item) {
            $valorTotalProdutosFinal += $item['vProd'];
        }
        $totalNF = round($valorTotalProdutosFinal - $descontoFinal, 2);
    }

    $totalArray = [
        'vProd' => round($valorTotalProdutosFinal, 2),
        'vNF' => round($totalNF, 2),
        'vBC' => 0,
        'vICMS' => 0
    ];

    // Adiciona vDesc apenas se houver desconto
    if($dados['tpAmb'] == '1'){
        if ($descontoFinal > 0) {
            $totalArray['vDesc'] = round($descontoFinal, 2);
        }
    }
    else {
        $totalArray['vDesc'] = round($descontoFinal, 2);
    }

    // Define as configurações para a NFePHP.
    $cnpjEmitente = $dados['cnpj']; 
    $configJsonPath = __DIR__ . $dados['configJson']; 
    $pemPath = __DIR__ . $dados['certificadoPem']; 
    $pfxPassword = $dados['senhaPfx']; 

    // Mapeamento de formas de pagamento para tPag (padrão SEFAZ).
    $payment_type_map = [
        'Dinheiro'          => '01',
        'Cartão de Crédito' => '03',
        'Cartão de Débito'  => '04',
        'PIX'               => '20' // PIX estático = 20, PIX dinâmico = 17
    ];

    // Obtém o próximo número sequencial
    $stmt = $conn->prepare("SELECT ultimo_numero FROM numeracao_nfe WHERE id = 1 FOR UPDATE");
    $stmt->execute();
    $result = $stmt->get_result();
    $numeracao = $result->fetch_assoc();
    $proximo_numero = $numeracao['ultimo_numero'] + 1;

    // Atualiza o contador
    $stmt_update = $conn->prepare("UPDATE numeracao_nfe SET ultimo_numero = ? WHERE id = 1");
    $stmt_update->bind_param("i", $proximo_numero);
    $stmt_update->execute();

    // Usa o número sequencial
    $nNF_gerado = $proximo_numero;

    // Geração do cNF (Código Numérico da NF) - deve ser aleatório mas único por 1 ano
    $cNF = str_pad(random_int(0, 99999999), 8, '0', STR_PAD_LEFT);

    // Monta o array de dados da venda para o XML da NFC-e.
    $ide = [
        'cUF' => '21', // Código da UF (MA - Maranhão), fixo para o emitente.
        'cNF' => $cNF, // Usa o cNF gerado acima
        'natOp' => 'VENDA', 'indPag' => 0, 'mod' => '65',
        'serie' => 1, 
        'nNF' => $nNF_gerado,
        'dhEmi' => date('Y-m-d\TH:i:sP'), // Data e hora de emissão com fuso horário
        'dhSaiEnt' => date('Y-m-d\TH:i:sP'), // Data e hora de saída/entrada com fuso horário
        'tpNF' => 1, 'idDest' => 1, 
        'cMunFG' => $dados['enderecoEmitente']['cMun'], // Código IBGE do município do emitente
        'tpImp' => 4, //Valor 4 - DANFE NFC-e ou 5 - DANFE NFC-e em mensagem eletrônica.
        'tpEmis' => 1, 
        // A linha 'cDV' foi REMOVIDA para que a NFePHP calcule automaticamente.
        'tpAmb' => $dados['tpAmb'], // 1 = Produção, 2 = Homologação
        'finNFe' => 1, 'indFinal' => 1, 'indPres' => 1, 'procEmi' => 0, 'verProc' => 'PDV-1.0'
    ];
    $emit = [
        'CNPJ' => $cnpjEmitente,
        'xNome' => $dados['razaoSocial'], // Razão Social do dados.php
        'xFant' => $dados['razaoSocial'], // Nome Fantasia (usando Razão Social por simplicidade)
        'enderEmit' => [ // Dados de endereço do emitente do dados.php
            'xLgr' => $dados['enderecoEmitente']['xLgr'],
            'nro' => $dados['enderecoEmitente']['nro'],
            'xCpl' => $dados['enderecoEmitente']['xCpl'], 
            'xBairro' => $dados['enderecoEmitente']['xBairro'],
            'cMun' => $dados['enderecoEmitente']['cMun'], 
            'xMun' => $dados['enderecoEmitente']['xMun'],
            'UF' => $dados['enderecoEmitente']['UF'],
            'CEP' => $dados['enderecoEmitente']['CEP'],
            'fone' => $dados['enderecoEmitente']['fone']
        ],
        'IE' => $dados['ieEmitente'], // Inscrição Estadual do dados.php
        'CRT' => 1 // Código de Regime Tributário (1=Simples Nacional)
    ];
    $dest = [ // Dados do destinatário (consumidor)
        'CPF' => $dados['cpfTeste'],
        'xNome' => "NF-E EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL" //-> quando o tpAmb é 2
        // 'indIEDest' NÃO DEVEM ser incluídos aqui em ambiente de produção
    ];
    $itens = $itensParaXml; 
    $total = $totalArray;
    $pagamentos = []; 
    $troco = $troco_final; 
    $infRespTec = [ // Informações do Responsável Técnico (opcional)
        'CNPJ' => $cnpjEmitente,
        'xContato' => 'DIEGO RODRIGUES CRISTALDO',
        'fone' => '19989909456',
        'email' => 'diegorcristaldo@hotmail.com'
    ];

    if($ide['tpAmb'] == 1){
        $dadosVenda = [
            'ide' => $ide,
            'emit' => $emit,
            'itens' => $itensParaXml, 
            'total' => $totalArray,
            'pagamentos' => [], // Inicializa o array de pagamentos
            'troco' => $troco_final, // Troco da venda
            'infRespTec' => $infRespTec
        ];
    }
    else {
        $dadosVenda = [
            'ide' => $ide,
            'emit' => $emit,
            'dest' => $dest, //Só é incluida em Ambiente de homologação tpAmb = 2
            'itens' => $itensParaXml, 
            'total' => $totalArray,
            'pagamentos' => [], // Inicializa o array de pagamentos
            'troco' => $troco_final, // Troco da venda
            'infRespTec' => $infRespTec
        ];
    }

    // Monta os pagamentos para o XML
    $dadosVenda['pagamentos'] = [];
    $totalPagoNfce = 0;

    // Percorre todos os arrays simultaneamente
    $count = count($formas_pagamento);
    for ($i = 0; $i < $count; $i++) {
        $fp = $formas_pagamento[$i];
        
        if ($fp === 'Múltipla') {
            continue; // Pula "Múltipla"
        }
        
        $valor_pag = isset($valores_pagamento[$i]) ? (float)str_replace(',', '.', $valores_pagamento[$i]) : 0;
        $tPag = $payment_type_map[$fp];
        
        $pagamento = [
            'tPag' => $tPag, 
            'vPag' => $valor_pag
        ];
        
        // Adiciona estrutura específica para cartão
        if (in_array($tPag, ['03', '04'])) {
            // Para pagamentos múltiplos, use índice sequencial para dados do cartão
            // Precisa determinar o índice correto baseado na posição atual
            $cartaoIndex = getCartaoIndex($formas_pagamento, $i);
            
            $bandeira = $_POST['bandeira_extra'][$cartaoIndex] ?? $_POST['bandeira_cartao'] ?? '99';
            $ultimosDigitos = $_POST['ultimos_digitos_extra'][$cartaoIndex] ?? $_POST['ultimos_digitos'] ?? '';
            
            if (empty($bandeira)) {
                $bandeira = '99';
            }
            
            $pagamento['card'] = [
                'tpIntegra' => 2,
                'tBand' => $bandeira,
                'cAut' => 'AUT' . ($ultimosDigitos ?: str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT))
            ];
        }
        
        $dadosVenda['pagamentos'][] = $pagamento;
        $totalPagoNfce += $valor_pag;
    }

    // === VALIDAÇÕES DE CONSISTÊNCIA ===

    // CORREÇÃO ERRO 865 - Garante que total dos pagamentos >= total da NF
    if (!validarConsistenciaPagamentos($dadosVenda, $totalNF)) {
        $_SESSION['flash'] = 'Erro na consistência dos pagamentos. Contate o suporte.';
        header("Location: comprovante.php?id_venda=$venda_id");
        exit;
    }

    // CORREÇÃO ERRO 869 - Garante que vTroco = vPag - vNF
    if (!validarConsistenciaTroco($dadosVenda, $totalNF)) {
        $_SESSION['flash'] = 'Erro no cálculo do troco. Contate o suporte.';
        header("Location: comprovante.php?id_venda=$venda_id");
        exit;
    }

    // LOG FINAL
    $totalPagoFinal = 0;
    foreach ($dadosVenda['pagamentos'] as $pag) {
        $totalPagoFinal += $pag['vPag'];
    }
    error_log("VALIDAÇÃO FINAL: vNF=$totalNF, vPag=$totalPagoFinal, vTroco=" . $dadosVenda['troco']);

    // --- Início do processo de assinatura e envio via NFePHP ---
    if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
        $_SESSION['flash'] = 'Biblioteca NFePHP não encontrada. Execute: composer require nfephp-org/sped-nfe';
        header("Location: comprovante.php?id_venda=$venda_id");
        exit;
    }

    require __DIR__ . '/vendor/autoload.php';

    try {
        $configJson = file_get_contents($configJsonPath);
        $certContent = file_get_contents($pemPath);
        $certificate = carregarCertificadoPem($certContent, $pfxPassword);
        $tools = new Tools($configJson, $certificate);
        
        $tools->model(65); // Define o modelo como NFC-e

        // Aplica as validações
        validarConsistenciaPagamentos($dadosVenda, $totalNF);
        validarConsistenciaTroco($dadosVenda, $totalNF);

        // Gera o XML não-assinado.
        $xml = gerarXmlNfce($dadosVenda, $desconto);

        // --- MODIFICAÇÃO MANUAL DO XML PARA INCLUIR CARD ---
        $detPagPattern = '/<detPag>.*?<\/detPag>/s';
        preg_match_all($detPagPattern, $xml, $matches);

        if (isset($matches[0])) {
            foreach ($matches[0] as $index => $detPagOriginal) {
                if (isset($dadosVenda['pagamentos'][$index]) && 
                    in_array($dadosVenda['pagamentos'][$index]['tPag'], ['03', '04']) && 
                    isset($dadosVenda['pagamentos'][$index]['card'])) {
                    
                    $pag = $dadosVenda['pagamentos'][$index];
                    $cardXml = "<card>".
                            "<tpIntegra>{$pag['card']['tpIntegra']}</tpIntegra>".
                            "<tBand>{$pag['card']['tBand']}</tBand>".
                            "<cAut>{$pag['card']['cAut']}</cAut>".
                            "</card>";
                    
                    $detPagModificado = str_replace('</detPag>', $cardXml . '</detPag>', $detPagOriginal);
                    $xml = str_replace($detPagOriginal, $detPagModificado, $xml);                    
                }
            }
        }
        
        // Assina o XML.
        $xmlAssinado = $tools->signNFe($xml);

        // Salva o XML assinado.
        $xmlFileSigned = __DIR__ . '/xmls/venda_' . $venda_id . '_assinado.xml';
        file_put_contents($xmlFileSigned, $xmlAssinado);

        // Envia o lote para a SEFAZ (síncrono)
        $idLote = str_pad(random_int(1, 999999999), 15, '0', STR_PAD_LEFT);
        $resp = $tools->sefazEnviaLote([$xmlAssinado], $idLote, 1);
        
        // Processa a resposta da SEFAZ
        $st = new Standardize();
        
        // Remove o envelope SOAP para obter o XML puro
        $xmlResp = simplexml_load_string($resp);
        $namespaces = $xmlResp->getNamespaces(true);
        $body = $xmlResp->children($namespaces['soap'])->Body;
        $nfeResultMsg = $body->children('http://www.portalfiscal.inf.br/nfe/wsdl/NFeAutorizacao4')->nfeResultMsg;
        $retEnviNFe = $nfeResultMsg->children('http://www.portalfiscal.inf.br/nfe')->retEnviNFe;
        
        // Converte para XML string para processamento
        $xmlPure = $retEnviNFe->asXML();
        // === NOVO: Log simples do XML ===
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) mkdir($logDir, 0755, true);

        $xmlParaLog = preg_replace('/\s+/', ' ', $xmlPure); // Remove quebras de linha
        $logMessage = "[" . date('d-M-Y H:i:s e') . "] XML puro da resposta: " . trim($xmlParaLog) . PHP_EOL;
        file_put_contents($logDir . '/nfe_log.txt', $logMessage, FILE_APPEND);
        // === FIM do log ===        
        $std = $st->toStd($xmlPure);

        // Processa a resposta da SEFAZ
        if (isset($std->cStat)) {
            $cStat = (string)$std->cStat;

            if ($cStat === '100' || (isset($std->protNFe->infProt->cStat) && (string)$std->protNFe->infProt->cStat === '100')) {
                // NFC-e Autorizada
                $protocolo = (string)$std->protNFe->infProt->nProt;
                $chave = (string)$std->protNFe->infProt->chNFe;
                
                // Monta e salva o nfeProc
                $nfeProc = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
                $nfeProc .= str_replace('<?xml version="1.0" encoding="UTF-8"?>', '', $xmlAssinado);
                $nfeProc .= '<protNFe versao="4.00"><infProt><tpAmb>' . $dadosVenda['ide']['tpAmb'] . '</tpAmb><verAplic>' . ($std->protNFe->infProt->verAplic ?? '') . '</verAplic><chNFe>' . $chave . '</chNFe><dhRecbto>' . ($std->protNFe->infProt->dhRecbto ?? '') . '</dhRecbto><nProt>' . $protocolo . '</nProt><digVal>' . ($std->protNFe->infProt->digVal ?? '') . '</digVal><cStat>100</cStat><xMotivo>Autorizado o uso da NF-e</xMotivo></infProt></protNFe>';
                
                $autFile = __DIR__ . '/xml_autorizado/venda_' . $venda_id . '_autorizada.xml';
                file_put_contents($autFile, $nfeProc);

                // Atualiza o banco
                try {
                    $stmtUpd = $conn->prepare("UPDATE vendas SET chave_nfe = ?, protocolo = ?, status_nf = ? WHERE id = ?");
                    $status_nf_db = 'AUTORIZADA';
                    $stmtUpd->bind_param("sssi", $chave, $protocolo, $status_nf_db, $venda_id);
                    $stmtUpd->execute();
                } catch (Exception $e) {
                    error_log("Erro ao atualizar banco: " . $e->getMessage());
                }

                // GERA O DANFCE - Configurado corretamente para NFC-e
                try {
                    // Caminho para o logo (ajuste conforme sua estrutura)
                    $logoPath = realpath(__DIR__ . '/assets/logo.png');
                    if (!file_exists($logoPath)) {
                        // Se não tiver logo, use null
                        $logoPath = null;
                    }
                    
                    // Cria o DANFCE com configurações específicas para NFC-e
                    $danfce = new Danfce($xmlAssinado);
                    
                    // Configurações do DANFCE (igual ao exemplo)
                    $danfce->debugMode(false); // false em produção
                    $danfce->setPaperWidth(80); // largura do papel em mm (max=80, min=58)
                    $danfce->setMargins(2); // margens
                    $danfce->setDefaultFont('arial'); // fonte pode ser 'times' ou 'arial'
                    $danfce->setOffLineDoublePrint(false); // false para NFC-e online
                    
                    // Adiciona créditos do integrador (opcional)
                    $danfce->creditsIntegratorFooter('Sis Hort QFruta');
                    
                    // Renderiza o PDF
                    $pdfData = $danfce->render($logoPath);
                    
                    // Salva o DANFE em PDF
                    $pdfFile = __DIR__ . '/danfes/venda_' . $venda_id . '_danfe.pdf';
                    if (!is_dir(__DIR__ . '/danfes')) {
                        mkdir(__DIR__ . '/danfes', 0755, true);
                    }
                    file_put_contents($pdfFile, $pdfData);
                    
                    $_SESSION['flash'] = 'NFC-e Autorizada com sucesso! DANFE gerado.';
                    header("Location: comprovante.php?id_venda=$venda_id&nf=ok&danfe=" . urlencode($pdfFile));
                    exit();
                    
                } catch (Exception $e) {
                    error_log("Erro ao gerar DANFCE: " . $e->getMessage());
                    
                    // Mesmo sem DANFE, a NFC-e foi autorizada
                    $_SESSION['flash'] = 'NFC-e Autorizada com sucesso! (Erro técnico no DANFE: ' . $e->getMessage() . ')';
                    header("Location: comprovante.php?id_venda=$venda_id&nf=ok");
                    exit();
                }

            } elseif ($cStat === '103' || $cStat === '104') {
                // VERIFICA SE JÁ VEIO AUTORIZADA DIRETAMENTE NO protNFe
                if (isset($std->protNFe->infProt->cStat) && (string)$std->protNFe->infProt->cStat === '100') {
                    // NFC-e foi autorizada diretamente - processe como cStat 100
                    $protocolo = (string)$std->protNFe->infProt->nProt;
                    $chave = (string)$std->protNFe->infProt->chNFe;
                    
                    // Monta e salva o nfeProc
                    $nfeProc = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
                    $nfeProc .= str_replace('<?xml version="1.0" encoding="UTF-8"?>', '', $xmlAssinado);
                    $nfeProc .= '<protNFe versao="4.00"><infProt><tpAmb>' . $dadosVenda['ide']['tpAmb'] . '</tpAmb><verAplic>' . ($std->protNFe->infProt->verAplic ?? '') . '</verAplic><chNFe>' . $chave . '</chNFe><dhRecbto>' . ($std->protNFe->infProt->dhRecbto ?? '') . '</dhRecbto><nProt>' . $protocolo . '</nProt><digVal>' . ($std->protNFe->infProt->digVal ?? '') . '</digVal><cStat>100</cStat><xMotivo>Autorizado o uso da NF-e</xMotivo></infProt></protNFe>';
                    
                    $autFile = __DIR__ . '/xml_autorizado/venda_' . $venda_id . '_autorizada.xml';
                    file_put_contents($autFile, $nfeProc);

                    // Atualiza o banco de dados
                    try {
                        $stmtUpd = $conn->prepare("UPDATE vendas SET chave_nfe = ?, protocolo = ?, status_nf = ? WHERE id = ?");
                        $status_nf_db = 'AUTORIZADA';
                        $stmtUpd->bind_param("sssi", $chave, $protocolo, $status_nf_db, $venda_id);
                        $stmtUpd->execute();
                    } catch (Exception $e) {
                        error_log("Erro ao atualizar banco: " . $e->getMessage());
                    }

                    // GERA O DANFE
                    try {
                        $danfe = new Danfce($nfeProc);
                        $danfe->montaDANFE();
                        $pdfData = $danfe->render();
                        
                        // Salva o DANFE em PDF
                        $pdfFile = __DIR__ . '/danfes/venda_' . $venda_id . '_danfe.pdf';
                        if (!is_dir(__DIR__ . '/danfes')) {
                            mkdir(__DIR__ . '/danfes', 0755, true);
                        }
                        file_put_contents($pdfFile, $pdfData);
                        
                        $_SESSION['flash'] = 'NFC-e Autorizada com sucesso! DANFE gerado.';
                        header("Location: comprovante.php?id_venda=$venda_id&nf=ok&danfe=" . urlencode($pdfFile));
                        exit();
                        
                    } catch (Exception $e) {
                        error_log("Erro ao gerar DANFE: " . $e->getMessage());
                        $_SESSION['flash'] = 'NFC-e Autorizada, mas erro ao gerar DANFE: ' . $e->getMessage();
                        header("Location: comprovante.php?id_venda=$venda_id&nf=ok");
                        exit();
                    }
                }
                
                // Se não veio autorizada, então procura o nRec para consulta
                $nRec = null;
                if (isset($std->infRec->nRec)) {
                    $nRec = (string)$std->infRec->nRec;
                }
                    
                if ($nRec) {
                    // Salva o recibo no banco de dados
                    try {
                        $stmtUpd = $conn->prepare("UPDATE vendas SET protocolo = ?, status_nf = ? WHERE id = ?");
                        $status_nf_db = 'PROCESSANDO';
                        $stmtUpd->bind_param("ssi", $nRec, $status_nf_db, $venda_id);
                        $stmtUpd->execute();
                    } catch (Exception $e) {
                        error_log("Erro ao atualizar banco: " . $e->getMessage());
                    }
                    
                    sleep(2);
                    
                    try {
                        $consulta = $tools->sefazConsultaRecibo($nRec);
                        $stdCons = $st->toStd($consulta);

                        if (isset($stdCons->cStat) && (string)$stdCons->cStat === '100') { // Autorizado após consulta
                            $protocolo = (string)$stdCons->protNFe->infProt->nProt;
                            $chave = (string)$stdCons->protNFe->infProt->chNFe;

                            $nfeProc = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
                            $nfeProc .= str_replace('<?xml version="1.0" encoding="UTF-8"?>', '', $xmlAssinado);
                            $nfeProc .= '<protNFe versao="4.00"><infProt><tpAmb>' . $dadosVenda['ide']['tpAmb'] . '</tpAmb><verAplic>' . ($stdCons->protNFe->infProt->verAplic ?? '') . '</verAplic><chNFe>' . $chave . '</chNFe><dhRecbto>' . ($stdCons->protNFe->infProt->dhRecbto ?? '') . '</dhRecbto><nProt>' . $protocolo . '</nProt><digVal>' . ($stdCons->protNFe->infProt->digVal ?? '') . '</digVal><cStat>100</cStat><xMotivo>Autorizado o uso da NF-e</xMotivo></infProt></protNFe>';
                            
                            $autFile = __DIR__ . '/xml_autorizado/venda_' . $venda_id . '_autorizada.xml';
                            file_put_contents($autFile, $nfeProc);

                            // Atualiza o banco de dados
                            try {
                                $stmtUpd = $conn->prepare("UPDATE vendas SET chave_nfe = ?, protocolo = ?, status_nf = ? WHERE id = ?");
                                $status_nf_db = 'AUTORIZADA';
                                $stmtUpd->bind_param("sssi", $chave, $protocolo, $status_nf_db, $venda_id);
                                $stmtUpd->execute();
                            } catch (Exception $e) {
                                error_log("Erro ao atualizar banco: " . $e->getMessage());
                            }

                            // GERA O DANFE
                            try {
                                $danfe = new Danfce($nfeProc);
                                $danfe->montaDANFE();
                                $pdfData = $danfe->render();
                                
                                // Salva o DANFE em PDF
                                $pdfFile = __DIR__ . '/danfes/venda_' . $venda_id . '_danfe.pdf';
                                if (!is_dir(__DIR__ . '/danfes')) {
                                    mkdir(__DIR__ . '/danfes', 0755, true);
                                }
                                file_put_contents($pdfFile, $pdfData);
                                
                                $_SESSION['flash'] = 'NFC-e Autorizada com sucesso (via consulta)! DANFE gerado.';
                                header("Location: comprovante.php?id_venda=$venda_id&nf=ok&danfe=" . urlencode($pdfFile));
                                exit();
                                
                            } catch (Exception $e) {
                                error_log("Erro ao gerar DANFE: " . $e->getMessage());
                                $_SESSION['flash'] = 'NFC-e Autorizada, mas erro ao gerar DANFE: ' . $e->getMessage();
                                header("Location: comprovante.php?id_venda=$venda_id&nf=ok");
                                exit();
                            }
                        } else {
                            $motivo = isset($stdCons->xMotivo) ? (string)$stdCons->xMotivo : 'sem motivo';
                            $cStatCons = isset($stdCons->cStat) ? (string)$stdCons->cStat : 'N/A';
                            
                            $_SESSION['flash'] = 'NFC-e enviada, mas ainda processando ou rejeitada na consulta. Motivo: ' . $motivo . ' (Código: ' . $cStatCons . ')';
                            header("Location: comprovante.php?id_venda=$venda_id&nf=pendente");
                            exit();
                        }
                    } catch (Exception $e) {
                        $_SESSION['flash'] = 'Erro ao consultar recibo: ' . $e->getMessage();
                        header("Location: comprovante.php?id_venda=$venda_id&nf=pendente");
                        exit();
                    }
                    
                } else {
                    $_SESSION['flash'] = 'Erro NFe: Lote processado mas número do recibo não encontrado.';
                    header("Location: comprovante.php?id_venda=$venda_id");
                    exit();
                }
            } else {
                $_SESSION['flash'] = 'Erro ao enviar NFC-e: ' . ($std->xMotivo ?? 'erro desconhecido');
                header("Location: comprovante.php?id_venda=$venda_id");
                exit();
            }
        } else {
            $_SESSION['flash'] = 'Erro NFe: Resposta da Sefaz incompleta ou inválida.';
            header("Location: comprovante.php?id_venda=$venda_id");
            exit();
        }

        // Commit da transação
        $conn->commit();
        
        // Limpa token e redireciona
        unset($_SESSION['form_token']);
        $_SESSION['ultima_venda_id'] = $venda_id;
        
        header("Location: comprovante.php?id_venda=" . $venda_id);
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("ERRO VENDA: " . $e->getMessage());
        unset($_SESSION['form_token']); // Permite nova tentativa
        die("Erro ao processar venda: " . $e->getMessage());
    }
}

// Configurações para links de navegação na interface.
$uri = $_SERVER['REQUEST_URI'];
$uri .= (strpos($uri, '?') !== false) ? '&duplicado=' . time() : '?duplicado=' . time();

$lista_links = listaLinks($uri, $usuario_tipo);
require 'view/header.php';
?>
<div class="container mt-4">
    <h2>💳 Registrar Venda</h2>
    <p class="text-muted mb-0" id="info-caixa">Caixa: Aberto • Operador: <?php echo $_SESSION['usuario'] ?? 'Usuário'; ?></p>
    <form method="POST">
        <input type="hidden" name="form_token" value="<?php echo $_SESSION['form_token']; ?>">
        <div class="d-flex flex-row justify-content-between align-items-center border p-1">
            <div class="mb-4 p-1">
                <label class="form-label"><i class="bi bi-upc-scan me-2"></i>Buscar por Código de Barras</label>
                <input type="text" id="codigo_barras" class="form-control" onkeypress="handleKeyPress(event)" autofocus autocomplete="off" placeholder="Escaneie ou digite o código de barras">
            </div>

            <div class="mb-4 p-1">
                <label class="form-label"><i class="bi bi-search me-2"></i>Buscar Produto por nome</label>
                <input type="text" id="busca_produto" class="form-control" placeholder="Digite o nome ou descrição" onkeypress="handleKeyPressBuscaNome(event)">
            </div>

            <div class="mb-4 p-1">
                <label class="form-label"><i class="bi bi-hash me-2"></i>Buscar Produto por Código do Produto</label>
                <input type="number" id="busca_produto_id" class="form-control" placeholder="Digite o ID do produto" onkeypress="handleKeyPressBuscaId(event)">
            </div>
        </div>

        <div id="resultados-busca" class="mb-3"></div>

        <h5 class="d-flex align-items-center"><i class="bi bi-cart4 me-2"></i>Produtos na Venda</h5>
        <div id="lista-produtos" class="mb-3"></div>

        <div class="mb-3">
            <label class="form-label"><i class="bi bi-tag me-2"></i>Desconto Total (R$)</label>
            <input type="number" step="0.01" min="0" name="desconto" id="desconto" class="form-control" value="0" oninput="atualizarTotal()">
        </div>

        <div class="mt-3">
            <h3><strong>Total: <span id="total">0.00</span></strong></h3>
        </div>

        <div class="mb-3 mt-3">
            <label class="form-label"><i class="bi bi-credit-card me-2"></i>Forma de Pagamento</label>
            <select class="form-select" name="forma_pagamento[]" id="forma_pagamento_principal" required onchange="verificarMultipla(this)">
                <option value="Cartão de Crédito">💳 Cartão de Crédito</option>
                <option value="Cartão de Débito">💳 Cartão de Débito</option>
                <option value="PIX">📱 PIX</option>
                <option value="Dinheiro">💵 Dinheiro</option>
                <option value="Múltipla">🔀 Dividir Pagamento</option>
            </select>
            </div>

            <!-- NOVO: Campos para dados do cartão (ocultos inicialmente) -->
            <div id="dados-cartao" style="display: none; margin-top: 10px; border: 1px solid #ddd; padding: 15px; border-radius: 5px;">
                <h6>💳 Dados do Cartão</h6>
                <div class="row">
                    <div class="col-md-6">
                        <label class="form-label">Bandeira do Cartão</label>
                        <select class="form-select" name="bandeira_cartao" id="bandeira_cartao" required>
                            <option value="">Selecione a bandeira</option>
                            <option value="01">💳 Visa</option>
                            <option value="02">💳 Mastercard</option>
                            <option value="03">💳 American Express</option>
                            <option value="06">💳 Elo</option>
                            <option value="07">💳 Hipercard</option>
                            <option value="99">💳 Outros</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Últimos 4 dígitos</label>
                        <input type="text" class="form-control" name="ultimos_digitos" id="ultimos_digitos" 
                            maxlength="4" pattern="[0-9]{4}" placeholder="0000" required
                            oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                    </div>
                    <div class="mb-3 mt-3" id="valor_total_container" style="margin-top: 10px; display: none;">
                        <label class="form-label">Valor total dessa forma:</label>
                        <input class="form-control" type="text" name="valor_pago[]" placeholder="Valor pago (R$)">
                    </div>
                </div>
                <small class="text-muted">Informações obrigatórias para pagamento com cartão.</small>
            </div>

            <!-- Bloco onde outras formas de pagamento serão adicionadas -->
            <div id="pagamentos-extras" style="margin-top: 20px;"></div>

            <!-- Botão para adicionar mais formas -->
            <div class="mb-3 mt-3" id="botao-adicionar" style="display: none; margin-top: 10px;">
                <button type="button" class="btn btn-primary" onclick="adicionarPagamento()"><i class="bi bi-plus-circle me-2"></i>Adicionar Forma de Pagamento</button>
            </div>
            
            <div class="mb-3 mt-3" id="valor-pago-div" style="display:none;">
                <label class="form-label"><i class="bi bi-cash-coin me-2"></i>Valor Pago (R$)</label>
                <input type="number" step="0.01" min="0" id="valor-pago" class="form-control" placeholder="Digite o valor pago" oninput="calcularTroco()">
            </div>

            <div class="mb-3 mt-3" id="troco-div" style="display:none;">
                <h5><i class="bi bi-arrow-left-right me-2"></i><strong>Troco: <span id="troco">0.00</span></strong></h5>
            </div>

            <input type="hidden" name="troco_final" id="troco_final" value="0.00">
            <button type="button" class="btn btn-success" onclick="confirmarFinalizacao()"><i class="bi bi-check-circle me-2"></i>Finalizar Venda</button>
        </div>

    </form>
</div>
<script src="assets/registrar_venda.js"></script>
</body>
</html>