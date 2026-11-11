<?php
declare(strict_types=1);

function atlas_config(): array
{
    $path = __DIR__ . '/config.local.php';
    $settings = is_file($path) ? require $path : require __DIR__ . '/config.example.php';
    foreach (['host', 'port', 'name', 'user', 'password'] as $key) {
        $override = getenv('ATLAS_DB_' . strtoupper($key));
        if ($override !== false) {
            $settings[$key] = $key === 'port' ? (int) $override : $override;
        }
    }
    if (!is_int($settings['port']) || $settings['port'] < 1 || $settings['port'] > 65535
        || !preg_match('/\A[a-zA-Z0-9_]+\z/', $settings['name'])
        || !preg_match('/\A[a-zA-Z0-9_.:\-]+\z/', $settings['host'])) {
        throw new RuntimeException('Configuração inválida do banco.');
    }
    return $settings;
}
