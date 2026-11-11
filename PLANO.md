# Atlas Turismo — plano de implementação

**Objetivo:** centralizar o atendimento e a operação de uma agência de viagens fictícia, da manutenção do catálogo ao recebimento e à prestação de contas. A proposta acadêmica corresponde à P2 de Desenvolvimento Web II da FATEC Itaquera, com apresentação da proposta até 18/11/2026.

**Arquitetura:** entrada única em `public/index.php`, rotas por `?p=`, formulários POST com redirecionamento, modelos para consultas, serviços para operações transacionais e views HTML/CSS. Separar a aplicação do ambiente de teste e manter o enunciado na raiz. O modelo relacional e os contratos abaixo permitem verificar cada módulo sem depender da interface dos demais.

**Tecnologias:** PHP 8.2+, PDO MySQL, MySQL 8+ com InnoDB, HTML5 e CSS3. Os valores financeiros usam DECIMAL no MySQL e centavos inteiros em PHP. A aplicação usa fontes locais do sistema e dispensa bibliotecas de frontend.

**Abordagem escolhida:** páginas renderizadas pelo servidor com controlador central e módulos por domínio. Um arquivo PHP para cada tela repetiria autorização e tratamento de erros. Uma arquitetura de API exigiria um segundo consumidor sem ganho para os formulários da avaliação.

## Fases

- [x] 1. Banco e instalação. Criar `database/schema.sql`, `database/seed.sql` separado, configuração local ignorada e comando PHP para cadastrar o primeiro administrador. Testar em instância MySQL isolada.
- [x] 2. Infraestrutura. Implementar validação, centavos, PDO, sessão, login/logout, autorização, CSRF, POST/Redirect/GET, roteamento, layout, navegação e mensagens. Testar acesso anônimo, credenciais inválidas e restrições por perfil.
- [x] 3. Fluxo principal. Cadastrar clientes, destinos e pacotes. Planejar e operar viagens, criar e cancelar reservas, registrar pagamentos e consultar histórico. Testar transições, datas inválidas, disputa de vaga, saldo parcial, quitação, tentativa de sobrepagamento e cancelamento com valor pago.
- [x] 4. Gestão. Adicionar painel com dados reais, filtros e paginação, administração de usuários, relatórios gerenciais, CSV, impressão, comprovante, alertas e auditoria.
- [x] 5. Entrega. Conferir sintaxe e fluxos no MySQL, revisar páginas desktop e móvel, documentar instalação, proposta, regras, resultados e roteiro da demonstração.

## Modelo relacional

Todas as tabelas têm `id` BIGINT UNSIGNED PK e `criado_em` DATETIME, exceto quando explicitado no SQL. Campos principais:

- `usuarios(nome,email,senha_hash,perfil,ativo)` com e-mail único e perfil `administrador` ou `funcionario`.
- `clientes(nome,email,telefone,documento,observacoes,ativo)`. E-mail e documento opcionais, documento único quando informado.
- `destinos(cidade,estado,pais,descricao,ativo)`. Estado opcional.
- `pacotes(destino_id,titulo,descricao,duracao_dias,preco_base,ativo)`.
- `viagens(pacote_id,partida,retorno,capacidade,preco,status)`. Datas DATE. Estados `planejada`, `aberta`, `em_andamento`, `concluida`, `cancelada`.
- `reservas(cliente_id,viagem_id,passageiros,preco_unitario,valor_total,status)`. Estados `confirmada` e `cancelada`.
- `pagamentos(reserva_id,valor,metodo,data_pagamento,status)`. `data_pagamento` DATE. Métodos `pix`, `cartao`, `dinheiro`, `transferencia`, estado `recebido`.
- `auditoria(usuario_id,operacao,entidade,registro_id,detalhes)`. ID do usuário aceita NULL para a criação do primeiro administrador. Registros são históricos e não editáveis pela interface.

Dinheiro DECIMAL(12,2), máximo R$ 9.999.999.999,99. Datas do negócio usam America/Sao_Paulo. Funcionário opera catálogo, viagens, reservas, pagamentos e relatórios. Administrador também gerencia usuários e consulta auditoria.

## Contratos dos módulos

- `app/controllers/catalog.php` define `catalog_controller(PDO $db, string $page): void`, para `clientes`, `destinos`, `pacotes`, `usuarios`.
- `app/controllers/operations.php` define `operations_controller(PDO $db, string $page): void`, para `viagens`, `reservas` e `comprovante`.
- `app/controllers/analytics.php` define `analytics_controller(PDO $db, string $page): void`, para `dashboard`, `relatorios` e `auditoria`.
- O controlador central verifica login, perfil e CSRF antes dos módulos. Os módulos escolhem as operações por `a` (GET ou POST), e identificam registros por `id`. Requisições POST bem ou malsucedidas usam mensagem e redirecionamento para a rota conhecida.
- Núcleo oferece `url(string $page, array $params = []): string`, `h(mixed $value): string`, `redirect(string $page, array $params = []): never`, `flash(string $type, string $message): void`, `csrf_field(): string`, `user(): array`, `require_admin(): void`, `render(string $title, callable $content, string $subtitle = ''): void`, `error_page(int $status, string $message): never`, `is_post(): bool`.
- Dados e domínio oferecem `db_all(PDO $db, string $sql, array $params=[]): array`, `db_one(...): ?array`, `db_value(...): mixed`, `db_exec(...): int`, `transaction(PDO $db, callable $work): mixed`, `audit(PDO $db, ?int $userId, string $operation, string $entity, int $recordId, string $details=''): void`. Todos os parâmetros inteiros são ligados como inteiros.
- Entrada e formatação oferecem `field_string(array $data,string $key,int $max,bool $required=true): ?string`, `field_int(array $data,string $key,int $min=1,int $max=2147483647): int`, `field_date(array $data,string $key): string`, `field_choice(array $data,string $key,array $choices): string`, `money_cents(string $value): int`, `decimal(int $cents): string`, `brl(string|int $value): string` (string representa DECIMAL e inteiro representa centavos), `date_br(?string $date): string`, `status_label(string $status): string`, `badge(string $status): string`. Validações lançam DomainException.
- Views compartilham `.panel`, `.table-wrap`, `.toolbar`, `.actions`, `.button` / `.button.secondary` / `.button.danger`, `.form-grid`, `.field`, `.full`, `.empty`, `.muted`, `.badge`, `.pagination`, `.no-print`, `.print-only` e `pagination(string $page,array $filters,int $current,int $total): void`.

## Regras e verificação

Reservas confirmadas consomem capacidade. A viagem deve estar aberta e a partida ainda não pode ter passado. A reserva guarda preço unitário e total históricos. Vagas, alterações de capacidade e cancelamentos são serializados por bloqueio da viagem. Pagamentos e cancelamentos verificam a reserva sob bloqueio. Operações que precisam dos dois bloqueiam primeiro a viagem, depois a reserva. A sessão MySQL da aplicação usa READ COMMITTED para que as somas dentro da transação vejam os dados já confirmados. Pagamento parcial mantém saldo em aberto. Reserva com pagamento recebido fica protegida contra cancelamento. Uma viagem só pode ser cancelada sem reservas confirmadas. A trilha de auditoria é gravada junto com a alteração.

O caso de concorrência será reproduzido com conexões MySQL diferentes tentando reservar a última vaga. Os casos financeiros usarão parcelas exatas e valor excedente de um centavo. Nenhum teste usa o banco já instalado da máquina.
