<?php
require 'vendor/autoload.php';
use NFePHP\DA\NFe\DanfeNFCe;

$xml = file_get_contents('xmls/autorizadas/minha_nfce.xml');
$danfe = new DanfeNFCe($xml);
$danfe->monta();
$danfe->printDANFEPDF('I');
?>