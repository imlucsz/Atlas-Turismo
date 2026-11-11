<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/catalog.php';
require_once __DIR__ . '/../views/catalog.php';

function catalog_controller(PDO $db, string $page): void
{
    if (!in_array($page, ['clientes', 'destinos', 'pacotes', 'usuarios'], true)) {
        error_page(404, 'Cadastro não encontrado.');
    }
    if ($page === 'usuarios') {
        require_admin();
    }
    $action = (string) ($_GET['a'] ?? '');
    $id = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);
    $id = $id === false || $id < 1 ? 0 : $id;
    $actor = (int) user()['id'];
    if (is_post()) {
        $postedId = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
        $postedId = $postedId === false || $postedId < 1 ? 0 : $postedId;
        try {
            if ($action === 'status') {
                if ($id < 1) error_page(400, 'Identificador de cadastro inválido.');
                $active = field_int($_POST, 'ativo', 0, 1) === 1;
                catalog_set_active($db, $page, $id, $active, $actor);
                flash('success', $active ? 'Cadastro ativado.' : 'Cadastro inativado.');
                redirect($page);
            }
            if (!in_array($action, ['novo', 'editar'], true)) {
                error_page(405, 'Ação inválida.');
            }
            if ($action === 'editar' && ($id < 1 || $postedId < 1 || $id !== $postedId)) {
                error_page(400, 'O cadastro enviado não corresponde ao cadastro aberto.');
            }
            $savedId = catalog_store($db, $page, $_POST, $action === 'novo' ? null : $postedId, $actor);
            flash('success', $action === 'novo' ? 'Cadastro criado.' : 'Alterações salvas.');
            redirect($page, ['a' => 'ver', 'id' => $savedId]);
        } catch (DomainException $error) {
            flash('error', $error->getMessage());
            redirect($page, $action === 'novo' ? ['a' => 'novo'] : ['a' => 'editar', 'id' => $postedId]);
        } catch (PDOException $error) {
            $duplicate = (string) ($error->errorInfo[1] ?? '') === '1062';
            if (!$duplicate) log_error($error);
            flash('error', $duplicate ? 'Já existe um cadastro com esse e-mail ou documento.' : 'Não foi possível salvar. Confira os dados relacionados e tente novamente.');
            redirect($page, $action === 'novo' ? ['a' => 'novo'] : ['a' => 'editar', 'id' => $postedId]);
        }
    }
    catalog_view($db, $page, $action, $id, $_GET);
}
