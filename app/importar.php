<?php
declare(strict_types=1);

// ---------------------------------------------------------------------------
// Importar planilha (CSV, TSV ou JSON) em qualquer cadastro, como no original:
// 1) analisa o arquivo e mostra quantas linhas são válidas e os problemas; 2) importa as válidas.
// Animal, lote e vacina precisam existir (a importação não cria cadastros sozinha).
// ---------------------------------------------------------------------------

const IMPORTAR_MAX_LINHAS = 20000;
const IMPORTAR_MAX_BYTES = 4 * 1024 * 1024;

function normalizar_cabecalho(string $h): string
{
    $h = sem_acento_minusculo($h);
    $h = preg_replace('/[^a-z0-9_]+/', '_', $h);
    return trim(preg_replace('/_{2,}/', '_', $h), '_');
}

/** Nomes que cada campo aceita: a chave do banco, o rótulo da tela e apelidos comuns. */
function apelidos_importacao(array $m): array
{
    $out = [];
    $add = function (string $nome, string $k) use (&$out, $m) {
        $n = normalizar_cabecalho($nome);
        if ($n === '' || (isset($m['campos'][$n]) && $n !== $k)) return;
        $out[$n] ??= $k;
    };
    foreach ($m['campos'] as $k => $c) $add($k, $k);
    foreach ($m['campos'] as $k => $c) $add($c['rotulo'], $k);
    $porCampo = ['numero_brinco' => ['brinco', 'n_brinco'], 'data_nascimento' => ['nascimento', 'data_de_nascimento'], 'observacao' => ['obs', 'observacoes', 'anotacao']];
    foreach ($m['campos'] as $k => $c) foreach ($porCampo[$k] ?? [] as $a) $add($a, $k);
    $porTipo = ['animal' => ['animal', 'brinco', 'numero_brinco', 'nome_animal', 'vaca'], 'lote' => ['lote', 'nome_lote'], 'vacina' => ['vacina', 'tipo_vacina', 'nome_vacina']];
    $contagem = array_count_values(array_map(fn($c) => $c['tipo'] ?? 'text', $m['campos']));
    foreach ($m['campos'] as $k => $c) {
        $t = $c['tipo'] ?? 'text';
        if (($contagem[$t] ?? 0) !== 1) continue;
        foreach ($porTipo[$t] ?? [] as $a) $add($a, $k);
    }
    return $out;
}

/** Lê CSV (separador ; , ou tab, aspas, quebras de linha) ou JSON. Devolve [cabeçalhos, linhas]. */
function ler_planilha(string $texto, string $nomeArquivo): array
{
    $texto = preg_replace('/^\xEF\xBB\xBF/', '', $texto);
    if (!mb_check_encoding($texto, 'UTF-8')) $texto = mb_convert_encoding($texto, 'UTF-8', 'Windows-1252');
    $inicio = ltrim($texto);
    if (str_ends_with(strtolower($nomeArquivo), '.json') || str_starts_with($inicio, '[') || str_starts_with($inicio, '{')) {
        $dados = str_starts_with($inicio, '[') ? json_decode($inicio, true)
            : array_map(fn($l) => json_decode($l, true), array_values(array_filter(preg_split('/\r?\n/', $inicio), fn($l) => trim($l) !== '')));
        if (!is_array($dados)) throw new RuntimeException('O arquivo JSON deve conter uma lista de registros.');
        $linhas = [];
        foreach ($dados as $r) {
            if (!is_array($r)) continue;
            $linha = [];
            foreach ($r as $k => $v) $linha[normalizar_cabecalho((string) $k)] = is_scalar($v) ? trim((string) $v) : '';
            $linhas[] = $linha;
        }
        return [array_keys(array_merge(...($linhas ?: [[]]))), $linhas];
    }
    $primeira = strtok($texto, "\n") ?: '';
    $sep = str_ends_with(strtolower($nomeArquivo), '.tsv') ? "\t" : ',';
    $cont = [',' => 0, ';' => 0, "\t" => 0];
    $aspas = false;
    foreach (str_split($primeira) as $ch) {
        if ($ch === '"') $aspas = !$aspas;
        elseif (!$aspas && isset($cont[$ch])) $cont[$ch]++;
    }
    if ($cont["\t"] > 0 && $cont["\t"] >= $cont[';'] && $cont["\t"] >= $cont[',']) $sep = "\t";
    elseif ($cont[';'] > $cont[',']) $sep = ';';
    $f = fopen('php://memory', 'r+');
    fwrite($f, $texto);
    rewind($f);
    $cab = null;
    $linhas = [];
    while (($l = fgetcsv($f, 0, $sep, '"', '')) !== false) {
        if ($cab === null) { $cab = array_map(fn($h) => normalizar_cabecalho((string) $h), $l); continue; }
        if (count(array_filter($l, fn($x) => trim((string) $x) !== '')) === 0) continue;
        $r = [];
        foreach ($cab as $i => $h) if ($h !== '') $r[$h] = trim((string) ($l[$i] ?? ''));
        $linhas[] = $r;
    }
    fclose($f);
    return [$cab ?? [], $linhas];
}

function pasta_importacoes(): string
{
    $p = RAIZ . '/storage/importacoes';
    if (!is_dir($p)) @mkdir($p, 0770, true);
    return $p;
}

/** Passo 1: guarda o arquivo já lido e confere todas as linhas sem gravar nada. */
function importar_analisar(array $m, array $arquivo): array
{
    if (!premium_ativo()) return ['erro' => 'A importação de planilhas é um recurso do plano Premium.'];
    if (($arquivo['error'] ?? 1) !== UPLOAD_ERR_OK || !is_uploaded_file($arquivo['tmp_name'])) return ['erro' => 'Escolha o arquivo da planilha.'];
    if ($arquivo['size'] > IMPORTAR_MAX_BYTES) return ['erro' => 'O arquivo passa de 4 MB. Divida a planilha em partes.'];
    $nome = mb_substr(basename((string) $arquivo['name']), 0, 200);
    if (!preg_match('/\.(csv|tsv|txt|json)$/i', $nome)) return ['erro' => 'Use um arquivo CSV, TSV ou JSON. No Excel: Arquivo → Salvar como → CSV.'];
    try {
        [$cab, $linhas] = ler_planilha((string) file_get_contents($arquivo['tmp_name']), $nome);
    } catch (Throwable) {
        return ['erro' => 'Não foi possível ler o arquivo. Confira se é um CSV ou JSON válido.'];
    }
    if (!$linhas) return ['erro' => 'A planilha está vazia (só tem o cabeçalho ou nada).'];
    if (count($linhas) > IMPORTAR_MAX_LINHAS) return ['erro' => 'No máximo ' . num_br(IMPORTAR_MAX_LINHAS) . ' linhas por vez. Divida a planilha.'];
    $ap = apelidos_importacao($m);
    $linhas = array_map(function ($r) use ($ap) {
        $o = [];
        foreach ($r as $h => $v) {
            $k = $ap[$h] ?? $h;
            if (!isset($o[$k]) || ($o[$k] === '' && $v !== '')) $o[$k] = $v;
        }
        return $o;
    }, $linhas);
    $id = uuid();
    file_put_contents(pasta_importacoes() . "/$id.json", json_encode($linhas, JSON_UNESCAPED_UNICODE));
    inserir('importacoes', ['id' => $id, 'propriedade_id' => pid(), 'tabela' => $m['tabela'], 'arquivo' => $nome, 'status' => 'previa', 'total_linhas' => count($linhas), 'criado_em' => agora(), 'atualizado_em' => agora()]);
    $r = importar_processar($m, $linhas, true);
    $ignoradas = count(array_diff(array_map('strval', $cab), array_keys($ap)));
    return ['id' => $id, 'arquivo' => $nome, 'total' => count($linhas), 'validas' => $r['aceitas'], 'problemas' => $r['motivos'], 'ignoradas' => $ignoradas];
}

/** Passo 2: importa as linhas válidas (cada linha separada: uma ruim não derruba as outras). */
function importar_iniciar(array $m, string $id): array
{
    if (!premium_ativo()) return ['erro' => 'A importação de planilhas é um recurso do plano Premium.'];
    $imp = eh_uuid($id) ? um("SELECT * FROM importacoes WHERE id = ? AND propriedade_id = ? AND tabela = ? AND status = 'previa'", [$id, pid(), $m['tabela']]) : null;
    $arq = pasta_importacoes() . "/$id.json";
    if (!$imp || !is_file($arq)) return ['erro' => 'Essa importação não está mais disponível. Escolha o arquivo de novo.'];
    $linhas = json_decode((string) file_get_contents($arq), true) ?: [];
    q("UPDATE importacoes SET status = 'processando', atualizado_em = ? WHERE id = ?", [agora(), $id]);
    $r = importar_processar($m, $linhas, false);
    atualizar('importacoes', ['status' => 'concluida', 'processadas' => count($linhas), 'aceitas' => $r['aceitas'], 'rejeitadas' => count($linhas) - $r['aceitas'],
        'rejeitadas_detalhe' => json_encode(array_slice($r['motivos'], 0, 200), JSON_UNESCAPED_UNICODE), 'atualizado_em' => agora()], 'id = ?', [$id]);
    @unlink($arq);
    return ['aceitas' => $r['aceitas'], 'duplicadas' => $r['duplicadas'], 'rejeitadas' => count($linhas) - $r['aceitas'] - $r['duplicadas'], 'motivos' => array_slice($r['motivos'], 0, 200)];
}

function importar_processar(array $m, array $linhas, bool $soConferir): array
{
    $GLOBALS['IMPORTANDO'] = true;
    $aceitas = 0;
    $duplicadas = 0;
    $motivos = [];
    $pdo = db();
    $pdo->beginTransaction();
    try {
        foreach ($linhas as $i => $linha) {
            $nLinha = $i + 2; // a linha 1 do arquivo é o cabeçalho
            $pdo->exec('SAVEPOINT linha');
            $post = [];
            foreach ($m['campos'] as $k => $c) {
                $v = (string) ($linha[$k] ?? '');
                if (($c['tipo'] ?? '') === 'date' && $v !== '') $v = ler_data($v) ?? $v;
                if ($k === 'situacao' && $v === '') $v = (string) array_key_first($c['opcoes']);
                $post[$k] = $v;
            }
            $r = crud_salvar($m, null, $post);
            if (isset($r['id'])) {
                $aceitas++;
                $pdo->exec('RELEASE SAVEPOINT linha');
                continue;
            }
            $pdo->exec('ROLLBACK TO SAVEPOINT linha');
            $msg = implode(' ', array_values($r['erros']));
            if (str_starts_with($msg, 'Já existe')) $duplicadas++;
            if (count($motivos) < 500) $motivos[] = ['linha' => $nLinha, 'motivo' => $msg];
        }
        $soConferir ? $pdo->rollBack() : $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    } finally {
        $GLOBALS['IMPORTANDO'] = false;
    }
    return compact('aceitas', 'duplicadas', 'motivos');
}
