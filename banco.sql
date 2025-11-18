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

CREATE TABLE `produtos` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) DEFAULT NULL,
  `preco` decimal(10,2) DEFAULT NULL,
  `codigo_barras` varchar(100) DEFAULT NULL,
  `unidade_medida` varchar(10) NOT NULL DEFAULT 'UN',
  `ncm` varchar(8) DEFAULT NULL,
  `cfop` varchar(4) DEFAULT '5102'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `produtos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo_barras` (`codigo_barras`),
  ADD UNIQUE KEY `nome` (`nome`,`unidade_medida`);

ALTER TABLE `produtos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=288;
COMMIT;

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

ALTER TABLE vendas ADD COLUMN chave_nfe VARCHAR(100) DEFAULT NULL, 
                      ADD COLUMN protocolo VARCHAR(50) DEFAULT NULL,
                      ADD COLUMN status_nf VARCHAR(30) DEFAULT NULL;
ALTER TABLE vendas ADD COLUMN caixa_id INT;

ALTER TABLE vendas ADD COLUMN hash_venda VARCHAR(32) NULL;
CREATE INDEX idx_hash_venda ON vendas(hash_venda);

CREATE TABLE pagamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venda_id INT,
    forma_pagamento VARCHAR(50),
    valor_pago DECIMAL(10,2),
    FOREIGN KEY (venda_id) REFERENCES vendas(id)
);

CREATE TABLE caixas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    operador_id INT,
    data_abertura DATETIME NOT NULL,
    valor_inicial DECIMAL(10,2) NOT NULL,
    data_fechamento DATETIME DEFAULT NULL,
    valor_fechamento DECIMAL(10,2) DEFAULT NULL
);

CREATE TABLE sangrias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    caixa_id INT NOT NULL,
    operador_id INT NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    descricao VARCHAR(255),
    data_sangria DATETIME NOT NULL,
    FOREIGN KEY (caixa_id) REFERENCES caixas(id),
    FOREIGN KEY (operador_id) REFERENCES operadores(id)
);

CREATE TABLE numeracao_nfe (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ultimo_numero INT NOT NULL DEFAULT 0,
    serie INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO numeracao_nfe (ultimo_numero, serie) VALUES (0, 1);
