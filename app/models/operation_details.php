<?php
declare(strict_types=1);

function open_trip_options(PDO $db): array
{
    return db_all($db, "SELECT v.id, v.partida, v.preco, p.titulo, d.cidade,
        v.capacidade - COALESCE((SELECT SUM(r.passageiros) FROM reservas r WHERE r.viagem_id=v.id AND r.status='confirmada'),0) vagas
        FROM viagens v JOIN pacotes p ON p.id=v.pacote_id JOIN destinos d ON d.id=p.destino_id
        WHERE v.status='aberta' AND v.partida >= ?
        ORDER BY v.partida, p.titulo, v.id", [date('Y-m-d')]);
}

function trip_recent_reservations(PDO $db, int $id): array
{
    return db_all($db, 'SELECT r.id, c.nome, r.passageiros, r.status, r.valor_total FROM reservas r
        JOIN clientes c ON c.id=r.cliente_id WHERE r.viagem_id = ? ORDER BY r.id DESC LIMIT 10', [$id]);
}
