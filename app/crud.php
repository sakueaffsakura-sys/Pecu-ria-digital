<?php
declare(strict_types=1);

// ---------------------------------------------------------------------------
// Listar, cadastrar, editar, excluir e exportar qualquer módulo de app/modulos.php.
// Toda consulta leva "propriedade_id = <propriedade em uso>": ninguém vê dados de outra fazenda.
// ---------------------------------------------------------------------------

const POR_PAGINA = 50;

function campos_ligados(array $m): array
{
    return array_filter($m['campos'], fn($c) => in_array($c['tipo'], ['ref', 'animal'], true) && empty($c['virtual']));
}

function tabela_ligada(array $campo): string
{
    return $campo['tipo'] === 'animal' ? 'animais' : $campo['ref'];
}

/** SELECT com os nomes das tabelas ligadas (lote, vacina, brinco…). */
function crud_select(array $m): array
{
    $cols = ['t.*'];
    $joins = [];
    foreach (campos_ligados($m) as $nome => $c) {
        $tab = tabela_ligada($c);
        $alias = "r_$nome";
        $cols[] = "$alias." . rotulo_ref($tab) . " AS {$nome}__rotulo";
        $joins[] = "LEFT JOIN $tab $alias ON $alias.id = t.$nome";
    }
    if (!empty($m['contagem'])) $cols[] = $m['contagem']['sql'] . ' AS contagem';
    return [implode(', ', $cols), implode(' ', $joins)];
}

/** Filtros da tela (busca, período, opções) → [where, params, valores escolhidos]. */
function crud_filtros(array $m, array $get): array
{
    $where = ['t.propriedade_id = ?'];
    $p = [propriedade()['id']];
    $escolhido = [];

    $busca = trim((string) ($get['busca'] ?? ''));
    if ($busca !== '' && !empty($m['busca'])) {
        $where[] = '(' . implode(' OR ', array_map(fn($c) => "$c LIKE ?", $m['busca'])) . ')';
        foreach ($m['busca'] as $_) $p[] = '%' . addcslashes($busca, '%_\\') . '%';
        $escolhido['busca'] = $busca;
    }
    if (!empty($m['data'])) {
        foreach (['de' => '>=', 'ate' => '<='] as $chave => $op) {
            $d = ler_data($get[$chave] ?? null);
            if ($d) {
                $where[] = "t.{$m['data']} $op ?";
                $p[] = $d;
                $escolhido[$chave] = $d;
            }
        }
    }
    foreach ($m['filtros'] ?? [] as $f) {
        $v = (string) ($get[$f] ?? '');
        if ($v === '') continue;
        $campo = $m['campos'][$f];
        if ($campo['tipo'] === 'opcao' && !isset($campo['opcoes'][$v])) continue;
        if ($campo['tipo'] === 'ref' && !ctype_digit($v)) continue;
        $where[] = "t.$f = ?";
        $p[] = $v;
        $escolhido[$f] = $v;
    }
    return [implode(' AND ', $where), $p, $escolhido];
}

function crud_listar(array $m, array $get, bool $tudo = false): array
{
    [$cols, $joins] = crud_select($m);
    [$where, $p, $escolhido] = crud_filtros($m, $get);
    $tabela = $m['slug'];
    $total = (int) valor("SELECT COUNT(*) FROM $tabela t $joins WHERE $where", $p);
    $soma = !empty($m['total']) ? (float) valor("SELECT COALESCE(SUM(t.{$m['total']}), 0) FROM $tabela t $joins WHERE $where", $p) : null;
    $saldo = null;
    if (!empty($m['saldo'])) {
        $s = array_column(todos("SELECT t.tipo, COALESCE(SUM(t.valor), 0) AS v FROM $tabela t WHERE $where GROUP BY t.tipo", $p), 'v', 'tipo');
        $saldo = ['entradas' => (float) ($s['entrada'] ?? 0), 'saidas' => (float) ($s['saida'] ?? 0)];
    }
    $pagina = max(1, (int) ($get['pagina'] ?? 1));
    $limite = $tudo ? 50000 : POR_PAGINA;
    $offset = $tudo ? 0 : ($pagina - 1) * POR_PAGINA;
    $linhas = todos("SELECT $cols FROM $tabela t $joins WHERE $where ORDER BY {$m['ordem']} LIMIT $limite OFFSET $offset", $p);
    return compact('linhas', 'total', 'soma', 'saldo', 'pagina', 'escolhido');
}

function crud_buscar(array $m, int $id): ?array
{
    [$cols, $joins] = crud_select($m);
    return um("SELECT $cols FROM {$m['slug']} t $joins WHERE t.id = ? AND t.propriedade_id = ?", [$id, propriedade()['id']]);
}

/** Texto de uma célula da lista. */
function mostrar(array $m, string $col, array $linha): string
{
    if ($col === 'contagem') return num_br($linha['contagem'] ?? 0);
    $c = $m['campos'][$col];
    $v = $linha[$col] ?? null;
    if ($v === null || $v === '') return '—';
    return match ($c['tipo']) {
        'ref', 'animal' => (string) ($linha["{$col}__rotulo"] ?? '—'),
        'opcao' => $c['opcoes'][$v] ?? (string) $v,
        'data' => data_br((string) $v),
        'decimal' => num_br($v, fmod((float) $v, 1.0) == 0.0 ? 0 : 2),
        'dinheiro' => dinheiro($v),
        'inteiro' => num_br($v),
        'area' => mb_strimwidth((string) $v, 0, 90, '…'),
        default => (string) $v,
    };
}

// ----- Formulário ----------------------------------------------------------

function opcoes_ref(string $tabela): array
{
    $col = rotulo_ref($tabela);
    $linhas = todos("SELECT id, $col AS rotulo FROM $tabela WHERE propriedade_id = ? ORDER BY $col", [propriedade()['id']]);
    return array_column($linhas, 'rotulo', 'id');
}

/** Brincos para a lista de sugestões dos campos de animal. */
function brincos(?string $sexo = null): array
{
    $sql = "SELECT brinco, nome FROM animais WHERE propriedade_id = ? AND situacao = 'ativo'";
    $p = [propriedade()['id']];
    if ($sexo) {
        $sql .= ' AND sexo = ?';
        $p[] = $sexo;
    }
    return todos($sql . ' ORDER BY brinco LIMIT 3000', $p);
}

function campos_do_form(array $m, bool $novo): array
{
    return array_filter($m['campos'], fn($c) => !($novo && !empty($c['so_edicao'])) && !(!$novo && !empty($c['so_novo'])));
}

/** Valores iniciais: do registro (edição) ou padrões + o que veio no endereço (?animal_id=123 etc.). */
function valores_iniciais(array $m, ?array $linha, array $get): array
{
    $v = [];
    foreach ($m['campos'] as $nome => $c) {
        if ($linha) {
            $v[$nome] = $c['tipo'] === 'animal' ? ($linha["{$nome}__rotulo"] ?? '') : ($linha[$nome] ?? '');
            if (in_array($c['tipo'], ['decimal', 'dinheiro'], true) && $v[$nome] !== '' && $v[$nome] !== null) {
                $v[$nome] = rtrim(rtrim(number_format((float) $v[$nome], 2, ',', ''), '0'), ',');
            }
            continue;
        }
        $v[$nome] = !empty($c['padrao_hoje']) ? hoje() : ($c['padrao'] ?? '');
        if (isset($get[$nome]) && is_string($get[$nome])) $v[$nome] = mb_substr($get[$nome], 0, 120);
    }
    return $v;
}

/** Lê e confere o formulário. Devolve [dados para gravar, erros por campo]. */
function ler_formulario(array $m, array $post, bool $novo): array
{
    $dados = [];
    $erros = [];
    foreach (campos_do_form($m, $novo) as $nome => $c) {
        $bruto = trim((string) ($post[$nome] ?? ''));
        $rotulo = $c['rotulo'];
        if ($bruto === '') {
            if (!empty($c['obrigatorio'])) $erros[$nome] = "Preencha: $rotulo.";
            $dados[$nome] = null;
            continue;
        }
        switch ($c['tipo']) {
            case 'texto':
            case 'area':
                $max = $c['max'] ?? 20000;
                if (mb_strlen($bruto) > $max) $erros[$nome] = "$rotulo: no máximo $max caracteres.";
                $dados[$nome] = $bruto;
                break;
            case 'inteiro':
                if (!preg_match('/^-?\d+$/', $bruto)) { $erros[$nome] = "$rotulo: digite um número inteiro."; break; }
                $n = (int) $bruto;
                if (isset($c['min']) && $n < $c['min']) $erros[$nome] = "$rotulo: no mínimo {$c['min']}.";
                if (isset($c['max']) && $n > $c['max']) $erros[$nome] = "$rotulo: no máximo {$c['max']}.";
                $dados[$nome] = $n;
                break;
            case 'decimal':
            case 'dinheiro':
                $n = ler_decimal($bruto);
                if ($n === null) { $erros[$nome] = "$rotulo: número inválido."; break; }
                if (isset($c['min']) && $n < $c['min']) $erros[$nome] = "$rotulo: no mínimo " . num_br($c['min'], 2) . '.';
                if (isset($c['max']) && $n > $c['max']) $erros[$nome] = "$rotulo: no máximo " . num_br($c['max']) . '.';
                if ($n > 9999999999) $erros[$nome] = "$rotulo: valor alto demais.";
                $dados[$nome] = round($n, 2);
                break;
            case 'data':
                $d = ler_data($bruto);
                if (!$d) { $erros[$nome] = "$rotulo: data inválida."; break; }
                if (!empty($c['nao_futura']) && $d > hoje()) $erros[$nome] = "$rotulo: não pode ser no futuro.";
                if ($d < '1950-01-01' || $d > '2100-12-31') $erros[$nome] = "$rotulo: data fora do esperado.";
                $dados[$nome] = $d;
                break;
            case 'opcao':
                if (!isset($c['opcoes'][$bruto])) { $erros[$nome] = "$rotulo: escolha uma das opções."; break; }
                $dados[$nome] = $bruto;
                break;
            case 'ref':
                $ok = ctype_digit($bruto) && valor("SELECT id FROM {$c['ref']} WHERE id = ? AND propriedade_id = ?", [(int) $bruto, propriedade()['id']]);
                if (!$ok) { $erros[$nome] = "$rotulo: escolha uma opção da lista."; break; }
                $dados[$nome] = (int) $bruto;
                break;
            case 'animal':
                $a = um('SELECT id, sexo FROM animais WHERE propriedade_id = ? AND brinco = ?', [propriedade()['id'], $bruto]);
                if (!$a) { $erros[$nome] = "$rotulo: não achei animal com o brinco \"$bruto\"."; break; }
                if (!empty($c['sexo']) && $a['sexo'] !== $c['sexo']) { $erros[$nome] = "$rotulo: o animal $bruto não é " . ($c['sexo'] === 'F' ? 'fêmea' : 'macho') . '.'; break; }
                $dados[$nome] = (int) $a['id'];
                break;
        }
    }
    return [$dados, $erros];
}

// ----- Ações ---------------------------------------------------------------

function crud_salvar(array $m, ?array $linha, array $post): array
{
    $novo = $linha === null;
    $id = $novo ? null : (int) $linha['id'];
    [$dados, $erros] = ler_formulario($m, $post, $novo);
    if ($erros) return $erros;

    try {
        transacao(function () use ($m, &$dados, &$id, $novo) {
            if (isset($m['antes_salvar'])) {
                $r = $m['antes_salvar']($dados, $id);
                if (is_string($r)) throw new DomainException($r);
                $dados = $r;
            }
            foreach ($m['campos'] as $nome => $c) {
                if (!empty($c['virtual'])) unset($dados[$nome]);
            }
            if ($novo) {
                $id = inserir($m['slug'], $dados + ['propriedade_id' => propriedade()['id']]);
            } else {
                atualizar($m['slug'], $dados, 'id = ? AND propriedade_id = ?', [$id, propriedade()['id']]);
            }
            if (isset($m['depois_salvar'])) $m['depois_salvar']($id, $dados);
        });
    } catch (DomainException $e) {
        return ['_geral' => $e->getMessage()];
    } catch (PDOException $e) {
        $tipo = erro_de_integridade($e);
        if ($tipo === 'duplicado') return ['_geral' => $m['duplicado'] ?? 'Já existe um registro igual a este (mesmo nome ou número).'];
        if ($tipo === 'ligado') return ['_geral' => 'Um dos itens escolhidos não existe mais. Recarregue a página.'];
        throw $e;
    }
    registrar_atividade($novo ? 'criou' : 'alterou', $m['slug'], $id);
    return ['_id' => $id];
}

function crud_excluir(array $m, array $linha): ?string
{
    if (isset($m['travado']) && ($motivo = $m['travado']($linha))) return $motivo;
    try {
        transacao(function () use ($m, $linha) {
            q("DELETE FROM {$m['slug']} WHERE id = ? AND propriedade_id = ?", [$linha['id'], propriedade()['id']]);
            if (isset($m['depois_excluir'])) $m['depois_excluir']((int) $linha['id']);
        });
    } catch (PDOException $e) {
        if (erro_de_integridade($e)) return $m['em_uso'] ?? 'Este registro está ligado a outros lançamentos e não pode ser excluído.';
        throw $e;
    }
    registrar_atividade('excluiu', $m['slug'], (int) $linha['id']);
    return null;
}

/** Planilha CSV (abre no Excel e no LibreOffice), com os mesmos filtros da tela. */
function crud_csv(array $m, array $get): never
{
    $r = crud_listar($m, $get, true);
    $nome = $m['slug'] . '-' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nome . '"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    $cols = array_keys(array_filter($m['campos'], fn($c) => empty($c['virtual'])));
    fputcsv($out, array_map(fn($c) => $m['campos'][$c]['rotulo'], $cols), ';', '"', '');
    foreach ($r['linhas'] as $l) {
        fputcsv($out, array_map(function ($c) use ($m, $l) {
            $tipo = $m['campos'][$c]['tipo'];
            if (in_array($tipo, ['decimal', 'dinheiro'], true)) return $l[$c] === null ? '' : number_format((float) $l[$c], 2, ',', '');
            $v = mostrar($m, $c, $l);
            $v = $v === '—' ? '' : $v;
            // Evita que o Excel trate o texto como fórmula.
            return preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v;
        }, $cols), ';', '"', '');
    }
    fclose($out);
    exit;
}
