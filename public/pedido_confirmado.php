<?php
// Inclusão de arquivos essenciais.
require_once __DIR__ . '/../config.php';
include __DIR__ . '/../includes/header.php';

// Recupera o ID do pedido finalizado da sessão.
// Este ID é esperado ter sido definido no script 'finalizar_pedido.php' após o sucesso da transação.
$pedido_id = $_SESSION['pedido_finalizado_id'] ?? null;

// Remove o ID do pedido da sessão após recuperá-lo.
// Isso evita que a mesma mensagem de confirmação seja exibida novamente se o usuário recarregar a página
// ou navegar para outras páginas e voltar. É uma prática de "flash message" one-time.
unset($_SESSION['pedido_finalizado_id']); 
?>
<div class="container mt-5 text-center">
    <?php // Verifica se um ID de pedido foi recuperado da sessão. ?>
    <?php if ($pedido_id): ?>
        <!-- Bloco de mensagem de sucesso, exibido se o pedido_id for válido. -->
        <div class="alert alert-success" role="alert">
            <h4 class="alert-heading">Pedido Realizado com Sucesso!</h4>
            <p>Obrigado por sua compra. Seu pedido número <strong>#<?php echo htmlspecialchars($pedido_id); ?></strong> foi recebido e está sendo processado.</p>
            <hr>
            <p class="mb-0">Você receberá atualizações sobre o status do seu pedido em breve.</p>
        </div>
    <?php else: ?>
        <!-- Bloco de mensagem de erro/aviso, exibido se nenhum pedido_id foi encontrado na sessão. -->
        <!-- Isso pode acontecer se o usuário acessar esta página diretamente sem ter finalizado um pedido. -->
        <div class="alert alert-danger" role="alert">
            <h4 class="alert-heading">Algo deu errado</h4>
            <p>Não conseguimos encontrar os detalhes do seu pedido. Por favor, entre em contato conosco.</p>
        </div>
    <?php endif; ?>
    <p class="mt-4">
        <!-- Link para o usuário continuar comprando na loja. -->
        <a href="<?php echo BASE_URL; ?>public/index.php" class="btn btn-primary">Continuar Comprando</a>
        <?php if (isset($_SESSION['user_id'])): // Se for um usuário logado, pode ir para "meus pedidos" no futuro ?>
            <!-- <a href="<?php echo BASE_URL; ?>cliente/meus_pedidos.php" class="btn btn-info">Ver Meus Pedidos</a> -->
        <?php endif; ?>
    </p>
</div>
<?php
// Inclusão do rodapé da página.
include __DIR__ . '/../includes/footer.php';
?>
