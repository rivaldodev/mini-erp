<?php
// Configurações para exibição de erros (ideal para ambiente de desenvolvimento).
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inclusão de arquivos essenciais.
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

// Inicialização de variáveis.
// Obtém o carrinho da sessão; se não existir, inicializa como um array vazio.
$carrinho = $_SESSION['carrinho'] ?? [];
$subtotal = 0;
$frete = 0;
// Obtém o CEP informado anteriormente (se houver) da sessão.
$cep_informado = $_SESSION['endereco_cep'] ?? '';
$desconto_cupom = 0;

// Calcula o subtotal dos itens no carrinho.
// Itera sobre cada item no carrinho para somar (preço * quantidade).
foreach ($carrinho as $item_id => $item) {
    if (isset($item['preco']) && isset($item['quantidade'])) {
        $subtotal += $item['preco'] * $item['quantidade'];
    }
}

// Verifica se um cupom de desconto foi aplicado e recalcula o valor do desconto.
// Isso é importante caso o subtotal do carrinho tenha mudado (ex: item removido/adicionado).
if (isset($_SESSION['cupom_aplicado'])) {
    $cupom_info = $_SESSION['cupom_aplicado'];
    // Se o cupom for do tipo percentual, o desconto é recalculado com base no subtotal atual.
    if ($cupom_info['tipo'] == 'percentual') {
        $desconto_calculado_novo = ($subtotal * (float)$cupom_info['valor_original']) / 100;
        // O desconto não pode ser maior que o próprio subtotal.
        $desconto_cupom = min($desconto_calculado_novo, $subtotal);
        // Atualiza o valor do desconto calculado na sessão, arredondado para 2 casas decimais.
        $_SESSION['cupom_aplicado']['desconto_calculado'] = round($desconto_cupom, 2);
    } else {
        // Se for um cupom de valor fixo, o valor do desconto já está calculado.
        $desconto_cupom = (float)$cupom_info['desconto_calculado'];
    }
}

// Lógica para cálculo do frete baseado em faixas de subtotal.
if ($subtotal > 0) { 
    if ($subtotal >= 52.00 && $subtotal <= 166.59) {
        $frete = 15.00;
    } elseif ($subtotal > 200.00) {
        $frete = 0.00; // Frete grátis acima de R$200.00
    } else {
        $frete = 20.00; // Valor padrão para outros casos (ex: subtotal < 52.00 ou entre 166.60 e 200.00)
    }
} else {
    $frete = 0.00;
}

// Calcula o total final do pedido.
$total_pedido = $subtotal + $frete - $desconto_cupom;
// Garante que o total do pedido não seja negativo.
$total_pedido = max(0, $total_pedido); 

// Inclusão do cabeçalho da página.
include __DIR__ . '/../includes/header.php';
?>

<!-- Container principal da página do carrinho -->
<div class="container mt-4">
    <h1>Seu Carrinho de Compras</h1>

    <?php if (isset($_SESSION['mensagem_aviso'])): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($_SESSION['mensagem_aviso']); ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
            <?php // Remove a mensagem da sessão após exibi-la para não aparecer novamente. ?>
        </div>
        <?php unset($_SESSION['mensagem_aviso']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['mensagem_sucesso'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($_SESSION['mensagem_sucesso']); ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <?php unset($_SESSION['mensagem_sucesso']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['mensagem_erro'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($_SESSION['mensagem_erro']); ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <?php unset($_SESSION['mensagem_erro']); ?>
    <?php endif; ?>


    <?php // Verifica se o carrinho está vazio. ?>
    <?php if (empty($carrinho)): ?>
        <div class="alert alert-info text-center">Seu carrinho está vazio. <a href="<?php echo BASE_URL; ?>public/index.php" class="alert-link">Comece a comprar!</a></div>
    <?php else: ?>
        <!-- Tabela para exibir os itens do carrinho -->
        <table class="table">
            <thead class="thead-light">
                <tr>
                    <th>Produto</th>
                    <th>Preço Unit.</th>
                    <th>Quantidade</th>
                    <th class="text-right">Subtotal Item</th>
                    <th>Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php // Itera sobre cada item do carrinho para exibi-lo. ?>
                <?php foreach ($carrinho as $item_id_carrinho => $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['nome'] ?? 'Nome Indisponível'); ?></td>
                        <td>R$&nbsp;<?php echo number_format($item['preco'] ?? 0, 2, ',', '.'); ?></td>
                        <td>
                            <!-- Formulário para atualizar a quantidade do item -->
                            <form action="<?php echo BASE_URL; ?>public/atualizar_carrinho.php" method="POST" style="display: inline-flex; align-items: center;">
                                <input type="hidden" name="item_id_carrinho" value="<?php echo htmlspecialchars($item_id_carrinho); ?>">
                                <input type="number" name="quantidade" value="<?php echo htmlspecialchars($item['quantidade'] ?? 1); ?>" min="1" class="form-control form-control-sm" style="width: 70px;" aria-label="Quantidade">
                                <!-- Botão para submeter a atualização da quantidade -->
                                <button type="submit" name="acao" value="atualizar" class="btn btn-sm btn-outline-primary ml-1 d-inline-flex align-items-center justify-content-center" title="Atualizar quantidade" style="width: 30px; height: 30px; padding: 0;"><i class="fas fa-sync-alt"></i></button>
                            </form>
                        </td>
                        <td class="text-right">R$&nbsp;<?php echo number_format(($item['preco'] ?? 0) * ($item['quantidade'] ?? 1), 2, ',', '.'); ?></td>
                        <td>
                            <!-- Formulário para remover o item do carrinho -->
                            <form action="<?php echo BASE_URL; ?>public/atualizar_carrinho.php" method="POST" style="display: inline;">
                                <input type="hidden" name="item_id_carrinho" value="<?php echo htmlspecialchars($item_id_carrinho); ?>">
                                <button type="submit" name="acao" value="remover" class="btn btn-sm btn-danger">Remover</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Seção para Cupom de Desconto e Cálculo de Frete -->
        <div class="row mt-4">
            <div class="col-md-6 mb-3 mb-md-0">
                <!-- Card para Cupom de Desconto -->
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Cupom de Desconto</h5>
                        <?php // Se um cupom já estiver aplicado, exibe suas informações e um botão para removê-lo. ?>
                        <?php if (isset($_SESSION['cupom_aplicado'])): ?>
                            <p>
                                Cupom aplicado: <strong><?php echo htmlspecialchars($_SESSION['cupom_aplicado']['codigo']); ?></strong>
                                (Desconto: R$&nbsp;<?php echo number_format($_SESSION['cupom_aplicado']['desconto_calculado'], 2, ',', '.'); ?>)
                                <a href="<?php echo BASE_URL; ?>public/aplicar_cupom.php?acao=remover_cupom" class="btn btn-sm btn-outline-danger ml-2">Remover</a>
                            </p>
                        <?php // Caso contrário, exibe o formulário para aplicar um novo cupom. ?>
                        <?php else: ?>
                            <form action="<?php echo BASE_URL; ?>public/aplicar_cupom.php" method="POST" class="form-inline">
                                <div class="form-group mr-2 flex-grow-1">
                                    <input type="text" name="cupom_codigo" class="form-control form-control-sm w-100" placeholder="Código do Cupom" aria-label="Código do Cupom">
                                </div>
                                <button type="submit" class="btn btn-sm btn-outline-primary">Aplicar</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                <!-- Card para Cálculo de Frete -->
                <div class="card mt-3">
                    <div class="card-body">
                        <h5 class="card-title">Calcular Frete</h5>
                        <form id="form-cep" method="POST">
                            <div class="input-group">
                                <input type="text" class="form-control form-control-sm" name="cep" id="cep" placeholder="Digite seu CEP" value="<?php echo htmlspecialchars($cep_informado); ?>" required pattern="\d{5}-?\d{3}" aria-label="CEP">
                                <div class="input-group-append">
                                    <button class="btn btn-sm btn-outline-secondary" type="submit" id="btn-verificar-cep">Verificar CEP</button>
                                </div>
                            </div>
                        </form>
                        <!-- Div para exibir o resultado da consulta do CEP (endereço ou mensagens de erro) -->
                        <div id="resultado-cep" class="mt-2">
                            <?php if (isset($_SESSION['endereco_formatado'])): ?>
                                <p class="mb-0"><small><strong>Endereço:</strong> <?php echo htmlspecialchars($_SESSION['endereco_formatado']); ?></small></p>
                            <?php endif; ?>
                            <?php if (isset($_SESSION['erro_cep'])): ?>
                                <div class="alert alert-danger p-2 mt-2"><?php echo htmlspecialchars($_SESSION['erro_cep']); unset($_SESSION['erro_cep']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Seção de Resumo do Pedido -->
            <div class="col-md-6 text-md-right">
                <h4>Resumo do Pedido</h4>
                <p>Subtotal: R$&nbsp;<?php echo number_format($subtotal, 2, ',', '.'); ?></p>
                <?php if ($desconto_cupom > 0): ?>
                    <p class="text-success">Desconto Cupom: - R$&nbsp;<?php echo number_format($desconto_cupom, 2, ',', '.'); ?></p>
                <?php endif; ?>
                <p>Frete: R$&nbsp;<?php echo number_format($frete, 2, ',', '.'); ?></p>
                <h5 class="font-weight-bold">Total: R$&nbsp;<?php echo number_format($total_pedido, 2, ',', '.'); ?></h5>
                <hr>
                <a href="<?php echo BASE_URL; ?>public/index.php" class="btn btn-light mb-2 mb-md-0">Continuar Comprando</a>
                <?php // Botão para finalizar a compra. Habilitado apenas se o carrinho não estiver vazio e o CEP tiver sido informado. ?>
                <?php if (!empty($carrinho) && !empty($cep_informado)): ?>
                    <a href="<?php echo BASE_URL; ?>public/checkout.php" class="btn btn-success">Finalizar Compra</a>
                <?php // Se o CEP não foi informado, o botão é desabilitado e exibe uma dica. ?>
                <?php elseif (empty($cep_informado) && !empty($carrinho)): ?>
                     <button class="btn btn-success" disabled title="Informe o CEP para calcular o frete e prosseguir">Finalizar Compra</button>
                <?php else: ?>
                     <button class="btn btn-success" disabled title="Adicione itens ao carrinho e informe o CEP">Finalizar Compra</button>
                <?php endif; ?>
            </div>
        </div>

    <?php endif; ?>
</div>

<!-- Script JavaScript para a funcionalidade de verificação de CEP -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const formCep = document.getElementById('form-cep');
    const resultadoCepDiv = document.getElementById('resultado-cep');
    const cepInput = document.getElementById('cep');

    if (formCep) {
        // Adiciona um listener para o evento de submit do formulário de CEP.
        formCep.addEventListener('submit', function(event) {
            event.preventDefault(); // Previne o comportamento padrão de submissão do formulário.
            const cep = cepInput.value;
            const btnVerificar = document.getElementById('btn-verificar-cep');

            // Limpa resultados anteriores e atualiza o estado do botão para "verificando".
            resultadoCepDiv.innerHTML = ''; 
            btnVerificar.disabled = true;
            btnVerificar.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Verificando...';

            // Faz uma requisição assíncrona (fetch) para o script PHP que consulta a API ViaCEP.
            // A URL é construída dinamicamente usando a constante BASE_URL do PHP.
            fetch('<?php echo BASE_URL; ?>public/verificar_cep.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'cep=' + encodeURIComponent(cep)
            })
            .then(response => {
                // Verifica se a resposta da requisição foi bem-sucedida.
                if (!response.ok) {
                    throw new Error('Erro na rede ou servidor: ' + response.statusText);
                }
                return response.json();
            })
            .then(data => {
                btnVerificar.disabled = false;
                btnVerificar.innerHTML = 'Verificar CEP'; // Restaura o texto do botão.
                // Processa a resposta JSON da API.
                if (data.erro) {
                    // Se houver um erro na resposta da API (ex: CEP não encontrado), exibe a mensagem de erro.
                    resultadoCepDiv.innerHTML = '<div class="alert alert-danger p-2">' + escapeHtml(data.erro) + '</div>';
                } else if (data.endereco_formatado) {
                    // Se o endereço for obtido com sucesso, recarrega a página.
                    // O recarregamento permite que o PHP processe os dados do endereço (armazenados na sessão pelo verificar_cep.php)
                    // e atualize o cálculo do frete e a disponibilidade do botão "Finalizar Compra".
                    window.location.reload();
                } else {
                    // Caso a resposta não contenha erro nem endereço formatado (situação inesperada).
                     resultadoCepDiv.innerHTML = '<div class="alert alert-warning p-2">Não foi possível obter o endereço. Verifique o CEP.</div>';
                }
            })
            .catch(error => {
                // Trata erros na requisição fetch (ex: falha de rede).
                btnVerificar.disabled = false;
                btnVerificar.innerHTML = 'Verificar CEP';
                resultadoCepDiv.innerHTML = '<div class="alert alert-danger p-2">Erro ao verificar CEP. Tente novamente. (' + error.message + ')</div>';
                console.error('Erro no fetch do CEP:', error);
            });
        });
    }
    
    // Função auxiliar para escapar caracteres HTML e prevenir XSS ao exibir dados da API.
    function escapeHtml(unsafe) {
        if (typeof unsafe !== 'string') return '';
        return unsafe
             .replace(/&/g, "&amp;")
             .replace(/</g, "&lt;")
             .replace(/>/g, "&gt;")
             .replace(/"/g, "&quot;")
             .replace(/'/g, "&#039;");
    }
});
</script>

<?php
// Inclusão do rodapé da página.
include __DIR__ . '/../includes/footer.php';
?>
