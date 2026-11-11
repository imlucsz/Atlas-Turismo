# Atlas Turismo ✈️

**Gestão de viagens, reservas e pagamentos em um só lugar.**

`PHP 8.2+` · `MySQL 8` · `HTML5` · `CSS3`

Sistema administrativo para uma agência de turismo fictícia, desenvolvido para a P2 de Desenvolvimento Web II da FATEC Itaquera. O enunciado oficial está na raiz do projeto. A proposta de apresentação está em [docs/PROPOSTA_P2.md](docs/PROPOSTA_P2.md).

O sistema permite cadastrar clientes, destinos e pacotes, planejar e operar viagens, confirmar reservas, registrar pagamentos, emitir comprovantes e consultar indicadores, relatórios e auditoria. O funcionário opera o sistema e o administrador também gerencia usuários e consulta a auditoria.

## Funcionalidades

- **Cadastros** de clientes, destinos e pacotes, com pesquisa e inativação.
- **Viagens e reservas** com controle de capacidade e proteção contra reservas simultâneas acima do limite.
- **Pagamentos** parciais ou integrais, com cálculo de saldo e comprovante imprimível.
- **Painel e relatórios** com indicadores reais, filtros, exportação CSV e impressão.
- **Segurança e gestão** com autenticação, perfis de acesso, proteção CSRF e auditoria.

Para conhecer a apresentação acadêmica, consulte a [proposta da P2](docs/PROPOSTA_P2.md). Para testar os fluxos, siga o [roteiro de demonstração](docs/DEMONSTRACAO.md).

## Requisitos

- PHP **8.2+** em 64 bits com `pdo_mysql` e `mbstring`. A extensão `curl` é necessária apenas para o teste HTTP.
- **MySQL 8.0.18+** com InnoDB. Essa versão já aplica as restrições CHECK e permite gerar a senha pelo comando de instalação abaixo.
- Navegador moderno. O frontend usa HTML e CSS locais. Não há JavaScript, Composer ou serviços externos necessários.

Confira o PHP com `php -v` e as extensões com `php -m`. Use executáveis compatíveis se o seu XAMPP vier com PHP 8.0 ou MariaDB.

## Instalação

1. Inicie o MySQL e conecte-se com um usuário autorizado a criar banco e usuários, por exemplo `mysql --user=root --password`.
2. Em uma instalação nova, execute o SQL abaixo. Guarde a senha aleatória retornada pelo MySQL para configurar a aplicação.

```sql
CREATE DATABASE atlas_turismo CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
CREATE USER 'atlas_app'@'127.0.0.1' IDENTIFIED BY RANDOM PASSWORD;
GRANT SELECT, INSERT, UPDATE ON atlas_turismo.* TO 'atlas_app'@'127.0.0.1';
```

3. Na pasta do projeto, importe o schema com a conta administrativa. Este exemplo usa PowerShell e o cliente `mysql` no PATH.

```powershell
$OutputEncoding = [Text.UTF8Encoding]::new($false)
Get-Content -LiteralPath .\database\schema.sql -Raw -Encoding UTF8 |
    mysql --host=127.0.0.1 --port=3306 --user=root --password --default-character-set=utf8mb4 atlas_turismo
Copy-Item .\app\config\config.example.php .\app\config\config.local.php
```

4. Preencha host, porta, banco, usuário e senha em `app/config/config.local.php`. Esse arquivo está no `.gitignore`. As variáveis `ATLAS_DB_HOST`, `ATLAS_DB_PORT`, `ATLAS_DB_NAME`, `ATLAS_DB_USER` e `ATLAS_DB_PASSWORD` têm precedência. Use a conta com permissões limitadas na aplicação.

5. Cadastre um administrador. A senha deve ter de 12 a 72 bytes. O comando recebe a senha pelo ambiente e armazena apenas o hash no banco.

```powershell
$senhaAtlas = Read-Host 'Senha do administrador Atlas' -AsSecureString
$env:ATLAS_ADMIN_PASSWORD = [System.Net.NetworkCredential]::new('', $senhaAtlas).Password
try { php database/create_admin.php 'Administrador Atlas' 'admin@example.invalid' }
finally { Remove-Item Env:ATLAS_ADMIN_PASSWORD }
```

6. Inicie o servidor local e abra [http://127.0.0.1:8000/](http://127.0.0.1:8000/). Para hospedar em um servidor web, use `public/` como raiz pública e HTTPS. O servidor embutido do PHP é para execução local.

```powershell
php -S 127.0.0.1:8000 -t public
```

## Demonstração e testes

Crie um **banco separado**, importe `database/schema.sql` e, opcionalmente, importe `database/seed.sql` antes de cadastrar o administrador. O seed contém somente dados fictícios e usa datas relativas à importação. Use [docs/DEMONSTRACAO.md](docs/DEMONSTRACAO.md) como roteiro.

O guia [docs/TESTES.md](docs/TESTES.md) registra os comandos, resultados observados e requisitos dos bancos de teste. As regras de negócio e os limites do fluxo financeiro estão em [docs/REGRAS_NEGOCIO.md](docs/REGRAS_NEGOCIO.md).

## Organização

- `public/` contém o ponto de entrada e os arquivos CSS.
- `app/config/` e `app/core/` tratam configuração, PDO, autenticação, sessão, CSRF e validação.
- `app/controllers/` recebe as requisições, `app/models/` consulta os dados, `app/services/` executa operações transacionais e `app/views/` gera o HTML.
- `database/`, `tests/` e `docs/` contêm instalação, verificações e documentação.
