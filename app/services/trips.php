<?php
declare(strict_types=1);

function operation_trip_fields(array $data): array
{
    $fields = [
        'pacote_id' => field_int($data, 'pacote_id'),
        'partida' => field_date($data, 'partida'),
        'retorno' => field_date($data, 'retorno'),
        'capacidade' => field_int($data, 'capacidade', 1, 100000),
        'preco' => decimal(money_cents(field_string($data, 'preco', 13))),
    ];
    if ($fields['retorno'] < $fields['partida']) {
        throw new DomainException('O retorno não pode ser anterior à partida.');
    }
    if ($fields['preco'] === '0.00') {
        throw new DomainException('O preço da viagem deve ser positivo.');
    }
    return $fields;
}

function operation_lock_trip(PDO $db, int $id): array
{
    $trip = db_one($db, 'SELECT * FROM viagens WHERE id = ? FOR UPDATE', [$id]);
    if (!$trip) {
        throw new DomainException('Viagem não encontrada.');
    }
    return $trip;
}

function operation_occupied_seats(PDO $db, int $id): int
{
    return (int) db_value($db, "SELECT COALESCE(SUM(passageiros),0) FROM reservas WHERE viagem_id = ? AND status = 'confirmada'", [$id]);
}

function operation_require_package(PDO $db, int $id): void
{
    $package = db_one($db, 'SELECT p.id FROM pacotes p JOIN destinos d ON d.id=p.destino_id
        WHERE p.id = ? AND p.ativo = 1 AND d.ativo = 1 FOR UPDATE', [$id]);
    if (!$package) {
        throw new DomainException('Selecione um pacote e destino ativos.');
    }
}

function operation_create_trip(PDO $db, int $actor, array $data): int
{
    $fields = operation_trip_fields($data);
    if ($fields['partida'] < date('Y-m-d')) {
        throw new DomainException('A partida deve ser hoje ou em uma data futura.');
    }
    return transaction($db, function (PDO $db) use ($actor, $fields): int {
        operation_require_package($db, $fields['pacote_id']);
        db_exec($db, 'INSERT INTO viagens (pacote_id, partida, retorno, capacidade, preco)
            VALUES (:pacote_id, :partida, :retorno, :capacidade, :preco)', $fields);
        $id = (int) $db->lastInsertId();
        audit($db, $actor, 'criar', 'viagens', $id, 'Viagem planejada');
        return $id;
    });
}

function operation_update_trip(PDO $db, int $actor, int $id, array $data): void
{
    $fields = operation_trip_fields($data);
    transaction($db, function (PDO $db) use ($actor, $id, $fields): void {
        $trip = operation_lock_trip($db, $id);
        if (!in_array($trip['status'], ['planejada','aberta'], true)) {
            throw new DomainException('Esta viagem não pode ser editada nesta situação.');
        }
        $count = operation_occupied_seats($db, $id);
        if ($fields['capacidade'] < $count) {
            throw new DomainException('A capacidade não pode ficar abaixo das reservas confirmadas.');
        }
        $packageChanged = $fields['pacote_id'] !== (int) $trip['pacote_id'];
        $departureChanged = $fields['partida'] !== $trip['partida'];
        if ($count > 0 && ($packageChanged || $departureChanged || $fields['retorno'] !== $trip['retorno'])) {
            throw new DomainException('O pacote e as datas ficam protegidos após a confirmação de reservas.');
        }
        if ($departureChanged && $fields['partida'] < date('Y-m-d')) {
            throw new DomainException('A nova partida deve ser hoje ou em uma data futura.');
        }
        if ($packageChanged) {
            operation_require_package($db, $fields['pacote_id']);
        }
        db_exec($db, 'UPDATE viagens SET pacote_id=:pacote_id, partida=:partida, retorno=:retorno,
            capacidade=:capacidade, preco=:preco WHERE id=:id', $fields + ['id'=>$id]);
        audit($db, $actor, 'editar', 'viagens', $id, 'Viagem atualizada');
    });
}

function operation_transition_trip(PDO $db, int $actor, int $id, string $next): void
{
    transaction($db, function (PDO $db) use ($actor, $id, $next): void {
        $trip = operation_lock_trip($db, $id);
        $allowed = [
            'planejada'=>['aberta','cancelada'],
            'aberta'=>['em_andamento','cancelada'],
            'em_andamento'=>['concluida'],
        ];
        if (!in_array($next, $allowed[$trip['status']] ?? [], true)) {
            throw new DomainException('Transição de viagem inválida.');
        }
        $today = date('Y-m-d');
        if ($next === 'aberta' && $trip['partida'] < $today) {
            throw new DomainException('Não é possível abrir reservas após a partida.');
        }
        if ($next === 'em_andamento' && $trip['partida'] > $today) {
            throw new DomainException('A viagem só pode começar a partir da data de partida.');
        }
        if ($next === 'concluida' && $trip['retorno'] > $today) {
            throw new DomainException('A viagem só pode ser concluída a partir da data de retorno.');
        }
        if ($next === 'cancelada' && operation_occupied_seats($db, $id) > 0) {
            throw new DomainException('Cancele as reservas confirmadas antes de cancelar a viagem.');
        }
        db_exec($db, 'UPDATE viagens SET status = ? WHERE id = ?', [$next,$id]);
        audit($db, $actor, 'alterar_status', 'viagens', $id, status_label($trip['status']) . ' → ' . status_label($next));
    });
}
