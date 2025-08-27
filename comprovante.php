<?php
include 'verifica_login.php';
include 'config.php';
require __DIR__ . '/vendor/autoload.php';
use NFePHP\NFe\Tools;
use NFePHP\Common\Certificate;
use NFePHP\NFe\Factories\DanfeFactory;


if (!isset($_GET['id_venda'])) {
    echo "Venda não encontrada.";
    exit;
}

$venda_id = intval($_GET['id_venda']);
$status_nf = $_GET['nf'] ?? null; // 'ok', 'pendente', etc.

// Mensagens de feedback para o usuário
$flash_message = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

// Busca informações da venda para o recibo interno
$sql = "SELECT v.*, o.usuario AS operador
        FROM vendas v LEFT JOIN operadores o ON v.operador_id = o.id
        WHERE v.id = $venda_id";
$result = $conn->query($sql);

if (!$result) {
    die("Erro na consulta: " . $conn->error);
}

$venda = $result->fetch_assoc();

if (!$venda) {
    echo "Venda inválida.";
    exit;
}

// Itens da venda para o recibo interno
$itens = $conn->query("SELECT iv.*, p.nome, p.preco, p.unidade_medida
                       FROM itens_venda iv JOIN produtos p ON iv.produto_id = p.id
                       WHERE iv.venda_id = $venda_id");
$pagamentos = $conn->query("SELECT * FROM pagamentos WHERE venda_id = $venda_id");

// --- Lógica para geração do DANFE NFC-e (integrada) ---
$nfe_autorizada_xml_path = __DIR__ . '/xmls/venda_' . $venda_id . '_autorizada.xml';
$danfe_html = '';
$danfe_gerado = false;

// Tenta gerar o DANFE NFC-e apenas se a NF foi autorizada e NFePHP está disponível
if (isset($danfe) && $status_nf === 'ok' && file_exists($nfe_autorizada_xml_path)) {
    try {
        $xml_nfe = file_get_contents($nfe_autorizada_xml_path);

        // Carregar configurações do ambiente (necessário para o DANFEFactory)
        $configJsonPath = __DIR__ . $configJsonCaminho;
        if (!file_exists($configJsonPath)) {
            throw new Exception("Arquivo de configuração JSON não encontrado: " . $configJsonPath);
        }
        $configJson = file_get_contents($configJsonPath);
        $config = json_decode($configJson);

        // O DANFE Factory precisa do XML e das configurações
        $danfe = new DanfeFactory($xml_nfe, 'NFCe'); // 'NFCe' para DANFE NFC-e
        $danfe->setPrintParameters(
            $config->logomarca ?? null, // Caminho para a logomarca (opcional)
            $config->papelWidth ?? 80,  // Largura do papel (ex: 80 para impressora térmica)
            $config->papelHeight ?? 120, // Altura do papel (aproximada, pode variar)
            $config->font ?? 'Inter',  // Fonte para o DANFE
            $config->fontSize ?? 9,   // Tamanho da fonte
            true, // Exibir nome do sistema
            'PDV-1.0' // Versão do sistema
        );

        // Gera o HTML do DANFE
        $danfe_html = $danfe->render();
        $danfe_gerado = true;

    } catch (Exception $e) {
        $flash_message .= (empty($flash_message) ? '' : '<br>') . "Erro ao gerar DANFE: " . $e->getMessage();
        error_log("Erro DANFE: " . $e->getMessage()); // Para logs de erro no servidor
    }
} else {
    // Se a NF não foi autorizada ou o XML não foi encontrado
    if ($status_nf === 'pendente') {
        $flash_message .= (empty($flash_message) ? '' : '<br>') . "NFC-e enviada, mas ainda processando. Tente consultar novamente em alguns minutos.";
    } elseif ($status_nf === 'erro') {
        $flash_message .= (empty($flash_message) ? '' : '<br>') . "Houve um erro no envio ou autorização da NFC-e. Verifique o log.";
    } elseif ($status_nf === 'ok' && !file_exists($nfe_autorizada_xml_path)) {
        $flash_message .= (empty($flash_message) ? '' : '<br>') . "NFC-e autorizada, mas o arquivo XML autorizado não foi encontrado. Contate o suporte.";
    }
}

// No comprovante.php, adicione:
$nfe_autorizada_xml_path = __DIR__ . '/xmls/venda_' . $venda_id . '_autorizada.xml';
error_log("Procurando XML em: " . $nfe_autorizada_xml_path);

// Verifique se o arquivo existe
if (file_exists($nfe_autorizada_xml_path)) {
    error_log("XML encontrado!");
} else {
    error_log("XML NÃO encontrado! Verifique permissões do diretório xmls/");
}

// No comprovante.php, adicione esta alternativa:
if (!file_exists($nfe_autorizada_xml_path)) {
    $xml_assinado_path = __DIR__ . '/xmls/venda_' . $venda_id . '_assinado.xml';
    if (file_exists($xml_assinado_path)) {
        $nfe_autorizada_xml_path = $xml_assinado_path;
        error_log("Usando XML assinado como fallback");
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprovante de Venda</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/comprovante.css">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<div class="container mt-4">
    <?php if ($flash_message): ?>
        <div class="alert alert-warning" role="alert">
            <?php echo $flash_message; ?>
        </div>
    <?php endif; ?>

    <!-- Recibo de Venda Interno -->
    <div class="content p-3 d-print-block">
        <img src="assets/banner.jpeg" alt="Banner escrito Hortifrut Quero Fruta" class="banner">

        <h2>Comprovante de Venda</h2>
        <p><strong>ID da Venda:</strong> <?= $venda['id'] ?></p>
        <p><strong>Data:</strong> <?= date('d/m/Y H:i', strtotime($venda['data'])) ?></p>
        <p><strong>Formas de Pagamento:</strong></p>
        <ul>
            <?php mysqli_data_seek($pagamentos, 0); // Garante que o ponteiro volte ao início para reuso ?>
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
    </div>

    <hr class="d-print-none">

    <!-- Seção do DANFE NFC-e -->
    <h3 class="d-print-none">Status da Nota Fiscal Eletrônica</h3>
    <p class="d-print-none">Status da NFC-e: <strong><?php echo htmlspecialchars($status_nf === 'ok' ? 'Autorizada' : ($status_nf === 'pendente' ? 'Processando' : 'Não Autorizada/Erro')); ?></strong></p>

    <?php if ($danfe_gerado): ?>
        <h3 class="d-print-none">DANFE NFC-e</h3>
        <div class="danfe-container border p-3 mb-3 d-print-none">
            <!-- O DANFE HTML será inserido aqui para visualização em tela -->
            <button class="btn btn-info mb-2" onclick="toggleDanfeVisibility()">Ver/Ocultar DANFE Completo</button>
            <div id="danfe-visualizacao" style="display: none;">
                <?php echo $danfe_html; ?>
            </div>
        </div>
        <!-- Área oculta para impressão, que será visível apenas no @media print -->
        <div class="danfe-printable-area" style="display: none;">
            <?php echo $danfe_html; ?>
        </div>
    <?php else: ?>
        <p class="d-print-none">Não foi possível gerar o DANFE para esta venda. Verifique o status da nota acima.</p>
        <?php if ($status_nf === 'pendente'): ?>
            <p class="d-print-none">Você pode tentar <a href="comprovante.php?id_venda=<?php echo htmlspecialchars($venda_id); ?>&nf=ok">consultar novamente o status da NFC-e</a> se ela já deveria estar autorizada.</p>
        <?php endif; ?>
    <?php endif; ?>

    <div class="text-center mt-3 d-print-none">
        <a href="registrar_venda.php" class="btn btn-primary">Nova Venda</a>
        <a href="index.php" class="btn btn-secondary">← Voltar ao Painel</a>
        <button onclick="window.print()" class="btn btn-secondary">Imprimir Comprovante/NFe</button>
    </div>
</div>

<script>
    function toggleDanfeVisibility() {
        let danfeDiv = document.getElementById('danfe-visualizacao');
        if (danfeDiv.style.display === 'none') {
            danfeDiv.style.display = 'block';
        } else {
            danfeDiv.style.display = 'none';
        }
    }
</script>

</body>
</html>
