<?php
declare(strict_types=1);

function page_start(string $title): void
{
    ?><!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title) ?> | Atlas Turismo</title>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body><?php
}

function flash_view(): void
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    foreach ($messages as $item) {
        $type = $item['type'] === 'success' ? 'success' : 'error';
        echo '<div class="alert alert--' . $type . '" role="' . ($type === 'error' ? 'alert' : 'status') . '">'
            . h($item['message']) . '</div>';
    }
}

function brand_view(): void
{
    ?><a class="brand" href="<?= h(url('dashboard')) ?>"><span class="brand-mark" aria-hidden="true">✦</span><span>Atlas<span class="brand-sub"> TURISMO</span></span></a><?php
}

function nav_view(string $current): void
{
    $sections = [
        'Visão geral' => ['dashboard' => ['Painel', '◈']],
        'Operação' => ['viagens' => ['Viagens', '➤'], 'reservas' => ['Reservas', '▤']],
        'Cadastros' => ['clientes' => ['Clientes', '◎'], 'destinos' => ['Destinos', '◇'], 'pacotes' => ['Pacotes', '▣']],
        'Gestão' => ['relatorios' => ['Relatórios', '▥']],
    ];
    if (user()['perfil'] === 'administrador') {
        $sections['Gestão'] += ['usuarios' => ['Usuários', '◉'], 'auditoria' => ['Auditoria', '≡']];
    }
    foreach ($sections as $label => $links) {
        ?><p class="nav-label"><?= h($label) ?></p><?php
        foreach ($links as $route => [$name, $icon]) {
            $active = $route === $current || ($route === 'reservas' && $current === 'comprovante');
            ?><a class="nav-link<?= $active ? ' is-active' : '' ?>" <?= $active ? 'aria-current="page"' : '' ?> href="<?= h(url($route)) ?>"><span aria-hidden="true"><?= h($icon) ?></span><?= h($name) ?></a><?php
        }
    }
}

function render(string $title, callable $content, string $subtitle = ''): void
{
    page_start($title);
    $account = user();
    ?><a class="skip-link" href="#conteudo">Pular para conteúdo</a>
<div class="app-shell">
    <aside class="sidebar no-print">
        <?php brand_view(); ?>
        <nav aria-label="Menu principal"><?php nav_view(is_string($_GET['p'] ?? null) ? $_GET['p'] : 'dashboard'); ?></nav>
        <div class="sidebar-foot">Operações de turismo</div>
    </aside>
    <div class="workspace">
        <header class="topbar no-print">
            <div class="topbar-context">Área administrativa</div>
            <div class="topbar-user">
                <span class="avatar" aria-hidden="true"><?= h(mb_strtoupper(mb_substr($account['nome'], 0, 1))) ?></span>
                <span><strong><?= h($account['nome']) ?></strong><small><?= h(status_label($account['perfil'])) ?></small></span>
                <form method="post" action="<?= h(url('logout')) ?>"><?= csrf_field() ?><button class="button secondary small" type="submit">Sair</button></form>
            </div>
        </header>
        <main class="main" id="conteudo">
            <div class="page-header">
                <span class="eyebrow">ATLAS TURISMO</span>
                <h1><?= h($title) ?></h1>
                <?php if ($subtitle !== ''): ?><p class="page-subtitle"><?= h($subtitle) ?></p><?php endif; ?>
            </div>
            <?php flash_view(); $content(); ?>
        </main>
    </div>
</div>
</body></html><?php
}

function login_view(): void
{
    page_start('Entrar');
    ?><main class="login-page">
    <section class="login-aside" aria-label="Atlas Turismo">
        <?php brand_view(); ?>
        <div class="login-intro">
            <span class="eyebrow">GESTÃO DE VIAGENS</span>
            <h1>Planeje viagens.<br>Cuide de cada reserva.</h1>
            <p>Organize destinos, partidas, passageiros e pagamentos em um só lugar.</p>
        </div>
        <div class="login-caption">ATLAS TURISMO</div>
    </section>
    <section class="login-main">
        <div class="login-card">
            <span class="eyebrow">ÁREA ADMINISTRATIVA</span>
            <h2>Entre na sua conta</h2>
            <p class="muted">Use seu e-mail de trabalho para acessar o painel.</p>
            <?php flash_view(); ?>
            <form method="post" action="<?= h(url('login')) ?>" class="login-form">
                <?= csrf_field() ?>
                <label class="field">E-mail<input type="email" name="email" maxlength="190" required autocomplete="username"></label>
                <label class="field">Senha<input type="password" name="senha" maxlength="72" required autocomplete="current-password"></label>
                <button type="submit" class="button">Entrar</button>
            </form>
        </div>
    </section>
</main></body></html><?php
}

function error_page(int $status, string $message): never
{
    http_response_code($status);
    $title = match ($status) {403 => 'Acesso restrito', 404 => 'Página não encontrada', 500 => 'Serviço indisponível', default => 'Solicitação inválida'};
    if (user()) {
        render($title, function () use ($message) {
            ?><div class="panel"><p><?= h($message) ?></p><a class="button" href="<?= h(url('dashboard')) ?>">Voltar ao painel</a></div><?php
        });
    } else {
        page_start($title);
        ?><main class="login-main error-main"><div class="login-card"><h1><?= h($title) ?></h1><p><?= h($message) ?></p><a class="button" href="<?= h(url('login')) ?>">Voltar ao acesso</a></div></main></body></html><?php
    }
    exit;
}
