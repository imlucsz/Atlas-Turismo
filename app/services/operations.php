<?php
declare(strict_types=1);

require_once __DIR__ . '/trips.php';

function operation_create_reservation(PDO $db, int $actor, array $data): int
{
    $client = field_int($data, 'cliente_id');
    $tripId = field_int($data, 'viagem_id');
    $passengers = field_int($data, 'passageiros', 1, 1000);

    return transaction($db, function (PDO $db) use ($actor, $client, $tripId, $passengers): int {
        $trip = operation_lock_trip($db, $tripId);
        if ($trip['status'] !== 'aberta' || $trip['partida'] < date('Y-m-d')) {
            throw new DomainException('A viagem não está disponível para reservas.');
        }
        if (!db_one($db, 'SELECT id FROM clientes WHERE id = ? AND ativo = 1 FOR UPDATE', [$client])) {
            throw new DomainException('Selecione um cliente ativo.');
        }
        if (operation_occupied_seats($db, $tripId) + $passengers > (int) $trip['capacidade']) {
            throw new DomainException('Não há vagas suficientes para essa quantidade de passageiros.');
        }

        $unit = money_cents((string) $trip['preco']);
        $max = 999999999999; // DECIMAL(12,2), em centavos.
        if ($unit < 1 || $unit > intdiv($max, $passengers)) {
            throw new DomainException('O valor total da reserva ultrapassa o limite aceito.');
        }
        db_exec($db, 'INSERT INTO reservas (cliente_id, viagem_id, passageiros, preco_unitario, valor_total)
            VALUES (?, ?, ?, ?, ?)', [$client, $tripId, $passengers, decimal($unit), decimal($unit * $passengers)]);
        $id = (int) $db->lastInsertId();
        audit($db, $actor, 'criar', 'reservas', $id, 'Reserva confirmada');
        return $id;
    });
}

function operation_lock_reservation(PDO $db, int $id): array
{
    $identity = db_one($db, 'SELECT viagem_id FROM reservas WHERE id = ?', [$id]);
    if (!$identity) {
        throw new DomainException('Reserva não encontrada.');
    }
    // A mesma ordem de bloqueio vale para toda mudança na reserva ou na viagem.
    $trip = operation_lock_trip($db, (int) $identity['viagem_id']);
    $reservation = db_one($db, 'SELECT * FROM reservas WHERE id = ? FOR UPDATE', [$id]);
    if (!$reservation || $reservation['status'] !== 'confirmada') {
        throw new DomainException('A reserva não está confirmada.');
    }
    return [$reservation, $trip];
}

function operation_received_cents(PDO $db, int $id): int
{
    $amount = db_value($db, "SELECT COALESCE(SUM(valor),0) FROM pagamentos WHERE reserva_id = ? AND status = 'recebido'", [$id]);
    return money_cents((string) $amount);
}

function operation_cancel_reservation(PDO $db, int $actor, int $id): void
{
    transaction($db, function (PDO $db) use ($actor, $id): void {
        [, $trip] = operation_lock_reservation($db, $id);
        if (!in_array($trip['status'], ['planejada','aberta'], true)) {
            throw new DomainException('Reservas não podem ser canceladas após o início da viagem.');
        }
        if (operation_received_cents($db, $id) > 0) {
            throw new DomainException('A reserva possui pagamentos e não pode ser cancelada sem estorno.');
        }
        db_exec($db, "UPDATE reservas SET status = 'cancelada' WHERE id = ?", [$id]);
        audit($db, $actor, 'cancelar', 'reservas', $id, 'Reserva cancelada');
    });
}

function operation_add_payment(PDO $db, int $actor, int $id, array $data): void
{
    $amount = money_cents(field_string($data, 'valor', 13));
    $method = field_choice($data, 'metodo', ['pix','cartao','dinheiro','transferencia']);
    $date = field_date($data, 'data_pagamento');
    if ($amount < 1 || $date > date('Y-m-d')) {
        throw new DomainException('Informe um valor positivo e uma data já ocorrida.');
    }
    transaction($db, function (PDO $db) use ($actor, $id, $amount, $method, $date): void {
        [$reservation] = operation_lock_reservation($db, $id);
        $balance = money_cents((string) $reservation['valor_total']) - operation_received_cents($db, $id);
        if ($amount > $balance) {
            throw new DomainException('O pagamento ultrapassa o saldo devido.');
        }
        db_exec($db, 'INSERT INTO pagamentos (reserva_id, valor, metodo, data_pagamento) VALUES (?, ?, ?, ?)',
            [$id, decimal($amount), $method, $date]);
        audit($db, $actor, 'registrar_pagamento', 'pagamentos', (int) $db->lastInsertId(), 'Reserva #' . $id);
    });
}
