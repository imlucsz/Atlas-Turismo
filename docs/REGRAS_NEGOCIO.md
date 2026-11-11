# Regras de negócio

## Catálogo e permissões

- Administradores e funcionários operam os cadastros, viagens, reservas, pagamentos e relatórios. Administração de usuários e auditoria exigem perfil de administrador.
- Os cadastros podem ser inativados sem apagar os vínculos históricos. Atribuições novas exigem clientes, destinos e pacotes ativos, conforme o fluxo. Um pacote já vinculado a viagens mantém o destino original. Para outro roteiro, crie um novo pacote.
- Um administrador não pode inativar ou rebaixar o próprio acesso. Alterações de conta verificam os administradores ativos sob bloqueio.
- Nenhum registro histórico de viagem, reserva, pagamento ou auditoria é removido pela interface.

## Viagens e capacidade

| Origem | Transições |
|---|---|
| Planejada | Aberta ou cancelada |
| Aberta | Em andamento ou cancelada |
| Em andamento | Concluída |
| Concluída ou cancelada | Estado final |

Retorno deve ser igual ou posterior à partida. A viagem deve ter capacidade e preço positivos. Uma viagem abre reservas até a data da partida, inicia a partir da data de partida e conclui a partir da data de retorno. Cancelar uma viagem exige que ela não tenha reservas confirmadas.

Só viagens abertas com partida hoje ou no futuro recebem reservas. Apenas reservas confirmadas consomem vagas. Uma edição não pode reduzir a capacidade abaixo da ocupação confirmada nem trocar o pacote ou as datas com reservas confirmadas. O preço pode mudar para reservas futuras.

A transação da reserva bloqueia a linha da viagem (`SELECT ... FOR UPDATE`) antes de contar passageiros confirmados. Assim, uma segunda transação aguarda e consulta o total já confirmado. A conexão usa READ COMMITTED. Quando precisa bloquear viagem e reserva, o serviço sempre começa pela viagem.

## Pagamentos e cancelamentos

Valores são armazenados como DECIMAL(12,2). PHP valida e calcula usando centavos inteiros. Cada reserva registra o preço unitário e o total no momento da confirmação. O saldo é o total contratado menos os pagamentos recebidos. Uma reserva confirmada fica pendente com zero recebido, parcial com saldo positivo e quitada com saldo zero.

O pagamento exige reserva confirmada, valor positivo, data válida até hoje e um método aceito. A transação bloqueia a reserva antes de consultar o saldo. Valores acima do saldo são recusados. Cancelamento de reserva exige viagem que ainda não começou e ausência de pagamentos. A aplicação registra recebimentos informados pela equipe. Estorno e integração com meios de pagamento ficam fora do fluxo implementado.

## Indicadores e relatórios

A ocupação geral divide o total de passageiros confirmados pela capacidade das viagens planejadas, abertas ou em andamento. O relatório de ocupação considera a data de partida. Receita considera a data do pagamento. Os demais relatórios usam a data de criação da reserva e aplicam os filtros disponíveis por tipo. Os rankings contam reservas confirmadas e informam a quantidade de passageiros.

O CSV exporta todas as linhas do resultado filtrado. A impressão pelo navegador usa a página visível. Auditoria é gravada na mesma transação da alteração correspondente.
