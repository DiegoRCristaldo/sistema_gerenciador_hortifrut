<?php
/**
 * comprovante.php
 * Emite NFC-e (XML), transmite para a SEFAZ, salva XML autorizado e imprime DANFCE (PDF).
 */

use NFePHP\NFe\Tools;
use NFePHP\Common\Certificate;
use NFePHP\NFe\Make;
use NFePHP\NFe\Common\Standardize;
use NFePHP\DA\NFe\Danfce;

include 'verifica_login.php';
include 'config.php';
include 'funcoes_caixa.php';

require __DIR__ . '/vendor/autoload.php'; // caminho para o autoload

// ---------- valida id_venda ----------
if (!isset($_GET['id_venda'])) {
    http_response_code(400);
    exit('Venda não encontrada.');
}
$id_venda = (int) $_GET['id_venda'];

// ---------- busca venda ----------
$sqlVenda = "SELECT v.*, o.usuario AS operador 
             FROM vendas v
             LEFT JOIN operadores o ON v.operador_id = o.id
             WHERE v.id = $id_venda
             LIMIT 1";
$rv = $conn->query($sqlVenda);
if (!$rv) exit('Erro na consulta (venda): '.$conn->error);
$venda = $rv->fetch_assoc();
if (!$venda) exit('Venda inválida.');

// ---------- itens da venda (com NCM/CFOP/EAN) ----------
$sqlItens = "SELECT iv.*, 
                    p.nome, p.preco, p.unidade_medida, 
                    p.ncm, p.cfop, p.codigo_barras
             FROM itens_venda iv
             JOIN produtos p ON iv.produto_id = p.id
             WHERE iv.venda_id = $id_venda";
$itens = $conn->query($sqlItens);
if (!$itens) exit('Erro na consulta (itens): '.$conn->error);

// ---------- pagamentos da venda ----------
$sqlPgt = "SELECT forma_pagamento, valor_pago FROM pagamentos WHERE venda_id = $id_venda";
$pagamentos = $conn->query($sqlPgt);
if (!$pagamentos) exit('Erro na consulta (pagamentos): '.$conn->error);

// ---------- carrega config.json e certificado ----------
$cfgPath = __DIR__ . '/config/config.json';
if (!file_exists($cfgPath)) exit('Arquivo config/config.json não encontrado.');
$configJson = file_get_contents($cfgPath);
$configArr = json_decode($configJson, true);
if (empty($configArr)) exit('Falha ao ler config.json (JSON inválido).');

$certPath = __DIR__ . '/' . ($configArr['certPfxName'] ?? '');
$certPass = $configArr['certPassword'] ?? '';
if (!file_exists($certPath)) exit('Certificado PFX não encontrado em: '.$certPath);
$certContent = file_get_contents($certPath);
$certificate = Certificate::readPfx($certContent, $certPass);

// ---------- prepara ambientes/paths ----------
$xmlDir = __DIR__ . '/xml_autorizados';
if (!is_dir($xmlDir)) mkdir($xmlDir, 0755, true);

// ---------- helpers ----------
function nf_number($v, $dec = 2) { return number_format((float)$v, $dec, '.', ''); }
function only_digits($s) { return preg_replace('/\D+/', '', (string)$s); }
function safe_ucwords($s) { // normaliza nome do produto
    $s = mb_strtolower(trim($s), 'UTF-8');
    return mb_convert_case($s, MB_CASE_TITLE, 'UTF-8');
}

try {
    require __DIR__ . '/vendor/autoload.php';

    // ---------- instancia Tools ----------
    $tools = new Tools($configJson, $certificate);
    $tools->model('65'); // NFC-e

    // ---------- monta XML ----------
    $nfe = new Make();

    // 1) infNFe = funcoes_caixa.php
    $inf = new \stdClass();
    $inf->versao = $configArr['versao'] ?? '4.00';
    $inf->Id = null; // gerado após assinatura/chave
    $nfe->taginfNFe($inf);

    // 2) IDE
    // cUF: 21=MA; cNF 8 dígitos aleatório; tpImp 4 para DANFCE; tpAmb de config.json
    $ide = new \stdClass();
    $ide->cUF     = 21;
    $ide->cNF     = str_pad((string)random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
    $ide->natOp   = 'VENDA AO CONSUMIDOR';
    $ide->mod     = 65;
    $ide->serie   = 1;
    $ide->nNF     = (int)$venda['id']; // pode ser seu controle – garanta unicidade por série
    $ide->dhEmi   = date('c');
    $ide->tpNF    = 1;
    $ide->idDest  = 1;
    $ide->cMunFG  = '2103604'; // seu município (MA/Coroatá)
    $ide->tpImp   = 4;
    $ide->tpEmis  = 1;
    $ide->cDV     = 0;
    $ide->tpAmb   = (int)($configArr['tpAmb'] ?? 2);
    $ide->finNFe  = 1;
    $ide->indFinal= 1;
    $ide->indPres = 1;
    $ide->procEmi = 0;
    $ide->verProc = 'PDV-1.0';
    $nfe->tagide($ide);

    // 3) Emitente
    $emit = new \stdClass();
    $emit->CNPJ = only_digits($configArr['cnpj'] ?? '60591030000141');
    $emit->xNome = $configArr['razaosocial'] ?? $razaoSocial;
    $emit->xFant = $configArr['razaosocial'] ?? $razaoSocial;
    $emit->IE    = 'ISENTO'; // ajuste se possuir IE
    $emit->CRT   = 1; // 1=Simples Nacional
    $nfe->tagemit($emit);

    // 4) Endereço do emitente
    $ender = new \stdClass();
    $ender->xLgr   = 'Av Magalhães de Almeida';
    $ender->nro    = '745';
    $ender->xCpl   = '';
    $ender->xBairro= 'Centro';
    $ender->cMun   = '2103604';
    $ender->xMun   = 'COROATÁ';
    $ender->UF     = 'MA';
    $ender->CEP    = '65415000';
    $ender->cPais  = '1058';
    $ender->xPais  = 'BRASIL';
    $ender->fone   = '19981658257';
    $nfe->tagenderEmit($ender);

    // 5) Destinatário (consumidor final, sem identificação)
    $dest = new \stdClass();
    $dest->xNome = 'CONSUMIDOR';
    $nfe->tagdest($dest);

    // 6) Itens
    $i = 1;
    mysqli_data_seek($itens, 0);
    $vProdTotal = 0.00;

    while ($row = $itens->fetch_assoc()) {
        $nome = safe_ucwords($row['nome']);
        $ncm  = substr(only_digits($row['ncm'] ?: '00000000'), 0, 8);
        $cfop = $row['cfop'] ?: '5102';
        $cEAN = only_digits($row['codigo_barras'] ?? '');
        if ($cEAN === '' || strlen($cEAN) < 8) $cEAN = 'SEM GTIN'; // conforme manual

        // produto
        $prod = new \stdClass();
        $prod->item   = $i;
        $prod->cProd  = (string)$row['produto_id']; // seu ID interno
        $prod->cEAN   = $cEAN;
        $prod->xProd  = $nome;
        $prod->NCM    = str_pad($ncm, 8, '0', STR_PAD_LEFT);
        $prod->CFOP   = $cfop;
        $prod->uCom   = $row['unidade_medida'];
        $prod->qCom   = nf_number($row['quantidade'], 3);
        $prod->vUnCom = nf_number($row['preco'], 4);
        $prod->vProd  = nf_number(((float)$row['quantidade'] * (float)$row['preco']), 2);
        $prod->cEANTrib = $cEAN;
        $prod->uTrib  = $row['unidade_medida'];
        $prod->qTrib  = nf_number($row['quantidade'], 3);
        $prod->vUnTrib= nf_number($row['preco'], 4);
        $prod->indTot = 1;
        $nfe->tagprod($prod);

        // imposto (grupo imposto do item)
        $imp = new \stdClass();
        $imp->item = $i;
        $imp->vTotTrib = 0.00;
        $nfe->tagimposto($imp);

        // ICMS – Simples Nacional CSOSN 102
        $icms = new \stdClass();
        $icms->item = $i;
        $icms->orig = 0;
        $icms->CSOSN = '102';
        $nfe->tagICMSSN($icms);

        // PIS (isento/99)
        $pis = new \stdClass();
        $pis->item = $i;
        $pis->CST  = '99';
        $pis->vBC  = 0.00;
        $pis->pPIS = 0.00;
        $pis->vPIS = 0.00;
        $nfe->tagPIS($pis);

        // COFINS (isento/99)
        $cof = new \stdClass();
        $cof->item = $i;
        $cof->CST  = '99';
        $cof->vBC  = 0.00;
        $cof->pCOFINS = 0.00;
        $cof->vCOFINS = 0.00;
        $nfe->tagCOFINS($cof);

        $vProdTotal += (float)$prod->vProd;
        $i++;
    }

    // 7) Totais
    $tot = new \stdClass();
    $tot->vBC         = 0.00;
    $tot->vICMS       = 0.00;
    $tot->vICMSDeson  = 0.00;
    $tot->vFCP        = 0.00;
    $tot->vBCST       = 0.00;
    $tot->vST         = 0.00;
    $tot->vFCPST      = 0.00;
    $tot->vFCPSTRet   = 0.00;
    $tot->vProd       = nf_number($vProdTotal, 2);
    $tot->vFrete      = 0.00;
    $tot->vSeg        = 0.00;
    $tot->vDesc       = nf_number($venda['desconto'] ?? 0, 2);
    $tot->vII         = 0.00;
    $tot->vIPI        = 0.00;
    $tot->vIPIDevol   = 0.00;
    $tot->vPIS        = 0.00;
    $tot->vCOFINS     = 0.00;
    $tot->vOutro      = 0.00;
    $tot->vNF         = nf_number($venda['total'], 2);
    $nfe->tagICMSTot($tot);

    // 8) Pagamentos (YA) – usa detPag por forma
    // vTroco opcional: calcule se precisar
    $pg = new \stdClass();
    $pg->vTroco = 0.00;
    $nfe->tagpag($pg);

    // mapeia suas formas para tPag
    $mapTPag = [
        'Dinheiro'            => '01',
        'Cartão de Crédito'   => '03',
        'Cartão de Débito'    => '04',
        'PIX'                 => '16',
        'Múltipla'            => '99', // fallback
    ];

    if ($pagamentos->num_rows > 0) {
        while ($p = $pagamentos->fetch_assoc()) {
            $det = new \stdClass();
            $det->tPag = $mapTPag[$p['forma_pagamento']] ?? '99';
            $det->vPag = nf_number($p['valor_pago'], 2);
            // se cartão: opcional informar CNPJ da credenciadora, tBand, cAut
            $nfe->tagdetPag($det);
        }
    } else {
        // sem tabela pagamentos => 1 forma igual ao total
        $det = new \stdClass();
        $det->tPag = '01';
        $det->vPag = nf_number($venda['total'], 2);
        $nfe->tagdetPag($det);
    }

    // 9) Informações de responsável técnico (opcional, mas recomendado)
    if (!empty($configArr['cnpj'])) {
        $irt = new \stdClass();
        $irt->CNPJ     = only_digits($configArr['cnpj']);
        $irt->xContato = 'DIEGO RODRIGUES CRISTALDO';
        $irt->email    = 'diegorcristaldo@hotmail.com';
        $irt->fone     = '19989909456';
        $nfe->taginfRespTec($irt);
    }

    // ---------- gera XML (sem assinar) ----------
    $xml = $nfe->getXML();
    if (!$xml) throw new \RuntimeException('Falha ao gerar XML da NFC-e.');

    // ---------- assina ----------
    $xmlAssinado = $tools->signNFe($xml);

    // ---------- envia lote ----------
    $idLote = str_pad((string)$id_venda, 15, '0', STR_PAD_LEFT);
    $resp   = $tools->sefazEnviaLote([$xmlAssinado], $idLote);

    $stz = new Standardize();
    $std = $stz->toStd($resp);

    if (!isset($std->cStat) || (string)$std->cStat !== '103') {
        $motivo = $std->xMotivo ?? 'Erro desconhecido no envio de lote';
        throw new \RuntimeException("Erro no envio do lote [{$std->cStat}]: {$motivo}");
    }

    // ---------- consulta recibo ----------
    $recibo = (string)$std->infRec->nRec;
    // Em produção, pode ser necessário aguardar alguns segundos
    usleep(800000); // 0,8s

    $ret = $tools->sefazConsultaRecibo($recibo);
    $stdRet = $stz->toStd($ret);

    // Trata códigos
    // 104 = retorno de processamento do lote
    if ((string)$stdRet->cStat !== '104') {
        throw new \RuntimeException("Lote não processado [{$stdRet->cStat}] {$stdRet->xMotivo}");
    }

    // Dentro do retorno, ver o protNFe
    $infProt = $stdRet->protNFe->infProt ?? null;
    if (!$infProt) {
        throw new \RuntimeException('Retorno sem protocolo de autorização.');
    }

    $cStat = (string)$infProt->cStat;
    $xMotivo = (string)$infProt->xMotivo;

    if ($cStat !== '100') { // 100 = autorizado
        // salva status PROCESSADO/REJEITADO
        try {
            $stmt = $conn->prepare("UPDATE vendas SET protocolo = ?, status_nf = ? WHERE id = ?");
            $status = 'REJEITADA';
            $protStr = (string)($infProt->nProt ?? '');
            $stmt->bind_param("ssi", $protStr, $status, $id_venda);
            $stmt->execute();
        } catch (\Throwable $e) {}
        throw new \RuntimeException("NFC-e rejeitada [{$cStat}] {$xMotivo}");
    }

    // ---------- autorizado: adiciona protocolo ao XML ----------
    $xmlFinal = $tools->addProtocolo($xmlAssinado, $stdRet->protNFe);

    // salva XML
    $chave = (string)$infProt->chNFe;
    $prot  = (string)$infProt->nProt;
    file_put_contents($xmlDir . "/{$chave}.xml", $xmlFinal);

    // atualiza venda com chave/protocolo/status
    try {
        $stmt = $conn->prepare("UPDATE vendas SET chave_nfe = ?, protocolo = ?, status_nf = ? WHERE id = ?");
        $status = 'AUTORIZADA';
        $stmt->bind_param("sssi", $chave, $prot, $status, $id_venda);
        $stmt->execute();
    } catch (\Throwable $e) {
        // ignora se a tabela não possuir essas colunas
    }

    // ---------- DANFCE (PDF) ----------
    // Observação: o CSC/CSCid devem estar corretos no config.json para o QRCode.
    $danfce = new Danfce($xmlFinal);
    $danfce->monta();
    $pdf = $danfce->render();

    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="DANFCE_'.$chave.'.pdf"');
    echo $pdf;
    exit;

} catch (\Throwable $e) {
    // tratamento de falhas: mostra mensagem amigável e loga o detalhe
    $msg = $e->getMessage();
    // opcional: registre log em arquivo
    if (!is_dir(__DIR__.'/logs')) mkdir(__DIR__.'/logs', 0755, true);
    file_put_contents(__DIR__.'/logs/nfce_'.date('Ymd').'.log', '['.date('c')."] ID {$id_venda} - {$msg}\n", FILE_APPEND);

    http_response_code(500);
    echo "<div style='padding:16px;font-family:Arial'>
            <h3>Falha na emissão da NFC-e</h3>
            <p><strong>Motivo:</strong> ".htmlspecialchars($msg)."</p>
            <p>Verifique os dados fiscais (NCM/CFOP, CSC/CSCid, certificado, ambiente) e tente novamente.</p>
            <p><a href='registrar_venda.php' class='btn btn-primary'>Voltar ao PDV</a></p>
          </div>";
    exit;
}

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Comprovante de Venda</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/comprovante.css">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<div class="content p-3">
    <img src="assets/banner.jpeg" alt="Banner escrito Hortifrut Quero Fruta" class="banner">

    <h2>Comprovante de Venda</h2>
    <p><strong>ID da Venda:</strong> <?= $venda['id'] ?></p>
    <p><strong>Data:</strong> <?= date('d/m/Y H:i', strtotime($venda['data'])) ?></p>
    <p><strong>Formas de Pagamento:</strong></p>
    <ul>
        <?php while ($pg = $pagamentos->fetch_assoc()): ?>
            <li><?= htmlspecialchars($pg['forma_pagamento']) ?>: R$ <?= number_format($pg['valor_pago'], 2, ',', '.') ?></li>
        <?php endwhile; ?>
    </ul>
    <p><strong>Operador:</strong> <?= $_SESSION['usuario'] ?></p>

    <hr>

    <h6>Itens da Venda:</h6>
    <span>| Produto | Qtd | Unit | Total |</span>
    <?php
    mysqli_data_seek($itens, 0); // Garante que o ponteiro volte ao início
    while ($item = $itens->fetch_assoc()):
        $qtd = rtrim(rtrim(number_format((float)$item['quantidade'], 3, ',', '.'), '0'), ',');
        $unit = number_format($item['preco'], 2, ',', '.');
        $total = number_format($item['preco'] * $item['quantidade'], 2, ',', '.');
    ?>
        <div class="linha-produto">
            <strong><?= htmlspecialchars(mb_strimwidth($item['nome'], 0, 20, '...')) ?> (<?= $item['unidade_medida'] ?>)</strong><br>
            <span class="d-flex justify-content-end"><?= $qtd ?> (Qtd) <?= $unit ?> (<?= $item['unidade_medida'] ?>) R$ <?= $total ?></span>
        </div>
    <?php endwhile; ?>

    <p class="d-flex justify-content-end total"><strong>TOTAL: R$ <?= number_format($venda['total'], 2, ',', '.') ?></strong></p>

    <div class="text-center mt-3 d-print-none">
        <a href="registrar_venda.php" class="btn btn-primary">Nova Venda</a>
        <a href="index.php" class="btn btn-secondary">← Voltar ao Painel</a>
        <button onclick="window.print()" class="btn btn-secondary">Imprimir Comprovante</button>
    </div>
</div>

</body>
</html>
