 
<?php
// Configurações para exibição de erros (ideal para ambiente de desenvolvimento).
// Em produção, os erros devem ser logados e não exibidos diretamente ao usuário.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inclusão de arquivos essenciais.
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

// Inicialização da variável que armazenará a lista de produtos para exibição na loja.
$produtos_loja = [];
try {
    // Prepara e executa uma query SQL para buscar os produtos.
    // A query inclui uma subconsulta para calcular o estoque total de cada produto,
    // somando as quantidades da tabela 'estoque' relacionadas ao produto.
    // Os resultados são ordenados pelo nome do produto em ordem ascendente.
    $stmt_loja = $pdo->query("
        SELECT p.id, p.nome, p.descricao, p.preco_base,
               (SELECT SUM(e.quantidade) FROM estoque e WHERE e.produto_id = p.id) as estoque_total
        FROM produtos p
        ORDER BY p.nome ASC
    ");
    // Busca todos os resultados da query e armazena no array $produtos_loja.
    // PDO::FETCH_ASSOC retorna cada linha como um array associativo.
    $produtos_loja = $stmt_loja->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Em caso de erro na consulta ao banco, registra o erro no log do servidor.
    // Uma mensagem de erro específica para a loja poderia ser definida na sessão aqui, se necessário.
    error_log("Erro ao buscar produtos para a loja: " . $e->getMessage());
}

include __DIR__ . '/../includes/header.php'; 
?>

<h1 class="mb-4">Nossa Loja</h1>

<?php // Bloco para exibir mensagens de erro específicas da loja, se houver. ?>
<?php if (isset($_SESSION['mensagem_erro_loja'])): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['mensagem_erro_loja']); unset($_SESSION['mensagem_erro_loja']); ?></div>
<?php endif; ?>

<div class="row">
    <?php // Verifica se não há produtos para exibir. ?>
    <?php if (empty($produtos_loja)): ?>
        <div class="col-12">
            <p>Nenhum produto disponível no momento.</p>
        </div>
    <?php else: ?>
        <?php // Itera sobre o array de produtos para exibir cada um como um card. ?>
        <?php foreach ($produtos_loja as $produto_item): ?>
            <div class="col-md-4 mb-4">
                <div class="card h-100"> <?php // 'h-100' tenta fazer com que todos os cards na mesma linha tenham a mesma altura. ?>
                    <!-- Imagem do produto (placeholder). Em um sistema real, viria do banco ou de um diretório de uploads. -->
                    <!-- <img src="caminho/para/imagem_produto.jpg" class="card-img-top" alt="<?php echo htmlspecialchars($produto_item['nome']); ?>"> -->
                    <!-- Corpo do card, utilizando flexbox para alinhar o botão "Comprar" ao final. -->
                    <div class="card-body d-flex flex-column"> 
                        <!-- Título do produto, com link para a página de detalhes. -->
                        <h5 class="card-title"><a href="<?php echo BASE_URL; ?>public/produto_detalhe.php?id=<?php echo $produto_item['id']; ?>"><?php echo htmlspecialchars($produto_item['nome']); ?></a></h5>
                        <!-- Descrição do produto, limitada a 100 caracteres com "..." se for maior. nl2br para converter quebras de linha em <br>. -->
                        <p class="card-text small text-muted flex-grow-1"><?php echo nl2br(htmlspecialchars(substr($produto_item['descricao'] ?? '', 0, 100))) . (strlen($produto_item['descricao'] ?? '') > 100 ? '...' : ''); ?></p>
                        <!-- Preço base do produto, formatado como moeda. -->
                        <p class="card-text font-weight-bold">R$&nbsp;<?php echo number_format($produto_item['preco_base'], 2, ',', '.'); ?></p>
                        
                        <!-- Div para agrupar os botões de ação, alinhada ao final do card devido ao 'mt-auto'. -->
                        <div class="mt-auto">
                            <!-- Botão para ver mais detalhes do produto. -->
                            <a href="<?php echo BASE_URL; ?>public/produto_detalhe.php?id=<?php echo $produto_item['id']; ?>" class="btn btn-outline-primary btn-sm">Ver Detalhes</a>
                            <?php
                            // Verifica se há estoque total para o produto (considerando todas as variações, se houver).
                            // Esta é uma verificação simplificada; a página de detalhes lidará com o estoque de variações específicas.
                            $tem_estoque_geral = isset($produto_item['estoque_total']) && $produto_item['estoque_total'] > 0;
                            ?>
                            <?php // Se houver estoque, exibe o botão "Comprar". ?>
                            <?php if ($tem_estoque_geral): ?>
                                <form action="<?php echo BASE_URL; ?>public/adicionar_carrinho.php" method="POST" style="display: inline-block;" class="ml-2">
                                    <input type="hidden" name="produto_id" value="<?php echo $produto_item['id']; ?>">
                                    <input type="hidden" name="quantidade" value="1">
                                    <button type="submit" class="btn btn-success btn-sm">Comprar</button>
                                </form>
                            <?php else: ?>
                                <!-- Caso contrário, exibe um botão "Esgotado" desabilitado. -->
                                <button type="button" class="btn btn-secondary btn-sm ml-2" disabled>Esgotado</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php
// Inclusão do rodapé da página.
include __DIR__ . '/../includes/footer.php';
?>
