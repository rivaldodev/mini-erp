<?php
// Inclusão de arquivos essenciais para o funcionamento da página.
require_once __DIR__ . '/../config.php'; // Carrega as configurações globais da aplicação (constantes, BASE_URL, etc.).
require_once __DIR__ . '/../includes/db.php'; // Script para estabelecer a conexão com o banco de dados, disponibilizando a variável $pdo.

// Verifica se a requisição foi feita utilizando o método POST.
// Esta é uma medida de segurança para garantir que os dados sejam enviados da forma esperada (via formulário).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Coleta e valida os dados enviados pelo formulário.
    // filter_input é usado para obter e validar os dados de forma segura.
    $produto_id = filter_input(INPUT_POST, 'produto_id', FILTER_VALIDATE_INT);
    $variacao_id = filter_input(INPUT_POST, 'variacao_id', FILTER_VALIDATE_INT); // Pode ser nulo se o produto não tiver variação.
    $quantidade = filter_input(INPUT_POST, 'quantidade', FILTER_VALIDATE_INT);

    // Validação básica: produto_id deve ser um inteiro válido e quantidade deve ser maior que zero.
    if (!$produto_id || $quantidade <= 0) {
        $_SESSION['mensagem_erro'] = "Dados inválidos para adicionar ao carrinho.";
        // Redireciona de volta para a página anterior (HTTP_REFERER) ou para a página inicial da loja como fallback.
        header("Location: " . ($_SERVER['HTTP_REFERER'] ?? BASE_URL . "public/index.php"));
        exit;
    }

    // Bloco try-catch para tratamento de exceções durante a interação com o banco de dados.
    try {
        // Busca as informações básicas do produto (nome e preço base).
        $stmt_produto = $pdo->prepare("SELECT nome, preco_base FROM produtos WHERE id = :id");
        $stmt_produto->execute([':id' => $produto_id]);
        $produto = $stmt_produto->fetch(PDO::FETCH_ASSOC);

        // Se o produto não for encontrado, define erro e redireciona.
        if (!$produto) {
            $_SESSION['mensagem_erro'] = "Produto não encontrado.";
            header("Location: " . BASE_URL . "public/index.php");
            exit;
        }

        // Inicializa as variáveis para o item do carrinho.
        $nome_item = $produto['nome'];
        $preco_item = (float)$produto['preco_base'];
        $estoque_disponivel = 0;

        // Se uma variação_id foi fornecida, busca os detalhes da variação.
        if ($variacao_id) {
            $stmt_variacao = $pdo->prepare("
                SELECT pv.nome_variacao, pv.preco_adicional, e.quantidade as estoque_variacao
                FROM produto_variacoes pv
                JOIN estoque e ON pv.id = e.variacao_id AND e.produto_id = pv.produto_id
                WHERE pv.id = :variacao_id AND pv.produto_id = :produto_id
            ");
            $stmt_variacao->execute([':variacao_id' => $variacao_id, ':produto_id' => $produto_id]);
            $variacao = $stmt_variacao->fetch(PDO::FETCH_ASSOC);

            // Se a variação não for encontrada, define erro e redireciona.
            if (!$variacao) {
                $_SESSION['mensagem_erro'] = "Variação do produto não encontrada.";
                header("Location: " . ($_SERVER['HTTP_REFERER'] ?? BASE_URL . "public/index.php"));
                exit;
            }
            // Concatena o nome da variação ao nome do produto e ajusta o preço.
            $nome_item .= " - " . $variacao['nome_variacao'];
            $preco_item += (float)$variacao['preco_adicional'];
            // Define o estoque disponível com base no estoque da variação.
            $estoque_disponivel = (int)($variacao['estoque_variacao'] ?? 0); 
        } else {
            // Se não houver variação, busca o estoque do produto principal.
            $stmt_estoque_principal = $pdo->prepare("SELECT quantidade FROM estoque WHERE produto_id = :produto_id AND variacao_id IS NULL");
            $stmt_estoque_principal->execute([':produto_id' => $produto_id]);
            $res_estoque = $stmt_estoque_principal->fetch(PDO::FETCH_ASSOC);
            $estoque_disponivel = $res_estoque ? (int)$res_estoque['quantidade'] : 0;

        }

        // Verifica se a quantidade solicitada excede o estoque disponível.
        if ($quantidade > $estoque_disponivel) {
            $_SESSION['mensagem_erro'] = "Quantidade solicitada para '" . htmlspecialchars($nome_item) . "' não disponível em estoque (Disponível: $estoque_disponivel).";
            header("Location: " . ($_SERVER['HTTP_REFERER'] ?? BASE_URL . "public/index.php"));
            exit;
        }

        // Inicializa o carrinho na sessão se ainda não existir.
        // O carrinho é armazenado como um array na variável de sessão 'carrinho'.
        if (!isset($_SESSION['carrinho'])) {
            $_SESSION['carrinho'] = [];
        }

        // Cria um ID único para o item no carrinho, combinando produto_id e variacao_id (ou _0 se não houver variação).
        // Isso permite que o mesmo produto com diferentes variações seja tratado como itens distintos no carrinho.
        $item_id_carrinho = $produto_id . ($variacao_id ? "_" . $variacao_id : "_0");

        // Verifica se o item já existe no carrinho.
        if (isset($_SESSION['carrinho'][$item_id_carrinho])) {
            // Se já existe, apenas incrementa a quantidade.
            $_SESSION['carrinho'][$item_id_carrinho]['quantidade'] += $quantidade;
        } else {
            // Se não existe, adiciona o novo item ao carrinho.
            $_SESSION['carrinho'][$item_id_carrinho] = [
                'produto_id' => $produto_id,
                'variacao_id' => $variacao_id, // Armazena o ID da variação (pode ser null)
                'nome' => $nome_item,
                'preco' => $preco_item,
                'quantidade' => $quantidade
            ];
        }

        // Após adicionar/atualizar, verifica novamente se a quantidade total no carrinho para este item
        // não excede o estoque. Isso é uma dupla verificação, especialmente útil se o usuário
        // tentar adicionar o mesmo item várias vezes rapidamente.
        if ($_SESSION['carrinho'][$item_id_carrinho]['quantidade'] > $estoque_disponivel) {
            $_SESSION['carrinho'][$item_id_carrinho]['quantidade'] = $estoque_disponivel;
            $_SESSION['mensagem_aviso'] = "A quantidade de '" . htmlspecialchars($nome_item) . "' foi ajustada para o máximo em estoque ($estoque_disponivel).";
        } else {
            // Define mensagem de sucesso.
            $_SESSION['mensagem_sucesso'] = "'" . htmlspecialchars($nome_item) . "' adicionado ao carrinho!";
        }

    } catch (PDOException $e) {
        // Em caso de erro no banco, define uma mensagem e registra o erro no log.
        $_SESSION['mensagem_erro'] = "Erro ao processar o item: " . $e->getMessage();
        error_log("Erro ao adicionar ao carrinho: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
    }
} else {
    // Se a requisição não for POST, define uma mensagem de erro.
    $_SESSION['mensagem_erro'] = "Ação inválida.";
}

// Redireciona o usuário.
// Prioriza o redirecionamento para 'redirect_to_carrinho' se definido no POST (útil para botões "Comprar e ir para o carrinho").
// Caso contrário, volta para a página anterior (HTTP_REFERER) ou para a página do carrinho como fallback.
header("Location: " . ($_POST['redirect_to_carrinho'] ?? $_SERVER['HTTP_REFERER'] ?? BASE_URL . "public/carrinho.php"));
exit;
?>
