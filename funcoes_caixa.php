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
    $stmt = $conn->prepare("SELECT id FROM operadores WHERE usuario = ? LIMIT 1");
    $stmt->bind_param("s", $usuario_logado);
    $stmt->execute();
    $stmt->bind_result($tmp_id);
    $stmt->fetch();
    $stmt->close();
    if (!empty($tmp_id)) {
        $operador_id = (int) $tmp_id;
        $_SESSION['operador_id'] = $operador_id;
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

function listaLinks($uri, $usuario_tipo){
    $lista_links_caixa = [];
    return $lista_links_caixa = [
        ['href' => $uri, 'icone' => 'bi bi-files', 'texto' => 'Duplicar Página', 'exibir' => true, 'target' => 'blank'],
        ['href' => 'produtos.php', 'icone' => 'bi bi-pencil-square', 'texto' => 'Editar Produtos', 'exibir' => true, 'target' => ''],
        ['href' => 'sangria.php', 'icone' => 'bi bi-arrow-down-circle', 'texto' => 'Fazer Sangria', 'exibir' => true, 'target' => ''],
        ['href' => 'fechar_caixa.php', 'icone' => 'bi bi-cash-coin', 'texto' => 'Fechar Caixa', 'exibir' => true, 'target' => ''],
        ['href' => 'index.php', 'icone' => 'bi bi-arrow-left', 'texto' => 'Voltar ao Painel', 'exibir' => $usuario_tipo === 'admin', 'target' => ''],
        ['href' => 'logout.php', 'icone' => 'bi bi-box-arrow-right', 'texto' => 'Sair', 'exibir' => true, 'target' => ''],
    ];
}