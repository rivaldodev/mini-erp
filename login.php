 
<?php
// Inclusão de arquivos essenciais.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

// Se o usuário já estiver logado, redireciona para a página principal do admin
// Isso evita que um usuário autenticado veja a página de login novamente.
if (isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "admin/index.php");
    exit();
}

// Inicializa a variável que armazenará mensagens de erro de login.
$erro_login = '';

// Verifica se o formulário foi submetido (método POST).
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Coleta e sanitiza (removendo espaços em branco) os dados do formulário.
    // O operador de coalescência nula (??) garante que, se os campos não estiverem definidos, uma string vazia seja usada.
    $nome_usuario = trim($_POST['nome_usuario'] ?? '');
    $senha = trim($_POST['senha'] ?? '');

    // Validação: verifica se ambos os campos foram preenchidos.
    if (empty($nome_usuario) || empty($senha)) {
        $erro_login = "Por favor, preencha o nome de usuário e a senha.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, nome_usuario, senha_hash FROM usuarios WHERE nome_usuario = :nome_usuario");
            $stmt->bindParam(':nome_usuario', $nome_usuario);
            $stmt->execute();
            // Busca o usuário no banco de dados.
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            // Verifica se o usuário foi encontrado e se a senha fornecida corresponde ao hash armazenado.
            // password_verify() é a função correta para verificar senhas com hash seguro.
            if ($usuario && password_verify($senha, $usuario['senha_hash'])) {
                // Senha correta, iniciar sessão
                // Armazena o ID e o nome do usuário na sessão para identificá-lo em outras páginas.
                $_SESSION['user_id'] = $usuario['id'];
                $_SESSION['nome_usuario'] = $usuario['nome_usuario'];
                $_SESSION['mensagem_sucesso'] = "Login realizado com sucesso!";
                // Redireciona para o painel de administração.
                header("Location: " . BASE_URL . "admin/index.php"); // Redireciona para o painel de administração
                exit();
            } else {
                // Usuário não encontrado ou senha incorreta
                $erro_login = "Nome de usuário ou senha inválidos.";
            }
        } catch (PDOException $e) {
            // Em caso de erro na consulta ao banco, define uma mensagem de erro genérica.
            $erro_login = "Erro ao tentar fazer login. Tente novamente.";
            // É uma boa prática registrar o erro detalhado no log do servidor para depuração.
            error_log("Erro no login (PDOException): " . $e->getMessage() . " | Usuário: " . $nome_usuario . " | Trace: " . $e->getTraceAsString());
        }
    }
}

// Inclusão do cabeçalho da página.
include __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-4">
        <div class="card">
            <div class="card-header text-center">
                <h3>Login Administrativo</h3>
            </div>
            <div class="card-body">
                <?php // Se houver uma mensagem de erro de login, exibe-a. ?>
                <?php if (!empty($erro_login)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($erro_login); ?></div>
                <?php endif; ?>
                <!-- Formulário de login -->
                <form action="login.php" method="post">
                    <div class="form-group">
                        <label for="nome_usuario">Nome de Usuário:</label>
                        <input type="text" class="form-control" id="nome_usuario" name="nome_usuario" required>
                    </div>
                    <div class="form-group">
                        <label for="senha">Senha:</label>
                        <input type="password" class="form-control" id="senha" name="senha" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Entrar</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php // Inclusão do rodapé da página. ?>
<?php include __DIR__ . '/includes/footer.php'; ?>