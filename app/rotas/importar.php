<?php
declare(strict_types=1);

function sem_acento(string $s): string
{
    $s = mb_strtolower(trim($s));
    return strtr($s, ['á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c']);
}

/** Lê o texto da planilha e devolve [cabeçalho normalizado, linhas]. */
function ler_planilha(string $texto): array
{
    $texto = preg_replace('/^\xEF\xBB\xBF/', '', $texto);
    if (!mb_check_encoding($texto, 'UTF-8')) $texto = mb_convert_encoding($texto, 'UTF-8', 'Windows-1252');
    $linhas = preg_split('/\r\n|\r|\n/', trim($texto));
    $primeira = $linhas[0] ?? '';
    $sep = ';';
    foreach ([';', ',', "\t"] as $s) if (substr_count($primeira, $s) > substr_count($primeira, $sep)) $sep = $s;
    $cab = array_map(fn($c) => str_replace([' ', '-'], '_', sem_acento($c)), str_getcsv($primeira, $sep, '"', ''));
    $dados = [];
    foreach (array_slice($linhas, 1) as $l) {
        if (trim($l) === '') continue;
        $dados[] = str_getcsv($l, $sep, '"', '');
    }
    return [$cab, $dados];
}

function rota_importar(): never
{
    $prop = exigir_propriedade();
    $resultado = null;
    if (eh_post()) {
        conferir_csrf();
        exigir_edicao();
        $texto = '';
        if (!empty($_FILES['arquivo']['tmp_name']) && is_uploaded_file($_FILES['arquivo']['tmp_name'])) {
            if ($_FILES['arquivo']['size'] > 2 * 1024 * 1024) pagina_erro(413, 'Arquivo maior que 2 MB. Divida a planilha em partes.');
            $texto = (string) file_get_contents($_FILES['arquivo']['tmp_name']);
        } else {
            $texto = (string) ($_POST['texto'] ?? '');
        }
        $resultado = importar_animais($texto, (int) $prop['id']);
        if ($resultado['criados'] > 0) registrar_atividade('importou', 'animais', null);
    }
    tela('importar', ['titulo' => 'Importar animais', 'resultado' => $resultado]);
}

function importar_animais(string $texto, int $p): array
{
    $r = ['criados' => 0, 'repetidos' => 0, 'erros' => []];
    if (trim($texto) === '') {
        $r['erros'][] = 'A planilha está vazia.';
        return $r;
    }
    [$cab, $linhas] = ler_planilha($texto);
    $col = fn(array $nomes) => array_values(array_intersect($nomes, $cab))[0] ?? null;
    $mapa = [
        'brinco' => $col(['brinco', 'numero', 'n', 'identificacao', 'id']),
        'nome' => $col(['nome']),
        'sexo' => $col(['sexo']),
        'raca' => $col(['raca']),
        'categoria' => $col(['categoria']),
        'data_nascimento' => $col(['data_nascimento', 'nascimento', 'data_de_nascimento', 'nasc']),
        'lote' => $col(['lote']),
    ];
    if (!$mapa['brinco'] || !$mapa['sexo']) {
        $r['erros'][] = 'A primeira linha precisa ter pelo menos as colunas "brinco" e "sexo".';
        return $r;
    }
    if (count($linhas) > 5000) {
        $r['erros'][] = 'No máximo 5.000 animais por vez.';
        return $r;
    }
    $idx = array_flip($cab);
    $categorias = modulos()['animais']['campos']['categoria']['opcoes'];
    $lotes = [];
    foreach (opcoes_ref('lotes') as $loteId => $loteNome) $lotes[mb_strtolower($loteNome)] = $loteId;
    $existentes = array_flip(array_column(todos('SELECT brinco FROM animais WHERE propriedade_id = ?', [$p]), 'brinco'));

    transacao(function () use ($linhas, $mapa, $idx, $categorias, &$lotes, &$existentes, $p, &$r) {
        foreach ($linhas as $n => $l) {
            $linha = $n + 2;
            $v = fn(string $k) => $mapa[$k] !== null ? trim((string) ($l[$idx[$mapa[$k]]] ?? '')) : '';
            $brinco = mb_substr($v('brinco'), 0, 40);
            if ($brinco === '') { $r['erros'][] = "Linha $linha: sem brinco."; continue; }
            if (isset($existentes[$brinco])) { $r['repetidos']++; continue; }
            $sexo = strtoupper(mb_substr(sem_acento($v('sexo')), 0, 1));
            if (!in_array($sexo, ['F', 'M'], true)) { $r['erros'][] = "Linha $linha ($brinco): sexo deve ser F ou M."; continue; }
            $nasc = null;
            if ($v('data_nascimento') !== '') {
                $nasc = ler_data($v('data_nascimento'));
                if (!$nasc || $nasc > hoje()) { $r['erros'][] = "Linha $linha ($brinco): data de nascimento inválida."; continue; }
            }
            $cat = sem_acento($v('categoria'));
            $cat = isset($categorias[$cat]) ? $cat : null;
            $loteId = null;
            $loteNome = mb_substr($v('lote'), 0, 80);
            if ($loteNome !== '') {
                $chave = mb_strtolower($loteNome);
                $lotes[$chave] ??= inserir('lotes', ['propriedade_id' => $p, 'nome' => $loteNome]);
                $loteId = $lotes[$chave];
            }
            inserir('animais', [
                'propriedade_id' => $p, 'brinco' => $brinco, 'nome' => mb_substr($v('nome'), 0, 80) ?: null, 'sexo' => $sexo,
                'raca' => mb_substr($v('raca'), 0, 60) ?: null, 'categoria' => $cat, 'data_nascimento' => $nasc, 'lote_id' => $loteId,
                'origem' => 'nascido', 'situacao' => 'ativo',
            ]);
            $existentes[$brinco] = true;
            $r['criados']++;
        }
    });
    return $r;
}
