<?php
declare(strict_types=1);

function operation_trip_source(): string
{
    return ' FROM viagens v JOIN pacotes p ON p.id=v.pacote_id JOIN destinos d ON d.id=p.destino_id';
}

function operation_trip_columns(): string
{
    return "v.*,p.titulo pacote,d.cidade,d.estado,d.pais,
        (SELECT COALESCE(SUM(r.passageiros),0) FROM reservas r WHERE r.viagem_id=v.id AND r.status='confirmada') ocupacao";
}

function operation_trips(PDO $db, array $filters, int $page, int $perPage = 12): array
{
    $where = ['1=1'];
    $params = [];
    if (($filters['q'] ?? '') !== '') {
        $where[] = '(p.titulo LIKE ? OR d.cidade LIKE ? OR d.pais LIKE ?)';
        $params = array_fill(0, 3, '%' . $filters['q'] . '%');
    }
    if (($filters['status'] ?? '') !== '') {
        $where[] = 'v.status = ?';
        $params[] = $filters['status'];
    }
    $query = operation_trip_source() . ' WHERE ' . implode(' AND ', $where);
    $total = (int) db_value($db, 'SELECT COUNT(*)' . $query, $params);
    $rows = db_all($db, 'SELECT ' . operation_trip_columns() . $query . ' ORDER BY v.partida DESC,v.id DESC LIMIT ? OFFSET ?',
        [...$params, $perPage, ($page - 1) * $perPage]);
    return ['rows'=>$rows, 'total'=>$total];
}

function operation_trip(PDO $db, int $id): ?array
{
    return db_one($db, 'SELECT ' . operation_trip_columns() . operation_trip_source() . ' WHERE v.id = ?', [$id]);
}

function operation_selects(PDO $db): array
{
    return [
        'packages' => db_all($db, 'SELECT p.id,p.titulo,p.preco_base,d.cidade,d.pais FROM pacotes p
            JOIN destinos d ON d.id=p.destino_id WHERE p.ativo=1 AND d.ativo=1 ORDER BY p.titulo,p.id'),
        'clients' => db_all($db, 'SELECT id,nome,email FROM clientes WHERE ativo=1 ORDER BY nome,id'),
    ];
}

function operation_reservation_source(): string
{
    return ' FROM reservas r JOIN clientes c ON c.id=r.cliente_id JOIN viagens v ON v.id=r.viagem_id
        JOIN pacotes p ON p.id=v.pacote_id JOIN destinos d ON d.id=p.destino_id';
}

function operation_reservation_columns(): string
{
    return "r.*,c.nome cliente,c.email,c.telefone,v.partida,v.retorno,v.status viagem_status,v.capacidade,
        p.titulo pacote,d.cidade,d.pais,
        (SELECT COALESCE(SUM(pg.valor),0) FROM pagamentos pg WHERE pg.reserva_id=r.id AND pg.status='recebido') pago";
}

function operation_reservations(PDO $db, array $filters, int $page, int $perPage = 12): array
{
    $where = ['1=1'];
    $params = [];
    if (($filters['q'] ?? '') !== '') {
        $where[] = '(c.nome LIKE ? OR p.titulo LIKE ? OR CAST(r.id AS CHAR) = ?)';
        $term = '%' . $filters['q'] . '%';
        $params = [$term,$term,$filters['q']];
    }
    if (($filters['status'] ?? '') !== '') {
        $where[] = 'r.status = ?';
        $params[] = $filters['status'];
    }
    $query = operation_reservation_source() . ' WHERE ' . implode(' AND ', $where);
    $total = (int) db_value($db, 'SELECT COUNT(*)' . $query, $params);
    $rows = db_all($db, 'SELECT ' . operation_reservation_columns() . $query . ' ORDER BY r.id DESC LIMIT ? OFFSET ?',
        [...$params, $perPage, ($page - 1) * $perPage]);
    return ['rows'=>$rows, 'total'=>$total];
}

function operation_reservation(PDO $db, int $id): ?array
{
    return db_one($db, 'SELECT ' . operation_reservation_columns() . operation_reservation_source() . ' WHERE r.id = ?', [$id]);
}

function operation_payments(PDO $db, int $id): array
{
    return db_all($db, 'SELECT * FROM pagamentos WHERE reserva_id = ? ORDER BY data_pagamento DESC,id DESC', [$id]);
}
