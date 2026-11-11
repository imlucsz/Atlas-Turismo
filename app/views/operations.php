<?php
declare(strict_types=1);

require_once __DIR__ . '/operation_details.php';

function operation_list_view(string $page, array $rows, array $filters, array $states, int $current, int $last): void
{
    $isTrip = $page === 'viagens';
    $columns = $isTrip ? ['Viagem', 'Partida', 'Ocupação', 'Preço por pessoa', 'Situação', ''] : ['Reserva', 'Viagem', 'Passageiros', 'Valor total', 'Financeiro', 'Situação', ''];
    ?>
    <section class="panel">
        <div class="toolbar no-print">
            <form method="get" class="filters" action="index.php">
                <input type="hidden" name="p" value="<?= h($page) ?>">
                <label class="field">Busca<input type="search" name="q" value="<?= h($filters['q']) ?>" maxlength="120" placeholder="<?= $isTrip ? 'Pacote ou destino' : 'Cliente, pacote ou número' ?>"></label>
                <label class="field">Situação<select name="status"><option value="">Todas</option>
                    <?php foreach ($states as $state): ?>
                    <option value="<?= h($state) ?>" <?= $filters['status'] === $state ? 'selected' : '' ?>><?= h(status_label($state)) ?></option>
                    <?php endforeach; ?>
                </select></label>
                <button type="submit" class="button secondary">Filtrar</button>
                <?php if ($filters['q'] !== '' || $filters['status'] !== ''): ?><a href="<?= h(url($page)) ?>">Limpar filtros</a><?php endif; ?>
            </form>
            <a class="button" href="<?= h(url($page, ['a' => 'novo'])) ?>"><?= $isTrip ? 'Nova viagem' : 'Nova reserva' ?></a>
        </div>
        <?php if (!$rows): ?><p class="empty">Nenhum registro encontrado.</p>
        <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><?php foreach ($columns as $column): ?><th scope="col"><?= h($column) ?></th><?php endforeach; ?></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): $viewUrl = h(url($page, ['a'=>'ver','id'=>$row['id']])); ?>
                <tr>
                    <td><a href="<?= $viewUrl ?>"><strong><?= h($isTrip ? $row['pacote'] : '#' . $row['id'] . ' · ' . $row['cliente']) ?></strong></a><br><small><?= h($isTrip ? $row['cidade'] . ', ' . $row['pais'] : date_br($row['criado_em'])) ?></small></td>
                    <td><?= h($isTrip ? date_br($row['partida']) : $row['pacote'] . ' · ' . date_br($row['partida'])) ?></td>
                    <?php if ($isTrip): ?><td><?= h($row['ocupacao']) ?> / <?= h($row['capacidade']) ?></td><td><?= h(brl((string) $row['preco'])) ?></td>
                    <?php else: ?><td><?= h($row['passageiros']) ?></td><td><?= h(brl((string) $row['valor_total'])) ?></td><td><?= badge(reservation_finance($row)) ?></td>
                    <?php endif; ?>
                    <td><?= badge($row['status']) ?></td>
                    <td><a class="button secondary small" href="<?= $viewUrl ?>">Ver detalhes</a></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php pagination($page, $filters, $current, $last); ?>
        <?php endif; ?>
    </section>
    <?php
}

function operation_trip_form(array $packages, array $record, array $previous): void
{
    $row = $previous + $record;
    $id = $record['id'] ?? null;
    $action = $id ? ['a'=>'editar','id'=>$id] : ['a'=>'novo'];
    $locked = (int) ($record['ocupacao'] ?? 0) > 0;
    $packageId = (int) ($row['pacote_id'] ?? 0);
    $availableIds = array_map(fn(array $p): int => (int) $p['id'], $packages);
    ?>
    <section class="panel">
        <?php if (!$packages && !$id): ?>
            <div class="empty">Cadastre um pacote ativo para planejar uma viagem. <a href="<?= h(url('pacotes',['a'=>'novo'])) ?>">Cadastrar pacote</a></div>
        <?php else: ?>
        <?php if ($locked): ?><p class="muted">O pacote e as datas ficam protegidos porque a viagem tem reservas confirmadas.</p><?php endif; ?>
        <form method="post" action="<?= h(url('viagens', $action)) ?>" class="form-grid">
            <?= csrf_field() ?>
            <?php if ($locked): ?>
                <label class="field full">Pacote<input value="<?= h($record['pacote']) ?>" readonly></label>
                <input type="hidden" name="pacote_id" value="<?= h($record['pacote_id']) ?>">
            <?php else: ?>
                <label class="field full">Pacote<select name="pacote_id" required>
                    <option value="">Selecione</option>
                    <?php if ($id && !in_array((int) $record['pacote_id'], $availableIds, true)): ?>
                    <option value="<?= h($record['pacote_id']) ?>" <?= (int) $record['pacote_id'] === $packageId ? 'selected' : '' ?>><?= h($record['pacote']) ?> · inativo</option>
                    <?php endif; ?>
                    <?php foreach ($packages as $package): ?>
                    <option value="<?= h($package['id']) ?>" <?= (int) $package['id'] === $packageId ? 'selected' : '' ?>><?= h($package['titulo'] . ' · ' . $package['cidade'] . ' · base ' . brl((string) $package['preco_base'])) ?></option>
                    <?php endforeach; ?>
                </select></label>
            <?php endif; ?>
            <label class="field">Partida<input type="date" name="partida" value="<?= h(($locked ? $record : $row)['partida'] ?? '') ?>" <?= $locked ? 'readonly' : '' ?> required></label>
            <label class="field">Retorno<input type="date" name="retorno" value="<?= h(($locked ? $record : $row)['retorno'] ?? '') ?>" <?= $locked ? 'readonly' : '' ?> required></label>
            <label class="field">Capacidade<input type="number" name="capacidade" min="<?= max(1, (int) ($record['ocupacao'] ?? 0)) ?>" max="100000" value="<?= h($row['capacidade'] ?? '') ?>" required></label>
            <label class="field">Preço por pessoa (R$)<input type="text" inputmode="decimal" name="preco" maxlength="13" value="<?= h($row['preco'] ?? '') ?>" placeholder="0,00" required></label>
            <div class="actions full"><button type="submit" class="button"><?= $id ? 'Salvar alterações' : 'Planejar viagem' ?></button>
            <a class="button secondary" href="<?= h(url('viagens', $id ? ['a'=>'ver','id'=>$id] : [])) ?>">Voltar</a></div>
        </form>
        <?php endif; ?>
    </section>
    <?php
}

function operation_reservation_form(array $clients, array $trips, int $selected, array $previous): void
{
    $selected = (int) ($previous['viagem_id'] ?? $selected);
    $available = array_filter($trips, fn(array $r): bool => (int) $r['vagas'] > 0);
    ?>
    <section class="panel">
        <?php if (!$clients || !$available): ?>
            <div class="empty"><?php if (!$clients): ?>Cadastre um cliente ativo para reservar. <a href="<?= h(url('clientes', ['a'=>'novo'])) ?>">Cadastrar cliente</a>
            <?php else: ?>Nenhuma viagem aberta tem vagas disponíveis. <a href="<?= h(url('viagens')) ?>">Consultar viagens</a><?php endif; ?></div>
        <?php else: ?>
        <form method="post" action="<?= h(url('reservas', ['a'=>'novo'])) ?>" class="form-grid">
            <?= csrf_field() ?>
            <label class="field full">Cliente<select name="cliente_id" required><option value="">Selecione</option>
                <?php foreach ($clients as $client): ?>
                <option value="<?= h($client['id']) ?>" <?= (int) $client['id'] === (int) ($previous['cliente_id'] ?? 0) ? 'selected' : '' ?>><?= h($client['nome'] . ($client['email'] ? ' · ' . $client['email'] : '')) ?></option>
                <?php endforeach; ?>
            </select></label>
            <label class="field full">Viagem<select name="viagem_id" required><option value="">Selecione</option>
                <?php foreach ($available as $trip): ?>
                <option value="<?= h($trip['id']) ?>" <?= (int) $trip['id'] === $selected ? 'selected' : '' ?>><?= h($trip['titulo'] . ' · ' . date_br($trip['partida']) . ' · ' . $trip['vagas'] . ' vagas · ' . brl((string) $trip['preco'])) ?></option>
                <?php endforeach; ?>
            </select></label>
            <label class="field">Passageiros<input type="number" name="passageiros" min="1" max="1000" value="<?= h($previous['passageiros'] ?? '1') ?>" required></label>
            <div class="actions full"><button type="submit" class="button">Confirmar reserva</button><a class="button secondary" href="<?= h(url('reservas')) ?>">Voltar</a></div>
        </form>
        <?php endif; ?>
    </section>
    <?php
}
