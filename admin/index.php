<?php
// Inclusão de arquivos essenciais para o funcionamento da página.
require_once __DIR__ . '/../config.php'; // Carrega as configurações globais da aplicação (constantes, BASE_URL, etc.).
require_once __DIR__ . '/../ver_sessao.php'; // Script para verificar se o usuário está autenticado; redireciona para login se não estiver.
require_once __DIR__ . '/../includes/db.php'; // Script para estabelecer a conexão com o banco de dados, disponibilizando a variável $pdo.

// Inicialização da variável que armazenará a lista de produtos.
$produtos = [];
try {
    // Prepara e executa uma query SQL para buscar todos os produtos.
    // A query inclui uma subconsulta para calcular o estoque total de cada produto,
    // somando as quantidades da tabela 'estoque' relacionadas ao produto.
    // Os resultados são ordenados pelo nome do produto em ordem ascendente.
    $stmt = $pdo->query("
        SELECT 
            p.id, 
            p.nome, 
            p.preco_base, 
            p.data_criacao,
            (SELECT SUM(e.quantidade) FROM estoque e WHERE e.produto_id = p.id) as estoque_total
        FROM produtos p
        ORDER BY p.nome ASC
    ");
    // Busca todos os resultados da query e armazena no array $produtos.
    // PDO::FETCH_ASSOC retorna cada linha como um array associativo (nome_coluna => valor).
    $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Em caso de erro na consulta ao banco, armazena uma mensagem de erro na sessão.
    $_SESSION['mensagem_erro'] = "Erro ao buscar produtos: " . $e->getMessage();
    // Registra o erro detalhado no log do servidor para depuração.
    error_log("Erro ao buscar produtos (admin/index.php): " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
}

// Inclusão do cabeçalho HTML da página (contém <head>, navbar, mensagens de sessão, etc.).
include __DIR__ . '/../includes/header.php';
?>

<!-- Cabeçalho da seção de gerenciamento de produtos -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Gerenciar Produtos</h2>
    <!-- Botão para redirecionar para a página de criação/edição de um novo produto -->
    <a href="produto_gerenciar.php" class="btn btn-success">Adicionar Novo Produto</a>
</div>

<?php
// Verifica se não há produtos cadastrados e se não ocorreu nenhum erro ao buscar os produtos.
// Exibe uma mensagem informativa caso não haja produtos.
if (empty($produtos) && !isset($_SESSION['mensagem_erro'])): ?>
    <div class="alert alert-info">Nenhum produto cadastrado ainda.</div>
<?php else: // Caso existam produtos ou tenha ocorrido um erro (que será exibido pelo header.php), exibe a tabela (se houver produtos) ?>
    <?php if (!empty($produtos)): // Garante que a tabela só seja exibida se houver produtos ?>
    <table class="table table-striped table-hover table-bordered">
        <thead class="thead-dark">
            <tr>
                <th>ID</th>
                <th>Nome</th>
                <th class="text-right">Preço Base</th>
                <th class="text-center">Estoque Total</th>
                <th>Criado em</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // Itera sobre o array de produtos para exibir cada um em uma linha da tabela.
            foreach ($produtos as $produto): ?>
            <tr>
                <td><?php echo htmlspecialchars($produto['id']); // Exibe o ID do produto, protegido contra XSS ?></td>
                <td><?php echo htmlspecialchars($produto['nome']); // Exibe o nome do produto, protegido contra XSS ?></td>
                <td class="text-right">R$&nbsp;<?php echo number_format($produto['preco_base'], 2, ',', '.'); // Formata o preço base para o padrão monetário brasileiro ?></td>
                <td class="text-center"><?php echo htmlspecialchars($produto['estoque_total'] ?? 0); // Exibe o estoque total; usa 0 se for nulo ?></td>
                <td><?php echo date("d/m/Y H:i", strtotime($produto['data_criacao'])); // Formata a data de criação para o padrão brasileiro ?></td>
                <td>
                    <!-- Botão para editar o produto, passando o ID via GET para a página de gerenciamento. -->
                    <a href="produto_gerenciar.php?id=<?php echo $produto['id']; ?>" class="btn btn-sm btn-primary">Editar</a>
                    <!-- Botão de Excluir (comentado): Implementação futura para exclusão de produtos.
                         Inclui uma confirmação JavaScript para evitar exclusões acidentais. -->
                    <!-- <a href="produto_excluir.php?id=<?php echo $produto['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Tem certeza que deseja excluir este produto?');">Excluir</a> -->
                </td>
            </tr>
            <?php endforeach; // Fim do loop de produtos ?>
        </tbody>
    </table>
    <?php endif; // Fim da verificação se há produtos para exibir a tabela ?>
<?php endif; // Fim da condição de exibição da tabela ou mensagem de "nenhum produto" ?>

<hr class="my-4"> <!-- Linha horizontal para separação visual -->
<p>
    <!-- Link para navegar para a visualização pública da loja. -->
    <a href="<?php echo BASE_URL; ?>public/index.php" class="btn btn-info">Ir para a Loja Pública</a>
</p>

<?php
// Inclusão do rodapé HTML da página (contém scripts JS, fechamento de tags, etc.).
include __DIR__ . '/../includes/footer.php';
?>
