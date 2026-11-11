<?php
declare(strict_types=1);

require_once __DIR__ . '/reports.php';

function analytics_view_dashboard(array $data): void
{
    $metrics = [
        ['Clientes', $data['clientes'], 'clientes', []],
        ['Viagens cadastradas', $data['viagens'], 'viagens', []],
        ['Reservas confirmadas', $data['reservas'], 'reservas', ['status'=>'confirmada']],
        ['Receita recebida', brl((string) $data['receita']), 'relatorios', ['tipo'=>'receita']],
        ['Saldo pendente', brl((string) $data['pendente']), 'relatorios', ['tipo'=>'pendentes']],
        ['Ocupação das viagens ativas', str_replace('.', ',', (string) $data['ocupacao']) . '%', 'relatorios', ['tipo'=>'ocupacao']],
    ];
    ?>
    <section class="metric-grid" aria-label="Indicadores">
        <?php foreach ($metrics as [$label,$value,$page,$params]): ?>
            <a class="metric-card" href="<?= h(url($page, $params)) ?>"><span><?= h($label) ?></span><strong><?= h($value) ?></strong></a>
        <?php endforeach; ?>
    </section>
    <div class="content-grid">
        <section class="panel"><h2>Próximas partidas</h2>
            <?php if (!$data['proximas']): ?><p class="empty">Nenhuma partida prevista.</p><?php endif; ?>
            <?php foreach ($data['proximas'] as $trip): ?>
                <a class="trip-row" href="<?= h(url('viagens',['a'=>'ver','id'=>$trip['id']])) ?>">
                    <strong><?= h($trip['titulo'] . ' · ' . $trip['cidade']) ?></strong>
                    <span><?= h(date_br($trip['partida'])) ?> · <?= h($trip['ocupadas']) ?> de <?= h($trip['capacidade']) ?> vagas ocupadas</span>
                    <progress aria-label="Ocupação da viagem" max="<?= h($trip['capacidade']) ?>" value="<?= h($trip['ocupadas']) ?>"></progress>
                </a>
            <?php endforeach; ?>
        </section>
        <section class="panel"><h2>Avisos</h2>
            <?php if (!$data['alertas']): ?><p class="empty">Nenhum aviso pendente.</p><?php endif; ?>
            <?php foreach ($data['alertas'] as $alert): ?>
                <p class="rank-row"><a href="<?= h(url($alert['pagina'],['a'=>'ver','id'=>$alert['id']])) ?>"><?= h($alert['tipo'] . ' · ' . $alert['descricao']) ?></a>
                <span>Partida <?= h(date_br($alert['data'])) ?></span></p>
            <?php endforeach; ?>
        </section>
        <?php foreach (['populares'=>'Pacotes mais reservados','destinos'=>'Destinos mais reservados'] as $key=>$title): ?>
        <section class="panel"><h2><?= h($title) ?></h2>
            <?php if (!$data[$key]): ?><p class="empty">Ainda não há reservas confirmadas.</p><?php endif; ?>
            <?php foreach ($data[$key] as $rank): ?>
                <p class="rank-row"><strong><?= h($rank['titulo'] ?? $rank['cidade']) ?></strong>
                    <span><?= h($rank['pais'] ?? $rank['cidade']) ?> · <?= h($rank['total']) ?> <?= (int) $rank['total'] === 1 ? 'reserva' : 'reservas' ?></span></p>
            <?php endforeach; ?>
        </section>
        <?php endforeach; ?>
    </div>
    <section class="panel"><h2>Reservas recentes</h2>
        <?php if (!$data['recentes']): ?><p class="empty">Ainda não há reservas.</p>
        <?php else: ?><div class="table-wrap"><table>
            <thead><tr><th scope="col">Cliente</th><th scope="col">Pacote</th><th scope="col">Valor</th><th scope="col">Situação</th></tr></thead>
            <tbody><?php foreach ($data['recentes'] as $row): ?><tr>
                <td><a href="<?= h(url('reservas',['a'=>'ver','id'=>$row['id']])) ?>"><?= h($row['cliente']) ?></a></td>
                <td><?= h($row['pacote'] . ' · ' . $row['cidade']) ?></td><td><?= h(brl((string) $row['valor_total'])) ?></td>
                <td><?= badge($row['status']) ?></td>
            </tr><?php endforeach; ?></tbody>
        </table></div><?php endif; ?>
    </section>
    <?php
}

function analytics_view_audit(array $results, string $search): void
{
    ?>
    <section class="panel">
        <form method="get" action="index.php" class="toolbar no-print">
            <input type="hidden" name="p" value="auditoria">
            <label class="field">Pesquisar<input type="search" name="q" maxlength="100" value="<?= h($search) ?>"></label>
            <button type="submit" class="button">Buscar</button>
        </form>
        <?php if (!$results['rows']): ?><p class="empty">Nenhuma operação encontrada.</p>
        <?php else: ?><div class="table-wrap"><table>
            <thead><tr><th scope="col">Data</th><th scope="col">Usuário</th><th scope="col">Ação</th><th scope="col">Registro</th><th scope="col">Detalhes</th></tr></thead>
            <tbody><?php foreach ($results['rows'] as $row): ?><tr>
                <td><?= h(date_br($row['criado_em'])) ?></td><td><?= h($row['usuario'] ?? 'Sistema') ?></td>
                <td><?= h(status_label($row['operacao'])) ?></td><td><?= h($row['entidade'] . ' #' . $row['registro_id']) ?></td>
                <td><?= h($row['detalhes']) ?></td>
            </tr><?php endforeach; ?></tbody>
        </table></div><?php endif; ?>
        <p class="muted"><?= h($results['total']) ?> registros encontrados.</p>
        <?php pagination('auditoria', ['q'=>$search], $results['page'], $results['last']); ?>
    </section>
    <?php
}
