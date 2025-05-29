<?php
// Inclusão de arquivos essenciais para o funcionamento da página.
require_once __DIR__ . '/../config.php'; 
require_once __DIR__ . '/../includes/db.php';

// Verifica se a requisição foi feita utilizando o método POST.
// Esta é uma medida de segurança e boa prática para operações que modificam dados.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Coleta os dados enviados pelo formulário.
    // 'item_id_carrinho' identifica o item específico no carrinho.
    $item_id_carrinho = $_POST['item_id_carrinho'] ?? null;
    // 'quantidade' é a nova quantidade desejada para o item, validada como inteiro.
    $quantidade = filter_input(INPUT_POST, 'quantidade', FILTER_VALIDATE_INT);
    // 'acao' determina se o item deve ser atualizado ou removido.
    $acao = $_POST['acao'] ?? null;

    // Validação: verifica se o ID do item foi fornecido e se o item realmente existe no carrinho da sessão.
    if (!$item_id_carrinho || !isset($_SESSION['carrinho'][$item_id_carrinho])) {
        $_SESSION['mensagem_erro'] = "Item inválido para atualizar.";
        header("Location: " . BASE_URL . "public/carrinho.php");
        exit;
    }

    // Recupera os dados do item específico do carrinho para facilitar o acesso.
    $item_carrinho = $_SESSION['carrinho'][$item_id_carrinho];

    // Lógica para REMOVER o item do carrinho.
    // Isso ocorre se a ação for 'remover' ou se a quantidade for atualizada para zero ou menos.
    if ($acao === 'remover' || ($acao === 'atualizar' && $quantidade <= 0)) {
        unset($_SESSION['carrinho'][$item_id_carrinho]);
        $_SESSION['mensagem_sucesso'] = "'" . htmlspecialchars($item_carrinho['nome']) . "' removido do carrinho.";
    // Lógica para ATUALIZAR a quantidade do item no carrinho.
    } elseif ($acao === 'atualizar' && $quantidade > 0) {
        try {
            $estoque_disponivel = 0;
            // Verifica o estoque da VARIAÇÃO do produto, se aplicável.
            if ($item_carrinho['variacao_id']) {
                $stmt_estoque = $pdo->prepare("
                    SELECT e.quantidade 
                    FROM estoque e 
                    WHERE e.produto_id = :produto_id AND e.variacao_id = :variacao_id
                ");
                $stmt_estoque->execute([
                    ':produto_id' => $item_carrinho['produto_id'],
                    ':variacao_id' => $item_carrinho['variacao_id']
                ]);
                $res = $stmt_estoque->fetch(PDO::FETCH_ASSOC);
                $estoque_disponivel = $res ? (int)$res['quantidade'] : 0;
            // Verifica o estoque do PRODUTO PRINCIPAL (sem variação).
            } else {
                $stmt_estoque = $pdo->prepare("
                    SELECT e.quantidade 
                    FROM estoque e 
                    WHERE e.produto_id = :produto_id AND e.variacao_id IS NULL
                ");
                $stmt_estoque->execute([':produto_id' => $item_carrinho['produto_id']]);
                $res = $stmt_estoque->fetch(PDO::FETCH_ASSOC);
                $estoque_disponivel = $res ? (int)$res['quantidade'] : 0;
            }

            // Se a quantidade solicitada for maior que o estoque disponível,
            // ajusta a quantidade no carrinho para o máximo em estoque e informa o usuário.
            if ($quantidade > $estoque_disponivel) {
                $_SESSION['carrinho'][$item_id_carrinho]['quantidade'] = $estoque_disponivel;
                $_SESSION['mensagem_aviso'] = "A quantidade de '" . htmlspecialchars($item_carrinho['nome']) . "' foi ajustada para o máximo em estoque ($estoque_disponivel).";
            // Caso contrário, atualiza a quantidade para o valor solicitado.
            } else {
                $_SESSION['carrinho'][$item_id_carrinho]['quantidade'] = $quantidade;
                $_SESSION['mensagem_sucesso'] = "Quantidade de '" . htmlspecialchars($item_carrinho['nome']) . "' atualizada.";
            }

        } catch (PDOException $e) {
            // Em caso de erro na consulta ao banco, define uma mensagem e registra o erro no log.
            $_SESSION['mensagem_erro'] = "Erro ao verificar estoque: " . $e->getMessage();
            error_log("Erro ao atualizar carrinho (verificar estoque): " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
        }
    } else {
        // Se a ação não for 'remover' nem 'atualizar' com quantidade válida, considera a ação inválida.
        $_SESSION['mensagem_erro'] = "Ação inválida no carrinho.";
    }

} else {
    // Se a requisição não for POST, define uma mensagem de erro.
    $_SESSION['mensagem_erro'] = "Requisição inválida.";
}

// Redireciona o usuário de volta para a página do carrinho.
// Todas as operações (sucesso, erro, aviso) resultam em um redirecionamento para o carrinho,
// onde as mensagens de sessão serão exibidas.
header("Location: " . BASE_URL . "public/carrinho.php");
exit;
?>