<?php
declare(strict_types=1);

function db_connect(array $settings): PDO
{
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $settings['host'], $settings['port'], $settings['name']
    );
    $db = new PDO($dsn, $settings['user'], $settings['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ]);
    $db->exec('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
    // Brasil usa UTC−03:00. Isso dispensa instalar as tabelas de fuso do MySQL.
    $db->exec("SET SESSION time_zone = '-03:00'");
    return $db;
}

function db_statement(PDO $db, string $sql, array $params): PDOStatement
{
    $statement = $db->prepare($sql);
    foreach ($params as $key => $value) {
        $type = match (true) {
            is_int($value) => PDO::PARAM_INT,
            is_bool($value) => PDO::PARAM_BOOL,
            $value === null => PDO::PARAM_NULL,
            default => PDO::PARAM_STR,
        };
        $statement->bindValue(is_int($key) ? $key + 1 : $key, $value, $type);
    }
    $statement->execute();
    return $statement;
}

function db_all(PDO $db, string $sql, array $params = []): array
{
    return db_statement($db, $sql, $params)->fetchAll();
}

function db_one(PDO $db, string $sql, array $params = []): ?array
{
    $row = db_statement($db, $sql, $params)->fetch(PDO::FETCH_ASSOC);
    return $row === false ? null : $row;
}

function db_value(PDO $db, string $sql, array $params = []): mixed
{
    $value = db_statement($db, $sql, $params)->fetchColumn();
    return $value === false ? null : $value;
}

function db_exec(PDO $db, string $sql, array $params = []): int
{
    return db_statement($db, $sql, $params)->rowCount();
}

function transaction(PDO $db, callable $work): mixed
{
    $db->beginTransaction();
    try {
        $result = $work($db);
        $db->commit();
        return $result;
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    }
}

function audit(PDO $db, ?int $userId, string $operation, string $entity, int $recordId, string $details = ''): void
{
    db_exec($db, 'INSERT INTO auditoria (usuario_id, operacao, entidade, registro_id, detalhes) VALUES (?, ?, ?, ?, ?)',
        [$userId, $operation, $entity, $recordId, $details]);
}
