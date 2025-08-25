<?php
use NFePHP\NFe\Tools;
use NFePHP\Common\Certificate;
use NFePHP\NFe\Common\Standardize;

include 'verifica_login.php';
include 'config.php'; 

// --- DEBUG PARA VERIFICAR O CONTEÚDO BRUTO DE funcoes_caixa.php NO DISCO ---
// Isso nos ajudará a confirmar se a versão EXATA do arquivo está sendo carregada no DISCO.
$funcoesCaixaContent = file_get_contents(__DIR__ . '/funcoes_caixa.php');
if (strpos($funcoesCaixaContent, '// VERIFICACAO_FINAL_20250824_2045') === false) { // Atualizado para o novo identificador
    echo "<pre>ERRO CRÍTICO: O arquivo funcoes_caixa.php carregado NO DISCO NÃO É A VERSÃO MAIS RECENTE!
Por favor, verifique se você copiou o código do Canvas 'Atualização FINAL para funcoes_caixa.php' **COMPLETAMENTE** para seu arquivo funcoes_caixa.php, salvou, e reiniciou o servidor.
Se o problema persistir, pode haver um problema de cache do servidor ou múltiplas cópias do arquivo.
Conteúdo carregado de funcoes_caixa.php (primeiras 25 linhas):
" . htmlspecialchars(implode("\n", array_slice(explode("\n", $funcoesCaixaContent), 0, 25))) . "</pre>";
    exit();
}
// --- FIM DO DEBUG NO DISCO ---

include 'funcoes_caixa.php'; // Inclui a versão de funcoes_caixa.php que foi verificada acima

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

// Processamento da requisição POST para registrar a venda.
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $quantidades = $_POST['quantidade'] ?? []; // Quantidades de cada produto vendido
    $desconto = isset($_POST['desconto']) ? (float)$_POST['desconto'] : 0; // Desconto total na venda
    $formas_pagamento = $_POST['forma_pagamento'] ?? []; // Formas de pagamento
    $valores_pagamento = $_POST['valor_pago'] ?? []; // Valores pagos por cada forma
    $total = 0; // Inicializa o total da venda

    $troco_final = isset($_POST['troco_final']) ? (float)$_POST['troco_final'] : 0; // Troco calculado

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
        $stmt_venda = $conn->prepare("INSERT INTO vendas (total, forma_pagamento, desconto, caixa_id, operador_id) VALUES (?, ?, ?, ?, ?)");
        $stmt_venda->bind_param("dssii", $total, $forma, $desconto, $caixa_id, $operador_id);
        $stmt_venda->execute();
        $venda_id = $stmt_venda->insert_id;
    } else { // Caso seja pagamento múltiplo
        $stmt_venda = $conn->prepare("INSERT INTO vendas (total, forma_pagamento, desconto, caixa_id, operador_id) VALUES (?, 'Multipla', ?, ?, ?)");
        $stmt_venda->bind_param("dsii", $total, $desconto, $caixa_id, $operador_id);
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

    // Calcula total e monta array de itens para o XML da NFC-e.
    $itensParaXml = [];
    foreach ($quantidades as $id => $qtd) {
        $id = (int)$id;
        $qtd = (float)$qtd;
        if ($qtd <= 0) continue;

        $stmt_produto->bind_param("i", $id);
        $stmt_produto->execute();
        $res = $stmt_produto->get_result();
        $produto = $res->fetch_assoc();

        $preco = (float)$produto['preco'];

        $itensParaXml[] = [
            'id' => $produto['id'],
            'cProd' => $produto['id'],
            'cEAN' => $produto['codigo_barras'] ?? '',
            'xProd' => $produto['nome'], 
            'NCM' => $produto['ncm'] ?? '07099990', // NCM padrão para produtos hortícolas
            'CFOP' => $produto['cfop'] ?? '5102',   // CFOP padrão para venda dentro do estado
            'uCom' => $produto['unidade_medida'] ?? 'UN',
            'qCom' => $qtd,
            'vUnCom' => $preco,
            'vProd' => $preco * $qtd,
            'indTot' => 1
        ];
    }

    // Define as configurações para a NFePHP.
    $cnpjEmitente = $dados['cnpj']; 
    $configJsonPath = __DIR__ . $dados['configJson']; 
    $pfxPath = __DIR__ . $dados['certificadoPfx']; 
    $pfxPassword = $dados['senhaPfx']; 

    // Mapeamento de formas de pagamento para tPag (padrão SEFAZ).
    $payment_type_map = [
        'Dinheiro'          => '01',
        'Cartão de Crédito' => '03',
        'Cartão de Débito'  => '04',
        'PIX'               => '16', 
        'Múltipla'          => '99', 
    ];

    // Geração do nNF (Número da Nota Fiscal). Em produção, deve ser sequencial.
    $nNF_gerado = random_int(1, 999999999); 
    $nNF_gerado = str_pad($nNF_gerado, 1, '1', STR_PAD_LEFT); 

    // Monta o array de dados da venda para o XML da NFC-e.
    $dadosVenda = [
        'ide' => [
            'cUF' => '21', // Código da UF (MA - Maranhão), fixo para o emitente.
            'cNF' => str_pad(rand(0,99999999), 8, '0', STR_PAD_LEFT), // Código Numérico da NF
            'natOp' => 'VENDA', 'indPag' => 0, 'mod' => '65',
            'serie' => 1, 
            'nNF' => $nNF_gerado,
            'dhEmi' => date('Y-m-d\TH:i:sP'), // Data e hora de emissão com fuso horário
            'dhSaiEnt' => date('Y-m-d\TH:i:sP'), // Data e hora de saída/entrada com fuso horário
            'tpNF' => 1, 'idDest' => 1, 
            'cMunFG' => $dados['enderecoEmitente']['cMun'], // Código IBGE do município do emitente
            'tpImp' => 1, 'tpEmis' => 1, 
            // A linha 'cDV' foi REMOVIDA para que a NFePHP calcule automaticamente.
            'tpAmb' => 2, // 1 = Produção, 2 = Homologação
            'finNFe' => 1, 'indFinal' => 1, 'indPres' => 1, 'procEmi' => 0, 'verProc' => 'PDV-1.0'
        ],
        'emit' => [
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
        ],
        'dest' => [ // Dados do destinatário (consumidor)
            'CPF' => '02914577117', // CPF do Diego (PARA TESTE).
            // 'xNome' e 'indIEDest' NÃO DEVEM ser incluídos aqui em ambiente de produção
            // se o CPF for informado, a Sefaz pode rejeitar ou sobrescrever.
        ],
        'itens' => $itensParaXml, // Itens da venda para o XML
        'total' => [ // Totais da NFC-e
            'vProd' => number_format(array_sum(array_column($itensParaXml, 'vProd')), 2, '.', ''),
            'vNF' => number_format($total, 2, '.', ''),
            'vBC' => 0, // Base de cálculo do ICMS (Simples Nacional)
            'vICMS' => 0 // Valor do ICMS (Simples Nacional)
        ],
        'pagamentos' => [], // Inicializa o array de pagamentos
        'troco' => $troco_final, // Troco da venda
        'infRespTec' => [ // Informações do Responsável Técnico (opcional)
            'CNPJ' => $cnpjEmitente,
            'xContato' => 'DIEGO RODRIGUES CRISTALDO',
            'fone' => '19989909456',
            'email' => 'diegorcristaldo@hotmail.com'
        ]
    ];

    // Monta os pagamentos para o XML, preenchendo o array 'pagamentos' em $dadosVenda.
    $dadosVenda['pagamentos'] = []; 
    $totalPagoNfce = 0;

    foreach ($formas_pagamento as $i => $fp) {
        $valor_pag = isset($valores_pagamento[$i]) ? (float)str_replace(',', '.', $valores_pagamento[$i]) : $total;
        $tPag = $payment_type_map[$fp] ?? '99'; 
        
        $dadosVenda['pagamentos'][] = [
            'tPag' => $tPag, 
            'vPag' => $valor_pag
        ];
        
        $totalPagoNfce += $valor_pag;
    }

    // Calcula o troco final para a NFC-e.
    $trocoNfce = 0;
    if ($totalPagoNfce > $total) {
        $trocoNfce = $totalPagoNfce - $total;
    }

    $dadosVenda['troco'] = $trocoNfce;

    // --- Início do processo de assinatura và envio via NFePHP ---
    if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
        $_SESSION['flash'] = 'Biblioteca NFePHP não encontrada. Execute: composer require nfephp-org/sped-nfe';
        header("Location: comprovante.php?id_venda=$venda_id");
        exit;
    }

    require __DIR__ . '/vendor/autoload.php';

    try {
        $configJson = file_get_contents($configJsonPath);
        $certContent = file_get_contents($pfxPath);
        $certificate = Certificate::readPfx($certContent, $pfxPassword);
        $tools = new Tools($configJson, $certificate);
        
        $tools->model(65); // Define o modelo como NFC-e

        // Gera o XML não-assinado.
        $xml = gerarXmlNfce($dadosVenda);
        
        // --- ADIÇÃO PARA DIAGNÓSTICO: SALVA O XML GERADO PARA INSPEÇÃO ---
        if (!is_dir(__DIR__ . '/xmls')) mkdir(__DIR__ . '/xmls', 0750, true);
        file_put_contents(__DIR__ . '/xmls/last_generated_nfce.xml', $xml);
        error_log("XML gerado para NFC-e (para debug): " . $xml); // Log to PHP error log as well
        // --- FIM DA ADIÇÃO PARA DIAGNÓSTICO ---

        // Assina o XML.
        $xmlAssinado = $tools->signNFe($xml);

        // Salva o XML assinado.
        $xmlFileSigned = __DIR__ . '/xmls/venda_' . $venda_id . '_assinado.xml';
        file_put_contents($xmlFileSigned, $xmlAssinado);

        // Envia o lote para a SEFAZ. O último parâmetro '1' indica envio síncrono.
        $idLote = str_pad(random_int(1, 999999999), 15, '0', STR_PAD_LEFT);
        $resp = $tools->sefazEnviaLote([$xmlAssinado], $idLote, 1); // Alterado para 1 (síncrono)

        // Salva a resposta bruta da SEFAZ para diagnóstico.
        file_put_contents(__DIR__ . '/xmls/sefaz_response_raw_' . $venda_id . '.xml', $resp);
        
        // --- DEBUG DETALHADO DA RESPOSTA ---
        error_log("Resposta bruta da SEFAZ: " . $resp);
        $dom = new DOMDocument();
        $dom->loadXML($resp);
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('nfe', 'http://www.portalfiscal.inf.br/nfe');
        
        // Tenta encontrar o recibo de várias formas
        $nRec = $xpath->evaluate('string(//nfe:infRec/nfe:nRec)');
        if (empty($nRec)) {
            $nRec = $xpath->evaluate('string(//nRec)');
        }
        if (empty($nRec)) {
            $nRec = $xpath->evaluate('string(//*[local-name()="nRec"])');
        }
        
        error_log("nRec encontrado: " . ($nRec ?: 'NÃO ENCONTRADO'));
        error_log("Estrutura completa da resposta: " . $dom->saveXML());
        // --- FIM DEBUG ---

        $st = new Standardize();
        $std = $st->toStd($resp);

        // Processa a resposta da SEFAZ.
        if (isset($std->cStat)) {
            $cStat = (string)$std->cStat;

            if ($cStat === '100') { // NFC-e Autorizada
                $protocolo = (string)$std->protNFe->infProt->nProt;
                
                $dom = new DOMDocument();
                $dom->loadXML($xmlAssinado);
                $infNFe = $dom->getElementsByTagName('infNFe')->item(0);
                $idAttr = $infNFe ? $infNFe->getAttribute('Id') : null;
                $chave = $idAttr ? substr($idAttr, 3) : null;
                
                // Monta e salva o nfeProc (XML assinado + protocolo de autorização).
                $nfeProc = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
                $nfeProc .= str_replace('<?xml version="1.0" encoding="UTF-8"?>', '', $xmlAssinado);
                $nfeProc .= '<protNFe versao="4.00"><infProt><tpAmb>' . $dadosVenda['ide']['tpAmb'] . '</tpAmb><verAplic>' . ($std->protNFe->infProt->verAplic ?? '') . '</verAplic><chNFe>' . $chave . '</chNFe><dhRecbto>' . ($std->protNFe->infProt->dhRecbto ?? '') . '</dhRecbto><nProt>' . $protocolo . '</nProt><digVal>' . ($std->protNFe->infProt->digVal ?? '') . '</digVal><cStat>100</cStat><xMotivo>Autorizado o uso da NF-e</xMotivo></infProt></protNFe>';
                $autFile = __DIR__ . '/xmls/venda_' . $venda_id . '_autorizada.xml';
                file_put_contents($autFile, $nfeProc);

                // Atualiza o banco de dados com a chave e protocolo da NFC-e.
                try {
                    $stmtUpd = $conn->prepare("UPDATE vendas SET chave_nfe = ?, protocolo = ?, status_nf = ? WHERE id = ?");
                    $status_nf_db = 'AUTORIZADA';
                    $stmtUpd->bind_param("sssi", $chave, $protocolo, $status_nf_db, $venda_id);
                    $stmtUpd->execute();
                } catch (Exception $e) { /* Ignora erros de atualização do DB */ }

                $_SESSION['flash'] = 'NFC-e Autorizada com sucesso!';
                header("Location: comprovante.php?id_venda=$venda_id&nf=ok");
                exit();

            } elseif ($cStat === '103' || $cStat === '104') { // Lote Recebido ou Processado (requer consulta por recibo)
                $recibo = null;
                
                // DEBUG: Verificar a estrutura completa da resposta
                error_log("Estrutura completa do std: " . print_r($std, true));
                
                // Tenta obter o recibo de diferentes formas possíveis na resposta
                if (isset($std->infRec->nRec)) {
                    $recibo = (string)$std->infRec->nRec;
                    error_log("Recibo encontrado em std->infRec->nRec: " . $recibo);
                } elseif (isset($std->nRec)) {
                    $recibo = (string)$std->nRec;
                    error_log("Recibo encontrado em std->nRec: " . $recibo);
                } else {
                    // Tentar encontrar o recibo manualmente na resposta XML
                    $domResp = new DOMDocument();
                    $domResp->loadXML($resp);
                    $xpath = new DOMXPath($domResp);
                    $xpath->registerNamespace('nfe', 'http://www.portalfiscal.inf.br/nfe');
                    
                    $nodes = $xpath->query('//nfe:infRec/nfe:nRec');
                    if ($nodes->length > 0) {
                        $recibo = $nodes->item(0)->nodeValue;
                        error_log("Recibo encontrado via XPath: " . $recibo);
                    } else {
                        $nodes = $xpath->query('//nRec');
                        if ($nodes->length > 0) {
                            $recibo = $nodes->item(0)->nodeValue;
                            error_log("Recibo encontrado via XPath (sem namespace): " . $recibo);
                        }
                    }
                }
                
                if ($recibo) {
                    // Salva o recibo no banco de dados.
                    try {
                        $stmtUpd = $conn->prepare("UPDATE vendas SET protocolo = ?, status_nf = ? WHERE id = ?");
                        $status_nf_db = 'PROCESSANDO';
                        $stmtUpd->bind_param("ssi", $recibo, $status_nf_db, $venda_id);
                        $stmtUpd->execute();
                    } catch (Exception $e) { /* Ignora */ }

                    sleep(2); // Pequena pausa antes de consultar
                    
                    try {
                        $consulta = $tools->sefazConsultaRecibo($recibo);
                        $stdCons = $st->toStd($consulta);

                        if (isset($stdCons->cStat) && (string)$stdCons->cStat === '100') { // Autorizado após consulta
                            $protocolo = (string)$stdCons->protNFe->infProt->nProt;
                            
                            $dom = new DOMDocument();
                            $dom->loadXML($xmlAssinado);
                            $infNFe = $dom->getElementsByTagName('infNFe')->item(0);
                            $idAttr = $infNFe ? $infNFe->getAttribute('Id') : null;
                            $chave = $idAttr ? substr($idAttr, 3) : null;

                            $nfeProc = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
                            $nfeProc .= str_replace('<?xml version="1.0" encoding="UTF-8"?>', '', $xmlAssinado);
                            $nfeProc .= '<protNFe versao="4.00"><infProt><tpAmb>' . $dadosVenda['ide']['tpAmb'] . '</tpAmb><verAplic>' . ($stdCons->protNFe->infProt->verAplic ?? '') . '</verAplic><chNFe>' . $chave . '</chNFe><dhRecbto>' . ($stdCons->protNFe->infProt->dhRecbto ?? '') . '</dhRecbto><nProt>' . $protocolo . '</nProt><digVal>' . ($stdCons->protNFe->infProt->digVal ?? '') . '</digVal><cStat>100</cStat><xMotivo>Autorizado o uso da NF-e</xMotivo></infProt></protNFe>';
                            $autFile = __DIR__ . '/xmls/venda_' . $venda_id . '_autorizada.xml';
                            file_put_contents($autFile, $nfeProc);

                            try {
                                $stmtUpd = $conn->prepare("UPDATE vendas SET chave_nfe = ?, protocolo = ?, status_nf = ? WHERE id = ?");
                                $status_nf_db = 'AUTORIZADA';
                                $stmtUpd->bind_param("sssi", $chave, $protocolo, $status_nf_db, $venda_id);
                                $stmtUpd->execute();
                            } catch (Exception $e) { /* Ignora */ }

                            $_SESSION['flash'] = 'NFC-e Autorizada com sucesso (via consulta)!';
                            header("Location: comprovante.php?id_venda=$venda_id&nf=ok");
                            exit();
                        } else { // Rejeitada na consulta ou ainda processando
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
                } else { // CStat 103 ou 104 sem recibo
                    $_SESSION['flash'] = 'Erro NFe: Lote recebido/processado, mas número do recibo não encontrado na resposta da Sefaz. Motivo: ' . ($std->xMotivo ?? 'sem motivo');
                    header("Location: comprovante.php?id_venda=$venda_id");
                    exit();
                }

            } else { // Outros cStat de erro
                $_SESSION['flash'] = 'Erro ao enviar NFC-e: ' . ($std->xMotivo ?? 'erro desconhecido');
                $_SESSION['flash'] .= '<br>Verifique o arquivo `xmls/sefaz_response_raw_' . $venda_id . '.xml` para detalhes da resposta da SEFAZ.';
                header("Location: comprovante.php?id_venda=$venda_id");
                exit();
            }
        } else { // Resposta da SEFAZ incompleta
            $_SESSION['flash'] = 'Erro NFe: Resposta da Sefaz incompleta ou inválida. Não foi possível determinar o cStat.';
            $_SESSION['flash'] .= '<br>Verifique o arquivo `xmls/sefaz_response_raw_' . $venda_id . '.xml` para detalhes da resposta da SEFAZ.';
            header("Location: comprovante.php?id_venda=$venda_id");
            exit();
        }
    } catch (Exception $e) { // Erros gerais de exceção
        $_SESSION['flash'] = 'Erro NFe: ' . $e->getMessage();
        $_SESSION['flash'] .= '<br>Verifique o log de erros do seu servidor PHP para mais detalhes.';
        header("Location: comprovante.php?id_venda=$venda_id");
        exit(); 
    }
}

// Configurações para links de navegação na interface.
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
            <select class="form-select" name="forma_pagamento[]" id="forma_pagamento_principal" required onchange="verificarMultipla(this)">
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

            <input type="hidden" name="troco_final" id="troco_final" value="0.00">
            <button type="button" class="btn btn-success" onclick="confirmarFinalizacao()">Finalizar Venda</button>
        </div>

    </form>
</div>

<script src="assets/registrar_venda.js"></script>

</body>
</html>
