<?php
declare(strict_types=1);

function start_atlas_session(): void
{
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', '1800');
    session_name('atlas_session');
    $https = $_SERVER['HTTPS'] ?? '';
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/',
        'secure' => $https !== '' && $https !== 'off',
        'httponly' => true, 'samesite' => 'Lax',
    ]);
    session_start();
}

function load_user(PDO $db): void
{
    $GLOBALS['atlas_user'] = [];
    $id = $_SESSION['uid'] ?? null;
    if (!is_int($id)) {
        return;
    }
    $last = $_SESSION['seen'] ?? 0;
    $row = time() - $last <= 1800
        ? db_one($db, 'SELECT id, nome, email, perfil, senha_hash FROM usuarios WHERE id = ? AND ativo = 1', [$id])
        : null;
    if (!$row || !hash_equals((string) ($_SESSION['auth_version'] ?? ''), hash('sha256', $row['senha_hash']))) {
        $_SESSION = [];
        session_regenerate_id(true);
        return;
    }
    unset($row['senha_hash']);
    $GLOBALS['atlas_user'] = $row;
    $_SESSION['seen'] = time();
}

function user(): array
{
    return $GLOBALS['atlas_user'] ?? [];
}

function require_admin(): void
{
    if ((user()['perfil'] ?? '') !== 'administrador') {
        error_page(403, 'Acesso reservado à administração.');
    }
}

function login_action(PDO $db): never
{
    if (($_SESSION['login_until'] ?? 0) > time()) {
        flash('error', 'Aguarde um minuto antes de tentar novamente.');
        redirect('login');
    }
    try {
        $email = strtolower(field_string($_POST, 'email', 190));
        $password = $_POST['senha'] ?? '';
        if (!is_string($password) || strlen($password) > 72 || str_contains($password, "\0")) {
            throw new DomainException();
        }
        $row = db_one($db, 'SELECT id, senha_hash, ativo FROM usuarios WHERE email = ?', [$email]);
        // Também calcula o hash para e-mails desconhecidos.
        $hash = $row['senha_hash'] ?? '$2y$12$R9h/cIPz0gi.URNNX3kh2OPST9/PgBkqquzi.Ss7KIUgO2t0jWMUW';
        if (!password_verify($password, $hash) || !$row || !(int) $row['ativo']) {
            throw new DomainException();
        }
    } catch (DomainException) {
        $attempts = ($_SESSION['login_attempts'] ?? 0) + 1;
        $_SESSION['login_attempts'] = $attempts % 5;
        if ($attempts >= 5) {
            $_SESSION['login_until'] = time() + 60;
        }
        flash('error', 'E-mail ou senha inválidos.');
        redirect('login');
    }
    session_regenerate_id(true);
    $_SESSION = [
        'uid' => (int) $row['id'], 'seen' => time(),
        'auth_version' => hash('sha256', $row['senha_hash']),
    ];
    redirect('dashboard');
}

function logout_action(): never
{
    $_SESSION = [];
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 3600, 'path' => $params['path'],
        'secure' => $params['secure'], 'httponly' => true, 'samesite' => 'Lax',
    ]);
    session_destroy();
    redirect('login');
}
