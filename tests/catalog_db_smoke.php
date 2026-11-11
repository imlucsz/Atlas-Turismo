<?php
declare(strict_types=1);
require __DIR__ . '/../app/core/db.php';
require __DIR__ . '/../app/core/validation.php';
require __DIR__ . '/../app/config/config.php';
require __DIR__ . '/../app/models/catalog.php';
$db = db_connect(atlas_config());
foreach (['clientes','destinos','pacotes','usuarios'] as $page) {
  $result = catalog_list($db, $page, '', null, 20, 0);
  if (!isset($result['items'], $result['total']) || $result['total'] < count($result['items'])) { throw new RuntimeException('Paginação inválida em ' . $page); }
  catalog_list($db, $page, 'sem-resultados-para-teste', null, 20, 0);
  catalog_list($db, $page, '', 1, 20, 0);
  catalog_list($db, $page, '', 0, 20, 0);
}
catalog_destinations($db);
echo "OK: listagem, contagem, busca e destinos ativos consultados no MySQL 8.4.\n";
