<?php
// Inclusão de arquivos essenciais para o funcionamento da página.
require_once __DIR__ . '/../config.php'; // Arquivo de configuração global (constantes, etc.)
require_once __DIR__ . '/../ver_sessao.php'; // Script para verificar se o usuário está logado.
require_once __DIR__ . '/../includes/db.php'; // Script para estabelecer a conexão com o banco de dados.

// Inicialização de variáveis.
$cupom_id = $_GET['id'] ?? null;
$cupom = null;

// Define o título da página dinamicamente, dependendo se é uma edição ou adição de cupom.
$page_title = $cupom_id ? "Editar Cupom" : "Adicionar Novo Cupom";

// Bloco de código executado se um ID de cupom é fornecido via GET (modo de edição).
if ($cupom_id) {
    try {
        // Prepara e executa a query para buscar os dados do cupom existente.
        $stmt = $pdo->prepare("SELECT * FROM cupons WHERE id = :id");
        $stmt->bindParam(':id', $cupom_id, PDO::PARAM_INT);
        $stmt->execute();
        $cupom = $stmt->fetch(PDO::FETCH_ASSOC);

        // Se o cupom não for encontrado, define uma mensagem de erro e redireciona o usuário.
        if (!$cupom) {
            $_SESSION['mensagem_erro'] = "Cupom não encontrado.";
            header("Location: cupons_listar.php");
            exit;
        }
    } catch (PDOException $e) {
        // Em caso de erro na busca, define uma mensagem e registra o erro no log.
        $_SESSION['mensagem_erro'] = "Erro ao carregar cupom: " . $e->getMessage();
        error_log("Erro ao carregar cupom (ID: $cupom_id): " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
    }
}

// Bloco de código executado quando o formulário é submetido (método POST).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Coleta e sanitiza os dados enviados pelo formulário.
    $codigo = strtoupper(trim($_POST['codigo'] ?? ''));
    $tipo_desconto = $_POST['tipo_desconto'] ?? 'fixo';
    $valor_desconto = (float)filter_var($_POST['valor_desconto'] ?? 0, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $valor_minimo_pedido = (float)filter_var($_POST['valor_minimo_pedido'] ?? 0, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $data_validade = !empty($_POST['data_validade']) ? $_POST['data_validade'] : null;
    $usos_maximos = !empty($_POST['usos_maximos']) ? (int)$_POST['usos_maximos'] : null;
    $ativo = isset($_POST['ativo']) ? 1 : 0;

    // Validação básica dos campos obrigatórios.
    if (empty($codigo) || $valor_desconto <= 0) {
        $_SESSION['mensagem_erro'] = "Código e Valor do Desconto são obrigatórios. O valor deve ser maior que zero.";
    } elseif ($tipo_desconto == 'percentual' && ($valor_desconto <= 0 || $valor_desconto > 100)) {
        // Validação específica para desconto percentual.
        $_SESSION['mensagem_erro'] = "Para desconto percentual, o valor deve ser entre 0.01 e 100.";
    } else {
        try {
            // Define a query SQL e os parâmetros com base na ação (inserir novo ou atualizar existente).
            if ($cupom_id) { 
                // Query para ATUALIZAR um cupom existente.
                $sql = "UPDATE cupons SET codigo = :codigo, tipo_desconto = :tipo_desconto, valor_desconto = :valor_desconto, valor_minimo_pedido = :valor_minimo_pedido, data_validade = :data_validade, usos_maximos = :usos_maximos, ativo = :ativo WHERE id = :id";
                $params = [
                    ':codigo' => $codigo,
                    ':tipo_desconto' => $tipo_desconto,
                    ':valor_desconto' => $valor_desconto,
                    ':valor_minimo_pedido' => $valor_minimo_pedido,
                    ':data_validade' => $data_validade,
                    ':usos_maximos' => $usos_maximos,
                    ':ativo' => $ativo,
                    ':id' => (int)$cupom_id // Garante que o ID seja um inteiro.
                ];
            } else { 
                // Query para INSERIR um novo cupom.
                $sql = "INSERT INTO cupons (codigo, tipo_desconto, valor_desconto, valor_minimo_pedido, data_validade, usos_maximos, ativo) VALUES (:codigo, :tipo_desconto, :valor_desconto, :valor_minimo_pedido, :data_validade, :usos_maximos, :ativo)";
                $params = [
                    ':codigo' => $codigo,
                    ':tipo_desconto' => $tipo_desconto,
                    ':valor_desconto' => $valor_desconto,
                    ':valor_minimo_pedido' => $valor_minimo_pedido,
                    ':data_validade' => $data_validade,
                    ':usos_maximos' => $usos_maximos,
                    ':ativo' => $ativo
                ];
            }
            // Log para debug da query SQL e parâmetros.
            error_log("DEBUG: Executando SQL Cupons. SQL: " . $sql . " | Params: " . json_encode($params));
            // Prepara e executa a query para salvar/atualizar o cupom.
            $stmt_cupom_save = $pdo->prepare($sql);
            $stmt_cupom_save->execute($params);

            // Define mensagem de sucesso e redireciona para a listagem de cupons.
            $_SESSION['mensagem_sucesso'] = "Cupom salvo com sucesso!";
            header("Location: cupons_listar.php");
            exit;

        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                // Trata erro específico de código de cupom duplicado (constraint UNIQUE).
                $_SESSION['mensagem_erro'] = "Erro ao salvar cupom: O código '$codigo' já existe.";
            } else {
                $_SESSION['mensagem_erro'] = "Erro ao salvar cupom: " . $e->getMessage();
            }
            // Registra o erro detalhado no log do servidor.
            error_log("PDOException em cupom_gerenciar.php: " . $e->getMessage() . " | SQLSTATE: " . $e->getCode() . " | Trace: " . $e->getTraceAsString());
        }
    }
}

// Inclusão do cabeçalho da página.
include __DIR__ . '/../includes/header.php';
?>

<h2><?php echo $page_title; ?></h2>
<!-- Formulário para adicionar/editar cupom. A action é dinâmica, incluindo o ID do cupom se estiver em modo de edição. -->

<form method="POST" action="cupom_gerenciar.php<?php echo $cupom_id ? '?id='.$cupom_id : ''; ?>">
    <div class="card mb-4">
        <div class="card-header">
            Detalhes do Cupom
        </div>
        <div class="card-body">
            <!-- Campo Código do Cupom -->
            <div class="form-group">
                <label for="codigo">Código do Cupom:</label>
                <input type="text" class="form-control" id="codigo" name="codigo" value="<?php echo htmlspecialchars($cupom['codigo'] ?? ''); ?>" required style="text-transform:uppercase">
                <small class="form-text text-muted">Use letras maiúsculas e números. Ex: PROMO10, NATAL25OFF</small>
            </div>

            <div class="form-row">
                <!-- Campo Tipo de Desconto -->
                <div class="form-group col-md-6">
                    <label for="tipo_desconto">Tipo de Desconto:</label>
                    <select class="form-control" id="tipo_desconto" name="tipo_desconto">
                        <option value="fixo" <?php echo (isset($cupom['tipo_desconto']) && $cupom['tipo_desconto'] == 'fixo') ? 'selected' : ''; ?>>Fixo (R$)</option>
                        <option value="percentual" <?php echo (isset($cupom['tipo_desconto']) && $cupom['tipo_desconto'] == 'percentual') ? 'selected' : ''; ?>>Percentual (%)</option>
                    </select>
                </div>
                <!-- Campo Valor do Desconto -->
                <div class="form-group col-md-6">
                    <label for="valor_desconto">Valor do Desconto:</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text" id="tipo_valor_desconto_label">R$</span>
                        </div>
                        <input type="number" step="0.01" class="form-control" id="valor_desconto" name="valor_desconto" value="<?php echo htmlspecialchars($cupom['valor_desconto'] ?? '0.00'); ?>" required>
                    </div>
                    <small class="form-text text-muted">Ex: 10.00 para R$10,00 ou 10 para 10%</small>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            Regras de Uso
        </div>
        <div class="card-body">
            <!-- Campo Valor Mínimo do Pedido -->
            <div class="form-group">
                <label for="valor_minimo_pedido">Valor Mínimo do Pedido:</label>
                 <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text">R$</span>
                    </div>
                    <input type="number" step="0.01" class="form-control" id="valor_minimo_pedido" name="valor_minimo_pedido" value="<?php echo htmlspecialchars($cupom['valor_minimo_pedido'] ?? '0.00'); ?>">
                </div>
                <small class="form-text text-muted">O subtotal do carrinho deve ser igual ou maior que este valor. Deixe 0 para não aplicar.</small>
            </div>

            <div class="form-row">
                <!-- Campo Data de Validade -->
                <div class="form-group col-md-6">
                    <label for="data_validade">Data de Validade (opcional):</label>
                    <input type="date" class="form-control" id="data_validade" name="data_validade" value="<?php echo htmlspecialchars($cupom['data_validade'] ?? ''); ?>">
                </div>
                <!-- Campo Usos Máximos -->
                <div class="form-group col-md-6">
                    <label for="usos_maximos">Usos Máximos (opcional):</label>
                    <input type="number" class="form-control" id="usos_maximos" name="usos_maximos" value="<?php echo htmlspecialchars($cupom['usos_maximos'] ?? ''); ?>" placeholder="Deixe em branco para ilimitado">
                </div>
            </div>

            <!-- Campo Ativo (Checkbox) -->
            <div class="form-group form-check">
                <?php // Define 'checked' se o cupom estiver ativo ou se for um novo cupom (ativo por padrão). ?>
                <input type="checkbox" class="form-check-input" id="ativo" name="ativo" value="1" <?php echo (isset($cupom['ativo']) && $cupom['ativo'] || !isset($cupom['ativo']) && !$cupom_id) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="ativo">Ativo</label>
                <small class="form-text text-muted">Marque para permitir o uso deste cupom.</small>
            </div>
        </div>
    </div>

    <!-- Botões de Ação -->
    <button type="submit" class="btn btn-primary">Salvar Cupom</button>
    <a href="cupons_listar.php" class="btn btn-secondary">Cancelar</a>
</form>

<script>
// Script JavaScript para interatividade no formulário.
document.addEventListener('DOMContentLoaded', function() {
    const tipoDescontoSelect = document.getElementById('tipo_desconto');
    const valorDescontoLabel = document.getElementById('tipo_valor_desconto_label');

    // Função para atualizar o label (R$ ou %) ao lado do campo "Valor do Desconto".
    function atualizarLabelValorDesconto() {
        if (tipoDescontoSelect.value === 'percentual') {
            valorDescontoLabel.textContent = '%';
        } else {
            valorDescontoLabel.textContent = 'R$';
        }
    }

    // Garante que o label seja atualizado ao carregar a página e ao mudar a seleção.
    if (tipoDescontoSelect) {
        tipoDescontoSelect.addEventListener('change', atualizarLabelValorDesconto);
        atualizarLabelValorDesconto(); // Chama a função uma vez para definir o estado inicial correto.
    }
});
</script>

<?php
// Inclusão do rodapé da página.
include __DIR__ . '/../includes/footer.php';
?>
