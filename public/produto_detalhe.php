<?php
// public/produto_detalhe.php

// Habilitar exibição de erros para depuração (REMOVER EM PRODUÇÃO)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inclusão de arquivos essenciais.
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

// Obtém o ID do produto da URL (via GET), ou define como nulo se não fornecido.
$produto_id = $_GET['id'] ?? null;
$produto = null;
$variacoes_produto = [];
$estoque_disponivel_total = 0; // Estoque total, considerando variações ou produto principal.

// Validação inicial do ID do produto.
// Se o ID não for fornecido ou não for numérico, redireciona para a página inicial da loja com uma mensagem de erro.
if (!$produto_id || !is_numeric($produto_id)) {
    $_SESSION['mensagem_erro'] = "Produto inválido.";
    header("Location: " . BASE_URL . "public/index.php");
    exit;
}

// Bloco try-catch para tratamento de exceções durante a interação com o banco de dados.
try {
    // Carregar dados do produto principal
    // Prepara e executa a query para buscar os dados principais do produto.
    $stmt_produto = $pdo->prepare("SELECT * FROM produtos WHERE id = :id");
    $stmt_produto->bindParam(':id', $produto_id, PDO::PARAM_INT);
    $stmt_produto->execute();
    $produto = $stmt_produto->fetch(PDO::FETCH_ASSOC);

    // Se o produto não for encontrado, define uma mensagem de erro e redireciona.
    if (!$produto) {
        $_SESSION['mensagem_erro'] = "Produto não encontrado.";
        header("Location: " . BASE_URL . "public/index.php");
        exit;
    }

    // Carregar variações e seus respectivos estoques
    // Prepara e executa a query para buscar as variações associadas ao produto,
    // incluindo a quantidade em estoque de cada variação.
    $stmt_variacoes = $pdo->prepare("
        SELECT pv.id as variacao_id, pv.nome_variacao, pv.preco_adicional, pv.sku, e.quantidade as estoque_variacao
        FROM produto_variacoes pv
        LEFT JOIN estoque e ON pv.id = e.variacao_id AND e.produto_id = pv.produto_id
        WHERE pv.produto_id = :produto_id
        ORDER BY pv.nome_variacao ASC
    ");
    $stmt_variacoes->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
    $stmt_variacoes->execute();
    $variacoes_produto = $stmt_variacoes->fetchAll(PDO::FETCH_ASSOC);

    // Lógica para determinar o estoque disponível.
    // Se não houver variações, busca o estoque do produto principal.
    if (empty($variacoes_produto)) {
        $stmt_estoque_principal = $pdo->prepare("SELECT quantidade FROM estoque WHERE produto_id = :produto_id AND variacao_id IS NULL");
        $stmt_estoque_principal->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
        $stmt_estoque_principal->execute();
        $res_estoque = $stmt_estoque_principal->fetch(PDO::FETCH_ASSOC);
        $estoque_disponivel_total = $res_estoque ? (int)$res_estoque['quantidade'] : 0;
    } else {
        // Se houver variações, soma o estoque de todas as variações disponíveis.
        // Este $estoque_disponivel_total é mais para uma indicação geral;
        // a lógica de compra considerará o estoque da variação específica selecionada.
        foreach ($variacoes_produto as $vp) {
            $estoque_disponivel_total += (int)($vp['estoque_variacao'] ?? 0);
        }
    }

} catch (PDOException $e) {
    // Em caso de erro no banco, registra o erro no log e define uma mensagem genérica para o usuário.
    error_log("Erro ao carregar detalhes do produto ID $produto_id: " . $e->getMessage());
    $_SESSION['mensagem_erro'] = "Erro ao carregar informações do produto.";
    // header("Location: " . BASE_URL . "public/index.php"); // Pode ser melhor exibir a mensagem na própria página
}

// Inclusão do cabeçalho da página.
include __DIR__ . '/../includes/header.php';
?>

<div class="container mt-4">
    <?php // Verifica se os dados do produto foram carregados com sucesso. ?>
    <?php if ($produto): ?>
        <div class="row">
            <div class="col-md-6">
                <!-- Imagem do produto (placeholder) -->
                <img src="https://via.placeholder.com/500x400.png?text=<?php echo urlencode($produto['nome']); ?>" class="img-fluid rounded shadow-sm" alt="<?php echo htmlspecialchars($produto['nome']); ?>">
            </div>
            <div class="col-md-6">
                <!-- Nome do Produto -->
                <h2><?php echo htmlspecialchars($produto['nome']); ?></h2>
                <!-- Descrição do Produto. nl2br converte quebras de linha em <br> tags. -->
                <p class="text-muted"><?php echo nl2br(htmlspecialchars($produto['descricao'] ?? '')); ?></p>
                <!-- Preço Base do Produto. O JavaScript atualizará este campo se houver variações. -->
                <h4 class="mb-3">
                    Preço Base: 
                    <span id="preco-exibido" class="font-weight-bold text-success">R$&nbsp;<?php echo number_format($produto['preco_base'], 2, ',', '.'); ?></span>
                </h4>


                <!-- Formulário para adicionar o produto ao carrinho. -->
                <form action="<?php echo BASE_URL; ?>public/adicionar_carrinho.php" method="POST" class="mt-3">
                    <input type="hidden" name="produto_id" value="<?php echo $produto['id']; ?>">

                    <?php // Se o produto tiver variações, exibe um campo de seleção. ?>
                    <?php if (!empty($variacoes_produto)): ?>
                        <div class="form-group">
                            <label for="variacao_id">Escolha uma variação:</label>
                            <!-- O select de variações contém atributos 'data-*' para serem usados pelo JavaScript
                                 para atualizar o preço e o estoque máximo no campo de quantidade. -->
                            <select name="variacao_id" id="variacao_id" class="form-control" required data-preco-base="<?php echo $produto['preco_base']; ?>">
                                <option value="">Selecione...</option>
                                <?php foreach ($variacoes_produto as $var): ?>
                                    <?php
                                    // Calcula o preço final da variação (preço base + adicional da variação).
                                    $preco_final_variacao_num = (float)$produto['preco_base'] + (float)$var['preco_adicional'];
                                    // Verifica se a variação está disponível em estoque.
                                    $disponivel = isset($var['estoque_variacao']) && $var['estoque_variacao'] > 0;
                                    ?>
                                    <option value="<?php echo $var['variacao_id']; ?>" data-preco-final="<?php echo $preco_final_variacao_num; ?>" data-estoque="<?php echo (int)($var['estoque_variacao'] ?? 0); ?>" <?php echo !$disponivel ? 'disabled' : ''; ?>>
                                        <?php echo htmlspecialchars($var['nome_variacao']); ?>
                                        (<?php echo $disponivel ? ($var['estoque_variacao'] . ' em estoque') : 'Esgotado'; ?>)
                                        - R$&nbsp;<?php echo number_format($preco_final_variacao_num, 2, ',', '.'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <!-- Campo para definir a quantidade do produto. -->
                    <div class="form-group">
                        <label for="quantidade">Quantidade:</label>
                        <input type="number" name="quantidade" id="quantidade" class="form-control" style="max-width: 100px;" value="1" min="1" 
                               <?php // Define o atributo 'max' e 'disabled' dinamicamente com base no estoque.
                                     // Se não houver variações e o estoque principal for 0, desabilita e define max="0".
                                     // Se houver variações, o JavaScript controlará o 'max' e 'disabled'.
                                     // Se não houver variações mas houver estoque principal, define 'max' para esse estoque.
                                     // O '99' é um fallback genérico se houver variações e nenhuma estiver selecionada inicialmente.
                                echo ($estoque_disponivel_total <= 0 && empty($variacoes_produto)) ? 'max="0" disabled' : ('max="' . ($estoque_disponivel_total > 0 && empty($variacoes_produto) ? $estoque_disponivel_total : 99) . '"'); ?>>
                    </div>

                    <?php // Botão "Adicionar ao Carrinho" ou "Produto Esgotado".
                          // Exibe o botão de adicionar se houver estoque total ou se houver variações (o JS tratará a disponibilidade por variação).
                          // Caso contrário (sem variações e sem estoque principal), exibe "Produto Esgotado". ?>
                    <?php if ($estoque_disponivel_total > 0 || !empty($variacoes_produto)): // Permite adicionar se houver variações (o select validará o estoque da variação) ?>
                        <button type="submit" class="btn btn-success btn-lg">Adicionar ao Carrinho</button>
                    <?php else: ?>
                        <button type="button" class="btn btn-secondary btn-lg" disabled>Produto Esgotado</button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    <?php // Bloco executado se o produto não pôde ser carregado (ex: erro no try-catch ou produto não encontrado). ?>
    <?php else: ?>
        <div class="alert alert-warning">O produto solicitado não está disponível ou não foi encontrado.</div>
    <?php endif; ?>
    <!-- Link para voltar à página inicial da loja. -->
    <p class="mt-4"><a href="<?php echo BASE_URL; ?>public/index.php" class="btn btn-light">&laquo; Voltar para a loja</a></p>
</div>

<script>
// Script JavaScript para interatividade na página de detalhes do produto.
document.addEventListener('DOMContentLoaded', function() {
    const variacaoSelect = document.getElementById('variacao_id');
    const precoExibidoSpan = document.getElementById('preco-exibido');
    const quantidadeInput = document.getElementById('quantidade');
    const precoBase = parseFloat(variacaoSelect ? variacaoSelect.dataset.precoBase : '<?php echo $produto["preco_base"]; ?>');

    if (variacaoSelect) {
        // Adiciona um listener para o evento de mudança no select de variações.
        variacaoSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            // Verifica se uma opção válida foi selecionada.
            if (selectedOption && selectedOption.value !== "") {
                const precoFinal = parseFloat(selectedOption.dataset.precoFinal);
                const estoqueVar = parseInt(selectedOption.dataset.estoque);

                // Atualiza o preço exibido na página com o preço da variação selecionada.
                precoExibidoSpan.textContent = 'R$ ' + precoFinal.toFixed(2).replace('.', ',');
                
                // Atualiza o campo de quantidade com base no estoque da variação.
                if (estoqueVar > 0) {
                    quantidadeInput.max = estoqueVar;
                    quantidadeInput.disabled = false;
                    // Se a quantidade atual for maior que o estoque da variação, ajusta para o máximo.
                    if (parseInt(quantidadeInput.value) > estoqueVar) {
                        quantidadeInput.value = estoqueVar;
                    }
                } else {
                    // Se a variação estiver esgotada, desabilita o campo de quantidade.
                    quantidadeInput.max = 0;
                    quantidadeInput.value = 0;
                    quantidadeInput.disabled = true;
                }
            } else {
                // Se nenhuma variação específica for selecionada (ex: "Selecione..."),
                // reverte o preço para o preço base do produto e ajusta o campo de quantidade
                // com base no estoque total (se não houver variações) ou um valor padrão.
                precoExibidoSpan.textContent = 'R$ ' + precoBase.toFixed(2).replace('.', ',');
                quantidadeInput.max = <?php echo ($estoque_disponivel_total > 0 && empty($variacoes_produto)) ? $estoque_disponivel_total : 99; ?>; // Reset max if no specific variation stock
                quantidadeInput.disabled = <?php echo ($estoque_disponivel_total <= 0 && empty($variacoes_produto)) ? 'true' : 'false'; ?>;
            }
        });
    }
});
</script>
<?php
// Inclusão do rodapé da página.
include __DIR__ . '/../includes/footer.php';
?>
