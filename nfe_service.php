<?php
require 'config.php';
require __DIR__ . '/vendor/autoload.php';

use NFePHP\NFe\Tools;
use NFePHP\Common\Certificate;

/**
 * Carrega as configurações do arquivo config.json.
 * @return object O objeto de configuração.
 */
function getConfig(): object
{
    global $dados;

    $configJsonPath = __DIR__ . $dados['configJson'];
    if (!file_exists($configJsonPath)) {
        throw new Exception("Arquivo de configuração 'config.json' não encontrado.");
    }
    $config = json_decode(file_get_contents($configJsonPath));
    return $config;
}

/**
 * Retorna uma instância da classe NFePHP\NFe\Tools.
 * @return Tools
 */
function getNfeTools(): Tools
{
    global $dados;

    $config = getConfig();

    // Prioridade: PEM > PFX
    $certPemPath = __DIR__ . '/certificados/certificado.pem';
    $certPfxPath = __DIR__ . $dados['certificadoPfx'];
    
    if (file_exists($certPemPath)) {
        // Usa o certificado PEM convertido
        echo "Usando certificado PEM...\n";
        $certContent = file_get_contents($certPemPath);
        $cert = Certificate::readPfx($certContent);
    } elseif (file_exists($certPfxPath)) {
        // Fallback para PFX (com tratamento de erro)
        echo "Usando certificado PFX (fallback)...\n";
        $pfxContent = file_get_contents($certPfxPath);
        $senhaPfx = $dados['senhaPfx'];
        
        // Tenta ler via OpenSSL direto primeiro
        if (openssl_pkcs12_read($pfxContent, $certs, $senhaPfx)) {
            $cert = Certificate::createFromPKey($certs['pkey'], $certs['cert']);
        } else {
            // Última tentativa com a biblioteca
            $cert = Certificate::readPfx($pfxContent, $senhaPfx);
        }
    } else {
        throw new Exception("Nenhum certificado encontrado. Execute o script de conversão primeiro.");
    }

    $tools = new Tools(
        json_encode($config),
        $config->tpAmb,
        null,
        $cert
    );

    return $tools;
}

// Helper: monta XML básico NFC-e com NFePHP\NFe\Make (simplificado)
function gerarXmlNfce($dadosVenda, $desconto) {
    $nfe = new \NFePHP\NFe\Make();

    // -----------------------
    // InfNFe
    // -----------------------
    $inf = new \stdClass();
    $inf->versao = '4.00'; 
    $inf->Id = null; // NFePHP gera a chave automaticamente
    $nfe->taginfNFe($inf);

    // -----------------------
    // Identificação (IDE)
    // -----------------------
    $ide = new \stdClass();
    $ide->cUF = $dadosVenda['ide']['cUF'];
    $ide->cNF = $dadosVenda['ide']['cNF'];
    $ide->natOp = $dadosVenda['ide']['natOp'];
    $ide->mod = $dadosVenda['ide']['mod'];
    $ide->serie = $dadosVenda['ide']['serie'];
    $ide->nNF = $dadosVenda['ide']['nNF'];
    $ide->dhEmi = $dadosVenda['ide']['dhEmi'];
    $ide->dhSaiEnt = $dadosVenda['ide']['dhSaiEnt'] ?? $dadosVenda['ide']['dhEmi'];
    $ide->tpNF = $dadosVenda['ide']['tpNF'];
    $ide->idDest = $dadosVenda['ide']['idDest'];
    $ide->cMunFG = $dadosVenda['emit']['enderEmit']['cMun'];
    $ide->tpImp = $dadosVenda['ide']['tpImp'];
    $ide->tpEmis = $dadosVenda['ide']['tpEmis'];
    // $ide->cDV foi REMOVIDO daqui e não será mais adicionado. NFePHP calcula automaticamente.
    
    $ide->tpAmb = $dadosVenda['ide']['tpAmb'];
    $ide->finNFe = $dadosVenda['ide']['finNFe'];
    $ide->indFinal = 1; // Sempre 1 (Consumidor final) para NFC-e
    $ide->indPres = 1; // Sempre 1 (Operação presencial) para NFC-e
    $ide->procEmi = 0; // Sempre 0 (emissor de NFe com aplicativo próprio)
    $ide->verProc = 'PDV-1.0'; // Versão do processo de emissão

    $nfe->tagide($ide);

    // -----------------------
    // Emitente
    // -----------------------
    $emit = new \stdClass();
    $emit->CNPJ = $dadosVenda['emit']['CNPJ'];
    $emit->xNome = $dadosVenda['emit']['xNome'];
    $emit->xFant = $dadosVenda['emit']['xFant'];
    $emit->IE    = $dadosVenda['emit']['IE']; // IE do EMITENTE
    $emit->CRT   = $dadosVenda['emit']['CRT'] ?? 1; // CRT: 1=Simples Nacional, 2=Simples Nacional excesso sublimite, 3=Normal. ADAPTE AO SEU REGIME TRIBUTÁRIO.
    
    $nfe->tagemit($emit);

    // Adiciona o endereço (enderEmit) DENTRO do <emit>
    $enderEmit = new \stdClass();
    $enderEmit->xLgr = $dadosVenda['emit']['enderEmit']['xLgr'] ?? '';
    $enderEmit->nro = $dadosVenda['emit']['enderEmit']['nro'] ?? '';
    $enderEmit->xCpl = $dadosVenda['emit']['enderEmit']['xCpl'] ?? ''; 
    $enderEmit->xBairro = $dadosVenda['emit']['enderEmit']['xBairro'] ?? '';
    $enderEmit->cMun = $dadosVenda['emit']['enderEmit']['cMun'] ?? '';
    $enderEmit->xMun = $dadosVenda['emit']['enderEmit']['xMun'] ?? '';
    $enderEmit->UF = $dadosVenda['emit']['enderEmit']['UF'] ?? '';
    $enderEmit->CEP = $dadosVenda['emit']['enderEmit']['CEP'] ?? '';
    $enderEmit->cPais = '1058'; // Código do Brasil
    $enderEmit->xPais = 'BRASIL';
    $enderEmit->fone = $dadosVenda['emit']['enderEmit']['fone'] ?? ''; // Opcional, mas bom ter
    $nfe->tagenderEmit($enderEmit); 

    /*
    // -----------------------
    // Destinatário
    // -----------------------
    $cpf_dest = $dadosVenda['dest']['CPF'] ?? null;
    
    $dest = new \stdClass();
    
    if (!empty($cpf_dest) && $cpf_dest !== '00000000000') {
        $dest->CPF = $cpf_dest;
        // A NFePHP e/ou a Sefaz irão lidar com xNome e indIEDest em ambiente de teste.
    } else {
        // Consumidor não identificado (CPF nulo ou '000...0'):
        // O objeto $dest permanece vazio.
        // A NFePHP (se tpAmb=2) adicionará 'xNome' (NF-E EMITIDA EM AMBIENTE DE HOMOLOGACAO...)
        // e 'indIEDest=9' automaticamente.
    }
    
    $nfe->tagdest($dest);*/

    // -----------------------
    // Produtos/Itens
    // -----------------------
    foreach ($dadosVenda['itens'] as $i => $item) {
        $prod = new \stdClass();
        $prod->item     = $i + 1;
        $prod->cProd    = $item['cProd'] ?? $item['id'];
        $prod->cEAN     = $item['cEAN'] ?? 'SEM GTIN';
        $prod->xProd    = $item['xProd'];
        $prod->NCM      = $item['NCM'] ?? '07099990';//Outros produtos horticulas
        $prod->CFOP     = $item['CFOP'] ?? '5102';
        $prod->uCom     = $item['uCom'] ?? 'UN';
        $prod->qCom     = number_format($item['qCom'], 4, '.', '');
        $prod->vUnCom   = number_format($item['vUnCom'], 2, '.', '');
        $prod->vProd    = number_format($item['vProd'], 2, '.', '');
        if($desconto > 0){
            $prod->vDesc    = number_format($item['vDesc'], 2, '.', '');
        }
        $prod->cEANTrib = $item['cEANTrib'] ?? 'SEM GTIN';
        $prod->uTrib    = $item['uTrib'] ?? $prod->uCom;
        $prod->qTrib    = number_format($item['qTrib'] ?? $item['qCom'], 4, '.', '');
        $prod->vUnTrib  = number_format($item['vUnTrib'] ?? $item['vUnCom'], 2, '.', '');
        $prod->indTot   = $item['indTot'] ?? 1;

        $nfe->tagprod($prod);

        // Imposto para Simples Nacional (CSOSN=102)
        $imposto = new \stdClass();
        $imposto->item = $i + 1;
        $imposto->vTotTrib = 0.00; // Valor aproximado dos tributos, pode ser calculado se necessário
        $nfe->tagimposto($imposto);

        $icmssn = new \stdClass();
        $icmssn->item = $i + 1;
        $icmssn->orig = 0; // Origem da mercadoria (0=Nacional)
        $icmssn->CSOSN = '102'; // Simples Nacional - Tributada pelo Simples Nacional sem permissão de crédito
        $nfe->tagICMSSN($icmssn);

        // PIS (para CSOSN=102) - Utiliza a tag genérica com CST 99 e valores zerados
        $pis = new \stdClass();
        $pis->item = $i + 1;
        $pis->CST  = '99'; // CST 99: Outras Operações
        $pis->vBC  = 0.00;
        $pis->pPIS = 0.00;
        $pis->vPIS = 0.00;
        $nfe->tagPIS($pis);

        // COFINS (para CSOSN=102) - Utiliza a tag genérica com CST 99 e valores zerados
        $cofins = new \stdClass();
        $cofins->item = $i + 1;
        $cofins->CST  = '99'; // CST 99: Outras Operações
        $cofins->vBC  = 0.00;
        $cofins->pCOFINS = 0.00;
        $cofins->vCOFINS = 0.00;
        $nfe->tagCOFINS($cofins);
    }

    // -----------------------
    // Totais
    // -----------------------
    $tot = new \stdClass();
    $tot->vBC = $dadosVenda['total']['vBC'] ?? 0.00;
    $tot->vICMS = $dadosVenda['total']['vICMS'] ?? 0.00;
    $tot->vICMSDeson = 0.00;
    $tot->vFCP = 0.00;
    $tot->vBCST = 0.00;
    $tot->vST = 0.00;
    $tot->vFCPST = 0.00;
    $tot->vFCPSTRet = 0.00;
    $tot->vProd = $dadosVenda['total']['vProd'];
    $tot->vFrete = 0.00;
    $tot->vSeg = 0.00;
    $tot->vDesc = $dadosVenda['total']['vDesc'];
    $tot->vII = 0.00;
    $tot->vIPI = 0.00;
    $tot->vIPIDevol = 0.00;
    $tot->vPIS = 0.00;
    $tot->vCOFINS = 0.00;
    $tot->vOutro = 0.00;
    $tot->vNF = $dadosVenda['total']['vNF'];
    $tot->vTotTrib = 0.00; // Valor aproximado dos tributos (pode ser ajustado se calculado)

    $nfe->tagICMSTot($tot);

    // -----------------------
    // Transporte (NFC-e geralmente sem transportador)
    // -----------------------
    $transp = new \stdClass();
    $transp->modFrete = 9; // 9=Sem Ocorrência de Transporte (padrão para NFC-e)
    $nfe->tagtransp($transp);

    // -----------------------
    // Pagamentos
    // -----------------------
    $pagObj = new \stdClass();
    $pagObj->indPag = 0; // 0=À vista (sempre para NFC-e)

    // Adiciona troco se houver e for > 0
    $troco = $dadosVenda['troco'];
    if ($troco > 0) {
        $pagObj->vTroco = number_format($troco, 2, '.', '');
    }

    $nfe->tagpag($pagObj);

    // Adiciona detalhes de pagamento (detPag) COM SUPORTE PARA CARD
    foreach ($dadosVenda['pagamentos'] as $pag) {
        $detPag = new \stdClass();
        $detPag->tPag = $pag['tPag'];
        $detPag->vPag = number_format($pag['vPag'], 2, '.', '');
        
        if($pag['tPag'] === '03' || $pag['tPag'] === '04'){
            // ADICIONE ESTA PARTE CORRIGIDA PARA SUPORTAR A ESTRUTURA CARD
            if (isset($pag['card'])) {
                $card = new \stdClass();
                $card->tpIntegra = $pag['card']['tpIntegra'];
                
                // CAMPOS OBRIGATÓRIOS PARA tpIntegra = 2 (não integrado)
                if ($pag['card']['tpIntegra'] == 2) {
                    // Para tpIntegra=2, tBand e cAut são OBRIGATÓRIOS
                    $card->tBand = $pag['card']['tBand'] ?? '99'; // 99 = Outros
                    $card->cAut = $pag['card']['cAut'] ?? 'AUT' . str_pad(random_int(1, 9999), 6, '0', STR_PAD_LEFT);
                }
                
                // CAMPOS OBRIGATÓRIOS PARA tpIntegra = 1 (integrado)
                if ($pag['card']['tpIntegra'] == 1) {
                    // Para tpIntegra=1, CNPJ, tBand e cAut são OBRIGATÓRIOS
                    $card->CNPJ = $pag['card']['CNPJ'] ?? '20387824000173'; // CNPJ STONE Maquininha
                    $card->tBand = $pag['card']['tBand'] ?? '99';
                    $card->cAut = $pag['card']['cAut'] ?? 'AUT' . str_pad(random_int(1, 9999), 6, '0', STR_PAD_LEFT);
                }
                
                $detPag->card = $card;
            }
        }
        
        $nfe->tagdetPag($detPag);
    }
    
    // -----------------------
    // Responsável Técnico (opcional)
    // -----------------------
    if (!empty($dadosVenda['infRespTec'])) {
        $irt = new \stdClass();
        $irt->CNPJ = $dadosVenda['infRespTec']['CNPJ'];
        $irt->xContato = $dadosVenda['infRespTec']['xContato'];
        $irt->email = $dadosVenda['infRespTec']['email'];
        $irt->fone = $dadosVenda['infRespTec']['fone'];
        $nfe->taginfRespTec($irt);
    }

    // -----------------------
    // Informações Suplementares (DANFE NFC-e)
    // -----------------------
    $infNFeSupl = new \stdClass();
    $infNFeSupl->qrCode = 'http://www.hom.nfce.sefaz.ma.gov.br/portal/consultarNFCe.jsp?p=CHAVE|2|2|1|DIGEST_VALUE';
    $infNFeSupl->urlChave = 'www.sefaz.ma.gov.br/nfce/consulta';
    $nfe->taginfNFeSupl($infNFeSupl);

    error_log("Dados de pagamento recebidos na gerarXmlNfce: " . print_r($dadosVenda['pagamentos'], true));

    // Dentro do loop de pagamentos:
    foreach ($dadosVenda['pagamentos'] as $index => $pag) {
        error_log("Pagamento {$index}: tPag = " . $pag['tPag']);
        if (isset($pag['card'])) {
            error_log("Dados do cartão: " . print_r($pag['card'], true));
        } else {
            error_log("SEM DADOS DO CARTÃO para tPag = " . $pag['tPag']);
        }
    }

    return $nfe->getXML();
}

// Função auxiliar para calcular o índice correto dos dados do cartão
function getCartaoIndex($formas_pagamento, $currentIndex) {
    $cartaoCount = 0;
    for ($j = 0; $j < $currentIndex; $j++) {
        if (in_array($formas_pagamento[$j], ['Cartão de Crédito', 'Cartão de Débito'])) {
            $cartaoCount++;
        }
    }
    return $cartaoCount;
}

function verificarConsistenciaDescontos($itensParaXml, $totalArray) {
    $somaVDescItens = 0;
    $somaVProdItens = 0;
    
    foreach ($itensParaXml as $item) {
        $somaVDescItens += (float)$item['vDesc'];
        $somaVProdItens += (float)$item['vProd'];
    }
    
    $vDescTotal = (float)($totalArray['vDesc'] ?? 0);
    $vProdTotal = (float)$totalArray['vProd'];
    $vNFTotal = (float)$totalArray['vNF'];
    
    error_log("=== VERIFICAÇÃO DE CONSISTÊNCIA ===");
    error_log("Soma vDesc itens: " . $somaVDescItens);
    error_log("vDesc total: " . $vDescTotal);
    error_log("Soma vProd itens: " . $somaVProdItens);
    error_log("vProd total: " . $vProdTotal);
    error_log("vNF total: " . $vNFTotal);
    error_log("Diferença vDesc: " . ($vDescTotal - $somaVDescItens));
    error_log("Cálculo esperado: " . ($vProdTotal - $vDescTotal) . " vs vNF: " . $vNFTotal);
    
    $diferencaVDesc = abs($vDescTotal - $somaVDescItens);
    $diferencaVNF = abs(($vProdTotal - $vDescTotal) - $vNFTotal);
    
    return ($diferencaVDesc < 0.01 && $diferencaVNF < 0.01);
}