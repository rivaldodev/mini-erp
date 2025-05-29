<?php
// Inclusão de arquivos essenciais para o funcionamento da página.
require_once __DIR__ . '/../config.php'; // Carrega as configurações globais da aplicação (constantes, BASE_URL, etc.).
require_once __DIR__ . '/../ver_sessao.php'; // Script para verificar se o usuário está autenticado; redireciona para login se não estiver.
require_once __DIR__ . '/../includes/db.php'; // Script para estabelecer a conexão com o banco de dados, disponibilizando a variável $pdo.

// Inicialização da variável que armazenará a lista de cupons.
$cupons = [];
try {
    // Prepara e executa uma query SQL para buscar todos os cupons, ordenados pelo código.
    $stmt = $pdo->query("SELECT * FROM cupons ORDER BY codigo ASC");
    // Busca todos os resultados da query e armazena no array $cupons.
    // PDO::FETCH_ASSOC retorna cada linha como um array associativo (nome_coluna => valor).
    $cupons = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Em caso de erro na consulta ao banco, armazena uma mensagem de erro na sessão.
    $_SESSION['mensagem_erro'] = "Erro ao buscar cupons: " . $e->getMessage();
    // Registra o erro detalhado no log do servidor para depuração.
    error_log("Erro ao buscar cupons: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
}

// Inclusão do cabeçalho HTML da página (contém <head>, navbar, etc.).
include __DIR__ . '/../includes/header.php';
?>

<!-- Cabeçalho da seção de gerenciamento de cupons -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Gerenciar Cupons de Desconto</h2>
    <!-- Botão para redirecionar para a página de criação/edição de um novo cupom -->
    <a href="cupom_gerenciar.php" class="btn btn-success">Adicionar Novo Cupom</a>
</div>

<?php
// Verifica se não há cupons cadastrados e se não ocorreu nenhum erro ao buscar os cupons.
// Exibe uma mensagem informativa caso não haja cupons.
if (empty($cupons) && !isset($_SESSION['mensagem_erro'])): ?>
    <div class="alert alert-info">Nenhum cupom cadastrado ainda.</div>
<?php else: // Caso existam cupons, exibe a tabela ?>
    <table class="table table-striped table-hover table-bordered">
        <thead class="thead-dark">
            <tr>
                <th>ID</th>
                <th>Código</th>
                <th class="text-center">Tipo</th>
                <th class="text-right">Valor</th>
                <th class="text-right">Valor Mín. Pedido</th>
                <th class="text-center">Validade</th>
                <th class="text-center">Usos (Máx/Atuais)</th>
                <th class="text-center">Ativo</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // Itera sobre o array de cupons para exibir cada um em uma linha da tabela.
            foreach ($cupons as $cupom): ?>
            <tr>
                <td><?php echo htmlspecialchars($cupom['id']); ?></td>
                <td><?php echo htmlspecialchars($cupom['codigo']); ?></td>
                <td class="text-center"><?php echo htmlspecialchars(ucfirst($cupom['tipo_desconto'])); // ucfirst para capitalizar a primeira letra ?></td>
                <td class="text-right">
                    <?php
                    // Formata o valor do desconto de acordo com o tipo (percentual ou fixo).
                    if ($cupom['tipo_desconto'] == 'percentual') {
                        echo number_format($cupom['valor_desconto'], 2, ',', '.') . '%';
                    } else {
                        echo 'R$&nbsp;' . number_format($cupom['valor_desconto'], 2, ',', '.');
                    }
                    ?>
                </td>
                <td class="text-right">
                    <?php echo 'R$&nbsp;' . number_format($cupom['valor_minimo_pedido'] ?? 0, 2, ',', '.'); // Exibe R$0,00 se não houver valor mínimo ?>
                </td>
                <td class="text-center">
                    <?php echo $cupom['data_validade'] ? date("d/m/Y", strtotime($cupom['data_validade'])) : 'Sem validade'; // Formata a data ou exibe "Sem validade" ?>
                </td>
                <td class="text-center">
                    <?php echo $cupom['usos_maximos'] ?? 'Ilimitado'; // Exibe "Ilimitado" se não houver limite de usos ?> / <?php echo htmlspecialchars($cupom['usos_atuais']); ?>
                </td>
                <td class="text-center">
                    <?php // Exibe um badge (etiqueta visual) indicando se o cupom está ativo ou não. ?>
                    <?php if ($cupom['ativo']): ?>
                        <span class="badge badge-success">Sim</span>
                    <?php else: ?>
                        <span class="badge badge-danger">Não</span>
                    <?php endif; ?>
                </td>
                <td>
                    <!-- Botão para editar o cupom, passando o ID via GET para a página de gerenciamento. -->
                    <a href="cupom_gerenciar.php?id=<?php echo $cupom['id']; ?>" class="btn btn-sm btn-primary">Editar</a>
                    <!-- Futuramente, poderia ser adicionado um botão de excluir aqui. Ex:
                    <a href="cupom_excluir.php?id=<?php echo $cupom['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Tem certeza?');">Excluir</a>
                    -->
                </td>
            </tr>
            <?php endforeach; // Fim do loop de cupons ?>
        </tbody>
    </table>
<?php endif; // Fim da condição de exibição da tabela ou mensagem de "nenhum cupom" ?>

<hr class="my-4">
<p>
    <!-- Link para voltar ao painel administrativo principal. -->
    <a href="<?php echo BASE_URL; ?>admin/index.php" class="btn btn-secondary">Voltar ao Painel</a>
</p>

<?php
// Inclusão do rodapé HTML da página (contém scripts JS, fechamento de tags, etc.).
include __DIR__ . '/../includes/footer.php';
?>
