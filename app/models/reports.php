<?php
declare(strict_types=1);

function analytics_payments_total(): string
{
    return "SELECT reserva_id,SUM(valor) total FROM pagamentos WHERE status='recebido' GROUP BY reserva_id";
}

function analytics_report_query(array $filter): array
{
    $type = $filter['tipo'] ?? 'reservas';
    $where = ['1=1'];
    $params = [];
    $dateColumn = match ($type) { 'receita'=>'pg.data_pagamento', 'ocupacao'=>'v.partida', default=>'r.criado_em' };
    if (!empty($filter['inicio'])) {
        $where[] = "$dateColumn >= :inicio";
        $params['inicio'] = $filter['inicio'];
    }
    if (!empty($filter['fim'])) {
        $where[] = $dateColumn . ' <= :fim';
        $params['fim'] = $dateColumn === 'r.criado_em' ? $filter['fim'] . ' 23:59:59' : $filter['fim'];
    }
    if (!empty($filter['status']) && in_array($type, ['reservas','cliente','ocupacao'], true)) {
        $where[] = ($type === 'ocupacao' ? 'v.status' : 'r.status') . ' = :status';
        $params['status'] = $filter['status'];
    }
    if (!empty($filter['cliente']) && in_array($type, ['reservas','cliente','receita','pendentes'], true)) {
        $where[] = 'r.cliente_id = :cliente';
        $params['cliente'] = (int) $filter['cliente'];
    } elseif ($type === 'cliente') {
        $where[] = '1=0';
    }

    $trip = 'viagens v JOIN pacotes p ON p.id=v.pacote_id JOIN destinos d ON d.id=p.destino_id';
    $booking = "reservas r JOIN clientes c ON c.id=r.cliente_id JOIN $trip ON v.id=r.viagem_id";
    $region = "CONCAT_WS(', ',d.cidade,d.estado,d.pais)";
    $group = '';
    switch ($type) {
        case 'receita':
            $where[] = "pg.status='recebido'";
            $select = "pg.id,r.id reserva_id,pg.data_pagamento data,c.nome cliente,p.titulo pacote,$region destino,pg.valor total,pg.metodo metodo";
            $from = "pagamentos pg JOIN $booking ON r.id=pg.reserva_id";
            $order = 'pg.data_pagamento DESC,pg.id DESC';
            break;
        case 'ocupacao':
            $select = "v.id,v.partida data,p.titulo pacote,$region destino,v.status situacao,
                COALESCE(SUM(r.passageiros),0) quantidade,v.capacidade,
                ROUND(100*COALESCE(SUM(r.passageiros),0)/v.capacidade,1) ocupacao";
            $from = "$trip LEFT JOIN reservas r ON r.viagem_id=v.id AND r.status='confirmada'";
            $group = ' GROUP BY v.id';
            $order = 'v.partida DESC,v.id DESC';
            break;
        case 'populares':
        case 'destinos':
            $where[] = "r.status='confirmada'";
            $byPackage = $type === 'populares';
            $select = ($byPackage ? 'p.id,p.titulo pacote,' : 'd.id,') . "$region destino,COUNT(*) quantidade,SUM(r.passageiros) passageiros";
            $from = $booking;
            $group = $byPackage ? ' GROUP BY p.id,d.id' : ' GROUP BY d.id';
            $order = 'quantidade DESC,id';
            break;
        default:
            $balance = 'r.valor_total-COALESCE(pg.total,0)';
            if ($type === 'pendentes') {
                $where[] = "r.status='confirmada' AND $balance>0";
            }
            $select = "r.id,DATE(r.criado_em) data,c.nome cliente,p.titulo pacote,$region destino,
                r.passageiros quantidade,r.status situacao,r.valor_total total,COALESCE(pg.total,0) recebido,
                CASE WHEN r.status='confirmada' THEN $balance ELSE 0 END saldo,
                CASE WHEN r.status='cancelada' THEN 'cancelada' WHEN COALESCE(pg.total,0)=0 THEN 'pendente'
                     WHEN pg.total<r.valor_total THEN 'parcial' ELSE 'quitado' END financeiro";
            $from = "$booking LEFT JOIN (" . analytics_payments_total() . ") pg ON pg.reserva_id=r.id";
            $order = 'r.criado_em DESC,r.id DESC';
    }
    return ["SELECT $select FROM $from WHERE " . implode(' AND ', $where) . $group, $params, $order];
}

function analytics_report_summary(PDO $db, array $filter): array
{
    [$sql, $params] = analytics_report_query($filter);
    $column = match ($filter['tipo']) { 'receita'=>'total', 'pendentes'=>'saldo', default=>null };
    $total = $column === null ? 'NULL' : "COALESCE(SUM($column),0)";
    return db_one($db, "SELECT COUNT(*) registros,$total valor FROM ($sql) AS resultados", $params);
}

function analytics_report(PDO $db, array $filter, ?int $limit = null, int $offset = 0): array
{
    [$sql, $params, $order] = analytics_report_query($filter);
    $sql .= " ORDER BY $order";
    if ($limit !== null) {
        $sql .= ' LIMIT :report_limit OFFSET :report_offset';
        $params += ['report_limit'=>$limit, 'report_offset'=>$offset];
    }
    return db_all($db, $sql, $params);
}
