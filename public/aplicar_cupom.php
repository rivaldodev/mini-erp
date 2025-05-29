<?php
// Inclusão de arquivos essenciais para o funcionamento da página.
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

// Verifica se a requisição foi feita utilizando o método POST (para aplicar um novo cupom).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Coleta o código do cupom enviado, converte para maiúsculas e remove espaços em branco.
    // O operador de coalescência nula (??) garante que, se 'cupom_codigo' não estiver definido, uma string vazia seja usada.
    $cupom_codigo = strtoupper(trim($_POST['cupom_codigo'] ?? ''));

    // Limpa qualquer informação de cupom previamente aplicado na sessão.
    // Isso garante que apenas um cupom seja considerado por vez.
    unset($_SESSION['cupom_aplicado']);
    unset($_SESSION['desconto_cupom_valor']); // Esta variável parece redundante, já que 'cupom_aplicado' armazena o desconto.

    // Validação: verifica se o código do cupom foi de fato inserido.
    if (empty($cupom_codigo)) {
        $_SESSION['mensagem_erro'] = "Por favor, insira um código de cupom.";
        header("Location: " . BASE_URL . "public/carrinho.php");
        exit;
    }

    // Obtém o carrinho atual da sessão e calcula o subtotal.
    // O subtotal é necessário para validar regras do cupom (valor mínimo) e calcular descontos percentuais.
    $carrinho_atual = $_SESSION['carrinho'] ?? [];
    $subtotal_carrinho = 0;
    foreach ($carrinho_atual as $item) {
        $subtotal_carrinho += ($item['preco'] ?? 0) * ($item['quantidade'] ?? 0);
    }
    // Bloco try-catch para tratamento de exceções durante a interação com o banco de dados.
    try {
        $stmt = $pdo->prepare("SELECT * FROM cupons WHERE codigo = :codigo");
        $stmt->execute([':codigo' => $cupom_codigo]);
        $cupom = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cupom) {
            $_SESSION['mensagem_erro'] = "Cupom inválido ou não encontrado.";
            // Redireciona de volta para a página do carrinho.
            header("Location: " . BASE_URL . "public/carrinho.php");
            exit;
        }

        // Validação: verifica se o cupom está ativo.
        if (!$cupom['ativo']) {
            $_SESSION['mensagem_erro'] = "Este cupom não está ativo.";
            header("Location: " . BASE_URL . "public/carrinho.php");
            exit;
        }

        // Validação: verifica a data de validade do cupom.
        if ($cupom['data_validade'] && strtotime($cupom['data_validade']) < time()) {
            $_SESSION['mensagem_erro'] = "Este cupom expirou em " . date("d/m/Y", strtotime($cupom['data_validade'])) . ".";
            header("Location: " . BASE_URL . "public/carrinho.php");
            exit;
        }

        // Validação: verifica o limite de usos do cupom.
        if ($cupom['usos_maximos'] !== null && $cupom['usos_atuais'] >= $cupom['usos_maximos']) {
            $_SESSION['mensagem_erro'] = "Este cupom atingiu o limite máximo de usos.";
            header("Location: " . BASE_URL . "public/carrinho.php");
            exit;
        }

        // Validação: verifica se o subtotal do carrinho atinge o valor mínimo exigido pelo cupom.
        if (isset($cupom['valor_minimo_pedido']) && $cupom['valor_minimo_pedido'] > 0 && $subtotal_carrinho < $cupom['valor_minimo_pedido']) {
            $_SESSION['mensagem_erro'] = "O subtotal do pedido (R$ " . number_format($subtotal_carrinho, 2, ',', '.') . ") não atinge o valor mínimo de R$ " . number_format($cupom['valor_minimo_pedido'], 2, ',', '.') . " para este cupom.";
            header("Location: " . BASE_URL . "public/carrinho.php");
            exit;
        }

        // Calcula o valor do desconto com base no tipo do cupom (fixo ou percentual).
        $desconto_calculado = 0;
        if ($cupom['tipo_desconto'] == 'fixo') {
            $desconto_calculado = (float)$cupom['valor_desconto'];
        } elseif ($cupom['tipo_desconto'] == 'percentual') {
            $desconto_calculado = ($subtotal_carrinho * (float)$cupom['valor_desconto']) / 100;
        }
        // Garante que o desconto não seja maior que o subtotal do carrinho.
        $desconto_calculado = min($desconto_calculado, $subtotal_carrinho);

        $_SESSION['cupom_aplicado'] = [
            'id' => $cupom['id'],
            'codigo' => $cupom['codigo'],
            'tipo' => $cupom['tipo_desconto'],
            'valor_original' => $cupom['valor_desconto'],
            'desconto_calculado' => round($desconto_calculado, 2) // Armazena o valor do desconto já calculado e arredondado.
        ];
        $_SESSION['mensagem_sucesso'] = "Cupom '" . htmlspecialchars($cupom['codigo']) . "' aplicado com sucesso!";

    } catch (PDOException $e) {
        // Em caso de erro no banco, define uma mensagem e registra o erro no log.
        $_SESSION['mensagem_erro'] = "Erro ao validar cupom: " . $e->getMessage();
        error_log("Erro ao aplicar cupom: " . $e->getMessage() . " | Código: " . $cupom_codigo . " | Trace: " . $e->getTraceAsString());
    }
// Verifica se a ação é para remover um cupom (via GET).
} elseif (isset($_GET['acao']) && $_GET['acao'] === 'remover_cupom') {
    // Remove as informações do cupom da sessão.
    unset($_SESSION['cupom_aplicado']);
    unset($_SESSION['desconto_cupom_valor']); 
    $_SESSION['mensagem_aviso'] = "Cupom removido.";
} else {
    // Se a requisição não for POST nem uma ação de remoção válida, define uma mensagem de erro.
    $_SESSION['mensagem_erro'] = "Ação inválida.";
}

// Redireciona o usuário de volta para a página do carrinho.
// Todas as operações (aplicar, remover, erro) resultam em um redirecionamento para o carrinho,
// onde as mensagens de sessão (sucesso, erro, aviso) serão exibidas.
header("Location: " . BASE_URL . "public/carrinho.php");
exit;
?>