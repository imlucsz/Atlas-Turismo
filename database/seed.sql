-- Somente para um banco de demonstração separado e recém-criado.
-- Pessoas, contatos e operações são fictícios. Não cria contas de acesso.
SET NAMES utf8mb4;
SET SESSION time_zone = '-03:00';
START TRANSACTION;

INSERT INTO clientes(nome,email,telefone,documento) VALUES
('Marina Costa','marina@example.invalid','(11) 0000-1010','DEMO-001'),
('André Pires','andre@example.invalid','(11) 0000-2020','DEMO-002'),
('Lúcia Duarte','lucia@example.invalid','(11) 0000-3030','DEMO-003'),
('Bruno Santos','bruno@example.invalid','(11) 0000-4040','DEMO-004'),
('Joana Lima','joana@example.invalid','(11) 0000-5050','DEMO-005');
SELECT id INTO @marina FROM clientes WHERE documento='DEMO-001';
SELECT id INTO @andre FROM clientes WHERE documento='DEMO-002';
SELECT id INTO @lucia FROM clientes WHERE documento='DEMO-003';
SELECT id INTO @bruno FROM clientes WHERE documento='DEMO-004';
SELECT id INTO @joana FROM clientes WHERE documento='DEMO-005';

INSERT INTO destinos(cidade,estado,pais,descricao) VALUES ('Bonito','Mato Grosso do Sul','Brasil','Rios cristalinos, trilhas e cavernas.');
SET @bonito = LAST_INSERT_ID();
INSERT INTO destinos(cidade,estado,pais,descricao) VALUES ('Recife','Pernambuco','Brasil','Passeios culturais e praias no litoral.');
SET @recife = LAST_INSERT_ID();
INSERT INTO destinos(cidade,estado,pais,descricao) VALUES ('Gramado','Rio Grande do Sul','Brasil','Gastronomia e passeios na Serra Gaúcha.');
SET @gramado = LAST_INSERT_ID();
INSERT INTO destinos(cidade,estado,pais,descricao) VALUES ('Foz do Iguaçu','Paraná','Brasil','Cataratas e parques naturais.');
SET @foz = LAST_INSERT_ID();

INSERT INTO pacotes(destino_id,titulo,descricao,duracao_dias,preco_base) VALUES (@bonito,'Águas de Bonito','Hospedagem e flutuação guiada.',5,1290.00);
SET @p_bonito = LAST_INSERT_ID();
INSERT INTO pacotes(destino_id,titulo,descricao,duracao_dias,preco_base) VALUES (@recife,'Costa Pernambucana','Recife, Olinda e passeio ao litoral.',6,1580.00);
SET @p_recife = LAST_INSERT_ID();
INSERT INTO pacotes(destino_id,titulo,descricao,duracao_dias,preco_base) VALUES (@gramado,'Sabores da Serra','Experiências gastronômicas em Gramado.',4,995.00);
SET @p_gramado = LAST_INSERT_ID();
INSERT INTO pacotes(destino_id,titulo,descricao,duracao_dias,preco_base) VALUES (@foz,'Rota das Cataratas','Parque Nacional e passeios em Foz.',4,850.00);
SET @p_foz = LAST_INSERT_ID();

INSERT INTO viagens(pacote_id,partida,retorno,capacidade,preco,status) VALUES
(@p_bonito,CURRENT_DATE + INTERVAL 4 DAY,CURRENT_DATE + INTERVAL 8 DAY,8,1290.00,'aberta');
SET @v_bonito = LAST_INSERT_ID();
INSERT INTO viagens(pacote_id,partida,retorno,capacidade,preco,status) VALUES
(@p_recife,CURRENT_DATE + INTERVAL 10 DAY,CURRENT_DATE + INTERVAL 15 DAY,12,1580.00,'aberta');
SET @v_recife = LAST_INSERT_ID();
INSERT INTO viagens(pacote_id,partida,retorno,capacidade,preco,status) VALUES
(@p_gramado,CURRENT_DATE + INTERVAL 21 DAY,CURRENT_DATE + INTERVAL 24 DAY,10,995.00,'planejada');
INSERT INTO viagens(pacote_id,partida,retorno,capacidade,preco,status) VALUES
(@p_foz,CURRENT_DATE - INTERVAL 10 DAY,CURRENT_DATE - INTERVAL 7 DAY,6,850.00,'concluida');
SET @v_foz = LAST_INSERT_ID();

INSERT INTO reservas(cliente_id,viagem_id,passageiros,preco_unitario,valor_total,criado_em) VALUES
(@marina,@v_bonito,3,1290.00,3870.00,CURRENT_TIMESTAMP - INTERVAL 3 DAY);
SET @r_marina = LAST_INSERT_ID();
INSERT INTO reservas(cliente_id,viagem_id,passageiros,preco_unitario,valor_total,criado_em) VALUES
(@andre,@v_bonito,2,1290.00,2580.00,CURRENT_TIMESTAMP - INTERVAL 2 DAY);
SET @r_andre = LAST_INSERT_ID();
INSERT INTO reservas(cliente_id,viagem_id,passageiros,preco_unitario,valor_total,criado_em) VALUES
(@lucia,@v_recife,4,1580.00,6320.00,CURRENT_TIMESTAMP - INTERVAL 1 DAY);
INSERT INTO reservas(cliente_id,viagem_id,passageiros,preco_unitario,valor_total,status,criado_em) VALUES
(@bruno,@v_recife,1,1580.00,1580.00,'cancelada',CURRENT_TIMESTAMP - INTERVAL 2 DAY);
INSERT INTO reservas(cliente_id,viagem_id,passageiros,preco_unitario,valor_total,criado_em) VALUES
(@joana,@v_foz,2,850.00,1700.00,CURRENT_TIMESTAMP - INTERVAL 18 DAY);
SET @r_joana = LAST_INSERT_ID();

INSERT INTO pagamentos(reserva_id,valor,metodo,data_pagamento,criado_em) VALUES
(@r_marina,1870.00,'pix',CURRENT_DATE - INTERVAL 2 DAY,CURRENT_TIMESTAMP - INTERVAL 2 DAY),
(@r_andre,2580.00,'cartao',CURRENT_DATE - INTERVAL 1 DAY,CURRENT_TIMESTAMP - INTERVAL 1 DAY),
(@r_joana,1700.00,'transferencia',CURRENT_DATE - INTERVAL 15 DAY,CURRENT_TIMESTAMP - INTERVAL 15 DAY);

COMMIT;
