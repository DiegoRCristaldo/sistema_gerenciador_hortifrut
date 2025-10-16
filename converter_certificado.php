<?php
// converter_e_usar.php
require __DIR__ . '/vendor/autoload.php';

use NFePHP\Common\Certificate;
use NFePHP\NFe\Tools;

$dados = require 'dados.php';

echo "=== SOLUÇÃO DEFINITIVA ===\n";

$pemPath = __DIR__ . $dados['certificadoPem'];
$configJsonPath = __DIR__ . $dados['configJson'];
$senha = $dados['senhaPfx'];

try {
    // 1. Lê o conteúdo PEM
    $pemContent = file_get_contents($pemPath);
    echo "✓ PEM lido: " . strlen($pemContent) . " bytes\n";
    
    // 2. Extrai componentes do PEM
    $privateKey = '';
    $certificate = '';
    $extracerts = [];
    
    // Extrai chave privada
    if (preg_match('/-----BEGIN.*PRIVATE KEY-----.*-----END.*PRIVATE KEY-----/s', $pemContent, $matches)) {
        $privateKey = $matches[0];
        echo "✓ Chave privada extraída\n";
    } else {
        throw new Exception("Chave privada não encontrada no PEM");
    }
    
    // Extrai certificado principal
    if (preg_match_all('/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s', $pemContent, $matches)) {
        $certificate = $matches[0][0]; // Primeiro certificado é o principal
        echo "✓ Certificado principal extraído\n";
        
        // Certificados extras (cadeia)
        if (count($matches[0]) > 1) {
            $extracerts = array_slice($matches[0], 1);
            echo "✓ Certificados da cadeia: " . count($extracerts) . "\n";
        }
    } else {
        throw new Exception("Certificado não encontrado no PEM");
    }
    
    // 3. Cria um PFX em memória
    echo "Criando PFX em memória...\n";
    $pfxContent = '';
    
    if (openssl_pkcs12_export($certificate, $pfxContent, $privateKey, $senha)) {
        echo "✓ PFX criado em memória: " . strlen($pfxContent) . " bytes\n";
    } else {
        throw new Exception("Falha ao criar PFX: " . openssl_error_string());
    }
    
    // 4. Lê o PFX com a NFePHP
    $certificateObj = Certificate::readPfx($pfxContent, $senha);
    echo "✓ Certificate::readPfx() bem-sucedido\n";
    
    // 5. Verifica o certificado
    echo "✓ CNPJ: " . $certificateObj->getCnpj() . "\n";
    echo "✓ Válido até: " . $certificateObj->getValidTo()->format('d/m/Y') . "\n";
    echo "✓ Expirou: " . ($certificateObj->isExpired() ? 'SIM' : 'NÃO') . "\n";
    
    // 6. Instancia o Tools
    $configJson = file_get_contents($configJsonPath);
    $tools = new Tools($configJson, $certificateObj);
    $tools->model(65);
    echo "✓ Tools instanciado\n";
    
    echo "\n🎉 SUCESSO TOTAL! Certificado PEM convertido e funcionando.\n";
    
} catch (Exception $e) {
    echo "✗ ERRO: " . $e->getMessage() . "\n";
}