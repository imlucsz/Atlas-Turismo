<?php
declare(strict_types=1);

$file = __DIR__ . '/../app/core/validation.php';
if (!is_file($file)) {
    fwrite(STDERR, "FALHA: validação da aplicação ainda não implementada.\n");
    exit(1);
}
require $file;

$checks = 0;
function expect_same(mixed $expected, mixed $actual): void
{
    global $checks;
    if ($expected !== $actual) {
        throw new RuntimeException('Esperado ' . var_export($expected, true) . ', obtido ' . var_export($actual, true));
    }
    $checks++;
}
function expect_rejection(callable $validate): void
{
    global $checks;
    try {
        $validate();
    } catch (DomainException) {
        $checks++;
        return;
    }
    throw new RuntimeException('Entrada inválida foi aceita.');
}

expect_same(101, money_cents('1,01'));
expect_same(1, money_cents('0.01'));
expect_same(999999999999, money_cents('9999999999.99'));
expect_same('0.30', decimal(money_cents('0.10') + money_cents('0.20')));
expect_same('R$ 1.234.567.890.123,45', brl('1234567890123.45'));
expect_rejection(fn() => money_cents('10000000000.00'));
expect_rejection(fn() => money_cents('0.009'));
expect_rejection(fn() => money_cents('-0.01'));
expect_rejection(fn() => money_cents('1e3'));
expect_same('2028-02-29', field_date(['partida' => '2028-02-29'], 'partida'));
expect_rejection(fn() => field_date(['partida' => '2026-02-29'], 'partida'));
expect_rejection(fn() => field_date(['partida' => '2026-11-11abc'], 'partida'));
expect_rejection(fn() => field_int(['id' => ['1']], 'id'));
expect_rejection(fn() => field_int(['id' => '9223372036854775808'], 'id'));
expect_rejection(fn() => field_int(['passageiros' => '0'], 'passageiros'));
expect_rejection(fn() => field_choice(['perfil' => 'root'], 'perfil', ['administrador','funcionario']));
expect_same(null, field_string(['estado' => '  '], 'estado', 100, false));
expect_same('Água', field_string(['nome' => ' Água '], 'nome', 4));
expect_rejection(fn() => field_string(['nome' => "\xFF"], 'nome', 100));
expect_same('&lt;script&gt;&quot;', h('<script>"'));
echo "$checks verificações de validação e valores exatos passaram.\n";
