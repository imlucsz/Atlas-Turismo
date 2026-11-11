<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/operations.php';
require_once __DIR__ . '/../models/operation_details.php';
require_once __DIR__ . '/../services/operations.php';
require_once __DIR__ . '/../views/operations.php';

function operations_controller(PDO $db, string $page): void
{
    $action = $_GET['a'] ?? '';
    if (!is_string($action)) {
        error_page(400, 'Ação inválida.');
    }
    if (is_post()) {
        operation_submit($db, $page, $action);
    }
    if ($page === 'comprovante') {
        $reservation = operation_reservation($db, field_int($_GET, 'id'));
        if (!$reservation) {
            error_page(404, 'Reserva não encontrada.');
        }
        render('Comprovante de reserva', fn() => operation_voucher($reservation));
        return;
    }

    if ($action === 'novo') {
        $options = operation_selects($db);
        if ($page === 'viagens') {
            render('Nova viagem', fn() => operation_trip_form($options['packages'], [], operation_old($page, $action)));
        } else {
            $preselected = isset($_GET['viagem_id']) ? field_int($_GET, 'viagem_id') : 0;
            render('Nova reserva', fn() => operation_reservation_form($options['clients'], open_trip_options($db), $preselected, operation_old($page, $action)),
                'Escolha o cliente, a viagem e a quantidade de passageiros.');
        }
        return;
    }
    if ($action === 'ver' || ($page === 'viagens' && $action === 'editar')) {
        $id = field_int($_GET, 'id');
        $record = $page === 'viagens' ? operation_trip($db, $id) : operation_reservation($db, $id);
        if (!$record) {
            error_page(404, 'Registro não encontrado.');
        }
        if ($action === 'editar') {
            if (!in_array($record['status'], ['planejada', 'aberta'], true)) {
                error_page(409, 'Esta viagem não pode ser editada nesta situação.');
            }
            $options = operation_selects($db);
            render('Editar viagem #' . $id, fn() => operation_trip_form($options['packages'], $record, operation_old($page, $action, $id)));
        } elseif ($page === 'viagens') {
            render('Viagem #' . $id, fn() => operation_trip_detail($record, trip_recent_reservations($db, $id)), $record['pacote']);
        } else {
            render('Reserva #' . $id, fn() => operation_reservation_detail($record, operation_payments($db, $id), operation_old($page, $action, $id)),
                $record['cliente'] . ' · ' . $record['pacote']);
        }
        return;
    }

    if ($action !== '') {
        error_page(404, 'Ação não encontrada.');
    }
    $states = $page === 'viagens' ? ['planejada','aberta','em_andamento','concluida','cancelada'] : ['confirmada','cancelada'];
    $q = field_string($_GET, 'q', 120, false) ?? '';
    $state = field_choice($_GET + ['status' => ''], 'status', ['', ...$states]);
    $current = isset($_GET['pagina']) ? field_int($_GET, 'pagina', 1, 100000) : 1;
    $filters = ['q' => $q, 'status' => $state];
    $query = $page === 'viagens' ? 'operation_trips' : 'operation_reservations';
    $result = $query($db, $filters, $current, 12);
    $last = max(1, (int) ceil($result['total'] / 12));
    if ($current > $last) {
        redirect($page, $filters + ['pagina' => $last]);
    }
    render(ucfirst($page), fn() => operation_list_view($page, $result['rows'], $filters, $states, $current, $last),
        $page === 'viagens' ? 'Planeje partidas, acompanhe a ocupação e atualize cada etapa.' : 'Consulte passageiros, pagamentos e a situação de cada reserva.');
}

function operation_submit(PDO $db, string $page, string $action): never
{
    $actor = (int) user()['id'];
    $id = $action === 'novo' ? 0 : field_int($_GET, 'id');
    $fallback = match ($action) { 'novo' => 'novo', 'editar' => 'editar', default => 'ver' };
    try {
        $message = match ($page . '/' . $action) {
            'viagens/novo' => (function () use ($db, $actor, &$id) {
                $id = operation_create_trip($db, $actor, $_POST);
                return 'Viagem planejada.';
            })(),
            'viagens/editar' => (function () use ($db, $actor, $id) {
                operation_update_trip($db, $actor, $id, $_POST);
                return 'Viagem atualizada.';
            })(),
            'viagens/status' => (function () use ($db, $actor, $id) {
                $status = field_choice($_POST, 'status', ['aberta','em_andamento','concluida','cancelada']);
                operation_transition_trip($db, $actor, $id, $status);
                return 'Situação da viagem atualizada.';
            })(),
            'reservas/novo' => (function () use ($db, $actor, &$id) {
                $id = operation_create_reservation($db, $actor, $_POST);
                return 'Reserva confirmada.';
            })(),
            'reservas/cancelar' => (function () use ($db, $actor, $id) {
                operation_cancel_reservation($db, $actor, $id);
                return 'Reserva cancelada.';
            })(),
            'reservas/pagamento' => (function () use ($db, $actor, $id) {
                operation_add_payment($db, $actor, $id, $_POST);
                return 'Pagamento registrado.';
            })(),
            default => error_page(405, 'Ação indisponível para este formulário.'),
        };
        flash('success', $message);
    } catch (DomainException $error) {
        $safe = [];
        foreach (['pacote_id','partida','retorno','capacidade','preco','cliente_id','viagem_id','passageiros','valor','metodo','data_pagamento'] as $key) {
            if (is_string($_POST[$key] ?? null) && strlen($_POST[$key]) <= 120) {
                $safe[$key] = $_POST[$key];
            }
        }
        $_SESSION['operation_form'] = [$page, $fallback, $id, $safe];
        flash('error', $error->getMessage());
        redirect($page, ['a' => $fallback] + ($id > 0 ? ['id' => $id] : []));
    }
    redirect($page, ['a' => 'ver', 'id' => $id]);
}

function operation_old(string $page, string $action, int $id = 0): array
{
    $previous = $_SESSION['operation_form'] ?? [];
    unset($_SESSION['operation_form']);
    return array_slice($previous, 0, 3) === [$page, $action, $id] ? $previous[3] : [];
}
