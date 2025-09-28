<?php
// converter_certificado_php.php
$dados = require 'dados.php';

$certPfxPath = __DIR__ . $dados['certificadoPfx'];
$senhaPfx = $dados['senhaPfx'];
$certPemPath = __DIR__ . '/certificado.pem';

echo "=== CONVERSÃO USANDO APENAS PHP ===\n";

if (!file_exists($certPfxPath)) {
    die("Certificado não encontrado: $certPfxPath\n");
}

// Método 1: Usando openssl_pkcs12_read (função nativa do PHP)
$pfxContent = file_get_contents($certPfxPath);

if (openssl_pkcs12_read($pfxContent, $certs, $senhaPfx)) {
    echo "✓ Certificado lido com sucesso\n";
    
    $pemContent = "";
    
    if (!empty($certs['pkey'])) {
        echo "✓ Chave privada extraída\n";
        $pemContent .= $certs['pkey'] . "\n";
        
        // Verifica se a chave privada é válida
        $pkey = openssl_pkey_get_private($certs['pkey']);
        if ($pkey) {
            echo "✓ Chave privada válida\n";
            openssl_pkey_free($pkey);
        } else {
            echo "⚠ Chave privada pode ter problemas: " . openssl_error_string() . "\n";
        }
    }
    
    if (!empty($certs['cert'])) {
        echo "✓ Certificado público extraído\n";
        $pemContent .= $certs['cert'] . "\n";
        
        // Verifica se o certificado é válido
        $x509 = openssl_x509_read($certs['cert']);
        if ($x509) {
            echo "✓ Certificado X509 válido\n";
            $info = openssl_x509_parse($x509);
            echo "  Válido até: " . date('d/m/Y', $info['validTo_time_t']) . "\n";
            echo "  Emitente: " . $info['issuer']['O'] . "\n";
            openssl_x509_free($x509);
        } else {
            echo "⚠ Certificado pode ter problemas: " . openssl_error_string() . "\n";
        }
    }
    
    // Salva o arquivo PEM
    file_put_contents($certPemPath, $pemContent);
    echo "✓ Certificado PEM salvo em: $certPemPath\n";
    echo "✓ Tamanho do arquivo: " . filesize($certPemPath) . " bytes\n";
    
} else {
    echo "✗ Erro ao ler certificado: " . openssl_error_string() . "\n";
    
    // Método 2: Tenta sem senha
    echo "Tentando sem senha...\n";
    if (openssl_pkcs12_read($pfxContent, $certs, '')) {
        echo "✓ Certificado lido sem senha\n";
        // ... repete o processo acima
    } else {
        echo "✗ Também falhou sem senha: " . openssl_error_string() . "\n";
        
        // Método 3: Tenta com senhas comuns
        $senhasComuns = ['', '1234', '123456', 'senha', 'password', 'certificado', '0101'];
        foreach ($senhasComuns as $senha) {
            if (openssl_pkcs12_read($pfxContent, $certs, $senha)) {
                echo "✓ Certificado lido com senha: '$senha'\n";
                // ... repete o processo de salvamento
                break;
            }
        }
    }
}