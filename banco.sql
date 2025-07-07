CREATE TABLE funcionarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    data_de_admissao DATE NOT NULL,
    data_de_demissao DATE DEFAULT NULL,
    cargo VARCHAR(50) NOT NULL,
    salario DECIMAL(10, 2) NOT NULL
);

CREATE TABLE IF NOT EXISTS gastos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    categoria VARCHAR(100),
    valor DECIMAL(10, 2),
    data DATE,
    descricao TEXT
);

CREATE TABLE itens_venda (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venda_id INT,
    produto_id INT,
    quantidade DOUBLE,
    unidade_medida VARCHAR(20),
    desconto DECIMAL(10,2) DEFAULT 0,
    FOREIGN KEY (venda_id) REFERENCES vendas(id),
    FOREIGN KEY (produto_id) REFERENCES produtos(id)
);

ALTER TABLE itens_venda MODIFY quantidade DOUBLE;

CREATE TABLE operadores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario VARCHAR(50) UNIQUE,
    senha VARCHAR(255),
    tipo ENUM('admin', 'vendedor') DEFAUT 'vendedor'
);

CREATE TABLE produtos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100),
    preco DECIMAL(10,2),
    codigo_barras VARCHAR(100) UNIQUE,
    unidade_medida VARCHAR(10) NOT NULL DEFAULT 'UN'
);

CREATE TABLE unidades_produto (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produto_id INT,
    unidade VARCHAR(20),
    fator_multiplicador DECIMAL(10,3), -- para calcular preço relativo, se necessário
    preco DECIMAL(10,2),
    FOREIGN KEY (produto_id) REFERENCES produtos(id)
);

CREATE TABLE venda_itens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venda_id INT,
    produto_id INT,
    quantidade INT,
    preco_unitario DECIMAL(10,2),
    total_item DECIMAL(10,2),
    FOREIGN KEY (venda_id) REFERENCES vendas(id),
    FOREIGN KEY (produto_id) REFERENCES produtos(id)
);

CREATE TABLE vendas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    data TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    total DECIMAL(10,2),
    forma_pagamento VARCHAR(20),
    operador_id INT,
    desconto DECIMAL(10,2) DEFAULT 0,
    total_liquido DECIMAL(10,2) DEFAULT 0

);

CREATE TABLE pagamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venda_id INT,
    forma_pagamento VARCHAR(50),
    valor_pago DECIMAL(10,2),
    FOREIGN KEY (venda_id) REFERENCES vendas(id)
);
