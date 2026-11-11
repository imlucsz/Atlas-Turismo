<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/services/operations.php';

$settings=atlas_config();
if(!preg_match('/\Aatlas_[a-z0-9_]*_test\z/',$settings['name']))throw new RuntimeException('O teste exige um banco atlas_*_test dedicado.');
$db=db_connect($settings);
$checks=0;
function check(bool $condition,string $label):void{global $checks;if(!$condition)throw new RuntimeException('FALHOU: '.$label);$checks++;}
function rejected(callable $work,string $label):void{try{$work();}catch(DomainException){check(true,$label);return;}throw new RuntimeException('FALHOU: deveria rejeitar '.$label);}

if(($argv[1]??'')==='--worker'){
    [,,$actor,$client,$trip]=$argv;
    echo "READY\n";flush();
    try{operation_create_reservation(db_connect(atlas_config()),(int)$actor,['cliente_id'=>(string)$client,'viagem_id'=>(string)$trip,'passageiros'=>'1']);exit(0);}catch(DomainException){exit(2);}
}

$suffix=bin2hex(random_bytes(6));
$stmt=$db->prepare("INSERT INTO usuarios(nome,email,senha_hash,perfil) VALUES('Teste',?,'x','administrador')");$stmt->execute(['ops-'.$suffix.'@example.invalid']);$actor=(int)$db->lastInsertId();
$stmt=$db->prepare("INSERT INTO clientes(nome,ativo) VALUES(?,1)");$stmt->execute(['Cliente A '.$suffix]);$clientA=(int)$db->lastInsertId();$stmt->execute(['Cliente B '.$suffix]);$clientB=(int)$db->lastInsertId();
$stmt=$db->prepare("INSERT INTO destinos(cidade,pais) VALUES(?, 'Brasil')");$stmt->execute(['Recife '.$suffix]);$dest=(int)$db->lastInsertId();
$stmt=$db->prepare("INSERT INTO pacotes(destino_id,titulo,duracao_dias,preco_base) VALUES(?,?,3,100.00)");$stmt->execute([$dest,'Praia '.$suffix]);$package=(int)$db->lastInsertId();
$departure=(new DateTimeImmutable('+2 days'))->format('Y-m-d');$return=(new DateTimeImmutable('+5 days'))->format('Y-m-d');
$makeTrip=static function(int $capacity)use($db,$actor,$package,$departure,$return):int{$id=operation_create_trip($db,$actor,['pacote_id'=>(string)$package,'partida'=>$departure,'retorno'=>$return,'capacidade'=>(string)$capacity,'preco'=>'100.00']);operation_transition_trip($db,$actor,$id,'aberta');return $id;};

$trip=$makeTrip(2);
$reservation=operation_create_reservation($db,$actor,['cliente_id'=>(string)$clientA,'viagem_id'=>(string)$trip,'passageiros'=>'1']);
check(db_value($db,'SELECT preco_unitario FROM reservas WHERE id=:id',['id'=>$reservation])==='100.00','preço histórico preservado');
rejected(fn()=>operation_create_trip($db,$actor,['pacote_id'=>$package,'partida'=>$return,'retorno'=>$departure,'capacidade'=>2,'preco'=>'100.00']),'retorno anterior à partida');
$unpaid=operation_create_reservation($db,$actor,['cliente_id'=>(string)$clientB,'viagem_id'=>(string)$trip,'passageiros'=>'1']);
rejected(fn()=>operation_create_reservation($db,$actor,['cliente_id'=>(string)$clientB,'viagem_id'=>(string)$trip,'passageiros'=>'1']),'capacidade máxima');
rejected(fn()=>operation_update_trip($db,$actor,$trip,['pacote_id'=>$package,'partida'=>$departure,'retorno'=>$return,'capacidade'=>1,'preco'=>'100.00']),'edição abaixo das duas vagas ocupadas');
rejected(fn()=>operation_transition_trip($db,$actor,$trip,'cancelada'),'cancelamento de viagem com reservas confirmadas');
rejected(fn()=>operation_transition_trip($db,$actor,$trip,'concluida'),'transição inválida');
operation_add_payment($db,$actor,$reservation,['valor'=>'40.00','metodo'=>'pix','data_pagamento'=>date('Y-m-d')]);
rejected(fn()=>operation_add_payment($db,$actor,$reservation,['valor'=>'60.01','metodo'=>'pix','data_pagamento'=>date('Y-m-d')]),'sobrepagamento de um centavo');
operation_add_payment($db,$actor,$reservation,['valor'=>'60.00','metodo'=>'cartao','data_pagamento'=>date('Y-m-d')]);
check(money_cents((string)db_value($db,'SELECT SUM(valor) FROM pagamentos WHERE reserva_id=:id',['id'=>$reservation]))===10000,'pagamentos parcial e integral');
rejected(fn()=>operation_cancel_reservation($db,$actor,$reservation),'cancelamento com pagamento');
operation_cancel_reservation($db,$actor,$unpaid);
$replacement=operation_create_reservation($db,$actor,['cliente_id'=>(string)$clientB,'viagem_id'=>(string)$trip,'passageiros'=>'1']);
check($replacement>0,'cancelamento libera vaga');

$race=$makeTrip(2);operation_create_reservation($db,$actor,['cliente_id'=>(string)$clientB,'viagem_id'=>(string)$race,'passageiros'=>'1']);$php=PHP_BINARY;$ini=php_ini_loaded_file();if(!$ini)throw new RuntimeException('PHP CLI precisa carregar o php.ini do runtime.');
$command=[$php,'-c',$ini,__FILE__,'--worker',(string)$actor,(string)$clientA,(string)$race];$pipesA=[];$pipesB=[];
$processA=proc_open($command,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipesA);
$processB=proc_open($command,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipesB);
if(!is_resource($processA)||!is_resource($processB))throw new RuntimeException('Falha ao iniciar os processos concorrentes.');
fclose($pipesA[0]);fclose($pipesB[0]);
$db->beginTransaction();$db->query('SELECT id FROM viagens WHERE id='.(int)$race.' FOR UPDATE')->fetch();
$readyA=fgets($pipesA[1]);$readyB=fgets($pipesB[1]);
if($readyA!=="READY\n"||$readyB!=="READY\n")throw new RuntimeException('Processos concorrentes não iniciaram corretamente.');
$db->commit();
$outA=stream_get_contents($pipesA[1]);$outB=stream_get_contents($pipesB[1]);$errA=stream_get_contents($pipesA[2]);$errB=stream_get_contents($pipesB[2]);
fclose($pipesA[1]);fclose($pipesA[2]);fclose($pipesB[1]);fclose($pipesB[2]);$codeA=proc_close($processA);$codeB=proc_close($processB);
check(($codeA===0&&$codeB===2)||($codeA===2&&$codeB===0),'uma conexão concorrente vence e a outra é rejeitada');
check((int)db_value($db,"SELECT SUM(passageiros) FROM reservas WHERE viagem_id=:id AND status='confirmada'",['id'=>$race])===2,'disputa pela última vaga não causa excesso');
if($errA!==''||$errB!=='')throw new RuntimeException('Falha inesperada nos processos concorrentes: '.$errA.$errB.$outA.$outB);
echo "$checks verificações de operações passaram.\n";
