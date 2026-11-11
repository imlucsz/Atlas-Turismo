<?php
declare(strict_types=1);

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function field_title(string $key): string
{
    $labels = [
        'id' => 'Identificador', 'destino_id' => 'Destino', 'pacote_id' => 'Pacote',
        'viagem_id' => 'Viagem', 'cliente_id' => 'Cliente', 'reserva_id' => 'Reserva',
        'email' => 'E-mail', 'data_pagamento' => 'Data do pagamento',
        'preco' => 'Preço', 'preco_base' => 'Preço base', 'duracao_dias' => 'Duração',
        'descricao' => 'Descrição', 'observacoes' => 'Observações', 'metodo' => 'Método',
    ];
    return $labels[$key] ?? ucfirst(str_replace('_', ' ', $key));
}

function field_string(array $data, string $key, int $max, bool $required = true): ?string
{
    $value = $data[$key] ?? '';
    $label = field_title($key);
    if (!is_string($value) || !preg_match('//u', $value)) {
        throw new DomainException("$label: informe um texto válido.");
    }
    $value = trim($value);
    if ($value === '') {
        if ($required) {
            throw new DomainException("$label: preencha este campo.");
        }
        return null;
    }
    if (mb_strlen($value, 'UTF-8') > $max) {
        throw new DomainException("$label: use até $max caracteres.");
    }
    return $value;
}

function field_int(array $data, string $key, int $min = 1, int $max = 2147483647): int
{
    $value = $data[$key] ?? null;
    if (is_int($value)) {
        $number = $value;
    } elseif (is_string($value) && preg_match('/\A[0-9]{1,19}\z/', $value)) {
        $number = filter_var($value, FILTER_VALIDATE_INT);
    } else {
        $number = false;
    }
    if ($number === false || $number < $min || $number > $max) {
        throw new DomainException(field_title($key) . ": informe um número inteiro entre $min e $max.");
    }
    return $number;
}

function field_date(array $data, string $key): string
{
    $value = field_string($data, $key, 10);
    if (!preg_match('/\A([1-9][0-9]{3})-([0-9]{2})-([0-9]{2})\z/', $value, $parts)
        || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
        throw new DomainException(field_title($key) . ': informe uma data válida.');
    }
    return $value;
}

function field_choice(array $data, string $key, array $choices): string
{
    $value = $data[$key] ?? null;
    if (!is_string($value) || !in_array($value, $choices, true)) {
        throw new DomainException(field_title($key) . ': selecione uma opção válida.');
    }
    return $value;
}

function money_cents(string $value): int
{
    if (!preg_match('/\A([0-9]{1,10})(?:[,.]([0-9]{1,2}))?\z/', trim($value), $parts)) {
        throw new DomainException('Informe um valor em reais com até duas casas decimais, sem separador de milhar.');
    }
    return ((int) $parts[1] * 100) + (int) str_pad($parts[2] ?? '', 2, '0');
}

function decimal(int $cents): string
{
    return ($cents < 0 ? '-' : '') . abs(intdiv($cents, 100))
        . '.' . str_pad((string) abs($cents % 100), 2, '0', STR_PAD_LEFT);
}

function brl(string|int $value): string
{
    $number = is_int($value) ? decimal($value) : $value;
    if (!preg_match('/\A(-?)([0-9]+)(?:[.,]([0-9]{1,2}))?\z/', $number, $parts)) {
        throw new InvalidArgumentException('Valor monetário inválido para exibição.');
    }
    $grouped = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $parts[2]);
    return 'R$ ' . $parts[1] . $grouped . ',' . str_pad($parts[3] ?? '', 2, '0');
}

function date_br(?string $date): string
{
    if (!$date || !preg_match('/\A(\d{4})-(\d{2})-(\d{2})(?: (\d{2}:\d{2}):\d{2})?\z/', $date, $parts)) {
        return '—';
    }
    return $parts[3] . '/' . $parts[2] . '/' . $parts[1] . (isset($parts[4]) ? ' às ' . $parts[4] : '');
}

function status_label(string $status): string
{
    return match ($status) {
        'administrador' => 'Administrador', 'funcionario' => 'Funcionário',
        'ativo', '1' => 'Ativo', 'inativo', '0' => 'Inativo',
        'planejada' => 'Planejada', 'aberta' => 'Aberta', 'em_andamento' => 'Em andamento',
        'concluida' => 'Concluída', 'cancelada' => 'Cancelada', 'confirmada' => 'Confirmada',
        'pendente' => 'Pendente', 'parcial' => 'Parcial', 'quitado' => 'Quitado',
        'recebido' => 'Recebido', 'pix' => 'Pix', 'cartao' => 'Cartão', 'dinheiro' => 'Dinheiro',
        'transferencia' => 'Transferência', default => ucfirst(str_replace('_', ' ', $status)),
    };
}

function badge(string $status): string
{
    $variant = match ($status) {
        '1', 'ativo', 'aberta', 'confirmada', 'quitado', 'recebido' => 'success',
        'cancelada', '0', 'inativo' => 'danger',
        'pendente', 'parcial' => 'warning',
        default => 'neutral',
    };
    return '<span class="badge badge--' . $variant . '">' . h(status_label($status)) . '</span>';
}
