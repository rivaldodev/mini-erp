<?php
// public/verificar_cep.php

// Inclusão do arquivo de configuração.
// É crucial para garantir que a sessão PHP (`$_SESSION`) esteja iniciada,
// permitindo armazenar os dados do endereço obtidos para uso posterior (ex: no carrinho, checkout).
require_once __DIR__ . '/../config.php'; // Garante que a sessão está iniciada para armazenar o resultado

// Define o cabeçalho da resposta HTTP para indicar que o conteúdo retornado será JSON.
// Isso é fundamental para que o JavaScript no frontend (função fetch) interprete corretamente os dados.
header('Content-Type: application/json'); 

// Inicializa a variável de resposta padrão.
// Define uma mensagem de erro genérica caso o script não consiga processar o CEP por algum motivo.
$response_data = ['erro' => 'Nenhum CEP fornecido.'];

// Verifica se a requisição foi feita utilizando o método POST e se o parâmetro 'cep' foi enviado.
// Esta é uma boa prática para endpoints que recebem dados.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cep'])) {
    // Remove quaisquer caracteres não numéricos do CEP enviado (ex: traços, pontos).
    // Isso garante que apenas os dígitos sejam usados na consulta à API.
    $cep = preg_replace("/[^0-9]/", "", $_POST['cep']); 

    // Valida se o CEP, após a limpeza, possui exatamente 8 dígitos.
    if (strlen($cep) == 8) {
        // Constrói a URL para a API ViaCEP, interpolando o CEP limpo.
        $url = "https://viacep.com.br/ws/{$cep}/json/";

        // Utiliza a biblioteca cURL para fazer a requisição HTTP à API ViaCEP.
        // cURL é uma ferramenta poderosa para transferir dados com URLs.
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url); // Define a URL da requisição.
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Configura para retornar o resultado da transferência como uma string, em vez de exibi-lo diretamente.
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Define um timeout de 10 segundos para a requisição. Evita que o script fique esperando indefinidamente.
        
        // Comentário importante sobre SSL:
        // Em ambientes de desenvolvimento locais (localhost), às vezes ocorrem problemas com a verificação de certificados SSL.
        // As linhas abaixo, se descomentadas, desabilitariam essa verificação.
        // ATENÇÃO: NUNCA use estas opções em produção, pois comprometem a segurança da comunicação HTTPS.
        // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        // curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        // Executa a requisição cURL e armazena a resposta da API.
        $resultado_api = curl_exec($ch);
        // Obtém o código de status HTTP da resposta.
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        // Obtém qualquer erro que tenha ocorrido durante a execução do cURL.
        $curl_error = curl_error($ch);
        // Fecha a sessão cURL, liberando os recursos.
        curl_close($ch);

        // Verifica se ocorreu algum erro durante a requisição cURL.
        if ($curl_error) {
            $response_data = ['erro' => "Erro na consulta à API de CEP: " . $curl_error];
            error_log("Erro cURL ViaCEP: " . $curl_error); // Registra o erro no log do servidor para depuração.
        // Verifica se o código HTTP da resposta foi 200 (OK), indicando sucesso.
        } elseif ($http_code == 200) {
            // Decodifica a resposta JSON da API para um array associativo PHP.
            $dados_endereco = json_decode($resultado_api, true);

            // A API ViaCEP retorna `{"erro": true}` se o CEP não for encontrado.
            if (isset($dados_endereco['erro']) && $dados_endereco['erro'] == true) {
                $response_data = ['erro' => "CEP não encontrado ou inválido."];
                unset($_SESSION['endereco_cep']); // Limpa qualquer CEP inválido previamente armazenado na sessão.
                unset($_SESSION['endereco_formatado']);
            // Se não houver erro na resposta da API e os dados do endereço existirem.
            } elseif ($dados_endereco && !isset($dados_endereco['erro'])) {
                // Armazena os dados do endereço na sessão PHP.
                // Isso permite que outras partes da aplicação (carrinho, checkout) acessem essas informações.
                $_SESSION['endereco_cep'] = $cep;
                $_SESSION['endereco_logradouro'] = $dados_endereco['logradouro'] ?? '';
                $_SESSION['endereco_bairro'] = $dados_endereco['bairro'] ?? '';
                $_SESSION['endereco_cidade'] = $dados_endereco['localidade'] ?? '';
                $_SESSION['endereco_uf'] = $dados_endereco['uf'] ?? '';
                // Cria uma string formatada do endereço para exibição amigável.
                // O `trim` remove vírgulas ou hífens desnecessários no início/fim caso algum campo esteja vazio.
                $_SESSION['endereco_formatado'] = trim(sprintf("%s, %s, %s - %s", $dados_endereco['logradouro'] ?? '', $dados_endereco['bairro'] ?? '', $dados_endereco['localidade'] ?? '', $dados_endereco['uf'] ?? ''), " ,-");

                // Prepara os dados de resposta para o frontend.
                $response_data = $dados_endereco; // Retorna todos os dados da API.
                $response_data['endereco_formatado'] = $_SESSION['endereco_formatado']; // Adiciona a string formatada à resposta.
            } else {
                // Caso a resposta da API seja inesperada (não é erro, mas também não tem os dados esperados).
                $response_data = ['erro' => "Resposta inválida da API de CEP."];
            }
        } else {
            // Se o código HTTP não for 200, indica um problema na comunicação com a API.
            $response_data = ['erro' => "Erro ao consultar o CEP. Código HTTP: " . $http_code];
        }
    } else {
        // Se o CEP não tiver 8 dígitos após a limpeza.
        $response_data = ['erro' => "Formato de CEP inválido. Use 8 dígitos."];
    }
}

// Converte o array de resposta PHP para uma string JSON.
echo json_encode($response_data);
// Encerra a execução do script.
// É uma boa prática usar exit após enviar uma resposta JSON, especialmente em scripts chamados via AJAX.
exit;
?>
