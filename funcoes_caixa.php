<?php
// VERIFICACAO_FINAL_20250824_2045 // NOVO IDENTIFICADOR para verificar a versão do arquivo
if (session_status() === PHP_SESSION_NONE) session_start();

// tenta obter operador_id da sessão (fallback para 'id' e para lookup por 'usuario')
$operador_id = null;
if (isset($_SESSION['operador_id'])) {
    $operador_id = (int) $_SESSION['operador_id'];
} elseif (isset($_SESSION['id'])) {
    $operador_id = (int) $_SESSION['id'];
} elseif (isset($_SESSION['usuario'])) {
    $usuario_logado = $_SESSION['usuario'];
    $stmt = $conn->prepare("SELECT id FROM operadores WHERE usuario = ? LIMIT 1");
    $stmt->bind_param("s", $usuario_logado);
    $stmt->execute();
    $stmt->bind_result($tmp_id);
    $stmt->fetch();
    $stmt->close();
    if (!empty($tmp_id)) {
        $operador_id = (int) $tmp_id;
        $_SESSION['operador_id'] = $operador_id; // salva para próximos requests
    }
}

if ($operador_id === null) {
    if (isset($_SESSION['operador_id'])) $operador_id = (int) $_SESSION['operador_id'];
    elseif (isset($_SESSION['id'])) $operador_id = (int) $_SESSION['id'];
    else return null;
}

function getCaixaAberto($conn, $operador_id = null) {
    $sql = "SELECT id FROM caixas WHERE operador_id = ? AND data_fechamento IS NULL ORDER BY data_abertura DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $operador_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $caixa = $result->fetch_assoc();
    return $caixa ? $caixa['id'] : null;
}

function getTotalVendas($conn, $caixa_id) {
    $sqlVendas = "SELECT COALESCE(SUM(total),0) AS total FROM vendas WHERE caixa_id = ?";
    $stmtV = $conn->prepare($sqlVendas);
    $stmtV->bind_param("i", $caixa_id);
    $stmtV->execute();
    return $stmtV->get_result()->fetch_assoc();
}

function getTotalSangrias($conn, $caixa_id) {
    $sqlSang = "SELECT COALESCE(SUM(valor),0) AS total FROM sangrias WHERE caixa_id = ?";
    $stmtS = $conn->prepare($sqlSang);
    $stmtS->bind_param("i", $caixa_id);
    $stmtS->execute();
    return $stmtS->get_result()->fetch_assoc();
}

function getOperadorId($conn, $usuario) {
    $sql = "SELECT id FROM operadores WHERE usuario = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc()['id'] ?? null;
}

// Helper: monta XML básico NFC-e com NFePHP\NFe\Make (simplificado)
function gerarXmlNfce($dadosVenda) {
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
    
    $nfe->tagdest($dest);

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
    $tot->vDesc = $dadosVenda['total']['vDesc'] ?? 0.00;
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
    $troco = $dadosVenda['troco'] ?? 0;
    if ($troco > 0) {
        $pagObj->vTroco = number_format($troco, 2, '.', '');
    }

    $nfe->tagpag($pagObj);

    // Adiciona detalhes de pagamento (detPag)
    foreach ($dadosVenda['pagamentos'] as $pag) {
        $detPag = new \stdClass();
        $detPag->tPag = $pag['tPag'];
        $detPag->vPag = number_format($pag['vPag'], 2, '.', '');
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

    return $nfe->getXML();
}
