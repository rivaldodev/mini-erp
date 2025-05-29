# Mini ERP - Sistema de Gestão de Vendas

Bem-vindo ao **Mini ERP**, um sistema web completo de gerenciamento de vendas desenvolvido em **PHP** com foco em simplicidade, eficiência e organização de pedidos, produtos e cupons de desconto. Ideal para pequenos negócios que desejam uma solução leve e funcional.

## 🚀 Funcionalidades Principais

### 👤 Autenticação
- Login de administrador com verificação de sessão.
- Proteção de páginas administrativas por sessão.

### 🛒 Área Pública (Loja)
- Página inicial com listagem de produtos.
- Página de detalhes do produto.
- Carrinho de compras com:
  - Adição e remoção de itens.
  - Aplicação de cupons de desconto.
  - Cálculo de frete (via `verificar_cep.php`).
- Checkout e finalização de pedidos.
- Página de confirmação de pedido.

### 🧑‍💼 Painel Administrativo
- **Gerenciamento de Produtos**: cadastrar, editar e remover produtos.
- **Gerenciamento de Pedidos**: visualizar pedidos realizados e seus detalhes.
- **Gerenciamento de Cupons**: criar e editar cupons de desconto.
- Logout seguro.

## 🛠️ Tecnologias Utilizadas
- **PHP** (puro, sem frameworks)
- **MySQL** (estrutura e comandos para criar em `database.sql`)
- **HTML/CSS** com estilização personalizada
- **JavaScript** para interações básicas
- Estrutura modular com includes para cabeçalho, rodapé e funções

## 📂 Estrutura de Pastas

```
mini_erp/
├── admin/               # Painel administrativo
├── public/              # Área pública (loja)
├── includes/            # Conexão e funções auxiliares
├── css/                 # Estilos do sistema
├── js/                  # Scripts JS
├── login.php            # Tela de login
├── ver_sessao.php       # Validação de sessão
├── config.php           # Configurações globais
├── database.sql         # Script para criação do banco
```

## 🧪 Acesso de Teste

Você pode utilizar o seguinte login para acessar o painel administrativo:

- **Usuário:** `admin`
- **Senha:** `admin123`

## 🧰 Instalação e Configuração

1. Clone ou extraia o projeto em seu servidor local (ex: `htdocs` do XAMPP):
   ```
   git clone https://github.com/rivaldodev/mini-erp.git
   ```

2. Crie o banco de dados no MySQL e importe o arquivo `database.sql`.

3. Altere o arquivo `includes/db.php` se necessário, com os dados de acesso ao seu banco de dados.

4. Acesse a aplicação:
   - Área pública: `http://localhost/mini_erp/public`
   - Admin: `http://localhost/mini_erp/admin`


## 📋 Licença
Este projeto é de uso livre para fins educacionais ou como base para projetos comerciais com modificações.

---

Desenvolvido com dedicação para fins de demonstração profissional. Qualquer dúvida ou sugestão, estou à disposição!
