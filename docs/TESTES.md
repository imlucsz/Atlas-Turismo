# Validação do Atlas Turismo

Execução em 11/11/2026, em Windows com **PHP 8.5.12** e **MySQL Community Server 8.4.13**. O MySQL de verificação rodou em instância portátil, com bancos separados do XAMPP e do ambiente de demonstração.

| Verificação | Resultado observado |
|---|---|
| `php -l` | 31 arquivos PHP verificados, sem erros de sintaxe. |
| `php tests/core.php` | 20 verificações de data, campo obrigatório, inteiros, escape de HTML e dinheiro em centavos. |
| `php tests/catalog_unit.php` | 12 verificações de validação de cadastros, e-mail, preço e senha. |
| `php tests/catalog_db_smoke.php` | Consultas reais de listagem, contagem, busca, status e destinos ativos. |
| `php tests/operations.php` | 12 verificações de datas, capacidade, transições, pagamentos e cancelamentos. Dois processos disputaram a última vaga em conexões MySQL diferentes. Um confirmou, o outro foi recusado e o total de passageiros permaneceu dentro da capacidade. |
| `php tests/analytics.php` | Consultas com o seed conferiram períodos, saldo, receita, ocupação, rankings, histórico por cliente, resumo, paginação e proteção de células CSV. |
| `php tests/http.php` | 76 verificações em HTTP, incluindo login, logout, CSRF, permissões, cadastro, edição, inativação, busca, reserva, lotação, pagamento parcial, quitação, recusa de sobrepagamento, auditoria, comprovante e CSV. |
| Navegador Chromium | Login e painel conferidos no desktop. Painel, viagens, detalhe da reserva, ocupação e comprovante abriram a 390 px sem transbordamento horizontal da página. No modo de impressão, o menu ficou oculto. |

## Reexecução

1. Para testes unitários, execute `php tests/core.php` e `php tests/catalog_unit.php` na raiz.
2. Para os testes com MySQL, configure `ATLAS_DB_HOST`, `ATLAS_DB_PORT`, `ATLAS_DB_USER` e `ATLAS_DB_PASSWORD` para uma instância de teste. Importe `database/schema.sql` em cada banco dedicado.
3. Aponte `ATLAS_DB_NAME` para `atlas_catalog_test` e execute `php tests/catalog_db_smoke.php`. Aponte para `atlas_ops_test` e execute `php tests/operations.php`. O teste de operações exige um nome no formato `atlas_*_test`, cria registros com identificadores únicos e precisa de `proc_open` para os dois processos concorrentes.
4. Para `php tests/analytics.php`, aponte `ATLAS_DB_NAME` para um banco `atlas_analytics_test` recém-criado com o schema e o seed importados no dia do teste. As expectativas de data são relativas à data atual.
5. Para o teste HTTP, crie um banco exclusivo, por exemplo `atlas_http_test`, com o schema. Cadastre `admin@atlas.example` pelo comando de instalação e inicie `php -S 127.0.0.1:8033 -t public` com esse banco configurado. Em outro terminal, defina `ATLAS_TEST_ADMIN_PASSWORD` com a senha do administrador de teste e execute `php tests/http.php`. O teste exige a extensão PHP `curl` e cria registros de exemplo. Use somente o servidor e o banco dedicados a esse teste.

A versão executada foi PHP 8.5.12. A entrega usa APIs disponíveis a partir do PHP 8.2.
