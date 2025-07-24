<?php

function getCaixaAberto($conn, $operador_id) {
    $sql = "SELECT id FROM caixas WHERE operador_id = ? AND data_fechamento IS NULL ORDER BY id DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $operador_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $caixa = $result->fetch_assoc();
    return $caixa ? $caixa['id'] : null;
}
?>