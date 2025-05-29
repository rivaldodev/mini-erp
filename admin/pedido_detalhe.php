<?php
// Inclusão de arquivos essenciais para o funcionamento da página.
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../ver_sessao.php';
require_once __DIR__ . '/../includes/db.php';

// Obtém o ID do pedido da URL (via GET), ou define como nulo se não fornecido.
$pedido_id = $_GET['id'] ?? null;
$pedido = null;
$itens_pedido = [];

// Validação inicial do ID do pedido.
// Se o ID não for fornecido ou não for numérico, redireciona para a listagem de pedidos com uma mensagem de erro.
if (!$pedido_id || !is_numeric($pedido_id)) {
    $_SESSION['mensagem_erro'] = "ID do pedido inválido.";
    header("Location: pedidos_listar.php");
    exit;
}

// Bloco try-catch para tratamento de exceções durante a interação com o banco de dados.
try {
    // Prepara e executa a query para buscar os dados principais do pedido.
    $stmt_pedido = $pdo->prepare("SELECT * FROM pedidos WHERE id = :id");
    $stmt_pedido->bindParam(':id', $pedido_id, PDO::PARAM_INT);
    $stmt_pedido->execute();
    $pedido = $stmt_pedido->fetch(PDO::FETCH_ASSOC);
    // Se o pedido não for encontrado, define uma mensagem de erro e redireciona.
    if (!$pedido) {
        $_SESSION['mensagem_erro'] = "Pedido não encontrado.";
        header("Location: pedidos_listar.php");
        exit;
    }

    $stmt_itens = $pdo->prepare("
        -- Query para buscar os itens associados ao pedido.
        SELECT pi.*, p.nome as nome_produto_original, pv.nome_variacao 
        FROM pedido_itens pi
        JOIN produtos p ON pi.produto_id = p.id
        LEFT JOIN produto_variacoes pv ON pi.variacao_id = pv.id
        WHERE pi.pedido_id = :pedido_id
        ORDER BY pi.id ASC
    ");
    $stmt_itens->bindParam(':pedido_id', $pedido_id, PDO::PARAM_INT);
    $stmt_itens->execute();
    $itens_pedido = $stmt_itens->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Em caso de erro no banco, define uma mensagem e registra o erro no log.
    $_SESSION['mensagem_erro'] = "Erro ao carregar detalhes do pedido: " . $e->getMessage();
    error_log("Erro ao carregar pedido_detalhe.php (ID: $pedido_id): " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
}

// Inclusão do cabeçalho da página.
include __DIR__ . '/../includes/header.php';
?>

<div class="container mt-4">
    <?php // Verifica se os dados do pedido foram carregados com sucesso. ?>
    <?php if ($pedido): ?>
        <!-- Cabeçalho da página de detalhes do pedido, exibindo o ID e o status. -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Detalhes do Pedido #<?php echo htmlspecialchars($pedido['id']); ?></h2>
            <h4><span class="badge badge-info"><?php echo htmlspecialchars(ucfirst($pedido['status_pedido'])); ?></span></h4>
        </div>

        <!-- Card com informações do cliente e endereço de entrega. -->
        <div class="card mb-4">
            <div class="card-header">
                Informações do Cliente e Entrega
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Coluna com dados do cliente. -->
                    <div class="col-md-6">
                        <h5>Cliente</h5>
                        <p class="mb-1"><strong>Nome:</strong> <?php echo htmlspecialchars($pedido['cliente_nome']); ?></p>
                        <p class="mb-0"><strong>E-mail:</strong> <?php echo htmlspecialchars($pedido['cliente_email']); ?></p>
                    </div>
                    <!-- Coluna com dados do endereço de entrega. -->
                    <div class="col-md-6">
                        <h5>Endereço de Entrega</h5>
                        <address class="mb-0">
                            <?php echo htmlspecialchars($pedido['logradouro_entrega']); ?>, <?php echo htmlspecialchars($pedido['numero_entrega']); ?>
                            <?php if (!empty($pedido['complemento_entrega'])): ?>
                                - <?php echo htmlspecialchars($pedido['complemento_entrega']); ?>
                            <?php endif; ?><br>
                            Bairro: <?php echo htmlspecialchars($pedido['bairro_entrega']); ?><br>
                            <?php echo htmlspecialchars($pedido['cidade_entrega']); ?> - <?php echo htmlspecialchars($pedido['uf_entrega']); ?><br>
                            CEP: <?php echo htmlspecialchars($pedido['cep_entrega']); ?>
                        </address>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card com a lista de itens do pedido. -->
        <div class="card mb-4">
            <div class="card-header">
                Itens do Pedido
            </div>
            <div class="card-body p-0"> 
                <?php // Verifica se existem itens no pedido. ?>
                <?php if (!empty($itens_pedido)): ?>
                    <table class="table table-striped table-hover mb-0"> 
                        <thead class="thead-light">
                            <tr>
                                <th>Produto</th>
                                <th class="text-center">Quantidade</th>
                                <th class="text-right">Preço Unit.</th>
                                <th class="text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php // Itera sobre cada item do pedido para exibi-lo na tabela. ?>
                            <?php foreach ($itens_pedido as $item): ?>
                            <tr>
                                <td>
                                    <?php echo htmlspecialchars($item['nome_produto_item']); ?>
                                </td>
                                <td class="text-center"><?php echo htmlspecialchars($item['quantidade']); ?></td>
                                <td class="text-right">R$&nbsp;<?php echo number_format($item['preco_unitario_item'], 2, ',', '.'); ?></td>
                                <td class="text-right">R$&nbsp;<?php echo number_format($item['subtotal_item'], 2, ',', '.'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-center p-3">Nenhum item encontrado para este pedido.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Seção com o resumo financeiro do pedido (subtotal, desconto, frete, total). -->
        <div class="row">
            <div class="col-md-6 offset-md-6 text-right">
                <p><strong>Subtotal dos Itens:</strong> R$ <?php echo number_format($pedido['subtotal_pedido'], 2, ',', '.'); ?></p>
                <?php // Exibe informações do cupom de desconto, se aplicado. ?>
                <?php if (isset($pedido['cupom_codigo_usado']) && !empty($pedido['cupom_codigo_usado'])): ?>
                <p class="text-success">
                    <strong>Desconto (Cupom <?php echo htmlspecialchars($pedido['cupom_codigo_usado']); ?>):</strong> 
                    - R$&nbsp;<?php echo number_format($pedido['valor_desconto_cupom'], 2, ',', '.'); ?>
                </p>
                <?php endif; ?>
                <p><strong>Frete:</strong> R$&nbsp;<?php echo number_format($pedido['valor_frete'], 2, ',', '.'); ?></p>
                <h4 class="font-weight-bold"><strong>Total do Pedido:</strong> R$&nbsp;<?php echo number_format($pedido['valor_total_pedido'], 2, ',', '.'); ?></h4>
            </div>
        </div>

        <!-- Espaço reservado para futuras funcionalidades, como alteração de status do pedido. -->
        <div class="mt-4">
            <!-- <h4>Status do Pedido: <span class="badge badge-primary"><?php echo htmlspecialchars(ucfirst($pedido['status_pedido'])); ?></span></h4> -->
            <!-- Futuramente, adicionar formulário para alterar status do pedido -->
        </div>

    <?php // Bloco executado se o pedido não pôde ser carregado. ?>
    <?php else: ?>
        <?php if (isset($_SESSION['mensagem_erro'])): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['mensagem_erro']); unset($_SESSION['mensagem_erro']); ?></div>
        <?php else: ?>
            <div class="alert alert-warning">Não foi possível carregar os detalhes do pedido.</div>
        <?php endif; ?>
    <?php endif; ?>

    <p class="mt-4">
        <!-- Link para voltar à página de listagem de pedidos. -->
        <a href="pedidos_listar.php" class="btn btn-secondary">&laquo; Voltar para Lista de Pedidos</a>
    </p>
</div>

<?php
// Inclusão do rodapé da página.
include __DIR__ . '/../includes/footer.php';
?>