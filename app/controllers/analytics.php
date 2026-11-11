<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/analytics.php';
require_once __DIR__ . '/../views/analytics.php';

function analytics_controller(PDO $db, string $page): void
{
    if (is_post()) {
        error_page(405, 'Use os filtros da página para consultar os relatórios.');
    }
    $current = isset($_GET['pagina']) ? field_int($_GET, 'pagina', 1, 100000) : 1;
    if ($page === 'auditoria') {
        require_admin();
        $search = field_string($_GET, 'q', 100, false) ?? '';
        $results = analytics_audit_rows($db, $search, $current);
        render('Auditoria', fn() => analytics_view_audit($results, $search), 'Histórico de alterações registradas pelo sistema.');
        return;
    }
    if ($page === 'relatorios') {
        $type = field_choice($_GET + ['tipo'=>'reservas'], 'tipo', array_keys(analytics_report_types()));
        $status = field_choice($_GET + ['status'=>''], 'status', ['', ...analytics_report_statuses($type)]);
        $client = isset($_GET['cliente']) ? field_int($_GET, 'cliente', 0) : 0;
        $filters = ['tipo'=>$type, 'status'=>$status, 'cliente'=>$client,
            'inicio'=>analytics_date($_GET['inicio'] ?? ''), 'fim'=>analytics_date($_GET['fim'] ?? '')];
        if ($filters['inicio'] !== '' && $filters['fim'] !== '' && $filters['inicio'] > $filters['fim']) {
            throw new DomainException('A data inicial não pode ser posterior à data final.');
        }
        if (($_GET['formato'] ?? '') === 'csv') {
            analytics_csv($type, analytics_report($db, $filters));
        }
        $summary = analytics_report_summary($db, $filters);
        $last = max(1, (int) ceil($summary['registros'] / 30));
        $current = min($last, $current);
        $rows = analytics_report($db, $filters, 30, ($current - 1) * 30);
        $clients = analytics_client_filter($type) ? db_all($db, 'SELECT id,nome FROM clientes ORDER BY nome,id') : [];
        render('Relatórios', fn() => analytics_view_report($rows, $filters, $clients, $summary, $current, $last),
            'Consulte os dados da operação por período e exporte os resultados.');
        return;
    }
    $data = analytics_dashboard($db);
    render('Visão geral', fn() => analytics_view_dashboard($data), 'Acompanhe partidas, ocupação e recebimentos.');
}

function analytics_date(mixed $value): string
{
    return $value === '' ? '' : field_date(['data'=>$value], 'data');
}

function analytics_csv_safe(mixed $value): string
{
    $value = (string) $value;
    return preg_match('/\A[\s\x00-\x1F]*[=+@-]/u', $value) ? "'" . $value : $value;
}

function analytics_csv(string $type, array $rows): never
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="atlas-' . $type . '.csv"');
    $output = fopen('php://output', 'wb');
    fwrite($output, "\xEF\xBB\xBF");
    $fields = analytics_report_fields($type);
    fputcsv($output, array_values($fields), ';', '"', '', "\r\n");
    foreach ($rows as $row) {
        $cells = array_map(fn(string $key): string => analytics_csv_safe(analytics_report_text($key, $row[$key])), array_keys($fields));
        fputcsv($output, $cells, ';', '"', '', "\r\n");
    }
    fclose($output);
    exit;
}
