<?php
include 'verifica_login.php';
include 'config.php';

// Mensagem de alerta
$mensagem = "";

// Deletar produto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $id = intval($_POST['delete_id']);
    $stmt = $conn->prepare("DELETE FROM produtos WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: produtos.php");
    exit;
}

// Adicionar novo produto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['novo_nome'], $_POST['novo_preco'], $_POST['novo_codigo'], $_POST['novo_ncm'], $_POST['novo_unidade'])) {
    $nome = trim($_POST['novo_nome']);
    $nome = ucwords(strtolower($nome));
    $preco = floatval($_POST['novo_preco']);
    if ($preco <= 0) {
        $mensagem = "Preço deve ser maior que zero.";
    }
    $codigo = trim($_POST['novo_codigo']);
    $ncm = formatarNCM($_POST['novo_ncm']);
    // Limita a no máximo 8 dígitos
    $ncm = substr($ncm, 0, 8);
    $unidade = trim($_POST['novo_unidade']);

    if (empty($nome) || empty($preco) || empty($unidade)) {
        $mensagem = "Todos os campos obrigatórios devem ser preenchidos.";
    } else {
        // Verifica se o nome já existe
        $stmt = $conn->prepare("SELECT id FROM produtos WHERE LOWER(nome) = LOWER(?) AND unidade_medida = ?");
        $stmt->bind_param("ss", $nome, $unidade);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $mensagem = "Produto com este nome e unidade de medida já está cadastrado.";
        }
        else {
            if (!empty($codigo)) {
                // Verifica se o código de barras preenchido já existe
                $stmt = $conn->prepare("SELECT id FROM produtos WHERE codigo_barras = ?");
                $stmt->bind_param("s", $codigo);
                $stmt->execute();
                $stmt->store_result();

                if ($stmt->num_rows > 0) {
                    $mensagem = "Já existe um produto com este código de barras.";
                } else {
                    // Insere normalmente
                    $stmt = $conn->prepare("INSERT INTO produtos (nome, preco, codigo_barras, ncm, unidade_medida) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("sdsss", $nome, $preco, $codigo, $ncm, $unidade);
                    $stmt->execute();
                    header("Location: produtos.php?sucesso=1");
                    exit;
                }
            } else {
                // Código de barras vazio: insere normalmente
                $stmt = $conn->prepare("INSERT INTO produtos (nome, preco, codigo_barras, ncm, unidade_medida) VALUES (?, ?, NULLIF(?, ''), NULLIF(?, ''), ?)");
                $stmt->bind_param("sdsss", $nome, $preco, $codigo, $ncm, $unidade);
                $stmt->execute();
                header("Location: produtos.php?sucesso=1");
                exit;
            }
        }
    }
}

// Atualizar produto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'], $_POST['edit_nome'], $_POST['edit_preco'], $_POST['edit_codigo'], $_POST['edit_ncm'], $_POST['edit_unidade'])) {
    $id = intval($_POST['edit_id']);
    $nome = trim($_POST['edit_nome']);
    $nome = ucwords(strtolower($nome));
    $preco = floatval($_POST['edit_preco']);
    if ($preco <= 0) {
        $mensagem = "Preço deve ser maior que zero.";
    }

    $codigo = trim($_POST['edit_codigo']);
    $ncm = formatarNCM($_POST['edit_ncm']);
    // Limita a no máximo 8 dígitos
    $ncm = substr($ncm, 0, 8);
    $unidade = trim($_POST['edit_unidade']);

    if (empty($nome) || empty($preco) || empty($unidade)) {
        $mensagem = "Todos os campos obrigatórios devem ser preenchidos.";
    } else {
        if (!empty($codigo)) {
            // Verifica se já existe outro produto com o mesmo código de barras
            $stmt = $conn->prepare("SELECT id FROM produtos WHERE codigo_barras = ? AND id != ?");
            $stmt->bind_param("si", $codigo, $id);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $mensagem = "Já existe outro produto com este código de barras.";
            } else {
                $stmt = $conn->prepare("UPDATE produtos SET nome = ?, preco = ?, codigo_barras = ?, ncm = ?, unidade_medida = ? WHERE id = ?");
                $stmt->bind_param("sdsssi", $nome, $preco, $codigo, $ncm, $unidade, $id);
                $stmt->execute();
                header("Location: produtos.php");
                exit;
            }
        } else {
            // Código vazio: atualiza como NULL
            $stmt = $conn->prepare("UPDATE produtos SET nome = ?, preco = ?, codigo_barras = NULLIF(?, ''), ncm = NULLIF(?, ''), unidade_medida = ? WHERE id = ?");
            $stmt->bind_param("sdsssi", $nome, $preco, $codigo, $ncm, $unidade, $id);
            $stmt->execute();
            header("Location: produtos.php");
            exit;
        }
    }
}

// Buscar produtos
$result = $conn->query("SELECT * FROM produtos ORDER BY id DESC");

// Função para formatar NCM
function formatarNCM($ncm) {
    // Remove tudo que não for número
    $ncm = preg_replace('/\D/', '', $ncm);

    // Preenche com zeros à esquerda até 8 dígitos
    return str_pad($ncm, 8, '0', STR_PAD_LEFT);
}


require 'lista_links_principal.php';
require "view/header.php";
?>

<div class="container py-5">
    <?php if (isset($_GET['sucesso'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Produto adicionado com sucesso!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif (!empty($mensagem)): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($mensagem) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <h2 class="mb-4">Gerenciar Produtos</h2>
    <a href="index.php" class="btn btn-secondary mb-3">← Voltar ao Painel</a>

    <!-- Botão que abre o modal de novo produto -->
    <button class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#novoProdutoModal">
        + Adicionar Produto
    </button>

    <table class="table table-bordered bg-white table-hover">
    <thead class="table-dark">
        <tr>
            <th>ID</th>
            <th>Nome</th>
            <th>Preço</th>
            <th>Código de Barras</th>
            <th>Código NCM</th>
            <th>Unidade</th>
            <th>Ações</th>
        </tr>
    </thead>
    <tbody>
        <?php while($row = $result->fetch_assoc()): ?>
        <tr>
            <td><?= $row['id'] ?></td>
            <td><?= htmlspecialchars($row['nome']) ?></td>
            <td>R$ <?= number_format($row['preco'], 2, ',', '.') ?></td>
            <td><?= htmlspecialchars($row['codigo_barras']) ?></td>
            <td><?= htmlspecialchars($row['ncm']) ?></td>
            <td><?= htmlspecialchars($row['unidade_medida']) ?></td>
            <td>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editarModal<?= $row['id'] ?>">
                    Editar
                </button>
                <form method="post" style="display:inline;" onsubmit="return confirm('Tem certeza que deseja excluir este produto?');">
                    <input type="hidden" name="delete_id" value="<?= $row['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm">Excluir</button>
                </form>
                <a href="#" class="btn btn-warning btn-sm" onclick="gerarCodigoBarras(<?= $row['id'] ?>)">
                    Gerar Código de Barras
                </a>
            </td>
        </tr>

        <!-- Modal Edição -->
        <div class="modal fade" id="editarModal<?= $row['id'] ?>" tabindex="-1" aria-labelledby="editarLabel<?= $row['id'] ?>" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="post">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editarLabel<?= $row['id'] ?>">Editar Produto - <?= htmlspecialchars($row['nome']) ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="edit_id" value="<?= $row['id'] ?>">
                            <div class="mb-3">
                                <label class="form-label">Nome do Produto</label>
                                <input type="text" name="edit_nome" class="form-control" required value="<?= htmlspecialchars($row['nome']) ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Preço</label>
                                <input type="number" step="0.01" min="0" name="edit_preco" class="form-control" value="<?= $row['preco'] ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Código de Barras</label>
                                <input type="text" name="edit_codigo" class="form-control" value="<?= htmlspecialchars($row['codigo_barras']) ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Código NCM</label>
                                <input type="text" name="edit_ncm" class="form-control" value="<?= htmlspecialchars($row['ncm']) ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Unidade de Medida</label>
                                <select class="form-select" name="edit_unidade" required>
                                    <option value="">Selecione</option>
                                    <?php
                                    $unidades = [
                                                    'UN' => 'Unidade', 
                                                    'PCT' => 'Pacote', 
                                                    'KG' => 'Quilo', 
                                                    '1KG' => '1 Quilo', 
                                                    '2KG' => '2 Quilos', 
                                                    '5KG' => '5 Quilos', 
                                                    '330ML' => '330 Mililitros', 
                                                    '350ML' => '350 Mililitros', 
                                                    '500ML' => '500 Mililitros', 
                                                    '600ML' => '600 Mililitros', 
                                                    'LT' => 'Litro',
                                                    '1LT' => '1 Litro',
                                                    '2LT' => '2 Litros', 
                                                    'DZ' => 'Duzia', 
                                                    'CTL' => 'Cartela',
                                                    'CTL20' => 'Cartela com 20',
                                                    'CTL30' => 'Cartela com 30',
                                                    'CX' => 'Caixa'
                                                ];
                                    foreach($unidades as $key => $label): ?>
                                        <option value="<?= $key ?>" <?= $row['unidade_medida'] == $key ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-success">Salvar Alterações</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    </tbody>
</table>
</div>

<!-- Modal Novo Produto -->
<div class="modal fade" id="novoProdutoModal" tabindex="-1" aria-labelledby="novoProdutoLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <div class="modal-header">
          <h5 class="modal-title" id="novoProdutoLabel">Novo Produto</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <div class="mb-3">
                <label class="form-label">Nome do Produto</label>
                <input type="text" class="form-control" name="novo_nome" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Preço</label>
                <input type="number" step="0.01" min="0" class="form-control" name="novo_preco" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Código de Barras</label>
                <input type="text" name="novo_codigo" class="form-control" maxlength="20" pattern="[0-9]*">
            </div>
            <div class="mb-3">
                <label class="form-label">Código NCM</label>
                <input type="text" name="novo_ncm" class="form-control" pattern="[0-9]*">
            </div>
            <div class="mb-3">
                <label class="form-label">Unidade de Medida</label>
                <select class="form-select" name="novo_unidade" required>
                    <option value="">Selecione</option>
                    <option value="UN">Unidade</option>
                    <option value="PCT">Pacote</option>
                    <option value="KG">Quilo</option>
                    <option value="1KG">1 Quilo</option> 
                    <option value="2KG">2 Quilos</option>
                    <option value="5KG">5 Quilos</option>
                    <option value="330ML">330 Mililitros</option> 
                    <option value="350ML">350 Mililitros</option>
                    <option value="500ML">500 Mililitros</option>
                    <option value="600ML">600 Mililitros</option>
                    <option value="LT">Litro</option>
                    <option value="1LT">1 Litro</option>
                    <option value="2LT">2 Litros</option>
                    <option value="DZ">Duzia</option>
                    <option value="CTL">Cartela</option>
                    <option value="CTL20">Cartela com 20</option>
                    <option value="CTL30">Cartela com 30</option>
                </select>
            </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Adicionar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function gerarCodigoBarras(id) {
    fetch('gerar_codigo_barras.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: 'id=' + encodeURIComponent(id)
    })
    .then(async response => {
        if (!response.ok) {
            const text = await response.text();
            throw new Error('Erro HTTP: ' + response.status + '\n' + text);
        }
        return response.json();
    })
    .then(data => {
        alert(data.mensagem);
        if (data.status === 'sucesso') {
            location.reload();
        }
    })
    .catch(error => {
        alert('Erro na requisição:\n' + error.message);
        console.error(error);
    });
}
</script>

</body>
</html>
