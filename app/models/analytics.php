<?php
declare(strict_types=1);

require_once __DIR__ . '/reports.php';

function analytics_dashboard(PDO $db): array
{
    return [
        'clientes' => db_value($db, 'SELECT COUNT(*) FROM clientes'),
        'viagens' => db_value($db, 'SELECT COUNT(*) FROM viagens'),
        'reservas' => db_value($db, "SELECT COUNT(*) FROM reservas WHERE status='confirmada'"),
        'receita' => db_value($db, "SELECT COALESCE(SUM(valor),0) FROM pagamentos WHERE status='recebido'"),
        'pendente' => db_value($db, "SELECT COALESCE(SUM(r.valor_total-COALESCE(pg.total,0)),0)
            FROM reservas r LEFT JOIN (" . analytics_payments_total() . ") pg ON pg.reserva_id=r.id WHERE r.status='confirmada'"),
        'ocupacao' => db_value($db, "SELECT COALESCE(ROUND(100*SUM(ocupadas)/NULLIF(SUM(capacidade),0),0),0)
            FROM (SELECT v.id,v.capacidade,COALESCE(SUM(r.passageiros),0) ocupadas FROM viagens v
            LEFT JOIN reservas r ON r.viagem_id=v.id AND r.status='confirmada'
            WHERE v.status IN ('planejada','aberta','em_andamento') GROUP BY v.id) t"),
        'proximas' => db_all($db, "SELECT v.id,v.partida,v.capacidade,p.titulo,d.cidade,COALESCE(SUM(r.passageiros),0) ocupadas
            FROM viagens v JOIN pacotes p ON p.id=v.pacote_id JOIN destinos d ON d.id=p.destino_id
            LEFT JOIN reservas r ON r.viagem_id=v.id AND r.status='confirmada'
            WHERE v.status IN ('planejada','aberta') AND v.partida>=CURRENT_DATE GROUP BY v.id ORDER BY v.partida,v.id LIMIT 5"),
        'recentes' => db_all($db, 'SELECT r.id,r.valor_total,r.status,c.nome cliente,p.titulo pacote,d.cidade
            FROM reservas r JOIN clientes c ON c.id=r.cliente_id JOIN viagens v ON v.id=r.viagem_id
            JOIN pacotes p ON p.id=v.pacote_id JOIN destinos d ON d.id=p.destino_id ORDER BY r.criado_em DESC,r.id DESC LIMIT 8'),
        'populares' => db_all($db, "SELECT p.titulo,d.cidade,COUNT(*) total FROM reservas r JOIN viagens v ON v.id=r.viagem_id
            JOIN pacotes p ON p.id=v.pacote_id JOIN destinos d ON d.id=p.destino_id
            WHERE r.status='confirmada' GROUP BY p.id ORDER BY total DESC,p.id LIMIT 5"),
        'destinos' => db_all($db, "SELECT d.cidade,d.pais,COUNT(*) total FROM reservas r JOIN viagens v ON v.id=r.viagem_id
            JOIN pacotes p ON p.id=v.pacote_id JOIN destinos d ON d.id=p.destino_id
            WHERE r.status='confirmada' GROUP BY d.id ORDER BY total DESC,d.id LIMIT 5"),
        'alertas' => db_all($db, "SELECT 'Viagem próxima' tipo,CONCAT(p.titulo,' · ',d.cidade) descricao,v.partida data,'viagens' pagina,v.id
            FROM viagens v JOIN pacotes p ON p.id=v.pacote_id JOIN destinos d ON d.id=p.destino_id
            WHERE v.status IN ('planejada','aberta') AND v.partida BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE,INTERVAL 14 DAY)
            UNION ALL SELECT 'Pagamento pendente',CONCAT(c.nome,' · reserva #',r.id),v.partida,'reservas',r.id
            FROM reservas r JOIN clientes c ON c.id=r.cliente_id JOIN viagens v ON v.id=r.viagem_id
            LEFT JOIN (" . analytics_payments_total() . ") pg ON pg.reserva_id=r.id
            WHERE r.status='confirmada' AND r.valor_total>COALESCE(pg.total,0) ORDER BY data,id LIMIT 8"),
    ];
}

function analytics_audit_rows(PDO $db, string $search, int $page): array
{
    $base = ' FROM auditoria a LEFT JOIN usuarios u ON u.id=a.usuario_id';
    $params = [];
    if ($search !== '') {
        $base .= ' WHERE a.entidade LIKE ? OR a.operacao LIKE ? OR u.nome LIKE ? OR a.detalhes LIKE ?';
        $params = array_fill(0, 4, '%' . $search . '%');
    }
    $total = (int) db_value($db, 'SELECT COUNT(*)' . $base, $params);
    $last = max(1, (int) ceil($total / 30));
    $page = min($page, $last);
    $rows = db_all($db, 'SELECT a.*,u.nome usuario' . $base . ' ORDER BY a.criado_em DESC,a.id DESC LIMIT 30 OFFSET ?',
        [...$params, ($page - 1) * 30]);
    return ['rows'=>$rows, 'total'=>$total, 'page'=>$page, 'last'=>$last];
}
