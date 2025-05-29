<?php
// Configurações para exibição de erros (ideal para ambiente de desenvolvimento).
// Em produção, os erros devem ser logados e não exibidos diretamente ao usuário.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inclusão de arquivos essenciais.
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

// Verifica se a requisição foi feita utilizando o método POST.
// A finalização do pedido deve ocorrer apenas através da submissão do formulário de checkout.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['mensagem_erro'] = "Acesso inválido.";
    header("Location: " . BASE_URL . "public/carrinho.php");
    exit;
}

// Obtém o carrinho da sessão; se não existir, inicializa como um array vazio.
$carrinho = $_SESSION['carrinho'] ?? [];
// Se o carrinho estiver vazio, redireciona o usuário de volta para a página do carrinho.
if (empty($carrinho)) {
    $_SESSION['mensagem_erro'] = "Seu carrinho está vazio.";
    header("Location: " . BASE_URL . "public/carrinho.php");
    exit;
}

// Coleta e sanitiza os dados do cliente enviados pelo formulário de checkout.
$cliente_nome = trim($_POST['cliente_nome'] ?? '');
$cliente_email = filter_var(trim($_POST['cliente_email'] ?? ''), FILTER_VALIDATE_EMAIL);
$cliente_endereco_numero = trim($_POST['cliente_endereco_numero'] ?? '');
$cliente_endereco_complemento = trim($_POST['cliente_endereco_complemento'] ?? '');

// Coleta os dados do endereço. Prioriza os dados da sessão (verificados via ViaCEP),
// mas mantém um fallback para os dados do POST caso a sessão não esteja disponível (menos ideal).
$cep_entrega = $_SESSION['endereco_cep'] ?? ($_POST['cep_entrega'] ?? '');
$logradouro_entrega = $_SESSION['endereco_logradouro'] ?? ($_POST['logradouro_entrega'] ?? '');
$bairro_entrega = $_SESSION['endereco_bairro'] ?? ($_POST['bairro_entrega'] ?? '');
$cidade_entrega = $_SESSION['endereco_cidade'] ?? ($_POST['cidade_entrega'] ?? '');
$uf_entrega = $_SESSION['endereco_uf'] ?? ($_POST['uf_entrega'] ?? '');

// Validação dos campos obrigatórios do cliente e endereço.
if (empty($cliente_nome) || !$cliente_email || empty($cliente_endereco_numero) || empty($cep_entrega) || empty($logradouro_entrega) || empty($bairro_entrega) || empty($cidade_entrega) || empty($uf_entrega)) {
    $_SESSION['mensagem_erro'] = "Por favor, preencha todos os campos obrigatórios do cliente e endereço.";
    header("Location: " . BASE_URL . "public/checkout.php");
    exit;
}

$subtotal_recalculado = 0;
// Recalcula o subtotal dos itens do carrinho.
// É uma boa prática recalcular no backend para garantir a integridade dos dados, em vez de confiar apenas no que foi exibido no frontend.
foreach ($carrinho as $item) {
    if (isset($item['preco']) && isset($item['quantidade'])) {
        $subtotal_recalculado += $item['preco'] * $item['quantidade'];
    }
}

$frete_recalculado = 0;
// Recalcula o valor do frete com base no subtotal recalculado.
if ($subtotal_recalculado > 0) {
    if ($subtotal_recalculado >= 52.00 && $subtotal_recalculado <= 166.59) {
        $frete_recalculado = 15.00;
    } elseif ($subtotal_recalculado > 200.00) {
        $frete_recalculado = 0.00;
    } else {
        $frete_recalculado = 20.00;
    }
}

$desconto_cupom_recalculado = 0;
$cupom_id_usado = null;
$cupom_codigo_usado = null;
// Recalcula o valor do desconto do cupom, se aplicável.
if (isset($_SESSION['cupom_aplicado']) && isset($_SESSION['cupom_aplicado']['desconto_calculado'])) {
    $cupom_info = $_SESSION['cupom_aplicado'];
    if ($cupom_info['tipo'] == 'fixo') {
        $desconto_cupom_recalculado = (float)$cupom_info['valor_original'];
    } elseif ($cupom_info['tipo'] == 'percentual') {
        $desconto_cupom_recalculado = ($subtotal_recalculado * (float)$cupom_info['valor_original']) / 100;
    }
    // Garante que o desconto não seja maior que o subtotal.
    $desconto_cupom_recalculado = min($desconto_cupom_recalculado, $subtotal_recalculado);
    $desconto_cupom_recalculado = round($desconto_cupom_recalculado, 2);

    // Armazena o ID e código do cupom para registrar no pedido.
    $cupom_id_usado = $cupom_info['id'];
    $cupom_codigo_usado = $cupom_info['codigo'];
}

// Calcula o total final do pedido, garantindo que não seja negativo.
$total_pedido_recalculado = $subtotal_recalculado + $frete_recalculado - $desconto_cupom_recalculado;
$total_pedido_recalculado = max(0, $total_pedido_recalculado);

try {
    // Inicia uma transação com o banco de dados.
    // Isso garante que todas as operações (inserir pedido, itens, atualizar estoque) sejam concluídas com sucesso,
    // ou todas são revertidas em caso de erro (atomicidade).
    $pdo->beginTransaction();

    // Prepara a query SQL para inserir os dados do pedido na tabela 'pedidos'.
    $sql_pedido = "INSERT INTO pedidos (cliente_nome, cliente_email, cep_entrega, logradouro_entrega, numero_entrega, complemento_entrega, bairro_entrega, cidade_entrega, uf_entrega, subtotal_pedido, valor_frete, cupom_id_usado, cupom_codigo_usado, valor_desconto_cupom, valor_total_pedido, status_pedido) 
                   VALUES (:cliente_nome, :cliente_email, :cep_entrega, :logradouro_entrega, :numero_entrega, :complemento_entrega, :bairro_entrega, :cidade_entrega, :uf_entrega, :subtotal_pedido, :valor_frete, :cupom_id_usado, :cupom_codigo_usado, :valor_desconto_cupom, :valor_total_pedido, :status_pedido)";
    
    $stmt_pedido = $pdo->prepare($sql_pedido);
    $stmt_pedido->execute([
        ':cliente_nome' => $cliente_nome,
        ':cliente_email' => $cliente_email,
        ':cep_entrega' => $cep_entrega,
        ':logradouro_entrega' => $logradouro_entrega,
        ':numero_entrega' => $cliente_endereco_numero,
        ':complemento_entrega' => !empty($cliente_endereco_complemento) ? $cliente_endereco_complemento : null,
        ':bairro_entrega' => $bairro_entrega,
        ':cidade_entrega' => $cidade_entrega,
        ':uf_entrega' => $uf_entrega,
        ':subtotal_pedido' => $subtotal_recalculado,
        ':valor_frete' => $frete_recalculado,
        ':cupom_id_usado' => $cupom_id_usado,
        ':cupom_codigo_usado' => $cupom_codigo_usado,
        ':valor_desconto_cupom' => $desconto_cupom_recalculado,
        ':valor_total_pedido' => $total_pedido_recalculado,
        ':status_pedido' => 'Pendente' 
    ]);
    // Obtém o ID do pedido recém-inserido.
    $pedido_id_novo = $pdo->lastInsertId();

    // Prepara a query SQL para inserir os itens do pedido na tabela 'pedido_itens'.
    $sql_item_pedido = "INSERT INTO pedido_itens (pedido_id, produto_id, variacao_id, nome_produto_item, quantidade, preco_unitario_item, subtotal_item) 
                        VALUES (:pedido_id, :produto_id, :variacao_id, :nome_produto_item, :quantidade, :preco_unitario_item, :subtotal_item)";
    $stmt_item_pedido = $pdo->prepare($sql_item_pedido);

    // Inicializa variáveis para a query de atualização de estoque.
    $sql_update_estoque = ""; 
    $stmt_update_estoque = null;

    // Itera sobre cada item do carrinho para inseri-lo na tabela 'pedido_itens' e atualizar o estoque.
    foreach ($carrinho as $item_id_carrinho => $item_c) {
        $stmt_item_pedido->execute([
            ':pedido_id' => $pedido_id_novo,
            ':produto_id' => $item_c['produto_id'],
            ':variacao_id' => $item_c['variacao_id'],
            ':nome_produto_item' => $item_c['nome'],
            ':quantidade' => $item_c['quantidade'],
            ':preco_unitario_item' => $item_c['preco'],
            ':subtotal_item' => $item_c['preco'] * $item_c['quantidade']
        ]);

        // Decrementa o estoque do produto/variação.
        if ($item_c['variacao_id']) {
            $sql_update_estoque = "UPDATE estoque SET quantidade = quantidade - :qtd WHERE produto_id = :pid AND variacao_id = :vid";
            $stmt_update_estoque = $pdo->prepare($sql_update_estoque);
            $stmt_update_estoque->execute([
                ':qtd' => $item_c['quantidade'],
                ':pid' => $item_c['produto_id'],
                ':vid' => $item_c['variacao_id']
            ]);
        } else {
            $sql_update_estoque = "UPDATE estoque SET quantidade = quantidade - :qtd WHERE produto_id = :pid AND variacao_id IS NULL";
            $stmt_update_estoque = $pdo->prepare($sql_update_estoque);
            $stmt_update_estoque->execute([
                ':qtd' => $item_c['quantidade'],
                ':pid' => $item_c['produto_id']
            ]);
        }
    }

    // Se um cupom foi utilizado, incrementa o contador de usos do cupom.
    if ($cupom_id_usado) {
        $stmt_update_cupom = $pdo->prepare("UPDATE cupons SET usos_atuais = usos_atuais + 1 WHERE id = :cupom_id");
        $stmt_update_cupom->execute([':cupom_id' => $cupom_id_usado]);
    }

    // Se todas as operações foram bem-sucedidas, commita a transação.
    $pdo->commit();

    // Limpa as informações do carrinho, cupom e endereço da sessão, pois o pedido foi finalizado.
    unset($_SESSION['carrinho']);
    unset($_SESSION['cupom_aplicado']);
    // Limpa também os dados de endereço que foram armazenados na sessão.
    unset($_SESSION['endereco_cep']);
    unset($_SESSION['endereco_formatado']);
    unset($_SESSION['endereco_logradouro']);
    unset($_SESSION['endereco_bairro']);
    unset($_SESSION['endereco_cidade']);
    unset($_SESSION['endereco_uf']);

    // Armazena o ID do pedido finalizado na sessão para exibição na página de confirmação.
    $_SESSION['pedido_finalizado_id'] = $pedido_id_novo;
    $_SESSION['mensagem_sucesso'] = "Pedido #" . $pedido_id_novo . " realizado com sucesso! Obrigado por sua compra.";
    

    header("Location: " . BASE_URL . "public/pedido_confirmado.php");
    exit;

} catch (PDOException $e) {
    // Em caso de erro durante a transação (ex: falha ao inserir no banco), faz o rollback para reverter quaisquer alterações.
    $pdo->rollBack();
    $_SESSION['mensagem_erro'] = "Erro ao finalizar o pedido: " . $e->getMessage();
    // Registra o erro detalhado no log do servidor.
    error_log("Erro ao finalizar pedido: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
    header("Location: " . BASE_URL . "public/checkout.php");
    exit;
} catch (Exception $e) {
    // Captura exceções genéricas que podem ocorrer.
    // Se uma transação estiver ativa, faz o rollback.
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['mensagem_erro'] = "Ocorreu um erro inesperado ao processar seu pedido.";
    error_log("Erro geral ao finalizar pedido: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
    header("Location: " . BASE_URL . "public/checkout.php");
    exit;
}
?>