<?php
declare(strict_types=1);

// ---------------------------------------------------------------------------
// Lista, formulário, gravação e exclusão de qualquer cadastro de app/modulos.php.
// Toda consulta filtra pela propriedade em uso. Nomes de tabela e coluna vêm só do código.
// ---------------------------------------------------------------------------

const TAMANHOS_PAGINA = [10, 25, 50, 100];

function tabela_ref(string $tipo): string
{
    return ['animal' => 'animais', 'lote' => 'lotes', 'vacina' => 'tipos_vacinas'][$tipo];
}

/** SELECT com os nomes das ligações (brinco e nome do animal, lote, vacina). */
function crud_select(array $m): array
{
    $cols = ['t.*'];
    $joins = [];
    foreach ($m['campos'] as $k => $c) {
        $tipo = $c['tipo'] ?? 'text';
        if (!in_array($tipo, ['animal', 'lote', 'vacina'], true)) continue;
        $a = "r_$k";
        $joins[] = 'LEFT JOIN ' . tabela_ref($tipo) . " $a ON $a.id = t.$k";
        if ($tipo === 'animal') {
            $cols[] = "$a.numero_brinco AS {$k}__brinco";
            $cols[] = "$a.nome AS {$k}__nome";
        } else {
            $cols[] = "$a.nome AS {$k}__nome";
        }
    }
    return [implode(', ', $cols), implode(' ', $joins)];
}

/** Período rápido do original: Tudo, Hoje, Este mês, Este ano ou um dia escolhido. */
function periodo_escolhido(array $get): array
{
    $pedido = (string) ($get['periodo'] ?? '');
    $dia = ler_data($get['dia'] ?? null);
    $modo = in_array($pedido, ['dia', 'mes', 'ano'], true) ? $pedido : ($pedido === '' && $dia ? 'dia' : 'todos');
    if ($pedido === 'dia') $dia = hoje();
    return match ($modo) {
        'dia' => ['dia', $dia ?? hoje(), $dia ?? hoje()],
        'mes' => ['mes', date('Y-m-01'), date('Y-m-t')],
        'ano' => ['ano', date('Y-01-01'), date('Y-12-31')],
        default => ['todos', null, null],
    };
}

function crud_filtros(array $m, array $get): array
{
    $where = ['t.propriedade_id = ?'];
    $p = [pid()];
    if (!empty($m['filtro_fixo'])) $where[] = $m['filtro_fixo'];
    $busca = trim((string) ($get['busca'] ?? ''));
    if ($busca !== '') {
        $cols = array_map(fn($c) => "t.$c", $m['busca']);
        if ($m['tabela'] === 'anotacoes_producao') array_push($cols, 'r_animal_id.numero_brinco', 'r_animal_id.nome');
        if ($cols) {
            $where[] = '(' . implode(' OR ', array_map(fn($c) => "$c LIKE ?", $cols)) . ')';
            foreach ($cols as $_) $p[] = '%' . addcslashes($busca, '%_\\') . '%';
        }
    }
    [$modo, $de, $ate] = periodo_escolhido($get);
    if ($modo !== 'todos') {
        $campo = campo_data($m);
        if ($campo) {
            $where[] = "t.$campo BETWEEN ? AND ?";
            array_push($p, $de, $ate);
        } else {
            $where[] = 't.created_at BETWEEN ? AND ?';
            array_push($p, "$de 00:00:00", "$ate 23:59:59");
        }
    }
    if ($m['tabela'] === 'anotacoes_producao') {
        $aba = $get['aba_notas'] ?? 'todas';
        if ($aba === 'marcadas') $where[] = 't.marcada = 1';
        if ($aba === 'animais') $where[] = 't.animal_id IS NOT NULL';
        if ($aba === 'gerais') $where[] = 't.animal_id IS NULL';
    }
    return [implode(' AND ', $where), $p, $busca !== '' || $modo !== 'todos'];
}

function tamanho_pagina(array $get): int
{
    $t = (int) ($get['por'] ?? 25);
    return in_array($t, TAMANHOS_PAGINA, true) ? $t : 25;
}

function crud_listar(array $m, array $get): array
{
    [$cols, $joins] = crud_select($m);
    [$where, $p, $filtrando] = crud_filtros($m, $get);
    $tab = $m['tabela'];
    $total = (int) valor("SELECT COUNT(*) FROM $tab t $joins WHERE $where", $p);
    $por = tamanho_pagina($get);
    $ultima = max(1, (int) ceil($total / $por));
    $pagina = min($ultima, max(1, (int) ($get['pagina'] ?? 1)));
    $offset = ($pagina - 1) * $por;
    $linhas = todos("SELECT $cols FROM $tab t $joins WHERE $where ORDER BY {$m['ordem']} LIMIT $por OFFSET $offset", $p);
    return compact('linhas', 'total', 'pagina', 'por', 'ultima', 'filtrando');
}

function crud_buscar(array $m, string $id): ?array
{
    if (!eh_uuid($id)) return null;
    [$cols, $joins] = crud_select($m);
    $extra = !empty($m['filtro_fixo']) ? ' AND ' . $m['filtro_fixo'] : '';
    return um("SELECT $cols FROM {$m['tabela']} t $joins WHERE t.id = ? AND t.propriedade_id = ?$extra", [$id, pid()]);
}

// ----- Formulário ----------------------------------------------------------

function campos_basicos(array $m): array
{
    return array_filter($m['campos'], fn($c) => empty($c['avancado']));
}

function campos_avancados(array $m): array
{
    return array_filter($m['campos'], fn($c) => !empty($c['avancado']));
}

/** Texto que aparece no campo de ligação (brinco do animal, nome do lote ou da vacina). */
function texto_ref(array $linha, string $k, string $tipo): string
{
    return (string) ($tipo === 'animal' ? ($linha["{$k}__brinco"] ?? '') : ($linha["{$k}__nome"] ?? ''));
}

function valores_iniciais(array $m, ?array $linha, array $get = []): array
{
    $v = [];
    foreach ($m['campos'] as $k => $c) {
        $tipo = $c['tipo'] ?? 'text';
        if ($linha) {
            $v[$k] = in_array($tipo, ['animal', 'lote', 'vacina'], true) ? texto_ref($linha, $k, $tipo)
                : ($tipo === 'number' && $linha[$k] !== null ? numero_campo($linha[$k]) : (string) ($linha[$k] ?? ''));
            continue;
        }
        $v[$k] = $tipo === 'date' && empty($c['sem_padrao']) ? hoje() : ($k === 'situacao' ? (string) array_key_first($c['opcoes']) : '');
        // Pedidos vindos de outra tela: ?animal_id=<brinco> preenche o campo.
        if (isset($get[$k]) && is_string($get[$k])) $v[$k] = mb_substr($get[$k], 0, 120);
    }
    return $v;
}

function numero_campo(mixed $n): string
{
    $s = rtrim(rtrim(number_format((float) $n, 2, ',', ''), '0'), ',');
    return $s === '' || $s === '-' ? '0' : $s;
}

function sem_acento_minusculo(string $t): string
{
    $t = mb_strtolower(trim($t));
    $t = strtr($t, ['á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'é' => 'e', 'ê' => 'e', 'è' => 'e', 'í' => 'i', 'ï' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ú' => 'u', 'ü' => 'u', 'ç' => 'c']);
    return preg_replace('/[_\s]+/', ' ', $t);
}

/**
 * Acha o animal/lote/vacina pelo que a pessoa digitou (brinco exato tem prioridade sobre nome).
 * Não achou: cria o cadastro, como no original. Devolve [id, erro, criado].
 */
function resolver_ref(array $c, string $texto, ?string $atual, array $form): array
{
    $tipo = $c['tipo'];
    $p = pid();
    if ($tipo === 'animal') {
        $achados = todos('SELECT id, numero_brinco, nome, sexo, situacao FROM animais WHERE propriedade_id = ? AND (numero_brinco = ? OR nome = ? OR id = ?) LIMIT 5', [$p, $texto, $texto, $texto]);
        $exatos = array_values(array_filter($achados, fn($a) => $a['id'] === $texto || sem_acento_minusculo($a['numero_brinco']) === sem_acento_minusculo($texto)));
        $candidatos = $exatos ?: $achados;
    } else {
        $candidatos = todos('SELECT id, nome FROM ' . tabela_ref($tipo) . ' WHERE propriedade_id = ? AND (nome = ? OR id = ?) LIMIT 2', [$p, $texto, $texto]);
    }
    if (count($candidatos) > 1) return [null, 'Há mais de um cadastro com esse nome. Digite o brinco ou o identificador.', false];
    if ($candidatos) {
        $a = $candidatos[0];
        if ($tipo === 'animal') {
            if (!empty($c['sexo']) && $a['sexo'] !== $c['sexo']) return [null, 'O animal já existe, mas deve ser ' . ($c['sexo'] === 'F' ? 'fêmea' : 'macho') . ' neste campo. Use outro animal.', false];
            if (!empty($c['so_ativos']) && $a['situacao'] !== 'ativo' && $a['id'] !== $atual) return [null, 'O animal já existe, mas não está ativo. Use um animal ativo.', false];
        }
        return [$a['id'], null, false];
    }
    if (!pode_editar() || !empty($GLOBALS['IMPORTANDO'])) return [null, 'Cadastro não encontrado. Confira o nome ou deixe o campo vazio.', false];
    if ($tipo === 'animal') {
        $sexo = $c['sexo'] ?? (isset($c['sexo_do_campo']) && in_array($form[$c['sexo_do_campo']] ?? '', ['F', 'M'], true) ? $form[$c['sexo_do_campo']] : 'F');
        $id = inserir('animais', ['propriedade_id' => $p, 'numero_brinco' => mb_substr($texto, 0, 60), 'nome' => mb_substr($texto, 0, 120), 'sexo' => $sexo, 'situacao' => 'ativo', 'created_at' => agora(), 'updated_at' => agora()]);
    } else {
        $id = inserir(tabela_ref($tipo), ['propriedade_id' => $p, 'nome' => mb_substr($texto, 0, 120), 'ativo' => 1, 'created_at' => agora(), 'updated_at' => agora()]);
    }
    return [$id, null, true];
}

/** Lê e confere o formulário. Devolve [dados, erros, cadastros criados]. */
function ler_formulario(array $m, array $post, ?array $linha): array
{
    $dados = [];
    $erros = [];
    $criados = [];
    foreach ($m['campos'] as $k => $c) {
        $tipo = $c['tipo'] ?? 'text';
        $bruto = trim((string) (is_string($post[$k] ?? null) ? $post[$k] : ''));
        $rotulo = $c['rotulo'];
        if ($bruto === '') {
            if (!empty($c['obrigatorio'])) $erros[$k] = "Preencha o campo $rotulo.";
            $dados[$k] = null;
            continue;
        }
        switch ($tipo) {
            case 'text':
            case 'textarea':
                $max = $c['max'] ?? 10000;
                if (mb_strlen($bruto) > $max) $erros[$k] = "$rotulo: no máximo $max caracteres.";
                $dados[$k] = $bruto;
                break;
            case 'number':
                $n = ler_decimal($bruto);
                if ($n === null) { $erros[$k] = "$rotulo: digite um número."; break; }
                if (!empty($c['obrigatorio']) && $n <= 0) $erros[$k] = "$rotulo deve ser maior que zero.";
                elseif ($n < 0) $erros[$k] = "$rotulo não pode ser negativo.";
                elseif ($n > 9999999999) $erros[$k] = "$rotulo: valor alto demais.";
                if (!empty($c['inteiro'])) $n = (float) (int) round($n);
                $dados[$k] = !empty($c['inteiro']) ? (int) $n : round($n, 2);
                break;
            case 'date':
                $d = ler_data($bruto);
                if (!$d || $d < '1900-01-01' || $d > '2100-12-31') { $erros[$k] = "$rotulo: data inválida."; break; }
                $dados[$k] = $d;
                break;
            case 'select':
                $achou = null;
                foreach ($c['opcoes'] as $valor => $rot) {
                    if (sem_acento_minusculo((string) $valor) === sem_acento_minusculo($bruto) || sem_acento_minusculo($rot) === sem_acento_minusculo($bruto)) $achou = (string) $valor;
                }
                if ($achou === null) { $erros[$k] = "$rotulo: informe " . implode(', ', $c['opcoes']) . '.'; break; }
                $dados[$k] = $achou;
                break;
            case 'animal':
            case 'lote':
            case 'vacina':
                [$id, $erro, $criado] = resolver_ref($c, $bruto, $linha[$k] ?? null, $post);
                if ($erro) { $erros[$k] = "$rotulo: $erro"; break; }
                $dados[$k] = $id;
                if ($criado) $criados[] = "$rotulo \"$bruto\"";
                break;
        }
    }
    return [$dados, $erros, $criados];
}

/** Grava (novo ou edição) com as regras de negócio. Devolve ['id' => …] ou ['erros' => …]. */
function crud_salvar(array $m, ?array $linha, array $post): array
{
    exigir_edicao();
    $tab = $m['tabela'];
    try {
        return transacao(function () use ($m, $linha, $post, $tab) {
            [$dados, $erros, $criados] = ler_formulario($m, $post, $linha);
            if ($erros) throw new ErroFormulario($erros);
            $r = regras_antes($tab, $dados, $linha);
            if (is_string($r)) throw new ErroFormulario(['_geral' => $r]);
            $dados = $r;
            if ($linha) {
                atualizar($tab, $dados + ['updated_at' => agora()], 'id = ? AND propriedade_id = ?', [$linha['id'], pid()]);
                $id = $linha['id'];
            } else {
                $id = inserir($tab, $dados + ['propriedade_id' => pid(), 'created_at' => agora(), 'updated_at' => agora()]);
            }
            regras_depois($tab, $id, $dados, $linha);
            return ['id' => $id, 'criados' => $criados];
        });
    } catch (ErroFormulario $e) {
        return ['erros' => $e->erros];
    } catch (PDOException $e) {
        $tipo = erro_de_integridade($e);
        if ($tipo === 'duplicado') {
            $msg = $m['duplicado'] ?? match ($tab) {
                'animais' => 'Já existe um animal com esse número de brinco.',
                'pesagens' => 'Já existe um registro desse animal nessa data.',
                default => 'Já existe um registro com esse nome nesta propriedade.',
            };
            return ['erros' => ['_geral' => $msg]];
        }
        if ($tipo === 'ligado') return ['erros' => ['_geral' => 'Os dados informados não atendem às regras do cadastro. Revise as datas e os vínculos.']];
        throw $e;
    }
}

final class ErroFormulario extends RuntimeException
{
    public function __construct(public readonly array $erros)
    {
        parent::__construct('formulário');
    }
}

function crud_excluir(array $m, array $linha): ?string
{
    exigir_edicao();
    try {
        transacao(function () use ($m, $linha) {
            regras_ao_excluir($m['tabela'], $linha);
            q("DELETE FROM {$m['tabela']} WHERE id = ? AND propriedade_id = ?", [$linha['id'], pid()]);
        });
    } catch (PDOException $e) {
        if (erro_de_integridade($e)) return $m['em_uso'] ?? 'Esse registro está vinculado a outros dados e não pode ser removido.';
        throw $e;
    }
    return null;
}

/** Texto de uma célula da lista. */
function celula(array $m, string $col, array $l): string
{
    $v = $l[$col] ?? null;
    $c = $m['campos'][$col] ?? null;
    $tipo = $c['tipo'] ?? 'text';
    if ($col === 'numero_brinco') return brinco((string) $v);
    if ($col === 'situacao' || $col === 'tipo' && $m['tabela'] === 'movimentacoes_financeiras') return selo((string) $v);
    if ($col === 'ativo') return e((int) $v === 1 ? 'Ativo' : 'Inativo');
    if ($v === null || $v === '') return '—';
    if ($tipo === 'animal') {
        return '<span class="ref-animal">' . brinco((string) ($l["{$col}__brinco"] ?? '—')) . '<span class="truncar">' . e($l["{$col}__nome"] ?? '') . '</span></span>';
    }
    if ($tipo === 'lote' || $tipo === 'vacina') return e($l["{$col}__nome"] ?? '—');
    if (in_array($col, ['valor', 'valor_total', 'valor_litro'], true)) return e(dinheiro($v));
    if ($col === 'sexo') return e(SEXOS[$v] ?? $v);
    if ($tipo === 'date' || in_array($col, ['data', 'proxima_aplicacao', 'previsao_parto', 'data_nascimento'], true)) return e(data_br((string) $v));
    if ($tipo === 'select') return e($c['opcoes'][$v] ?? bonito($v));
    if (is_numeric($v) && in_array($tipo, ['number'], true) || in_array($col, ['litros', 'quantidade', 'peso_kg', 'producao', 'intervalo_dias'], true)) return e(num_br((float) $v, fmod((float) $v, 1.0) == 0.0 ? 0 : 2));
    return e(bonito($v));
}
