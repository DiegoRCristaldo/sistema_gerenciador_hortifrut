<?php
// teste_final_conversao.php
require __DIR__ . '/vendor/autoload.php';

use NFePHP\Common\Certificate;

$dados = require 'dados.php';

echo "=== TESTE FINAL DE CONVERSÃO ===\n";

$pemPath = __DIR__ . $dados['certificadoPem'];
$senha = $dados['senhaPfx'];

function carregarCertificadoPem($pemContent, $password) {
    echo "Extraindo componentes do PEM...\n";
    
    // CORREÇÃO: Pattern corrigido
    $privateKeyPattern = '/-----BEGIN PRIVATE KEY-----[\s\S]*?-----END PRIVATE KEY-----/';
    $certificatePattern = '/-----BEGIN CERTIFICATE-----[\s\S]*?-----END CERTIFICATE-----/';
    
    // Extrai chave privada
    if (preg_match($privateKeyPattern, $pemContent, $privateMatches)) {
        $privateKey = trim($privateMatches[0]);
        echo "✓ Chave privada: " . strlen($privateKey) . " bytes\n";
    } else {
        throw new Exception("Chave privada não encontrada");
    }
    
    // Extrai certificado
    if (preg_match($certificatePattern, $pemContent, $certMatches)) {
        $certificate = trim($certMatches[0]);
        echo "✓ Certificado: " . strlen($certificate) . " bytes\n";
    } else {
        throw new Exception("Certificado não encontrado");
    }
    
    // Valida com OpenSSL
    echo "Validando com OpenSSL...\n";
    if (!openssl_pkey_get_private($privateKey)) {
        throw new Exception("Chave privada inválida: " . openssl_error_string());
    }
    
    if (!openssl_x509_read($certificate)) {
        throw new Exception("Certificado inválido: " . openssl_error_string());
    }
    echo "✓ Componentes válidos\n";
    
    // Cria PFX
    echo "Criando PFX...\n";
    $pfxContent = '';
    if (!openssl_pkcs12_export($certificate, $pfxContent, $privateKey, $password)) {
        throw new Exception("Falha ao criar PFX: " . openssl_error_string());
    }
    echo "✓ PFX criado: " . strlen($pfxContent) . " bytes\n";
    
    // Lê com NFePHP
    echo "Lendo com NFePHP...\n";
    return Certificate::readPfx($pfxContent, $password);
}

try {
    $pemContent = file_get_contents($pemPath);
    echo "PEM lido: " . strlen($pemContent) . " bytes\n\n";
    
    $certificate = carregarCertificadoPem($pemContent, $senha);
    
    echo "\n=== CERTIFICADO CARREGADO ===\n";
    echo "CNPJ: " . $certificate->getCnpj() . "\n";
    echo "Válido até: " . $certificate->getValidTo()->format('d/m/Y') . "\n";
    echo "Expirou: " . ($certificate->isExpired() ? 'SIM' : 'NÃO') . "\n";
    
    echo "\n🎉 CONVERSÃO BEM-SUCEDIDA! 🎉\n";
    
} catch (Exception $e) {
    echo "✗ ERRO: " . $e->getMessage() . "\n";
}