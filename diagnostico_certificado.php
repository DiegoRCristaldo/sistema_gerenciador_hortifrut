<?php
// diagnostico_detalhado.php
$dados = require 'dados.php';

$certPfxPath = __DIR__ . $dados['certificadoPfx'];
$senhaPfx = $dados['senhaPfx'];

echo "=== DIAGNÓSTICO DETALHADO DO CERTIFICADO ===\n";

// Verifica se as funções OpenSSL estão disponíveis
echo "OpenSSL disponível: " . (extension_loaded('openssl') ? 'SIM' : 'NÃO') . "\n";
echo "Função openssl_pkcs12_read: " . (function_exists('openssl_pkcs12_read') ? 'SIM' : 'NÃO') . "\n";

if (!file_exists($certPfxPath)) {
    die("Arquivo não encontrado: $certPfxPath\n");
}

echo "Tamanho do arquivo: " . filesize($certPfxPath) . " bytes\n";

// Tenta detectar o tipo de arquivo
$content = file_get_contents($certPfxPath);
$firstBytes = bin2hex(substr($content, 0, 4));

echo "Primeiros bytes (hex): $firstBytes\n";

// Verifica se é um PKCS12 válido
if (strpos($firstBytes, '3082') === 0 || strpos($firstBytes, '300e') === 0) {
    echo "✓ Estrutura PKCS12 detectada\n";
} else {
    echo "⚠ Estrutura não reconhecida como PKCS12\n";
}

// Tenta ler com a senha fornecida
if (openssl_pkcs12_read($content, $certs, $senhaPfx)) {
    echo "✓ Certificado lido com a senha fornecida\n";
    analisarCertificado($certs);
} else {
    echo "✗ Falha com a senha fornecida: " . openssl_error_string() . "\n";
    
    // Tenta sem senha
    if (openssl_pkcs12_read($content, $certs, '')) {
        echo "✓ Certificado lido sem senha (o certificado não tem senha)\n";
        analisarCertificado($certs);
    } else {
        echo "✗ Também falhou sem senha: " . openssl_error_string() . "\n";
    }
}

function analisarCertificado($certs) {
    echo "\n--- ANÁLISE DO CERTIFICADO ---\n";
    
    if (!empty($certs['pkey'])) {
        echo "Chave privada: " . strlen($certs['pkey']) . " bytes\n";
        
        $pkey = openssl_pkey_get_private($certs['pkey']);
        if ($pkey) {
            $details = openssl_pkey_get_details($pkey);
            echo "Tipo: " . ($details['type'] === 0 ? "RSA" : "Outro") . "\n";
            echo "Bits: " . $details['bits'] . "\n";
            openssl_pkey_free($pkey);
        } else {
            echo "ERRO na chave: " . openssl_error_string() . "\n";
        }
    }
    
    if (!empty($certs['cert'])) {
        echo "Certificado: " . strlen($certs['cert']) . " bytes\n";
        
        $x509 = openssl_x509_read($certs['cert']);
        if ($x509) {
            $info = openssl_x509_parse($x509);
            echo "Subject: " . $info['name'] . "\n";
            echo "Válido até: " . date('d/m/Y H:i:s', $info['validTo_time_t']) . "\n";
            echo "Emissor: " . $info['issuer']['O'] . "\n";
            openssl_x509_free($x509);
        } else {
            echo "ERRO no certificado: " . openssl_error_string() . "\n";
        }
    }
}