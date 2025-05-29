<?php
// Configurações para exibição de erros (ideal para ambiente de desenvolvimento).
// Em produção, os erros devem ser logados e não exibidos diretamente ao usuário.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inclusão de arquivos essenciais.
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

// Obtém o carrinho da sessão; se não existir, inicializa como um array vazio.
$carrinho = $_SESSION['carrinho'] ?? [];

// Se o carrinho estiver vazio, redireciona o usuário de volta para a página do carrinho.
// Não faz sentido prosseguir para o checkout sem itens.
if (empty($carrinho)) {
    header("Location: " . BASE_URL . "public/carrinho.php");
    exit;
}

// Verifica se o CEP foi informado e verificado na etapa anterior (página do carrinho).
// Se o CEP não estiver na sessão, redireciona de volta para o carrinho com uma mensagem de erro.
if (!isset($_SESSION['endereco_cep']) || empty($_SESSION['endereco_cep'])) {
    $_SESSION['mensagem_erro'] = "Por favor, verifique seu CEP no carrinho antes de prosseguir para o checkout.";
    header("Location: " . BASE_URL . "public/carrinho.php");
    exit;
}
// Inicialização das variáveis para os totais do pedido.
$subtotal = 0;
$frete = 0;
$desconto_cupom = 0;

foreach ($carrinho as $item) {
    if (isset($item['preco']) && isset($item['quantidade'])) {
        $subtotal += $item['preco'] * $item['quantidade'];
    }
}

// Lógica para cálculo do frete.
// Esta lógica é uma simplificação e, em um sistema real, seria mais complexa (ex: consulta a APIs de transportadoras).
if ($subtotal > 0) {
    if ($subtotal >= 52.00 && $subtotal <= 166.59) {
        $frete = 15.00;
    } elseif ($subtotal > 200.00) {
        $frete = 0.00;
    } else {
        $frete = 20.00;
    }
} else {
    $frete = 0.00;
}

// Verifica se um cupom de desconto foi aplicado e obtém o valor do desconto.
if (isset($_SESSION['cupom_aplicado']) && isset($_SESSION['cupom_aplicado']['desconto_calculado'])) {
    $desconto_cupom = (float)$_SESSION['cupom_aplicado']['desconto_calculado'];
}

// Calcula o total final do pedido.
$total_pedido = $subtotal + $frete - $desconto_cupom;
// Garante que o total do pedido não seja negativo (caso o desconto seja maior que subtotal + frete).
$total_pedido = max(0, $total_pedido);

// Recupera os dados do endereço da sessão, que foram obtidos pela consulta ao ViaCEP na página do carrinho.
$endereco_formatado = $_SESSION['endereco_formatado'] ?? 'Endereço não informado';
$cep_entrega = $_SESSION['endereco_cep'] ?? '';
$logradouro_entrega = $_SESSION['endereco_logradouro'] ?? '';
$bairro_entrega = $_SESSION['endereco_bairro'] ?? '';
$cidade_entrega = $_SESSION['endereco_cidade'] ?? '';
$uf_entrega = $_SESSION['endereco_uf'] ?? '';

// Inclusão do cabeçalho da página.
include __DIR__ . '/../includes/header.php';
?>

<div class="container mt-4">
    <h1>Finalizar Compra</h1>
    <hr class="mb-4">
    <!-- Layout de duas colunas: formulário de informações e resumo do pedido. -->
    <div class="row">
        <div class="col-md-7 order-md-1">
            <div class="card">
                <div class="card-header">
                    Informações do Cliente e Entrega
                </div>
                <div class="card-body">
                    <!-- Formulário para coletar dados do cliente e confirmar o endereço. -->
                    <!-- Os dados do endereço (CEP, logradouro, etc.) são pré-preenchidos e enviados via campos ocultos,
                         pois já foram validados. O cliente apenas informa nome, e-mail, número e complemento. -->
                    <form action="<?php echo BASE_URL; ?>public/finalizar_pedido.php" method="POST" id="form-checkout">
                        <!-- Campo Nome Completo do Cliente -->
                        <div class="form-group">
                            <label for="cliente_nome">Nome Completo</label>
                            <input type="text" class="form-control" id="cliente_nome" name="cliente_nome" required>
                        </div>
                        <!-- Campo E-mail do Cliente -->
                        <div class="form-group">
                            <label for="cliente_email">E-mail</label>
                            <input type="email" class="form-control" id="cliente_email" name="cliente_email" required>
                        </div>

                        <h5 class="mt-4">Endereço de Entrega</h5>
                        <!-- Exibe o endereço obtido pela consulta ao CEP para confirmação visual. -->
                        <p class="text-muted">
                            <strong>CEP:</strong> <?php echo htmlspecialchars($cep_entrega); ?><br>
                            <?php echo htmlspecialchars($endereco_formatado); ?>
                        </p>
                        <!-- Campo Número do Endereço -->
                        <div class="form-group">
                            <label for="cliente_endereco_numero">Número</label>
                            <input type="text" class="form-control" id="cliente_endereco_numero" name="cliente_endereco_numero" required>
                        </div>
                        <div class="form-group">
                            <label for="cliente_endereco_complemento">Complemento (opcional)</label>
                            <input type="text" class="form-control" id="cliente_endereco_complemento" name="cliente_endereco_complemento">
                        </div>
                        <!-- Campos ocultos para enviar os dados do endereço que já foram validados. -->
                        <input type="hidden" name="cep_entrega" value="<?php echo htmlspecialchars($cep_entrega); ?>">
                        <input type="hidden" name="logradouro_entrega" value="<?php echo htmlspecialchars($logradouro_entrega); ?>">
                        <input type="hidden" name="bairro_entrega" value="<?php echo htmlspecialchars($bairro_entrega); ?>">
                        <input type="hidden" name="cidade_entrega" value="<?php echo htmlspecialchars($cidade_entrega); ?>">
                        <input type="hidden" name="uf_entrega" value="<?php echo htmlspecialchars($uf_entrega); ?>">

                        <!-- Botão para submeter o formulário e finalizar o pedido. -->
                        <hr class="my-4">
                        <button class="btn btn-primary btn-lg btn-block" type="submit">Finalizar Pedido e Pagar</button>
                    </form>
                </div>
            </div>
        </div>
        <!-- Coluna para exibir o resumo do pedido. -->
        <div class="col-md-5 order-md-0 mb-4">
            <!-- Cabeçalho do resumo, mostrando a quantidade de itens no carrinho. -->
            <h4 class="d-flex justify-content-between align-items-center mb-3">
                <span class="text-muted">Resumo do Pedido</span>
                <span class="badge badge-secondary badge-pill"><?php echo count($carrinho); ?></span>
            </h4>
            <!-- Lista de itens do pedido. -->
            <ul class="list-group mb-3">
                <?php // Itera sobre cada item do carrinho para exibi-lo no resumo. ?>
                <?php foreach ($carrinho as $item): ?>
                <li class="list-group-item d-flex justify-content-between lh-condensed">
                    <div>
                        <h6 class="my-0"><?php echo htmlspecialchars($item['nome']); ?></h6>
                        <small class="text-muted">Quantidade: <?php echo $item['quantidade']; ?></small>
                    </div>
                    <span class="text-muted">R$&nbsp;<?php echo number_format($item['preco'] * $item['quantidade'], 2, ',', '.'); ?></span>
                </li>
                <?php endforeach; ?>
                <!-- Exibição do Subtotal -->
                <li class="list-group-item d-flex justify-content-between">
                    <span>Subtotal</span>
                    <strong>R$&nbsp;<?php echo number_format($subtotal, 2, ',', '.'); ?></strong>
                </li>
                <?php // Se um cupom foi aplicado, exibe as informações do desconto. ?>
                <?php if (isset($_SESSION['cupom_aplicado'])): ?>
                <li class="list-group-item d-flex justify-content-between bg-light">
                    <div class="text-success">
                        <h6 class="my-0">Cupom de Desconto</h6>
                        <small><?php echo htmlspecialchars($_SESSION['cupom_aplicado']['codigo']); ?></small>
                    </div>
                    <span class="text-success">- R$&nbsp;<?php echo number_format($desconto_cupom, 2, ',', '.'); ?></span>
                </li>
                <?php endif; ?>
                <!-- Exibição do Valor do Frete -->
                <li class="list-group-item d-flex justify-content-between">
                    <span>Frete</span>
                    <strong>R$&nbsp;<?php echo number_format($frete, 2, ',', '.'); ?></strong>
                </li>
                <!-- Exibição do Total Final do Pedido -->
                <li class="list-group-item d-flex justify-content-between">
                    <span>Total (R$)</span>
                    <strong>R$ <?php echo number_format($total_pedido, 2, ',', '.'); ?></strong>
                </li>
            </ul>
            <!-- Link para voltar à página do carrinho, caso o usuário queira fazer alterações. -->
            <a href="<?php echo BASE_URL; ?>public/carrinho.php" class="btn btn-sm btn-outline-secondary">&laquo; Voltar ao Carrinho</a>
        </div>
    </div>
</div>

<?php // Inclusão do rodapé da página. ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>