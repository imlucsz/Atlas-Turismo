<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}
require __DIR__ . '/../app/bootstrap.php';

if ($argc !== 3) {
    fwrite(STDERR, "Uso: php database/create_admin.php nome email\nDefina ATLAS_ADMIN_PASSWORD com uma senha de 12 a 72 bytes.\n");
    exit(1);
}
try {
    $name = field_string(['nome' => $argv[1]], 'nome', 120);
    $email = strtolower(field_string(['email' => $argv[2]], 'email', 190));
    $password = getenv('ATLAS_ADMIN_PASSWORD');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new DomainException('Informe um e-mail válido.');
    }
    if (!is_string($password) || strlen($password) < 12 || strlen($password) > 72 || str_contains($password, "\0")) {
        throw new DomainException('Defina ATLAS_ADMIN_PASSWORD com uma senha de 12 a 72 bytes.');
    }
    $db = db_connect(atlas_config());
    transaction($db, function (PDO $db) use ($name, $email, $password): void {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        db_exec($db, "INSERT INTO usuarios(nome,email,senha_hash,perfil) VALUES(?,?,?,'administrador')", [$name,$email,$hash]);
        audit($db, null, 'criar', 'usuarios', (int) $db->lastInsertId(), 'Administrador criado pela linha de comando');
    });
    echo "Administrador criado.\n";
} catch (DomainException $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
} catch (PDOException $error) {
    log_error($error);
    fwrite(STDERR, (int) ($error->errorInfo[1] ?? 0) === 1062
        ? "Já existe uma conta com esse e-mail.\n"
        : "Falha ao criar a conta. Confira a configuração e o schema.\n");
    exit(1);
} catch (Throwable $error) {
    log_error($error);
    fwrite(STDERR, "Falha ao criar a conta. Confira a configuração.\n");
    exit(1);
}
