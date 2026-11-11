<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/core/http.php';
require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/views/layout.php';

header("Content-Security-Policy: default-src 'none'; style-src 'self'; img-src 'self' data:; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');
start_atlas_session();

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($method, ['GET', 'POST', 'HEAD'], true)) {
        header('Allow: GET, HEAD, POST');
        error_page(405, 'Método de acesso indisponível.');
    }
    $page = $_GET['p'] ?? 'dashboard';
    if (!is_string($page)) {
        error_page(400, 'Endereço inválido.');
    }
    $db = db_connect(atlas_config());
    load_user($db);
    if ($page === 'login') {
        if (user()) {
            redirect('dashboard');
        }
        if (is_post()) {
            verify_csrf();
            login_action($db);
        }
        login_view();
        return;
    }
    if (!user()) {
        redirect('login');
    }
    if (is_post()) {
        verify_csrf();
    }
    if ($page === 'logout') {
        if (!is_post()) {
            header('Allow: POST');
            error_page(405, 'Use o botão Sair para encerrar a sessão.');
        }
        logout_action();
    }
    if (in_array($page, ['usuarios', 'auditoria'], true)) {
        require_admin();
    }

    $group = match ($page) {
        'clientes', 'destinos', 'pacotes', 'usuarios' => 'catalog',
        'viagens', 'reservas', 'comprovante' => 'operations',
        'dashboard', 'relatorios', 'auditoria' => 'analytics',
        default => null,
    };
    if ($group === null) {
        error_page(404, 'Página não encontrada.');
    }
    $level = ob_get_level();
    ob_start();
    try {
        require_once __DIR__ . '/../app/controllers/' . $group . '.php';
        ($group . '_controller')($db, $page);
        ob_end_flush();
    } catch (Throwable $error) {
        while (ob_get_level() > $level) {
            ob_end_clean();
        }
        throw $error;
    }
} catch (DomainException $error) {
    error_page(422, $error->getMessage());
} catch (Throwable $error) {
    log_error($error);
    error_page(500, 'O sistema não conseguiu concluir a solicitação. Tente novamente ou fale com a administração.');
}
