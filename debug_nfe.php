<?php
include 'verifica_login.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Debug NFC-e</title>
</head>
<body>
    <h2>Logs NFC-e</h2>
    <pre><?php
    $logFile = __DIR__ . '/logs/nfe_log.txt';
    if (file_exists($logFile)) {
        echo htmlspecialchars(file_get_contents($logFile));
    } else {
        echo "Arquivo de log não encontrado.";
    }
    ?></pre>
    
    <h2>Últimos Debugs da Sessão</h2>
    <pre><?php
    if (!empty($_SESSION['debug_log'])) {
        foreach ($_SESSION['debug_log'] as $log) {
            echo htmlspecialchars($log) . "\n";
        }
    } else {
        echo "Nenhum debug na sessão.";
    }
    ?></pre>
</body>
</html>