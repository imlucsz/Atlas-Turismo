# Proposta P2 · Atlas Turismo

**Disciplina** Desenvolvimento Web II  
**Instituição** FATEC Itaquera – Faculdade de Tecnologia de Itaquera “Prof. Miguel Reale”  
**Apresentação da proposta ao docente** até 18/11/2026

## Problema e objetivo

Uma agência de viagens que depende de planilhas separadas precisa reconciliar dados de clientes, reservas e pagamentos para saber quantas vagas ainda estão disponíveis ou quanto falta receber. Isso dificulta confirmar reservas com segurança, acompanhar partidas e produzir relatórios confiáveis.

O Atlas Turismo centraliza essas operações em um sistema web. A equipe registra os dados em um banco relacional e consulta a mesma informação em cadastros, no painel de gestão e nos relatórios.

## Módulos e demonstração

| Módulo | O que a equipe consegue fazer |
|---|---|
| Cadastros | Gerenciar clientes, destinos e pacotes, incluindo consulta, edição, busca e inativação. |
| Viagens | Planejar partidas e retornos, informar preço e capacidade, abrir reservas e acompanhar a situação operacional. |
| Reservas | Confirmar vagas para clientes, consultar o histórico, cancelar conforme as regras e emitir comprovante. |
| Pagamentos | Registrar recebimentos integrais ou parcelados e verificar o saldo de cada reserva. |
| Gestão | Consultar indicadores, avisos, relatórios por período, exportações CSV e registros de auditoria. |
| Administração | Criar contas, atribuir perfis e controlar o acesso de funcionários. |

## Diferenciais

- O sistema bloqueia a viagem durante a confirmação de uma reserva para que dois atendentes não consigam vender a mesma última vaga.
- Cada reserva preserva o preço contratado. Pagamentos parciais atualizam o saldo calculado e pagamentos acima do devido são recusados.
- A interface mostra partidas próximas e saldos pendentes. O comprovante e a página do relatório têm estilos de impressão.
- As operações relevantes registram usuário, ação, entidade e data na trilha de auditoria.

## Arquitetura e tecnologia

PHP 8.2+ processa formulários, aplica as regras e renderiza HTML. O CSS organiza a interface para desktop e celular. MySQL 8+ com InnoDB mantém os relacionamentos e executa transações. A aplicação separa controladores, modelos, serviços e templates. Usa PDO com parâmetros, validação no servidor, sessões autenticadas, proteção CSRF e autorização por perfil.

O roteiro apresenta um cadastro, uma viagem, uma reserva, dois recebimentos e o comprovante. Em seguida, mostra o efeito no painel e em um relatório exportável. O banco de demonstração contém somente informações fictícias.
