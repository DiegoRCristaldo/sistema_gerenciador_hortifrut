<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// tenta obter operador_id da sessão (fallback para 'id' e para lookup por 'usuario')
$operador_id = null;
if (isset($_SESSION['operador_id'])) {
    $operador_id = (int) $_SESSION['operador_id'];
} elseif (isset($_SESSION['id'])) {
    $operador_id = (int) $_SESSION['id'];
} elseif (isset($_SESSION['usuario'])) {
    $usuario_logado = $_SESSION['usuario'];
    $stmt = $conn->prepare("SELECT id FROM operadores WHERE usuario = ?");
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
    $inf->versao = $configArr['versao'] ?? '4.00';
    $inf->Id = null; // NFePHP gera
    $nfe->taginfNFe($inf);

    // -----------------------
    // Identificação (IDE)
    // -----------------------
    $ide = new \stdClass();
    foreach ($dadosVenda['ide'] as $k => $v) {
        $ide->$k = $v;
    }
    $nfe->tagide($ide);

    // -----------------------
    // Emitente
    // -----------------------
    $emit = new \stdClass();
    $emit->CNPJ  = $dadosVenda['emit']['CNPJ'];
    $emit->xNome = $dadosVenda['emit']['xNome'];
    $emit->xFant = $dadosVenda['emit']['xFant'];
    $emit->IE    = $dadosVenda['emit']['IE'];
    $emit->CRT   = $dadosVenda['emit']['CRT'] ?? 3; // 1=SN, 2=SN excesso sublimite, 3=Normal

    $emit->enderEmit = new \stdClass();
    foreach ($dadosVenda['emit']['enderEmit'] as $k => $v) {
        $emit->enderEmit->$k = $v;
    }
    $emit->enderEmit->cPais = '1058';
    $emit->enderEmit->xPais = 'BRASIL';

    $nfe->tagemit($emit);

    // -----------------------
    // Destinatário
    // -----------------------
    $dest = new \stdClass();
    $dest->CPF   = $dadosVenda['dest']['CPF'] ?? null;
    $dest->xNome = $dadosVenda['dest']['xNome'] ?? 'CONSUMIDOR';
    $dest->indIEDest = 9; // 9 = não contribuinte (NFC-e)
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
        $prod->qCom     = number_format($item['qCom'], 3, '.', '');
        $prod->vUnCom   = number_format($item['vUnCom'], 2, '.', '');
        $prod->vProd    = number_format($item['vProd'], 2, '.', '');
        $prod->cEANTrib = $item['cEANTrib'] ?? 'SEM GTIN';
        $prod->uTrib    = $item['uTrib'] ?? $prod->uCom;
        $prod->qTrib    = number_format($item['qTrib'] ?? $item['qCom'], 3, '.', '');
        $prod->vUnTrib  = number_format($item['vUnTrib'] ?? $item['vUnCom'], 2, '.', '');
        $prod->indTot   = $item['indTot'] ?? 1;

        $nfe->tagprod($prod);

        // imposto (mínimo Simples Nacional CSOSN=102)
        $imposto = new \stdClass();
        $imposto->item = $i + 1;
        $imposto->vTotTrib = 0.00;
        $nfe->tagimposto($imposto);

        $icmssn = new \stdClass();
        $icmssn->item = $i + 1;
        $icmssn->orig = 0;
        $icmssn->CSOSN = '102';
        $nfe->tagICMSSN($icmssn);

        $pis = new \stdClass();
        $pis->item = $i + 1;
        $pis->CST  = '99';
        $pis->vBC  = 0.00;
        $pis->pPIS = 0.00;
        $pis->vPIS = 0.00;
        $nfe->tagPIS($pis);

        $cofins = new \stdClass();
        $cofins->item = $i + 1;
        $cofins->CST  = '99';
        $cofins->vBC  = 0.00;
        $cofins->pCOFINS = 0.00;
        $cofins->vCOFINS = 0.00;
        $nfe->tagCOFINS($cofins);
    }

    // -----------------------
    // Totais
    // -----------------------
    $tot = new \stdClass();
    foreach ($dadosVenda['total'] as $k => $v) {
        $tot->$k = $v;
    }
    $nfe->tagICMSTot($tot);

    // -----------------------
    // Pagamentos
    // -----------------------
    foreach ($dadosVenda['pagamentos'] as $pag) {
        $p = new \stdClass();
        $p->tPag = $pag['tPag'];
        $p->vPag = number_format($pag['vPag'], 2, '.', '');
        $nfe->tagpag($p);
    }

    // -----------------------
    // Responsável Técnico (opcional)
    // -----------------------
    if (!empty($dadosVenda['infRespTec'])) {
        $irt = new \stdClass();
        foreach ($dadosVenda['infRespTec'] as $k => $v) {
            $irt->$k = $v;
        }
        $nfe->taginfRespTec($irt);
    }

    return $nfe->getXML();
}

?>