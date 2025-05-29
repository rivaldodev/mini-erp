 
<?php
// ver_sessao.php
// Este script verifica se o usuário está logado.
// Deve ser incluído no início das páginas que requerem autenticação.

if (!isset($_SESSION['user_id'])) {
    // Se não houver user_id na sessão, o usuário não está logado.
    $_SESSION['mensagem_erro'] = "Você precisa estar logado para acessar esta página.";
    header("Location: " . (defined('BASE_URL') ? BASE_URL : '') . "login.php"); // Adapta o BASE_URL
    exit();
}
?>