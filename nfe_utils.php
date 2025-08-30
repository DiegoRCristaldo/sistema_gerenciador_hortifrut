<?php
// nfe_utils.php
use NFePHP\NFe\Factories\DanfeFactory;
use NFePHP\NFe\Tools;

/**
 * Cria o XML nfeProc corretamente com os nós NFe e protNFe.
 *
 * @param string $xmlAssinado O XML da NFe assinado.
 * @param object $stdResponse A resposta da SEFAZ padronizada.
 * @param string $tpAmb Tipo de ambiente (1=produção, 2=homologação).
 * @return string O XML nfeProc completo.
 */
function createNfeProc(string $xmlAssinado, $stdResponse, string $tpAmb): string
{
    $domNFe = new DOMDocument();
    $domNFe->loadXML($xmlAssinado);
    
    $infNFe = $domNFe->getElementsByTagName('infNFe')->item(0);
    $idAttr = $infNFe ? $infNFe->getAttribute('Id') : null;
    $chave = $idAttr ? substr($idAttr, 3) : null;

    $domNfeProc = new DOMDocument('1.0', 'UTF-8');
    $domNfeProc->formatOutput = true;
    
    $nfeProc = $domNfeProc->createElement('nfeProc');
    $nfeProc->setAttribute('versao', '4.00');
    $nfeProc->setAttribute('xmlns', 'http://www.portalfiscal.inf.br/nfe');
    
    $nfeNode = $domNfeProc->importNode($domNFe->documentElement, true);
    
    $protNFe = $domNfeProc->createElement('protNFe');
    $protNFe->setAttribute('versao', '4.00');
    
    $infProt = $domNfeProc->createElement('infProt');
    $infProt->appendChild($domNfeProc->createElement('tpAmb', $tpAmb));
    $infProt->appendChild($domNfeProc->createElement('verAplic', ($stdResponse->infProt->verAplic ?? '')));
    $infProt->appendChild($domNfeProc->createElement('chNFe', $chave));
    $infProt->appendChild($domNfeProc->createElement('dhRecbto', ($stdResponse->infProt->dhRecbto ?? '')));
    $infProt->appendChild($domNfeProc->createElement('nProt', ($stdResponse->infProt->nProt ?? '')));
    $infProt->appendChild($domNfeProc->createElement('digVal', ($stdResponse->infProt->digVal ?? '')));
    $infProt->appendChild($domNfeProc->createElement('cStat', ($stdResponse->infProt->cStat ?? '')));
    $infProt->appendChild($domNfeProc->createElement('xMotivo', ($stdResponse->infProt->xMotivo ?? '')));
    
    $protNFe->appendChild($infProt);
    $nfeProc->appendChild($nfeNode);
    $nfeProc->appendChild($protNFe);
    $domNfeProc->appendChild($nfeProc);
    
    return $domNfeProc->saveXML();
}

/**
 * Tenta gerar o DANFE NFC-e a partir de um XML autorizado.
 *
 * @param string $xmlContent O conteúdo do XML autorizado.
 * @return string|null O HTML do DANFE ou null em caso de erro.
 */
function gerarDanfeNFCe(string $xmlContent): ?string
{
    try {
        $danfe = new DanfeFactory($xmlContent, 'NFCe');
        $danfe->setPrintParameters(null, 80, 120, 'Inter', 9, true, 'PDV-1.0');
        return $danfe->render();
    } catch (Exception $e) {
        error_log("Erro ao gerar DANFE: " . $e->getMessage());
        return null;
    }
}