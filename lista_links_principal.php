<?php
$lista_links = [
    ['href' => 'registrar_venda.php','icone' => 'fas fa-cash-register', 'texto' => 'Registrar Venda', 'exibir' => true, 'target' => ''],
    ['href' => 'gastos.php','icone' => 'fas fa-money-bill-wave', 'texto' => 'Registrar Despesas', 'exibir' => $usuario_tipo === 'admin', 'target' => ''],
    ['href' => 'produtos.php','icone' => 'fas fa-boxes', 'texto' => 'Gerenciar Produtos', 'exibir' => true, 'target' => ''],
    ['href' => 'usuarios.php','icone' => 'fas fa-users-cog', 'texto' => 'Gerenciar Usuários', 'exibir' => $usuario_tipo === 'admin', 'target' => ''],
    ['href' => 'funcionarios.php','icone' => 'fas fa-users-cog', 'texto' => 'Gerenciar Funcionários', 'exibir' => $usuario_tipo === 'admin', 'target' => ''],
    ['href' => 'etiquetas.php','icone' => 'fas fa-users-cog', 'texto' => 'Imprimir Etiquetas', 'exibir' => true, 'target' => ''],
    ['href' => 'relatorio.php','icone' => 'fas fa-chart-line', 'texto' => 'Relatórios', 'exibir' => $usuario_tipo === 'admin', 'target' => ''],
];
?>