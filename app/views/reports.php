<?php
declare(strict_types=1);

function analytics_report_types(): array
{
    return ['reservas'=>'Reservas por período e situação', 'receita'=>'Receita recebida', 'pendentes'=>'Pagamentos pendentes',
        'ocupacao'=>'Ocupação por viagem', 'populares'=>'Pacotes mais reservados', 'destinos'=>'Destinos mais reservados', 'cliente'=>'Histórico por cliente'];
}

function analytics_report_statuses(string $type): array
{
    return match ($type) {
        'reservas', 'cliente' => ['confirmada','cancelada'],
        'ocupacao' => ['planejada','aberta','em_andamento','concluida','cancelada'],
        default => [],
    };
}

function analytics_client_filter(string $type): bool
{
    return in_array($type, ['reservas','receita','pendentes','cliente'], true);
}

function analytics_report_fields(string $type): array
{
    return match ($type) {
        'receita' => ['id'=>'Pagamento', 'reserva_id'=>'Reserva', 'data'=>'Data do pagamento', 'cliente'=>'Cliente',
            'pacote'=>'Pacote', 'metodo'=>'Método', 'total'=>'Recebido'],
        'ocupacao' => ['id'=>'Viagem', 'pacote'=>'Pacote', 'destino'=>'Destino', 'data'=>'Partida',
            'quantidade'=>'Passageiros', 'capacidade'=>'Capacidade', 'ocupacao'=>'Ocupação', 'situacao'=>'Situação'],
        'populares' => ['id'=>'Pacote', 'pacote'=>'Título', 'destino'=>'Destino', 'quantidade'=>'Reservas confirmadas', 'passageiros'=>'Passageiros'],
        'destinos' => ['id'=>'Destino', 'destino'=>'Localidade', 'quantidade'=>'Reservas confirmadas', 'passageiros'=>'Passageiros'],
        default => ['id'=>'Reserva', 'data'=>'Data da reserva', 'cliente'=>'Cliente', 'pacote'=>'Pacote',
            'quantidade'=>'Passageiros', 'situacao'=>'Situação', 'total'=>'Contratado',
            'recebido'=>'Recebido', 'saldo'=>'Saldo', 'financeiro'=>'Financeiro'],
    };
}

function analytics_report_text(string $key, mixed $value): string
{
    return match ($key) {
        'data' => date_br($value),
        'situacao', 'financeiro', 'metodo' => status_label((string) $value),
        'total', 'recebido', 'saldo' => brl((string) $value),
        'ocupacao' => str_replace('.', ',', (string) $value) . '%',
        default => (string) $value,
    };
}

function analytics_report_cell(string $type, string $key, mixed $value): string
{
    if (in_array($key, ['situacao','financeiro'], true)) {
        return badge($value);
    }
    if ($key === 'id' || $key === 'reserva_id') {
        $page = match ($type) { 'ocupacao'=>'viagens', 'populares'=>'pacotes', 'destinos'=>'destinos', default=>'reservas' };
        $label = '#' . h($value);
        return $type === 'receita' && $key === 'id' ? $label
            : '<a href="' . h(url($page, ['a'=>'ver','id'=>$value])) . '">' . $label . '</a>';
    }
    return h(analytics_report_text($key, $value));
}

function analytics_view_report(array $rows, array $filter, array $clients, array $summary, int $page, int $last): void
{
    $types = analytics_report_types();
    $type = $filter['tipo'];
    $fields = analytics_report_fields($type);
    $statuses = analytics_report_statuses($type);
    $dateMeaning = match ($type) { 'receita'=>'Data do pagamento', 'ocupacao'=>'Data da partida', default=>'Data da reserva' };
    ?>
    <section class="panel no-print">
        <form method="get" action="index.php" class="filters">
            <input type="hidden" name="p" value="relatorios">
            <label class="field">Tipo de relatório<select name="tipo">
                <?php foreach ($types as $key=>$label): ?><option value="<?= h($key) ?>" <?= $type === $key ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?>
            </select></label>
            <button type="submit" class="button secondary">Abrir relatório</button>
        </form>
    </section>
    <section class="panel">
        <h2><?= h($types[$type]) ?></h2>
        <p class="muted"><?= h($dateMeaning) ?> · <?= h($filter['inicio'] ? date_br($filter['inicio']) : 'sem início definido') ?>
            até <?= h($filter['fim'] ? date_br($filter['fim']) : 'sem fim definido') ?></p>
        <form method="get" action="index.php" class="toolbar no-print">
            <input type="hidden" name="p" value="relatorios"><input type="hidden" name="tipo" value="<?= h($type) ?>">
            <label class="field">De<input type="date" name="inicio" value="<?= h($filter['inicio']) ?>"></label>
            <label class="field">Até<input type="date" name="fim" value="<?= h($filter['fim']) ?>"></label>
            <?php if ($statuses): ?><label class="field">Situação<select name="status"><option value="">Todas</option>
                <?php foreach ($statuses as $status): ?><option value="<?= h($status) ?>" <?= $filter['status'] === $status ? 'selected' : '' ?>><?= h(status_label($status)) ?></option><?php endforeach; ?>
            </select></label><?php endif; ?>
            <?php if (analytics_client_filter($type)): ?><label class="field">Cliente<select name="cliente" <?= $type === 'cliente' ? 'required' : '' ?>>
                <option value="<?= $type === 'cliente' ? '' : '0' ?>"><?= $type === 'cliente' ? 'Selecione' : 'Todos os clientes' ?></option>
                <?php foreach ($clients as $client): ?><option value="<?= h($client['id']) ?>" <?= $filter['cliente'] === (int) $client['id'] ? 'selected' : '' ?>><?= h($client['nome']) ?></option><?php endforeach; ?>
            </select></label><?php endif; ?>
            <div class="actions">
                <button type="submit" class="button">Filtrar</button>
                <button type="submit" class="button secondary" name="formato" value="csv">Exportar CSV</button>
            </div>
        </form>
        <?php if (!$rows): ?><p class="empty"><?= $type === 'cliente' && !$filter['cliente'] ? 'Selecione um cliente para ver o histórico.' : 'Nenhum registro para estes filtros.' ?></p>
        <?php else: ?><div class="table-wrap"><table>
            <thead><tr><?php foreach ($fields as $label): ?><th scope="col"><?= h($label) ?></th><?php endforeach; ?></tr></thead>
            <tbody><?php foreach ($rows as $row): ?><tr>
                <?php foreach ($fields as $key=>$_): ?><td><?= analytics_report_cell($type, $key, $row[$key]) ?></td><?php endforeach; ?>
            </tr><?php endforeach; ?></tbody>
        </table></div><?php endif; ?>
        <p class="muted"><?= h($summary['registros']) ?> registros no período.
            <?php if ($summary['valor'] !== null): ?><?= $type === 'receita' ? 'Total recebido' : 'Saldo pendente' ?> <?= h(brl((string) $summary['valor'])) ?>.<?php endif; ?>
        </p>
        <p class="muted no-print">Use Ctrl+P ou o comando Imprimir do navegador. O CSV inclui todas as páginas do resultado.</p>
        <?php pagination('relatorios', $filter, $page, $last); ?>
    </section>
    <?php
}
