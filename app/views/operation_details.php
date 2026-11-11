<?php
declare(strict_types=1);

function detail_items(array $items): void
{
    ?><dl class="detail-grid"><?php foreach ($items as $label => $value): ?>
        <div><dt><?= h($label) ?></dt><dd><?= h($value) ?></dd></div>
    <?php endforeach; ?></dl><?php
}

function operation_trip_detail(array $trip, array $bookings): void
{
    $id = (int) $trip['id'];
    $occupied = (int) $trip['ocupacao'];
    $capacity = (int) $trip['capacidade'];
    $status = $trip['status'];
    $today = date('Y-m-d');
    $remaining = max(0, $capacity - $occupied);
    $options = [];
    if ($status === 'planejada' && $trip['partida'] >= $today) {
        $options['aberta'] = 'Abrir reservas';
    }
    if ($status === 'aberta' && $trip['partida'] <= $today) {
        $options['em_andamento'] = 'Iniciar viagem';
    }
    if ($status === 'em_andamento' && $trip['retorno'] <= $today) {
        $options['concluida'] = 'Concluir viagem';
    }
    if (in_array($status, ['planejada','aberta'], true) && $occupied === 0) {
        $options['cancelada'] = 'Cancelar viagem';
    }
    ?>
    <section class="panel">
        <div class="toolbar"><h2>Dados da viagem</h2><?= badge($status) ?></div>
        <?php detail_items(['Destino' => $trip['cidade'] . ', ' . $trip['pais'], 'Partida' => date_br($trip['partida']),
            'Retorno' => date_br($trip['retorno']), 'Preço por pessoa' => brl((string) $trip['preco']),
            'Passageiros confirmados' => $occupied, 'Vagas livres' => $remaining]); ?>
        <label class="progress-field">Ocupação · <?= h($occupied) ?> de <?= h($capacity) ?> lugares
            <progress max="<?= h($capacity) ?>" value="<?= h($occupied) ?>"><?= h($occupied) ?> / <?= h($capacity) ?></progress>
        </label>
        <div class="actions no-print">
            <a class="button secondary" href="<?= h(url('viagens')) ?>">Voltar</a>
            <?php if (in_array($status, ['planejada','aberta'], true)): ?>
                <a class="button secondary" href="<?= h(url('viagens',['a'=>'editar','id'=>$id])) ?>">Editar viagem</a>
            <?php endif; ?>
            <?php if ($status === 'aberta' && $trip['partida'] >= $today && $remaining > 0): ?>
                <a class="button" href="<?= h(url('reservas', ['a'=>'novo','viagem_id'=>$id])) ?>">Nova reserva</a>
            <?php endif; ?>
            <?php foreach ($options as $next => $label): ?>
                <form method="post" action="<?= h(url('viagens', ['a'=>'status','id'=>$id])) ?>">
                    <?= csrf_field() ?><input type="hidden" name="status" value="<?= h($next) ?>">
                    <button type="submit" class="button <?= $next === 'cancelada' ? 'danger' : 'secondary' ?>"><?= h($label) ?></button>
                </form>
            <?php endforeach; ?>
        </div>
    </section>
    <section class="panel">
        <h2>Reservas recentes</h2>
        <?php if (!$bookings): ?><p class="empty">Ainda não há reservas nesta viagem.</p>
        <?php else: ?><div class="table-wrap"><table>
            <thead><tr><th scope="col">Cliente</th><th scope="col">Passageiros</th><th scope="col">Situação</th><th scope="col">Valor</th></tr></thead>
            <tbody><?php foreach ($bookings as $booking): ?><tr>
                <td><a href="<?= h(url('reservas', ['a'=>'ver','id'=>$booking['id']])) ?>">#<?= h($booking['id']) ?> · <?= h($booking['nome']) ?></a></td>
                <td><?= h($booking['passageiros']) ?></td><td><?= badge($booking['status']) ?></td><td><?= h(brl((string) $booking['valor_total'])) ?></td>
            </tr><?php endforeach; ?></tbody>
        </table></div><?php endif; ?>
    </section>
    <?php
}

function reservation_finance(array $row): string
{
    if ($row['status'] === 'cancelada') {
        return 'cancelada';
    }
    $paid = money_cents((string) $row['pago']);
    return match (true) {
        $paid === 0 => 'pendente',
        $paid < money_cents((string) $row['valor_total']) => 'parcial',
        default => 'quitado',
    };
}

function operation_reservation_detail(array $row, array $payments, array $previous): void
{
    $id = (int) $row['id'];
    $paid = money_cents((string) $row['pago']);
    $due = $row['status'] === 'confirmada' ? money_cents((string) $row['valor_total']) - $paid : 0;
    ?>
    <section class="panel">
        <div class="toolbar"><h2>Dados da reserva</h2><div class="actions"><?= badge($row['status']) ?><?= badge(reservation_finance($row)) ?></div></div>
        <?php detail_items(['Cliente'=>$row['cliente'], 'Destino'=>$row['cidade'] . ', ' . $row['pais'],
            'Partida'=>date_br($row['partida']), 'Retorno'=>date_br($row['retorno']), 'Passageiros'=>$row['passageiros'],
            'Preço por pessoa'=>brl((string) $row['preco_unitario']), 'Valor contratado'=>brl((string) $row['valor_total']),
            'Total recebido'=>brl($paid), 'Saldo devido'=>brl($due)]); ?>
        <div class="actions no-print">
            <a class="button secondary" href="<?= h(url('reservas')) ?>">Voltar</a>
            <a class="button secondary" href="<?= h(url('viagens', ['a'=>'ver','id'=>$row['viagem_id']])) ?>">Ver viagem</a>
            <a class="button secondary" href="<?= h(url('comprovante', ['id'=>$id])) ?>">Ver comprovante</a>
            <?php if ($row['status'] === 'confirmada' && $paid === 0 && in_array($row['viagem_status'], ['planejada', 'aberta'], true)): ?>
                <form method="post" action="<?= h(url('reservas', ['a'=>'cancelar','id'=>$id])) ?>"><?= csrf_field() ?><button type="submit" class="button danger">Cancelar reserva</button></form>
            <?php endif; ?>
        </div>
        <?php if ($paid > 0): ?><p class="muted">Reservas com pagamentos exigem estorno para cancelamento. O registro está protegido.</p><?php endif; ?>
    </section>
    <?php if ($due > 0): ?>
    <section class="panel no-print">
        <h2>Registrar pagamento</h2>
        <p class="muted">Saldo disponível para receber <?= h(brl($due)) ?>.</p>
        <form method="post" action="<?= h(url('reservas', ['a'=>'pagamento','id'=>$id])) ?>" class="form-grid">
            <?= csrf_field() ?>
            <label class="field">Valor (R$)<input name="valor" inputmode="decimal" maxlength="13" value="<?= h($previous['valor'] ?? decimal($due)) ?>" required></label>
            <label class="field">Método<select name="metodo" required><option value="">Selecione</option>
                <?php foreach (['pix','cartao','dinheiro','transferencia'] as $method): ?>
                <option value="<?= h($method) ?>" <?= ($previous['metodo'] ?? '') === $method ? 'selected' : '' ?>><?= h(status_label($method)) ?></option>
                <?php endforeach; ?>
            </select></label>
            <label class="field">Data do pagamento<input type="date" name="data_pagamento" max="<?= h(date('Y-m-d')) ?>" value="<?= h($previous['data_pagamento'] ?? date('Y-m-d')) ?>" required></label>
            <div class="actions full"><button type="submit" class="button">Registrar pagamento</button></div>
        </form>
    </section>
    <?php endif; ?>
    <section class="panel">
        <h2>Histórico financeiro</h2>
        <?php if (!$payments): ?><p class="empty">Nenhum pagamento registrado.</p>
        <?php else: ?><div class="table-wrap"><table>
            <thead><tr><th scope="col">Data</th><th scope="col">Método</th><th scope="col">Valor</th><th scope="col">Situação</th></tr></thead>
            <tbody><?php foreach ($payments as $payment): ?><tr>
                <td><?= h(date_br($payment['data_pagamento'])) ?></td><td><?= h(status_label($payment['metodo'])) ?></td>
                <td><?= h(brl((string) $payment['valor'])) ?></td><td><?= badge($payment['status']) ?></td>
            </tr><?php endforeach; ?></tbody>
        </table></div><?php endif; ?>
    </section>
    <?php
}

function operation_voucher(array $row): void
{
    ?>
    <article class="panel voucher">
        <div class="toolbar"><h2>Atlas Turismo · Reserva #<?= h($row['id']) ?></h2><?= badge($row['status']) ?></div>
        <?php detail_items(['Cliente'=>$row['cliente'], 'Pacote'=>$row['pacote'], 'Destino'=>$row['cidade'] . ', ' . $row['pais'],
            'Partida'=>date_br($row['partida']), 'Retorno'=>date_br($row['retorno']), 'Passageiros'=>$row['passageiros'],
            'Valor total'=>brl((string) $row['valor_total']), 'Recebido'=>brl((string) $row['pago']),
            'Situação financeira'=>status_label(reservation_finance($row)), 'Emissão'=>date_br(date('Y-m-d'))]); ?>
        <p class="no-print muted">Use o comando Imprimir do navegador (Ctrl+P ou ⌘+P) para imprimir ou salvar o comprovante.</p>
        <a class="button secondary no-print" href="<?= h(url('reservas', ['a'=>'ver','id'=>$row['id']])) ?>">Voltar à reserva</a>
    </article>
    <?php
}
