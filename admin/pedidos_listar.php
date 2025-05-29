<?php
// Inclusão de arquivos essenciais para o funcionamento da página.
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../ver_sessao.php';
require_once __DIR__ . '/../includes/db.php';

// Inicialização da variável que armazenará a lista de pedidos.
$pedidos = [];
// Obtém o parâmetro 'status' da URL, se existir, para filtrar pedidos.
// Atualmente, a lógica de filtro no SQL está comentada, mas a variável está pronta para uso futuro.
$filtro_status = $_GET['status'] ?? ''; 

// Bloco try-catch para tratamento de exceções durante a interação com o banco de dados.
try {
    // Define a query SQL base para buscar todos os pedidos.
    $sql = "SELECT * FROM pedidos"; 
    // Lógica de filtro por status (atualmente comentada no código original).
    // Se um status for fornecido, a query seria modificada para incluir uma cláusula WHERE.
    if (!empty($filtro_status)) {
        // $sql .= " WHERE status_pedido = :status_pedido"; // Sem uso por enquanto
    }
    
    // Adiciona a ordenação dos pedidos pela data em ordem decrescente (mais recentes primeiro).
    $sql .= " ORDER BY data_pedido DESC";
    
    // Prepara a query SQL para execução.
    $stmt = $pdo->prepare($sql);
    
    // Se o filtro de status estivesse ativo, aqui seria feito o bind do parâmetro.
    // if (!empty($filtro_status)) {
    //     $stmt->bindParam(':status_pedido', $filtro_status); // Sem uso por enquanto
    // }

    // Executa a query preparada.
    $stmt->execute();
    // Busca todos os resultados da query e armazena no array $pedidos.
    // PDO::FETCH_ASSOC retorna cada linha como um array associativo.
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Tratamento específico para o erro '42S02', que indica que a tabela 'pedidos' não existe.
    if ($e->getCode() == '42S02') {
        $_SESSION['mensagem_aviso'] = "A tabela de pedidos ainda não existe. Finalize um pedido para criá-la ou crie-a manualmente.";
    } else {
        // Para outros erros de PDO, armazena uma mensagem de erro genérica.
        $_SESSION['mensagem_erro'] = "Erro ao buscar pedidos: " . $e->getMessage();
    }
    // Registra o erro detalhado no log do servidor para depuração.
    error_log("Erro ao buscar pedidos: " . $e->getMessage() . " | SQLSTATE: " . $e->getCode() . " | Trace: " . $e->getTraceAsString());
}

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <!-- Título da seção de gerenciamento de pedidos. -->
    <h2>Gerenciar Pedidos</h2>
    <!-- Futuramente, adicionar filtros ou botões de ação -->
</div>

<?php // Exibe mensagens de aviso (warning) da sessão, se houver. ?>
<?php if (isset($_SESSION['mensagem_aviso'])): ?>
    <div class="alert alert-warning"><?php echo htmlspecialchars($_SESSION['mensagem_aviso']); unset($_SESSION['mensagem_aviso']); ?></div>
<?php endif; ?>
<?php // Verifica se não há pedidos e se não há mensagem de aviso (para não duplicar mensagens de erro/aviso). ?>
<?php if (empty($pedidos) && !isset($_SESSION['mensagem_aviso'])): ?>
    <div class="alert alert-info">Nenhum pedido encontrado.</div>
<?php // Se houver pedidos, exibe a tabela. ?>
<?php elseif (!empty($pedidos)): ?>
    <table class="table table-striped table-hover table-bordered">
        <thead class="thead-dark">
            <tr>
                <th>ID Pedido</th>
                <th>Cliente</th>
                <th>Data</th>
                <th class="text-right">Total</th>
                <th class="text-center">Status</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php // Itera sobre o array de pedidos para exibir cada um em uma linha da tabela. ?>
            <?php foreach ($pedidos as $pedido): ?>
            <tr>
                <?php // Exibe o ID do pedido. ?>
                <td><strong>#<?php echo htmlspecialchars($pedido['id']); ?></strong></td>
                <?php // Exibe o nome e e-mail do cliente. Usa 'N/A' ou string vazia como fallback. ?>
                <td><?php echo htmlspecialchars($pedido['cliente_nome'] ?? 'N/A'); ?> <br><small><?php echo htmlspecialchars($pedido['cliente_email'] ?? ''); ?></small></td>
                <?php // Formata e exibe a data do pedido. ?>
                <td><?php echo date("d/m/Y H:i", strtotime($pedido['data_pedido'])); ?></td>
                <?php // Formata e exibe o valor total do pedido. ?>
                <td class="text-right">R$&nbsp;<?php echo number_format($pedido['valor_total_pedido'] ?? 0, 2, ',', '.'); ?></td>
                <?php // Exibe o status do pedido, com a primeira letra capitalizada. ?>
                <td class="text-center"><span class="badge badge-info"><?php echo htmlspecialchars(ucfirst($pedido['status_pedido'] ?? 'Desconhecido')); ?></span></td>
                <td>
                    <!-- Link para a página de detalhes do pedido, passando o ID via GET. -->
                    <a href="pedido_detalhe.php?id=<?php echo $pedido['id']; ?>" class="btn btn-sm btn-primary">Ver Detalhes</a>
                    <!-- Futuramente, adicionar botões para mudar status, etc. -->
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<hr class="my-4">
<p>
    <!-- Link para voltar ao painel administrativo principal. -->
    <a href="<?php echo BASE_URL; ?>admin/index.php" class="btn btn-secondary">Voltar ao Painel</a>
</p>

<?php
// Inclusão do rodapé HTML da página.
include __DIR__ . '/../includes/footer.php';
?>