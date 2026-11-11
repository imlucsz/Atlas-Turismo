<?php
declare(strict_types=1);

function catalog_view(PDO $db, string $page, string $action, int $id, array $filters): void
{
    $titles = ['clientes' => 'Clientes', 'destinos' => 'Destinos', 'pacotes' => 'Pacotes', 'usuarios' => 'Usuários'];
    $title = $titles[$page];
    if (in_array($action, ['novo', 'editar', 'ver'], true)) {
        $row = $id ? catalog_find($db, $page, $id) : null;
        if ($id && !$row) error_page(404, 'Cadastro não encontrado.');
        render($action === 'ver' ? $title : ($action === 'editar' ? 'Editar cadastro' : 'Novo cadastro'), static function () use ($db, $page, $action, $id, $row): void {
            if ($action === 'ver') { catalog_detail($page, $row); return; }
            catalog_form($db, $page, $action, $id, $row ?? []);
        }, 'Cadastros');
        return;
    }
    $query = trim((string) ($filters['q'] ?? ''));
    $activeFilter = $filters['ativo'] ?? '';
    $active = $activeFilter === '0' || $activeFilter === '1' ? (int) $activeFilter : null;
    $pageNo = max(1, (int) ($filters['pagina'] ?? 1));
    $result = catalog_list($db, $page, $query, $active, 20, ($pageNo - 1) * 20);
    $pageCount = max(1, (int) ceil($result['total'] / 20));
    if ($pageNo > $pageCount) {
        $pageNo = $pageCount;
        $result = catalog_list($db, $page, $query, $active, 20, ($pageNo - 1) * 20);
    }
    render($title, static function () use ($page, $title, $query, $activeFilter, $pageNo, $result, $pageCount): void {
        echo '<section class="panel"><div class="toolbar"><form method="get" class="toolbar"><input type="hidden" name="p" value="'.h($page).'">';
        echo '<label class="field">Buscar<input type="search" name="q" value="'.h($query).'"></label><label class="field">Situação<select name="ativo"><option value="">Todas</option><option value="1"'.($activeFilter==='1'?' selected':'').'>Ativas</option><option value="0"'.($activeFilter==='0'?' selected':'').'>Inativas</option></select></label><button class="button secondary">Filtrar</button></form><a class="button" href="'.h(url($page,['a'=>'novo'])).'">Novo cadastro</a></div>';
        if (!$result['items']) { echo '<p class="empty">Nenhum cadastro encontrado.</p>'; }
        else {
            $cols = match($page) { 'clientes'=>['Nome','E-mail','Telefone','Documento','Situação'], 'destinos'=>['Cidade','Estado','País','Descrição','Situação'], 'pacotes'=>['Pacote','Destino','Duração','Preço-base','Situação'], default=>['Nome','E-mail','Perfil','Situação'] };
            echo '<div class="table-wrap"><table><thead><tr>'; foreach($cols as $col) echo '<th scope="col">'.h($col).'</th>'; echo '<th scope="col">Ações</th></tr></thead><tbody>';
            foreach($result['items'] as $r) {
                $id=(int)$r['id']; $active=(int)$r['ativo']===1;
                $values=match($page) { 'clientes'=>[$r['nome'],$r['email']?:'—',$r['telefone']?:'—',$r['documento']?:'—',$active?'Ativo':'Inativo'], 'destinos'=>[$r['cidade'],$r['estado']?:'—',$r['pais'],$r['descricao']?:'—',$active?'Ativo':'Inativo'], 'pacotes'=>[$r['titulo'],$r['cidade'].', '.$r['pais'],$r['duracao_dias'].' dias',brl((string)$r['preco_base']),$active?'Ativo':'Inativo'], default=>[$r['nome'],$r['email'],$r['perfil']==='administrador'?'Administrador':'Funcionário',$active?'Ativo':'Inativo'] };
                echo '<tr>'; foreach($values as $v) echo '<td>'.h((string)$v).'</td>'; echo '<td><div class="actions"><a class="button secondary" href="'.h(url($page,['a'=>'ver','id'=>$id])).'">Ver</a><a class="button secondary" href="'.h(url($page,['a'=>'editar','id'=>$id])).'">Editar</a><form method="post" action="'.h(url($page,['a'=>'status','id'=>$id])).'">'.csrf_field().'<input type="hidden" name="ativo" value="'.($active?'0':'1').'"><button class="button '.($active?'danger':'secondary').'">'.($active?'Inativar':'Ativar').'</button></form></div></td></tr>';
            }
            echo '</tbody></table></div>'; pagination($page,['q'=>$query,'ativo'=>$activeFilter],$pageNo,$pageCount);
        }
        echo '</section>';
    }, 'Gerencie cadastros e dados da agência');
}

function catalog_form(PDO $db, string $page, string $action, int $id, array $r): void
{
    echo '<section class="panel"><form method="post" class="form-grid">'.csrf_field().'<input type="hidden" name="id" value="'.$id.'">';
    if($page==='clientes') { catalog_input('nome','Nome completo',$r,true); catalog_input('email','E-mail',$r,false,'email'); catalog_input('telefone','Telefone',$r); catalog_input('documento','Documento',$r); catalog_area('observacoes','Observações',$r); }
    elseif($page==='destinos') { catalog_input('cidade','Cidade',$r,true); catalog_input('estado','Estado',$r); catalog_input('pais','País',$r,true); catalog_area('descricao','Descrição',$r); }
    elseif($page==='pacotes') {
        echo '<label class="field">Destino<select name="destino_id" required><option value="">Selecione</option>';
        foreach(catalog_destinations($db, isset($r['destino_id']) ? (int)$r['destino_id'] : null) as $d) echo '<option value="'.(int)$d['id'].'"'.((int)($r['destino_id']??0)===(int)$d['id']?' selected':'').'>'.h($d['cidade'].' — '.$d['pais'].((int)$d['ativo']===1?'':' (destino inativo, somente para este pacote)')).'</option>';
        echo '</select></label>'; catalog_input('titulo','Nome do pacote',$r,true); catalog_input('duracao_dias','Duração em dias',$r,true,'number',' min="1" max="365"'); catalog_input('preco_base','Preço-base',$r,true,'number',' min="0.01" max="9999999999.99" step="0.01"'); catalog_area('descricao','Descrição',$r);
    } else {
        require_admin(); catalog_input('nome','Nome completo',$r,true); catalog_input('email','E-mail',$r,true,'email');
        echo '<label class="field">Perfil<select name="perfil" required>'; foreach(['administrador'=>'Administrador','funcionario'=>'Funcionário'] as $v=>$l) echo '<option value="'.$v.'"'.(($r['perfil']??'funcionario')===$v?' selected':'').'>'.$l.'</option>'; echo '</select></label>';
        catalog_input('senha',$action==='novo'?'Senha (mínimo 12 caracteres)':'Nova senha (opcional)',[], $action==='novo','password',' minlength="12" maxlength="72" autocomplete="new-password"');
    }
    echo '<div class="actions full"><button class="button">Salvar</button><a class="button secondary" href="'.h(url($page)).'">Cancelar</a></div></form></section>';
}

function catalog_input(string $name,string $label,array $r,bool $required=false,string $type='text',string $attrs=''): void
{
    $value=(string)($r[$name]??''); if($name==='preco_base'&&$value!=='') $value=number_format((float)$value,2,'.','');
    echo '<label class="field">'.h($label).'<input type="'.h($type).'" name="'.h($name).'" value="'.h($value).'"'.($required?' required':'').$attrs.'></label>';
}
function catalog_area(string $name,string $label,array $r): void { echo '<label class="field full">'.h($label).'<textarea name="'.h($name).'" rows="3">'.h((string)($r[$name]??'')).'</textarea></label>'; }

function catalog_detail(string $page,array $r): void
{
    $fields=match($page) { 'clientes'=>['Nome'=>'nome','E-mail'=>'email','Telefone'=>'telefone','Documento'=>'documento','Observações'=>'observacoes'], 'destinos'=>['Cidade'=>'cidade','Estado'=>'estado','País'=>'pais','Descrição'=>'descricao'], 'pacotes'=>['Pacote'=>'titulo','Destino'=>'cidade','País'=>'pais','Duração'=>'duracao_dias','Preço-base'=>'preco_base','Descrição'=>'descricao'], default=>['Nome'=>'nome','E-mail'=>'email','Perfil'=>'perfil'] };
    echo '<section class="panel"><dl class="detail-grid">'; foreach($fields as $label=>$key) { $v=$r[$key]??''; if($key==='preco_base') $v=brl((string)$v); echo '<div><dt>'.h($label).'</dt><dd>'.h((string)($v?:'—')).'</dd></div>'; } echo '<div><dt>Situação</dt><dd>'.((int)($r['ativo']??0)?'Ativo':'Inativo').'</dd></div></dl><div class="actions"><a class="button secondary" href="'.h(url($page)).'">Voltar</a><a class="button" href="'.h(url($page,['a'=>'editar','id'=>(int)$r['id']])).'">Editar</a></div></section>';
}
