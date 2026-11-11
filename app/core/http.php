<?php
declare(strict_types=1);

function url(string $page, array $params = []): string
{
    return 'index.php?' . http_build_query(['p' => $page] + $params, '', '&', PHP_QUERY_RFC3986);
}

function redirect(string $page, array $params = []): never
{
    header('Location: ' . url($page, $params), true, 303);
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function csrf_token(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $input = $_POST['csrf'] ?? null;
    if (!is_string($input) || !hash_equals(csrf_token(), $input)) {
        error_page(403, 'O formulário expirou ou é inválido. Abra a página novamente e tente outra vez.');
    }
}

function pagination(string $page, array $filters, int $current, int $total): void
{
    if ($total <= 1) {
        return;
    }
    unset($filters['pagina']);
    echo '<nav class="pagination no-print" aria-label="Paginação">';
    if ($current > 1) {
        echo '<a class="button secondary" rel="prev" href="' . h(url($page, $filters + ['pagina' => $current - 1])) . '">Anterior</a>';
    }
    echo '<span>Página ' . h($current) . ' de ' . h($total) . '</span>';
    if ($current < $total) {
        echo '<a class="button secondary" rel="next" href="' . h(url($page, $filters + ['pagina' => $current + 1])) . '">Próxima</a>';
    }
    echo '</nav>';
}
