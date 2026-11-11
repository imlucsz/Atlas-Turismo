<?php
declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');
$password = getenv('ATLAS_TEST_ADMIN_PASSWORD');
if (!$password) {
    fwrite(STDERR, "Defina ATLAS_TEST_ADMIN_PASSWORD com a senha da conta admin@atlas.example no servidor de teste em 127.0.0.1:8033.\n");
    exit(1);
}

final class Browser
{
    private CurlHandle $curl;
    public string $body = '';
    public string $location = '';
    public int $status = 0;

    public function __construct()
    {
        $this->curl = curl_init();
        curl_setopt_array($this->curl, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => '',
            CURLOPT_TIMEOUT => 8, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADERFUNCTION => function ($curl, string $line): int {
                if (stripos($line, 'Location:') === 0) {
                    $this->location = trim(substr($line, 9));
                }
                return strlen($line);
            },
        ]);
    }

    public function request(string $route, ?array $fields = null): self
    {
        $this->location = '';
        curl_setopt($this->curl, CURLOPT_URL, 'http://127.0.0.1:8033/index.php?' . $route);
        if ($fields === null) {
            curl_setopt($this->curl, CURLOPT_HTTPGET, true);
        } else {
            curl_setopt($this->curl, CURLOPT_POST, true);
            curl_setopt($this->curl, CURLOPT_POSTFIELDS, http_build_query($fields));
        }
        $body = curl_exec($this->curl);
        if ($body === false) {
            throw new RuntimeException(curl_error($this->curl));
        }
        $this->body = $body;
        $this->status = curl_getinfo($this->curl, CURLINFO_RESPONSE_CODE);
        return $this;
    }

    public function csrf(): string
    {
        if (!preg_match('/name="csrf" value="([a-f0-9]{64})"/', $this->body, $matches)) {
            throw new RuntimeException('Formulário sem token CSRF.');
        }
        return $matches[1];
    }
}

$checks = 0;
function verify(bool $condition, string $label): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException("FALHA HTTP: $label");
    }
    $checks++;
}

function follow(Browser $browser): Browser
{
    verify($browser->status === 303 && str_starts_with($browser->location, 'index.php?'), 'redirecionamento 303');
    return $browser->request(substr($browser->location, 10));
}

function create_record(Browser $browser, string $page, array $input): int
{
    $route = "p=$page&a=novo";
    $browser->request($route);
    verify($browser->status === 200, "formulário $page");
    $browser->request($route, $input + ['csrf' => $browser->csrf()]);
    verify($browser->status === 303 && preg_match('/[&]id=(\d+)$/', $browser->location, $m) === 1
        && str_contains($browser->location, 'a=ver'), "criação $page");
    verify(follow($browser)->status === 200, "detalhe $page");
    return (int) $m[1];
}

$b = new Browser();
verify($b->request('p=reservas')->location === 'index.php?p=login', 'proteção anônima');
verify($b->request('p=login', ['email'=>'admin@atlas.example','senha'=>$password])->status === 403, 'CSRF no login');
$b->request('p=login');
$b->request('p=login', ['email'=>'admin@atlas.example','senha'=>'Incorreta#123','csrf'=>$b->csrf()]);
verify(str_contains(follow($b)->body, 'inválidos'), 'senha inválida');
$b->request('p=login', ['email'=>'admin@atlas.example','senha'=>$password,'csrf'=>$b->csrf()]);
verify(str_contains(follow($b)->body, 'Visão geral'), 'login e painel');

$tag = bin2hex(random_bytes(4));
$person = ['nome'=>'Viajante ' . $tag,'email'=>"$tag@example.invalid",'telefone'=>'1100001234','documento'=>'HTTP-' . $tag];
$customer = create_record($b, 'clientes', $person);
$destination = create_record($b, 'destinos', ['cidade'=>'Itaquera ' . $tag,'estado'=>'São Paulo','pais'=>'Brasil','descricao'=>'Teste de destino']);
$package = create_record($b, 'pacotes', ['destino_id'=>$destination,'titulo'=>'Passeio ' . $tag,'duracao_dias'=>'3','preco_base'=>'125.50']);
$employeePassword = 'Teste-' . bin2hex(random_bytes(9));
create_record($b, 'usuarios', ['nome'=>'Atendente ' . $tag,'email'=>"atendente-$tag@example.invalid",'perfil'=>'funcionario','senha'=>$employeePassword]);

$edit = "p=clientes&a=editar&id=$customer";
$b->request($edit);
$b->request($edit, ['id'=>$customer,'csrf'=>$b->csrf(),'nome'=>'Viajante atualizado ' . $tag] + $person);
verify(str_contains(follow($b)->body, 'Viajante atualizado ' . $tag), 'edição do cliente');
verify(str_contains($b->request("p=clientes&q=$tag&ativo=1")->body, $tag), 'busca e filtro de clientes ativos');
$b->request("p=clientes&a=status&id=$customer", ['ativo'=>0,'csrf'=>$b->csrf()]);
follow($b);
verify(str_contains($b->request("p=clientes&q=$tag&ativo=0")->body, $tag), 'inativação de cliente');
$b->request("p=clientes&a=status&id=$customer", ['ativo'=>1,'csrf'=>$b->csrf()]);
follow($b);

$trip = create_record($b, 'viagens', ['pacote_id'=>$package,'partida'=>date('Y-m-d', strtotime('+4 days')),
    'retorno'=>date('Y-m-d', strtotime('+6 days')),'capacidade'=>2,'preco'=>'125.50']);
$alternate = create_record($b, 'destinos', ['cidade'=>'Destino alternativo ' . $tag,'pais'=>'Brasil']);
$b->request("p=pacotes&a=editar&id=$package");
$b->request("p=pacotes&a=editar&id=$package", ['id'=>$package,'destino_id'=>$alternate,'titulo'=>'Passeio ' . $tag,
    'duracao_dias'=>3,'preco_base'=>'125.50','csrf'=>$b->csrf()]);
verify(str_contains(follow($b)->body, 'deve manter o destino'), 'roteiro de pacote vinculado preservado');
$b->request("p=viagens&a=ver&id=$trip");
$b->request("p=viagens&a=status&id=$trip", ['status'=>'aberta','csrf'=>$b->csrf()]);
verify(str_contains(follow($b)->body, 'Nova reserva'), 'abrir viagem para reservas');
verify(str_contains($b->request("p=viagens&q=$tag")->body, 'Passeio ' . $tag), 'pesquisa de viagens');
$booking = create_record($b, 'reservas', ['cliente_id'=>$customer,'viagem_id'=>$trip,'passageiros'=>2]);
verify(str_contains($b->body, '251,00'), 'valor da reserva em centavos');
verify(str_contains($b->request("p=reservas&q=$tag")->body, $tag), 'pesquisa de reservas');
$b->request('p=reservas&a=novo', ['cliente_id'=>$customer,'viagem_id'=>$trip,'passageiros'=>1,'csrf'=>$b->csrf()]);
verify(str_contains(follow($b)->body, 'vagas suficientes'), 'bloqueio de viagem lotada');
$b->request("p=reservas&a=ver&id=$booking");
$payment = ['metodo'=>'pix','data_pagamento'=>date('Y-m-d')];
$b->request("p=reservas&a=pagamento&id=$booking", $payment + ['valor'=>'100.00','csrf'=>$b->csrf()]);
verify(str_contains(follow($b)->body, 'Parcial'), 'pagamento parcial');
$b->request("p=reservas&a=pagamento&id=$booking", $payment + ['valor'=>'151.01','csrf'=>$b->csrf()]);
verify(str_contains(follow($b)->body, 'ultrapassa'), 'recusa sobrepagamento');
$b->request("p=reservas&a=pagamento&id=$booking", $payment + ['valor'=>'151.00','csrf'=>$b->csrf()]);
verify(str_contains(follow($b)->body, 'Quitado'), 'quitação do saldo');
$b->request("p=reservas&a=cancelar&id=$booking", ['csrf'=>$b->csrf()]);
verify(str_contains(follow($b)->body, 'estorno'), 'cancelamento pago protegido');
verify(str_contains($b->request("p=comprovante&id=$booking")->body, 'Comprovante de reserva'), 'comprovante');
verify(str_contains($b->request('p=dashboard')->body, 'Visão geral'), 'painel com dados');
verify(str_contains($b->request('p=auditoria')->body, 'Auditoria'), 'auditoria para admin');
verify($b->request('p=auditoria&q=reservas')->status === 200 && str_contains($b->body, 'reservas #'), 'busca na auditoria');
verify(str_contains($b->request('p=relatorios&tipo=reservas&cliente=' . $customer)->body, 'Viajante atualizado ' . $tag), 'relatório por cliente');
foreach (['receita','pendentes','ocupacao','populares','destinos','cliente&cliente=' . $customer] as $type) {
    verify($b->request('p=relatorios&tipo=' . $type)->status === 200, 'abertura de relatório ' . $type);
}
verify($b->request('p=relatorios&inicio=2026-02-30')->status === 422, 'data inválida de relatório');
verify(str_starts_with($b->request('p=relatorios&tipo=reservas&formato=csv&cliente=' . $customer)->body, "\xEF\xBB\xBF")
    && str_contains($b->body, $tag), 'CSV real filtrado com UTF-8');

$b->request('p=dashboard');
$b->request('p=logout', ['csrf'=>$b->csrf()]);
verify(follow($b)->status === 200 && $b->request('p=dashboard')->location === 'index.php?p=login', 'logout encerra sessão');
$b->request('p=login');
$b->request('p=login', ['email'=>"atendente-$tag@example.invalid",'senha'=>$employeePassword,'csrf'=>$b->csrf()]);
verify(follow($b)->status === 200, 'login funcionário');
verify($b->request('p=usuarios')->status === 403 && $b->request('p=auditoria')->status === 403, 'permissões de funcionário');
verify($b->request('p=reservas')->status === 200, 'funcionário pode operar reservas');

echo "$checks verificações HTTP passaram.\n";
