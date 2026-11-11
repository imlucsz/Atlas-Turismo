<?php
declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');
ini_set('display_errors', '0');
ini_set('zend.exception_ignore_args', '1');
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/core/validation.php';

function log_error(Throwable $error): void
{
    // Não registra SQL, entradas de formulário nem valores da conexão.
    $location = basename($error->getFile()) . ':' . $error->getLine();
    error_log('[Atlas Turismo] ' . $error::class . ' código ' . $error->getCode() . ' em ' . $location);
}
