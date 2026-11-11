<?php
declare(strict_types=1);
require __DIR__ . '/../app/core/validation.php';
require __DIR__ . '/../app/models/catalog.php';
$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void { if (!$condition) { fwrite(STDERR, "FAIL: $message\n"); exit(1); } ++$checks; };
$client = catalog_validate('clientes', ['nome' => '  Maria da Silva  ', 'email' => ' MARIA@EXEMPLO.COM ', 'telefone' => '', 'documento' => '123.456-7', 'observacoes' => ''], true);
$assert($client['nome'] === 'Maria da Silva', 'nome aparado');
$assert($client['email'] === 'maria@exemplo.com', 'e-mail normalizado');
$assert($client['documento'] === '1234567', 'documento normalizado');
$assert($client['telefone'] === null, 'campo opcional vazio');
$assert(catalog_validate('pacotes', ['destino_id'=>'3','titulo'=>'Pantanal','duracao_dias'=>'5','preco_base'=>'1250.90','descricao'=>''], true)['preco_base'] === '1250.90', 'preço permanece decimal exato');
foreach ([['email'=>'invalido'], ['duracao_dias'=>'0'], ['preco_base'=>'12.999']] as $bad) {
  try { catalog_validate(isset($bad['email']) ? 'clientes' : 'pacotes', isset($bad['email']) ? ['nome'=>'Ana','email'=>$bad['email']] : $bad + ['destino_id'=>'1','titulo'=>'Teste','descricao'=>''], true); $assert(false, 'entrada inválida rejeitada'); }
  catch (DomainException) { $assert(true, 'entrada inválida rejeitada'); }
}
try { catalog_validate('usuarios', ['nome'=>'Ana','email'=>'ana@example.test','perfil'=>'administrador','id'=>'0'], true); $assert(false, 'senha obrigatória na criação'); }
catch (DomainException) { $assert(true, 'senha obrigatória na criação'); }
try { catalog_user_fields(['nome'=>'Ana','email'=>'ana@example.test','perfil'=>'funcionario','senha'=>'curta'], true); $assert(false, 'senha abaixo do mínimo rejeitada'); }
catch (DomainException) { $assert(true, 'senha abaixo do mínimo rejeitada'); }
try { catalog_user_fields(['nome'=>'Ana','email'=>'ana@example.test','perfil'=>'funcionario','senha'=>['valor']], true); $assert(false, 'senha em formato inválido rejeitada'); }
catch (DomainException) { $assert(true, 'senha em formato inválido rejeitada'); }
try { catalog_price(['preco_base'=>'0.00']); $assert(false, 'preço zero rejeitado'); }
catch (DomainException) { $assert(true, 'preço zero rejeitado'); }
echo "OK: {$checks} verificações de validação do catálogo.\n";
