<?php
// Arquivo de Configuração do Sistema mini_erp

// --- Configurações do Banco de Dados MySQL ---
// Define constantes para as credenciais e detalhes de conexão com o banco de dados.
// É uma prática comum usar constantes para configurações que não mudam durante a execução do script.

define('DB_HOST', 'localhost');     // Endereço do servidor onde o banco de dados MySQL está hospedado.
define('DB_NAME', 'mini_erp_db');   // Nome específico do banco de dados a ser utilizado pela aplicação.
define('DB_USER', 'root');          // Nome de usuário para autenticação no banco de dados.
define('DB_PASS', '');              // Senha para o usuário do banco de dados. Em ambientes de desenvolvimento local, 'root' frequentemente não tem senha.
define('DB_CHARSET', 'utf8mb4');    // Define o conjunto de caracteres padrão para a conexão com o banco.
                                    // 'utf8mb4' é recomendado para suportar uma ampla gama de caracteres, incluindo emojis.

// URL Base do Sistema
// Altere para o URL raiz da sua aplicação. Ex: http://localhost/mini_erp/
define('BASE_URL', 'http://localhost/mini_erp/'); // Define a URL base da aplicação.
                                                  // Essencial para construir URLs absolutas para links, assets (CSS, JS, imagens),
                                                  // e redirecionamentos, garantindo que funcionem corretamente
                                                  // independentemente de onde o projeto está hospedado (localhost, servidor de produção, subdiretório, etc.).

// --- Gerenciamento de Sessão ---
// Verifica se uma sessão PHP já foi iniciada.
if (session_status() == PHP_SESSION_NONE) {
    // Se nenhuma sessão estiver ativa, configura a sessão para que os cookies sejam acessíveis apenas via HTTP (medida de segurança contra XSS).
    ini_set('session.cookie_httponly', 1);
    // Inicia uma nova sessão ou resume uma sessão existente.
    // As sessões são usadas para armazenar informações do usuário entre requisições (ex: login, carrinho de compras).
    session_start();
}
?>
