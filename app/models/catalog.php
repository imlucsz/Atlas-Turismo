<?php
declare(strict_types=1);

function catalog_find(PDO $db, string $page, int $id): ?array
{
    $queries = [
        'clientes' => ['SELECT * FROM clientes WHERE id = :id', ['id' => $id]],
        'destinos' => ['SELECT * FROM destinos WHERE id = :id', ['id' => $id]],
        'pacotes' => ['SELECT p.*, d.cidade, d.estado, d.pais FROM pacotes p JOIN destinos d ON d.id = p.destino_id WHERE p.id = :id', ['id' => $id]],
        'usuarios' => ['SELECT id, nome, email, perfil, ativo, criado_em FROM usuarios WHERE id = :id', ['id' => $id]],
    ];
    if (!isset($queries[$page])) {
        return null;
    }
    return db_one($db, $queries[$page][0], $queries[$page][1]);
}

function catalog_list(PDO $db, string $page, string $search, ?int $active, int $limit, int $offset): array
{
    $like = '%' . $search . '%';
    $status = $active === null ? -1 : $active;
    $definitions = [
        'clientes' => [
            "SELECT id, nome, email, telefone, documento, ativo FROM clientes WHERE (:status = -1 OR ativo = :active) AND (:all = '' OR nome LIKE :name OR email LIKE :email OR telefone LIKE :phone OR documento LIKE :document) ORDER BY nome, id LIMIT :limit OFFSET :offset",
            ['status' => $status, 'active' => $active ?? 0, 'all' => $search, 'name' => $like, 'email' => $like, 'phone' => $like, 'document' => $like],
        ],
        'destinos' => [
            "SELECT id, cidade, estado, pais, descricao, ativo FROM destinos WHERE (:status = -1 OR ativo = :active) AND (:all = '' OR cidade LIKE :city OR estado LIKE :state OR pais LIKE :country) ORDER BY pais, cidade, id LIMIT :limit OFFSET :offset",
            ['status' => $status, 'active' => $active ?? 0, 'all' => $search, 'city' => $like, 'state' => $like, 'country' => $like],
        ],
        'pacotes' => [
            "SELECT p.id, p.titulo, p.duracao_dias, p.preco_base, p.ativo, d.cidade, d.estado, d.pais FROM pacotes p JOIN destinos d ON d.id = p.destino_id WHERE (:status = -1 OR p.ativo = :active) AND (:all = '' OR p.titulo LIKE :title OR d.cidade LIKE :city OR d.pais LIKE :country) ORDER BY p.titulo, p.id LIMIT :limit OFFSET :offset",
            ['status' => $status, 'active' => $active ?? 0, 'all' => $search, 'title' => $like, 'city' => $like, 'country' => $like],
        ],
        'usuarios' => [
            "SELECT id, nome, email, perfil, ativo FROM usuarios WHERE (:status = -1 OR ativo = :active) AND (:all = '' OR nome LIKE :name OR email LIKE :email) ORDER BY nome, id LIMIT :limit OFFSET :offset",
            ['status' => $status, 'active' => $active ?? 0, 'all' => $search, 'name' => $like, 'email' => $like],
        ],
    ];
    if (!isset($definitions[$page])) {
        return ['items' => [], 'total' => 0];
    }
    [$sql, $params] = $definitions[$page];
    $items = db_all($db, $sql, $params + ['limit' => $limit, 'offset' => $offset]);
    $countSql = match ($page) {
        'clientes' => "SELECT COUNT(*) FROM clientes WHERE (:status = -1 OR ativo = :active) AND (:all = '' OR nome LIKE :name OR email LIKE :email OR telefone LIKE :phone OR documento LIKE :document)",
        'destinos' => "SELECT COUNT(*) FROM destinos WHERE (:status = -1 OR ativo = :active) AND (:all = '' OR cidade LIKE :city OR estado LIKE :state OR pais LIKE :country)",
        'pacotes' => "SELECT COUNT(*) FROM pacotes p JOIN destinos d ON d.id = p.destino_id WHERE (:status = -1 OR p.ativo = :active) AND (:all = '' OR p.titulo LIKE :title OR d.cidade LIKE :city OR d.pais LIKE :country)",
        default => "SELECT COUNT(*) FROM usuarios WHERE (:status = -1 OR ativo = :active) AND (:all = '' OR nome LIKE :name OR email LIKE :email)",
    };
    $total = (int) db_value($db, $countSql, $params);
    return ['items' => $items, 'total' => $total];
}

function catalog_destinations(PDO $db, ?int $currentId = null): array
{
    return db_all($db, 'SELECT id, cidade, estado, pais, ativo FROM destinos WHERE ativo = 1 OR id = :id ORDER BY pais, cidade', ['id' => $currentId ?? 0]);
}

function catalog_store(PDO $db, string $page, array $input, ?int $id, int $actorId): int
{
    $fields = catalog_validate($page, $input, $id === null);
    return transaction($db, function () use ($db, $page, $fields, $id, $actorId): int {
        $lockedAdmins = [];
        if ($page === 'usuarios') {
            $lockedAdmins = db_all($db, "SELECT id FROM usuarios WHERE perfil = 'administrador' AND ativo = 1 ORDER BY id FOR UPDATE");
            if (!in_array($actorId, array_column($lockedAdmins, 'id'), false)) {
                throw new DomainException('É necessário estar ativo como administrador para alterar usuários.');
            }
        }
        if ($id === null) {
            if ($page === 'pacotes') {
                $destination = db_one($db, 'SELECT ativo FROM destinos WHERE id = :id FOR UPDATE', ['id' => $fields['destino_id']]);
                if (!$destination || (int) $destination['ativo'] !== 1) {
                    throw new DomainException('Selecione um destino ativo.');
                }
            }
            $columns = implode(', ', array_keys($fields));
            $placeholders = implode(', ', array_map(static fn(string $key): string => ':' . $key, array_keys($fields)));
            db_exec($db, "INSERT INTO {$page} ({$columns}) VALUES ({$placeholders})", $fields);
            $savedId = (int) $db->lastInsertId();
            audit($db, $actorId, 'criar', $page, $savedId);
            return $savedId;
        }
        if ($page === 'usuarios') {
            $target = db_one($db, 'SELECT perfil, ativo FROM usuarios WHERE id = :id FOR UPDATE', ['id' => $id]);
            if (!$target) {
                throw new DomainException('Usuário não encontrado.');
            }
            $removesAdmin = $target['perfil'] === 'administrador' && (int) $target['ativo'] === 1
                && (($fields['perfil'] ?? 'administrador') !== 'administrador' || (int) ($fields['ativo'] ?? 1) !== 1);
            if ($removesAdmin && count($lockedAdmins) <= 1) {
                throw new DomainException('O último administrador ativo não pode perder o acesso administrativo.');
            }
            if ($id === $actorId && (($fields['perfil'] ?? 'administrador') !== 'administrador' || (int) ($fields['ativo'] ?? 1) !== 1)) {
                throw new DomainException('Você não pode desativar seu próprio acesso administrativo.');
            }
        }
        if ($page === 'pacotes') {
            $package = db_one($db, 'SELECT destino_id FROM pacotes WHERE id = :id FOR UPDATE', ['id' => $id]);
            if (!$package) {
                throw new DomainException('Pacote não encontrado.');
            }
            $destination = db_one($db, 'SELECT ativo FROM destinos WHERE id = :id FOR UPDATE', ['id' => $fields['destino_id']]);
            $unchangedDestination = (int) $package['destino_id'] === (int) $fields['destino_id'];
            if (!$unchangedDestination && db_value($db, 'SELECT id FROM viagens WHERE pacote_id = ? LIMIT 1', [$id])) {
                throw new DomainException('Um pacote vinculado a viagens deve manter o destino. Cadastre outro pacote para o novo roteiro.');
            }
            if (!$destination || ((int) $destination['ativo'] !== 1 && !$unchangedDestination)) {
                throw new DomainException('Selecione um destino ativo. Um pacote existente pode manter seu destino atual.');
            }
        }
        if (!in_array($page, ['usuarios', 'pacotes'], true)
            && !db_one($db, "SELECT id FROM {$page} WHERE id = :id FOR UPDATE", ['id' => $id])) {
            throw new DomainException('Cadastro não encontrado.');
        }
        $assignments = implode(', ', array_map(static fn(string $key): string => "{$key} = :{$key}", array_keys($fields)));
        db_exec($db, "UPDATE {$page} SET {$assignments} WHERE id = :id", $fields + ['id' => $id]);
        audit($db, $actorId, 'editar', $page, $id);
        return $id;
    });
}

function catalog_validate(string $page, array $input, bool $isNew = false): array
{
    $name = static function (string $key, int $max, bool $required = true) use ($input): ?string {
        return field_string($input, $key, $max, $required);
    };
    return match ($page) {
        'clientes' => [
            'nome' => $name('nome', 140),
            'email' => catalog_email($name('email', 190, false)),
            'telefone' => $name('telefone', 30, false),
            'documento' => catalog_document($name('documento', 40, false)),
            'observacoes' => $name('observacoes', 1000, false),
        ],
        'destinos' => [
            'cidade' => $name('cidade', 100),
            'estado' => $name('estado', 100, false),
            'pais' => $name('pais', 100),
            'descricao' => $name('descricao', 1000, false),
        ],
        'pacotes' => [
            'destino_id' => field_int($input, 'destino_id'),
            'titulo' => $name('titulo', 150),
            'descricao' => $name('descricao', 1500, false),
            'duracao_dias' => field_int($input, 'duracao_dias', 1, 365),
            'preco_base' => catalog_price($input),
        ],
        'usuarios' => catalog_user_fields($input, $isNew),
        default => throw new DomainException('Cadastro desconhecido.'),
    };
}

function catalog_price(array $input): string
{
    $cents = money_cents(field_string($input, 'preco_base', 13));
    if ($cents < 1) {
        throw new DomainException('O preço-base deve ser maior que zero.');
    }
    return decimal($cents);
}

function catalog_email(?string $email): ?string
{
    $email = $email === null ? null : mb_strtolower(trim($email), 'UTF-8');
    if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        throw new DomainException('Informe um e-mail válido.');
    }
    return $email;
}

function catalog_document(?string $document): ?string
{
    $document = $document === null ? null : preg_replace('/[^0-9A-Za-z]/', '', $document);
    if ($document !== null && $document === '') {
        return null;
    }
    return $document;
}

function catalog_user_fields(array $input, bool $isNew): array
{
    $fields = [
        'nome' => field_string($input, 'nome', 120),
        'email' => catalog_email(field_string($input, 'email', 190)),
        'perfil' => field_choice($input, 'perfil', ['administrador', 'funcionario']),
    ];
    if (array_key_exists('ativo', $input)) {
        $fields['ativo'] = field_int($input, 'ativo', 0, 1);
    }
    $password = $input['senha'] ?? '';
    if (!is_string($password)) {
        throw new DomainException('Informe uma senha válida.');
    }
    if ($password !== '') {
        $length = strlen($password);
        if ($length < 12 || $length > 72) {
            throw new DomainException('A senha deve ter entre 12 e 72 bytes.');
        }
        $fields['senha_hash'] = password_hash($password, PASSWORD_DEFAULT);
    } elseif ($isNew) {
        throw new DomainException('Defina uma senha com pelo menos 12 caracteres.');
    }
    return $fields;
}

function catalog_set_active(PDO $db, string $page, int $id, bool $active, int $actorId): void
{
    if (!in_array($page, ['clientes', 'destinos', 'pacotes', 'usuarios'], true)) {
        throw new DomainException('Cadastro desconhecido.');
    }
    if ($page === 'usuarios') {
        require_admin();
        if ($id === $actorId && !$active) {
            throw new DomainException('Você não pode desativar seu próprio usuário.');
        }
    }
    transaction($db, function () use ($db, $page, $id, $active, $actorId): void {
        if ($page === 'usuarios') {
            $admins = db_all($db, "SELECT id FROM usuarios WHERE perfil = 'administrador' AND ativo = 1 ORDER BY id FOR UPDATE");
            if (!in_array($actorId, array_column($admins, 'id'), false)) {
                throw new DomainException('É necessário estar ativo como administrador para alterar usuários.');
            }
            $target = db_one($db, 'SELECT perfil, ativo FROM usuarios WHERE id = :id FOR UPDATE', ['id' => $id]);
            if (!$target) {
                throw new DomainException('Usuário não encontrado.');
            }
            if (!$active && $target['perfil'] === 'administrador' && (int) $target['ativo'] === 1) {
                if (count($admins) <= 1) {
                    throw new DomainException('O último administrador ativo não pode ser desativado.');
                }
            }
        }
        $record = db_one($db, "SELECT id FROM {$page} WHERE id = :id FOR UPDATE", ['id' => $id]);
        if (!$record) {
            throw new DomainException('Cadastro não encontrado.');
        }
        db_exec($db, "UPDATE {$page} SET ativo = :ativo WHERE id = :id", ['ativo' => (int) $active, 'id' => $id]);
        audit($db, $actorId, $active ? 'ativar' : 'inativar', $page, $id);
    });
}

