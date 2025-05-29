<?php
// Inclusão de arquivos essenciais para o funcionamento da página.
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../ver_sessao.php';
require_once __DIR__ . '/../includes/db.php';

// Inicialização de variáveis.
// $produto_id armazena o ID do produto vindo da URL (modo edição) ou nulo (modo adição).
$produto_id = $_GET['id'] ?? null;
$produto = null;
$variacoes = [];
$estoque_principal = 0;

// Define o título da página dinamicamente, dependendo se é uma edição ou adição de produto.
$page_title = $produto_id ? "Editar Produto" : "Adicionar Novo Produto";

// Bloco de código executado se um ID de produto é fornecido via GET (modo de edição).
if ($produto_id) {
    try {
        // Busca os dados principais do produto.
        $sql_select_produto = "SELECT * FROM produtos WHERE id = :id";
        $stmt_select_produto = $pdo->prepare($sql_select_produto);
        $stmt_select_produto->bindParam(':id', $produto_id, PDO::PARAM_INT);
        $stmt_select_produto->execute();
        $produto = $stmt_select_produto->fetch(PDO::FETCH_ASSOC);

        // Se o produto não for encontrado, define uma mensagem de erro e redireciona para a listagem.
        if (!$produto) {
            $_SESSION['mensagem_erro'] = "Produto não encontrado.";
            header("Location: index.php");
            exit;
        }

        // Busca as variações associadas ao produto, incluindo a quantidade em estoque de cada variação.
        // LEFT JOIN com 'estoque' para garantir que todas as variações sejam listadas, mesmo sem estoque registrado.
        $sql_select_variacoes = "SELECT pv.*, e.quantidade FROM produto_variacoes pv LEFT JOIN estoque e ON pv.id = e.variacao_id WHERE pv.produto_id = :produto_id ORDER BY pv.id ASC";
        $stmt_select_variacoes = $pdo->prepare($sql_select_variacoes);
        $stmt_select_variacoes->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
        $stmt_select_variacoes->execute();
        $variacoes = $stmt_select_variacoes->fetchAll(PDO::FETCH_ASSOC);

        // Busca a quantidade em estoque do produto principal (sem variação).
        // Isso é relevante se o produto pode ser vendido sem especificar uma variação,
        // ou se ele não possui variações.
        $sql_select_estoque_principal = "SELECT quantidade FROM estoque WHERE produto_id = :produto_id AND variacao_id IS NULL";
        $stmt_select_estoque_principal = $pdo->prepare($sql_select_estoque_principal);
        $stmt_select_estoque_principal->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
        $stmt_select_estoque_principal->execute();
        $estoque_res = $stmt_select_estoque_principal->fetch(PDO::FETCH_ASSOC);
        if ($estoque_res) {
            $estoque_principal = $estoque_res['quantidade'];
        }

    } catch (PDOException $e) {
        // Em caso de erro na busca, define uma mensagem e registra o erro no log.
        $_SESSION['mensagem_erro'] = "Erro ao carregar produto: " . $e->getMessage();
        error_log("Erro ao carregar produto (ID: $produto_id): " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
    }
}

// Bloco de código executado quando o formulário é submetido (método POST).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Coleta e sanitiza os dados do produto principal.
    $nome = trim($_POST['nome'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $preco_base = (float)filter_var($_POST['preco_base'] ?? 0, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $estoque_produto_principal = (int)filter_var($_POST['estoque_principal'] ?? 0, FILTER_SANITIZE_NUMBER_INT);

    // Coleta os dados das variações (arrays).
    $nomes_variacoes = $_POST['variacao_nome'] ?? [];
    $precos_adicionais = $_POST['variacao_preco_adicional'] ?? [];
    $skus_variacoes = $_POST['variacao_sku'] ?? [];
    $estoques_variacoes = $_POST['variacao_estoque'] ?? [];

    // Validação básica dos campos obrigatórios.
    if (empty($nome) || $preco_base <= 0) {
        $_SESSION['mensagem_erro'] = "Nome e Preço Base são obrigatórios e o preço deve ser maior que zero.";
    } else {
        try {
            // Inicia uma transação para garantir a atomicidade das operações no banco.
            // Ou todas as queries são executadas com sucesso, ou nenhuma é.
            $pdo->beginTransaction();

            // Lógica para ATUALIZAR um produto existente.
            if ($produto_id) {
                $sql_update_produto_query = "UPDATE produtos SET nome = :nome, descricao = :descricao, preco_base = :preco_base WHERE id = :id";
                $params_update_produto = [
                    ':nome' => $nome,
                    ':descricao' => $descricao,
                    ':preco_base' => $preco_base,
                    ':id' => (int)$produto_id // Garante que o ID seja um inteiro.
                ];
                // Log para depuração.
                error_log("DEBUG: Executando UPDATE produtos. SQL: " . $sql_update_produto_query . " | Params: " . json_encode($params_update_produto));
                $stmt_update_produto_obj = $pdo->prepare($sql_update_produto_query);
                $stmt_update_produto_obj->execute($params_update_produto);
            // Lógica para INSERIR um novo produto.
            } else { 
                $sql_insert_produto_query = "INSERT INTO produtos (nome, descricao, preco_base) VALUES (:nome, :descricao, :preco_base)";
                $params_insert_produto = [
                    ':nome' => $nome,
                    ':descricao' => $descricao,
                    ':preco_base' => $preco_base
                ];
                // Log para depuração.
                error_log("DEBUG: Executando INSERT produtos. SQL: " . $sql_insert_produto_query . " | Params: " . json_encode($params_insert_produto));
                $stmt_insert_produto_obj = $pdo->prepare($sql_insert_produto_query);
                $stmt_insert_produto_obj->execute($params_insert_produto);
                // Obtém o ID do produto recém-inserido.
                $produto_id = $pdo->lastInsertId();
                error_log("DEBUG: Novo produto ID: $produto_id");
            }

            // Validação crítica: verifica se o $produto_id é válido após a inserção/atualização.
            if (!is_numeric($produto_id) || (int)$produto_id <= 0) {
                 throw new PDOException("ID do produto inválido após inserção/atualização. Valor: " . $produto_id);
            }
            $produto_id = (int)$produto_id;

            // Verifica se alguma variação foi submetida no formulário.
            // Isso é importante para decidir se o estoque principal deve ser gerenciado
            // ou se o estoque será por variação.
            $has_submitted_variations = false;
            if (!empty($nomes_variacoes)) {
                foreach ($nomes_variacoes as $vn) {
                    if (!empty(trim($vn))) {
                        $has_submitted_variations = true;
                        break;
                    }
                }
            }
            error_log("DEBUG: Produto ID: $produto_id. Has submitted variations? " . ($has_submitted_variations ? 'Yes' : 'No'));
            error_log("DEBUG: Produto ID: $produto_id. Value of estoque_produto_principal from POST: " . $estoque_produto_principal);

            // Se NÃO houver variações submetidas, gerencia o estoque principal do produto.
            if (!$has_submitted_variations) { 
                $sql_check_estoque_principal = "SELECT id FROM estoque WHERE produto_id = :pid AND variacao_id IS NULL";
                $stmt_check = $pdo->prepare($sql_check_estoque_principal);
                $stmt_check->execute([':pid' => $produto_id]);
                $existing_estoque_id = $stmt_check->fetchColumn();

                if ($existing_estoque_id) {
                    // Atualiza o registro de estoque principal existente.
                    $sql_update_estoque_principal = "UPDATE estoque SET quantidade = :qtd WHERE id = :estoque_id";
                    $params_update_estoque = [
                        ':qtd' => $estoque_produto_principal,
                        ':estoque_id' => $existing_estoque_id
                    ];
                    error_log("DEBUG: Executando UPDATE estoque principal. SQL: " . $sql_update_estoque_principal . " | Params: " . json_encode($params_update_estoque));
                    $stmt_estoque_principal_obj = $pdo->prepare($sql_update_estoque_principal);
                    $stmt_estoque_principal_obj->execute($params_update_estoque);
                    error_log("DEBUG: Estoque principal atualizado. rowCount: " . $stmt_estoque_principal_obj->rowCount());
                } else {
                    // Insere um novo registro de estoque principal.
                    $sql_insert_estoque_principal = "INSERT INTO estoque (produto_id, variacao_id, quantidade) VALUES (:pid, NULL, :qtd)";
                    $params_insert_estoque = [
                        ':pid' => $produto_id,
                        ':qtd' => $estoque_produto_principal
                    ];
                    error_log("DEBUG: Executando INSERT estoque principal. SQL: " . $sql_insert_estoque_principal . " | Params: " . json_encode($params_insert_estoque));
                    $stmt_estoque_principal_obj = $pdo->prepare($sql_insert_estoque_principal);
                    $stmt_estoque_principal_obj->execute($params_insert_estoque);
                    error_log("DEBUG: Estoque principal inserido. rowCount: " . $stmt_estoque_principal_obj->rowCount());
                }
            // Se HOUVER variações submetidas, o estoque principal (sem variação) é removido.
            } else {
                $sql_delete_estoque_principal_query = "DELETE FROM estoque WHERE produto_id = :pid AND variacao_id IS NULL";
                $params_delete_estoque_principal = [':pid' => $produto_id];
                error_log("DEBUG: Deletando estoque principal para produto com variações. SQL: " . $sql_delete_estoque_principal_query . " | Params: " . json_encode($params_delete_estoque_principal));
                $stmt_delete_estoque_principal_obj = $pdo->prepare($sql_delete_estoque_principal_query);
                $stmt_delete_estoque_principal_obj->execute($params_delete_estoque_principal);
            }

            // Lógica para lidar com a atualização de variações (deletar antigas e inserir novas).
            // Isso simplifica o CRUD de variações: sempre deleta as existentes para o produto_id
            // e insere as que vieram do formulário.
            $produto_id_url = $_GET['id'] ?? null;
            if ($produto_id_url) { 
                $sql_delete_variacoes_query = "DELETE FROM produto_variacoes WHERE produto_id = :pid";
                $params_delete_variacoes = [':pid' => (int)$produto_id_url];
                error_log("DEBUG: Deletando variações antigas. SQL: " . $sql_delete_variacoes_query . " | Params: " . json_encode($params_delete_variacoes));
                $stmt_delete_variacoes_obj = $pdo->prepare($sql_delete_variacoes_query);
                $stmt_delete_variacoes_obj->execute($params_delete_variacoes);
                error_log("DEBUG: Variações antigas deletadas para produto ID: " . (int)$produto_id_url);
            }

            // Itera sobre as variações enviadas pelo formulário.
            for ($i = 0; $i < count($nomes_variacoes); $i++) {
                $nome_var = trim($nomes_variacoes[$i]);
                // Pula variações sem nome (consideradas inválidas ou não preenchidas).
                if (empty($nome_var)) {
                    error_log("DEBUG: Pulando variação sem nome na posição $i.");
                    continue;
                }

                // Coleta e sanitiza dados da variação atual.
                $preco_add_var = (float)filter_var($precos_adicionais[$i] ?? 0, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                $sku_var = trim($skus_variacoes[$i] ?? '');
                $estoque_var = (int)filter_var($estoques_variacoes[$i] ?? 0, FILTER_SANITIZE_NUMBER_INT);
                // Insere a nova variação.
                $sql_insert_variacao_query = "INSERT INTO produto_variacoes (produto_id, nome_variacao, preco_adicional, sku) VALUES (:pid, :nome_v, :preco_v, :sku_v)";
                $params_insert_variacao = [
                    ':pid' => $produto_id,
                    ':nome_v' => $nome_var,
                    ':preco_v' => $preco_add_var,
                    ':sku_v' => !empty($sku_var) ? $sku_var : null
                ];
                error_log("DEBUG: Executando INSERT produto_variacoes. SQL: " . $sql_insert_variacao_query . " | Params: " . json_encode($params_insert_variacao));
                $stmt_insert_variacao_obj = $pdo->prepare($sql_insert_variacao_query);
                $stmt_insert_variacao_obj->execute($params_insert_variacao);
                $variacao_id = $pdo->lastInsertId();
                // Validação crítica: verifica se o ID da variação é válido.
                if (!is_numeric($variacao_id) || (int)$variacao_id <= 0) {
                     throw new PDOException("ID da variação inválido após inserção. Valor: " . $variacao_id);
                }
                $variacao_id = (int)$variacao_id;
                error_log("DEBUG: Nova variação ID: $variacao_id");

                // Insere ou atualiza o estoque da variação.
                // ON DUPLICATE KEY UPDATE é usado para simplificar: se a combinação produto_id + variacao_id já existir
                // (o que não deveria acontecer aqui devido à deleção prévia, mas é uma boa prática), atualiza a quantidade.
                $sql_estoque_variacao_query = "INSERT INTO estoque (produto_id, variacao_id, quantidade) VALUES (:pid, :vid, :qtd) ON DUPLICATE KEY UPDATE quantidade = VALUES(quantidade)";
                $params_estoque_variacao = [
                    ':pid' => $produto_id,
                    ':vid' => $variacao_id, 
                    ':qtd' => $estoque_var
                ];
                error_log("DEBUG: Executando INSERT/UPDATE estoque variação. SQL: " . $sql_estoque_variacao_query . " | Params: " . json_encode($params_estoque_variacao));
                $stmt_est_var_obj = $pdo->prepare($sql_estoque_variacao_query);
                $stmt_est_var_obj->execute($params_estoque_variacao);
                error_log("DEBUG: Estoque da variação gerenciado para variação ID: $variacao_id");
            }

            // Se todas as operações foram bem-sucedidas, commita a transação.
            $pdo->commit();
            $_SESSION['mensagem_sucesso'] = "Produto salvo com sucesso!";
            header("Location: index.php");
            exit;

        } catch (PDOException $e) {
            // Em caso de erro durante a transação, faz o rollback para reverter as alterações.
            $pdo->rollBack();
            $_SESSION['mensagem_erro'] = "Erro ao salvar produto: " . $e->getMessage();
            // Log detalhado do erro.
            error_log("PDOException em produto_gerenciar.php: " . $e->getMessage() . " | SQLSTATE: " . $e->getCode() . " | Trace: " . $e->getTraceAsString());
        } catch (Exception $e) {
             // Captura exceções genéricas que podem ocorrer.
             if ($pdo->inTransaction()) {
                 $pdo->rollBack();
             }
             $_SESSION['mensagem_erro'] = "Ocorreu um erro inesperado: " . $e->getMessage();
             error_log("Exception inesperada em produto_gerenciar.php: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
        }
    }
}

// Inclusão do cabeçalho da página.
include __DIR__ . '/../includes/header.php';
?>

<h2><?php echo $page_title; ?></h2>

<!-- Formulário para adicionar/editar produto. A action é dinâmica, incluindo o ID do produto se estiver em modo de edição. -->
<form method="POST" action="produto_gerenciar.php<?php echo $produto_id ? '?id=' . $produto_id : ''; ?>">
    <!-- Seção para informações principais do produto -->
    <div class="mb-4 p-3 border rounded">
        <h5 class="mb-3">Informações Principais do Produto</h5>
        <!-- Campo Nome do Produto -->
        <div class="form-group">
            <label for="nome">Nome do Produto:</label>
            <input type="text" class="form-control" id="nome" name="nome" value="<?php echo htmlspecialchars($produto['nome'] ?? ''); ?>" required>
        </div>
        <!-- Campo Descrição -->
        <div class="form-group">
            <label for="descricao">Descrição:</label>
            <textarea class="form-control" id="descricao" name="descricao" rows="3"><?php echo htmlspecialchars($produto['descricao'] ?? ''); ?></textarea>
        </div>
        <div class="form-row">
            <!-- Campo Preço Base -->
            <div class="form-group col-md-6">
                <label for="preco_base">Preço Base:</label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text">R$</span>
                    </div>
                    <input type="number" step="0.01" class="form-control" id="preco_base" name="preco_base" value="<?php echo htmlspecialchars($produto['preco_base'] ?? '0.00'); ?>" required>
                </div>
            </div>
            <!-- Campo Estoque Principal (para produtos sem variação) -->
            <div class="form-group col-md-6">
                <label for="estoque_principal">Estoque Principal (sem variação):</label>
                <input type="number" class="form-control" id="estoque_principal" name="estoque_principal" value="<?php echo htmlspecialchars($estoque_principal); ?>">
                <small class="form-text text-muted">Use este campo se o produto não tiver variações.</small>
            </div>
        </div>
    </div>

    <!-- Seção para gerenciar as variações do produto -->
    <div class="mb-4 p-3 border rounded">
        <h5 class="mb-3">Variações do Produto</h5>
        <div id="variacoes-container">
            <?php // Se estiver editando um produto e ele já tiver variações, exibe os campos preenchidos. ?>
            <?php if (!empty($variacoes)) : ?>
                <?php foreach ($variacoes as $idx => $var) : ?>
                    <div class="card mb-3 variacao-item">
                        <div class="card-body">
                            <!-- Campo oculto para o ID da variação (útil se fosse fazer update individual, mas aqui deletamos e recriamos) -->
                            <input type="hidden" name="variacao_id[]" value="<?php echo htmlspecialchars($var['id']); ?>">
                            <!-- Campos da variação: Nome, Preço Adicional, SKU, Estoque -->
                            <div class="form-row">
                                <div class="form-group col-md-4">
                                    <label>Nome da Variação (Ex: Cor, Tamanho)</label>
                                    <input type="text" name="variacao_nome[]" class="form-control" value="<?php echo htmlspecialchars($var['nome_variacao']); ?>" placeholder="Ex: Vermelho P">
                                </div>
                                <div class="form-group col-md-2">
                                    <label>Preço Adicional</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">R$</span>
                                        </div>
                                        <input type="number" step="0.01" name="variacao_preco_adicional[]" class="form-control" value="<?php echo htmlspecialchars($var['preco_adicional'] ?? '0.00'); ?>" placeholder="0.00">
                                    </div>
                                </div>
                                <div class="form-group col-md-3">
                                    <label>SKU</label>
                                    <input type="text" name="variacao_sku[]" class="form-control" value="<?php echo htmlspecialchars($var['sku'] ?? ''); ?>">
                                </div>
                                <div class="form-group col-md-2">
                                    <label>Estoque</label>
                                    <input type="number" name="variacao_estoque[]" class="form-control" value="<?php echo htmlspecialchars($var['quantidade'] ?? 0); ?>">
                                </div>
                                <!-- Botão para remover a variação (apenas no front-end antes de submeter) -->
                                <div class="form-group col-md-1 align-self-end">
                                    <button type="button" class="btn btn-danger btn-sm remove-variacao">Remover</button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <!-- Botão para adicionar um novo conjunto de campos de variação via JavaScript -->
        <button type="button" id="add-variacao" class="btn btn-info btn-sm mt-3">Adicionar Variação</button>
    </div>

    <!-- Botões de Ação do Formulário -->
    <button type="submit" class="btn btn-primary">Salvar Produto</button>
    <a href="index.php" class="btn btn-secondary">Cancelar</a>
</form>

<!-- Template HTML para um novo item de variação (usado pelo JavaScript) -->
<div id="variacao-template" style="display: none;">
    <div class="card mb-3 variacao-item">
        <div class="card-body">
            <input type="hidden" name="variacao_id[]" value="">
            <div class="form-row">
                <div class="form-group col-md-4"><label>Nome da Variação</label><input type="text" name="variacao_nome[]" class="form-control" placeholder="Ex: Azul G"></div>
                <div class="form-group col-md-2">
                    <label>Preço Adicional</label>
                    <div class="input-group">
                        <div class="input-group-prepend"><span class="input-group-text">R$</span></div>
                        <input type="number" step="0.01" name="variacao_preco_adicional[]" class="form-control" placeholder="0.00">
                    </div>
                </div>
                <div class="form-group col-md-3"><label>SKU</label><input type="text" name="variacao_sku[]" class="form-control"></div>
                <div class="form-group col-md-2"><label>Estoque</label><input type="number" name="variacao_estoque[]" class="form-control" value="0"></div>
                <div class="form-group col-md-1 align-self-end"><button type="button" class="btn btn-danger btn-sm remove-variacao">Remover</button></div>
            </div>
        </div>
    </div>
</div>

<script>
// Script JavaScript para interatividade no formulário de variações.
document.addEventListener('DOMContentLoaded', function() {
    // Event listener para o botão "Adicionar Variação".
    document.getElementById('add-variacao').addEventListener('click', function() {
        // Clona o template HTML de variação.
        var template = document.getElementById('variacao-template').innerHTML;
        // Adiciona o HTML clonado ao container de variações.
        document.getElementById('variacoes-container').insertAdjacentHTML('beforeend', template);
    });

    // Event listener no container de variações para lidar com a remoção de itens.
    // Utiliza delegação de eventos para funcionar com itens adicionados dinamicamente.
    document.getElementById('variacoes-container').addEventListener('click', function(e) {
        if (e.target && e.target.classList.contains('remove-variacao')) {
            // Remove o elemento pai '.variacao-item' do botão clicado.
            e.target.closest('.variacao-item').remove();
        }
    });
});
</script>
<?php // Inclusão do rodapé da página. ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
