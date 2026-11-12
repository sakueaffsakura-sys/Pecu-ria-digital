<?php
declare(strict_types=1);

/** Qual cadastro uma seção mostra (algumas seções têm abas, como no original). */
function modulo_da_secao(string $secao, array $get): ?array
{
    return match ($secao) {
        'vacinacao' => modulo(($get['aba'] ?? '') === 'tipos' ? 'tipos_vacinas' : 'vacinacao'),
        'producao' => modulo(($get['aba'] ?? '') === 'anotacoes' ? 'anotacoes' : 'producao'),
        'comercial' => modulo(($get['aba'] ?? '') === 'vendas' ? 'vendas' : 'compras'),
        'rebanho', 'lotes', 'bezerros', 'inseminacao', 'nascimentos', 'financeiro' => modulo($secao),
        default => null,
    };
}

/** Abas de cada seção: [rótulo, parâmetro aba]. */
function abas_da_secao(string $secao): array
{
    return match ($secao) {
        'vacinacao' => [['Aplicações', ''], ['Tipos de vacina', 'tipos']],
        'producao' => [['Registros de leite', ''], ['Anotações', 'anotacoes']],
        'comercial' => [['Compras', ''], ['Vendas', 'vendas']],
        default => [],
    };
}

function rota_app(string $secao): never
{
    exigir_login();
    if (!preg_match('/^[a-z-]+$/', $secao)) pagina_erro(404, 'Esta área não existe.');
    $prop = propriedade();
    $semPropriedade = ['perfil', 'seguranca', 'aplicativo', 'ajuda'];
    if (!$prop && !in_array($secao, $semPropriedade, true)) {
        tela('sem_propriedade', ['titulo' => 'Comece pela sua propriedade', 'secao' => $secao]);
    }
    if ($secao === 'inicio') rota_painel();
    if ($secao === 'agenda') rota_agenda();
    if ($secao === 'relatorios') rota_relatorios();
    if ($secao === 'indicadores') rota_indicadores();
    if ($secao === 'diagnostico') rota_diagnostico();
    if ($secao === 'ferramentas') rota_ferramentas();
    if ($secao === 'perfil-propriedade' || $secao === 'configuracoes') rota_dados_propriedade();
    $m = modulo_da_secao($secao, $_GET + $_POST);
    if (!$m) tela('area_inexistente', ['titulo' => 'Esta área não existe', 'secao' => $secao]);
    rota_cadastro($secao, $m);
}

/** Lista + formulário (num quadro por cima, como os diálogos do original) + ficha do animal. */
function rota_cadastro(string $secao, array $m): never
{
    $aba = (string) ($_GET['aba'] ?? $_POST['aba'] ?? '');
    $base = '/app/' . $secao;
    $voltar = fn(array $extra = []) => url("app/$secao", array_filter(['aba' => $aba] + $extra));
    $form = null;
    $importacao = null;

    if (eh_post()) {
        conferir_csrf();
        $acao = (string) ($_POST['acao'] ?? '');
        if ($acao === 'importar_analisar' || $acao === 'importar_iniciar') {
            exigir_edicao();
            $importacao = $acao === 'importar_analisar' ? importar_analisar($m, $_FILES['arquivo'] ?? []) : importar_iniciar($m, (string) ($_POST['importacao'] ?? ''));
            $importacao['passo'] = isset($importacao['erro']) ? 'erro' : ($acao === 'importar_analisar' ? 'previa' : 'pronto');
        }
        $id = (string) ($_POST['id'] ?? '');
        $linha = $id !== '' ? crud_buscar($m, $id) : null;
        if ($id !== '' && !$linha) pagina_erro(404, 'Esse registro não foi encontrado nesta propriedade.');
        if ($acao === 'excluir' && $linha) {
            $erro = crud_excluir($m, $linha);
            aviso($erro ? 'erro' : 'ok', $erro ?? 'Registro excluído.');
            header('Location: ' . $voltar(), true, 303);
            exit;
        }
        if ($acao === 'marcar' && $linha && $m['tabela'] === 'anotacoes_producao') {
            exigir_edicao();
            q('UPDATE anotacoes_producao SET marcada = ?, updated_at = ? WHERE id = ? AND propriedade_id = ?', [(int) $linha['marcada'] === 1 ? 0 : 1, agora(), $linha['id'], pid()]);
            header('Location: ' . $voltar(array_intersect_key($_POST, ['aba_notas' => 1, 'busca' => 1])), true, 303);
            exit;
        }
        if ($acao === 'salvar' && !isset($importacao)) {
            $r = crud_salvar($m, $linha, $_POST);
            if (isset($r['id'])) {
                if ($r['criados']) aviso('ok', 'Cadastro criado automaticamente: ' . implode(', ', $r['criados']) . '.');
                aviso('ok', $linha ? 'Alterações salvas.' : ($m['tabela'] === 'anotacoes_producao' ? 'Anotação criada com sucesso.' : ucfirst($m['singular']) . ' cadastrado.'));
                header('Location: ' . $voltar(), true, 303);
                exit;
            }
            $valores = [];
            foreach ($m['campos'] as $k => $_) $valores[$k] = is_string($_POST[$k] ?? null) ? $_POST[$k] : '';
            $form = ['linha' => $linha, 'valores' => $valores, 'erros' => $r['erros']];
            http_response_code(422);
        }
    }

    if (!$form && ($_GET['novo'] ?? '') === '1') {
        $form = ['linha' => null, 'valores' => valores_iniciais($m, null, $_GET), 'erros' => []];
    }
    if (!$form && !empty($_GET['editar'])) {
        $linha = crud_buscar($m, (string) $_GET['editar']);
        if (!$linha) aviso('erro', 'Esse registro não foi encontrado nesta propriedade.');
        else $form = ['linha' => $linha, 'valores' => valores_iniciais($m, $linha), 'erros' => []];
    }
    $ficha = null;
    if ($m['tabela'] === 'animais' && !empty($_GET['animal'])) {
        $ficha = ficha_animal((string) $_GET['animal']);
        if (!$ficha) aviso('erro', 'Esse registro não foi encontrado nesta propriedade.');
    }

    $r = crud_listar($m, $_GET);
    $get = array_intersect_key($_GET, array_flip(['busca', 'periodo', 'dia', 'por', 'aba', 'aba_notas']));
    $lista_animais = $form ? lista_animais_form() : [];
    tela($m['tabela'] === 'anotacoes_producao' ? 'anotacoes' : 'cadastro', compact('secao', 'm', 'r', 'form', 'ficha', 'get', 'base', 'aba', 'lista_animais', 'importacao') + ['titulo' => $m['titulo']]);
}

/** Animais para as sugestões dos campos (brinco e nome). */
function lista_animais_form(): array
{
    return todos("SELECT id, numero_brinco, nome, sexo, situacao FROM animais WHERE propriedade_id = ? ORDER BY numero_brinco LIMIT 5000", [pid()]);
}

/** Ficha do animal: cadastro, família e linha do tempo. */
function ficha_animal(string $id): ?array
{
    if (!eh_uuid($id)) return null;
    $p = pid();
    $a = um('SELECT a.*, l.nome AS lote, m.nome AS mae_nome, m.numero_brinco AS mae_brinco, pa.nome AS pai_nome, pa.numero_brinco AS pai_brinco
        FROM animais a LEFT JOIN lotes l ON l.id = a.lote_id LEFT JOIN animais m ON m.id = a.mae_id LEFT JOIN animais pa ON pa.id = a.pai_id
        WHERE a.id = ? AND a.propriedade_id = ?', [$id, $p]);
    if (!$a) return null;
    $pesos = todos('SELECT id, data, peso_kg, observacoes FROM pesagens WHERE animal_id = ? ORDER BY data DESC LIMIT 200', [$id]);
    $ganho = [];
    $ordenados = array_reverse($pesos);
    for ($i = 1; $i < count($ordenados); $i++) {
        $dias = dias_entre($ordenados[$i - 1]['data'], $ordenados[$i]['data']);
        if ($dias > 0) $ganho[$ordenados[$i]['id']] = ((float) $ordenados[$i]['peso_kg'] - (float) $ordenados[$i - 1]['peso_kg']) / $dias;
    }
    $linha = [];
    foreach ($pesos as $x) $linha[] = ['data' => $x['data'], 'titulo' => 'Pesagem: ' . num_br((float) $x['peso_kg'], fmod((float) $x['peso_kg'], 1) == 0.0 ? 0 : 2) . ' kg',
        'detalhe' => isset($ganho[$x['id']]) ? num_br($ganho[$x['id']], 2) . ' kg por dia desde a pesagem anterior' : $x['observacoes']];
    foreach (todos('SELECT v.data_aplicacao, v.proxima_aplicacao, v.responsavel, tv.nome FROM vacinacoes v JOIN tipos_vacinas tv ON tv.id = v.tipo_vacina_id WHERE v.animal_id = ? ORDER BY v.data_aplicacao DESC', [$id]) as $x) {
        $linha[] = ['data' => $x['data_aplicacao'], 'titulo' => 'Vacina: ' . $x['nome'], 'detalhe' => $x['proxima_aplicacao'] ? 'Próxima dose em ' . data_br($x['proxima_aplicacao']) : $x['responsavel']];
    }
    foreach (todos('SELECT data_inseminacao, previsao_parto, situacao, reprodutor_semen FROM inseminacoes WHERE animal_id = ? ORDER BY data_inseminacao DESC', [$id]) as $x) {
        $linha[] = ['data' => $x['data_inseminacao'], 'titulo' => 'Inseminação: ' . bonito($x['situacao']), 'detalhe' => $x['reprodutor_semen'] . ' · parto previsto em ' . data_br($x['previsao_parto'])];
    }
    foreach (todos('SELECT data, litros, valor_total FROM producao_leite WHERE animal_id = ? ORDER BY data DESC LIMIT 200', [$id]) as $x) {
        $linha[] = ['data' => $x['data'], 'titulo' => 'Leite: ' . num_br((float) $x['litros'], fmod((float) $x['litros'], 1) == 0.0 ? 0 : 2) . ' litros', 'detalhe' => dinheiro($x['valor_total'])];
    }
    foreach (todos('SELECT data, observacao FROM anotacoes_producao WHERE animal_id = ? ORDER BY data DESC LIMIT 200', [$id]) as $x) {
        $linha[] = ['data' => $x['data'], 'titulo' => 'Anotação', 'detalhe' => $x['observacao']];
    }
    usort($linha, fn($x, $y) => strcmp($y['data'], $x['data']));
    $filhos = todos('SELECT id, nome, numero_brinco, data_nascimento, sexo FROM animais WHERE propriedade_id = ? AND (mae_id = ? OR pai_id = ?) ORDER BY data_nascimento DESC', [$p, $id, $id]);
    return ['animal' => $a, 'linha' => $linha, 'filhos' => $filhos];
}

function rota_trocar_propriedade(): never
{
    exigir_login();
    if (eh_post()) {
        conferir_csrf();
        escolher_propriedade((string) ($_POST['id'] ?? ''));
    }
    $volta = destino_seguro($_POST['volta'] ?? null);
    header('Location: ' . (preg_match('#^/app/[a-z-]+#', $volta, $mm) ? $mm[0] : '/app/inicio'), true, 303);
    exit;
}

function rota_nova_propriedade(): never
{
    exigir_login();
    if (!eh_post()) redirecionar('app/inicio');
    conferir_csrf();
    $nome = preg_replace('/\s+/', ' ', trim((string) ($_POST['nome'] ?? '')));
    $cidade = trim((string) ($_POST['cidade'] ?? '')) ?: null;
    $estado = strtoupper(trim((string) ($_POST['estado'] ?? ''))) ?: null;
    if (mb_strlen($nome) < 2 || mb_strlen($nome) > 120) {
        aviso('erro', 'Digite o nome da propriedade (2 a 120 letras).');
    } elseif ($cidade && mb_strlen($cidade) > 80 || $estado && !preg_match('/^[A-Z]{2}$/', $estado)) {
        aviso('erro', 'Confira a cidade e o estado (2 letras, como MG).');
    } else {
        $id = criar_propriedade($nome, $cidade, $estado);
        escolher_propriedade($id);
        aviso('ok', 'Propriedade cadastrada.');
    }
    redirecionar('app/inicio');
}

function rota_dados_propriedade(): never
{
    $prop = propriedade();
    $erros = [];
    if (eh_post()) {
        conferir_csrf();
        if (!pode_administrar()) pagina_erro(403, 'Só o dono ou um administrador da propriedade pode mudar estes dados.');
        $nome = preg_replace('/\s+/', ' ', trim((string) ($_POST['nome'] ?? '')));
        $cidade = trim((string) ($_POST['cidade'] ?? '')) ?: null;
        $estado = strtoupper(trim((string) ($_POST['estado'] ?? ''))) ?: null;
        $gest = (int) ($_POST['periodo_gestacao_dias'] ?? 283);
        if (mb_strlen($nome) < 2 || mb_strlen($nome) > 120) $erros['nome'] = 'Digite o nome (2 a 120 letras).';
        if ($cidade && mb_strlen($cidade) > 80) $erros['cidade'] = 'Nome da cidade longo demais.';
        if ($estado && !preg_match('/^[A-Z]{2}$/', $estado)) $erros['estado'] = 'Use as 2 letras do estado.';
        if ($gest < 260 || $gest > 300) $erros['periodo_gestacao_dias'] = 'Use entre 260 e 300 dias.';
        if (!$erros) {
            atualizar('propriedades', ['nome' => $nome, 'cidade' => $cidade, 'estado' => $estado, 'periodo_gestacao_dias' => $gest, 'updated_at' => agora()], 'id = ?', [$prop['id']]);
            aviso('ok', 'Dados da propriedade salvos.');
            redirecionar('app/perfil-propriedade');
        }
        $prop = array_merge($prop, array_map(fn($x) => is_string($x) ? $x : '', $_POST));
    }
    tela('dados_propriedade', ['titulo' => 'Dados da propriedade', 'prop' => $prop, 'erros' => $erros, 'secao' => 'perfil-propriedade']);
}

function rota_aviso_visto(): never
{
    $u = exigir_login();
    if (!eh_post()) redirecionar('app/inicio');
    conferir_csrf();
    $chave = mb_substr((string) ($_POST['chave'] ?? ''), 0, 190);
    $notaId = (string) ($_POST['notificacao'] ?? '');
    if ($notaId !== '' && eh_uuid($notaId)) {
        q('UPDATE notificacoes SET lida = 1 WHERE id = ? AND user_id = ?', [$notaId, $u['id']]);
    } elseif ($chave !== '' && propriedade()) {
        foreach (central_avisos(pid(), true) as $a) {
            if ($a['chave'] !== $chave) continue;
            if (!valor('SELECT 1 FROM notificacoes WHERE user_id = ? AND chave = ?', [$u['id'], $chave])) {
                inserir('notificacoes', ['user_id' => $u['id'], 'propriedade_id' => pid(), 'chave' => $chave, 'titulo' => $a['titulo'], 'mensagem' => $a['texto'], 'lida' => 1, 'created_at' => agora()]);
            }
        }
    }
    $volta = destino_seguro($_POST['volta'] ?? null);
    header('Location: ' . $volta, true, 303);
    exit;
}

/** Busca do topo (Ctrl K): páginas, animais e lotes. */
function rota_api_busca(): never
{
    exigir_login();
    $q = trim((string) ($_GET['q'] ?? ''));
    $paginas = [];
    foreach (grupos_menu() as $g) {
        foreach ($g['itens'] as [$chave, $rotulo, $ic, $frase]) {
            if (!in_array($chave, secoes_prontas(), true)) continue;
            if ($q === '' || str_contains(sem_acento_minusculo("$rotulo $frase"), sem_acento_minusculo($q))) {
                $paginas[] = ['tipo' => 'pagina', 'rotulo' => $rotulo, 'frase' => $frase, 'url' => url("app/$chave")];
            }
        }
    }
    $animais = [];
    $lotes = [];
    if (mb_strlen($q) >= 1 && propriedade()) {
        $like = '%' . addcslashes($q, '%_\\') . '%';
        foreach (todos('SELECT id, numero_brinco, nome, situacao FROM animais WHERE propriedade_id = ? AND (numero_brinco LIKE ? OR nome LIKE ?) ORDER BY numero_brinco LIMIT 8', [pid(), $like, $like]) as $a) {
            $animais[] = ['tipo' => 'animal', 'brinco' => $a['numero_brinco'], 'rotulo' => $a['nome'] ?: 'Brinco ' . $a['numero_brinco'], 'frase' => selos()[$a['situacao']][0] ?? $a['situacao'], 'url' => url('app/rebanho', ['animal' => $a['id']])];
        }
        foreach (todos('SELECT id, nome, finalidade FROM lotes WHERE propriedade_id = ? AND nome LIKE ? ORDER BY nome LIMIT 5', [pid(), $like]) as $l) {
            $lotes[] = ['tipo' => 'lote', 'rotulo' => $l['nome'], 'frase' => $l['finalidade'] ?: 'Lote', 'url' => url('app/lotes', ['editar' => $l['id']])];
        }
    }
    responder_json(['animais' => $animais, 'lotes' => $lotes, 'paginas' => array_slice($paginas, 0, 8)]);
}
