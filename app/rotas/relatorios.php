<?php
declare(strict_types=1);

const MESES = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
const TIPOS_RELATORIO = ['geral' => ['Visão geral', 'layout-dashboard'], 'financeiro' => ['Financeiro', 'circle-dollar-sign'], 'leite' => ['Produção de leite', 'milk'], 'rebanho' => ['Nascimentos', 'bezerro'], 'comercial' => ['Compra e venda', 'shopping-cart']];

/** Filtro dos relatórios: tipo, ano e mês (0 = ano inteiro). Guardado na sessão, como no original. */
function filtro_relatorio(array $get): array
{
    $f = $_SESSION['filtro_relatorio'] ?? ['tipo' => 'geral', 'ano' => (int) date('Y'), 'mes' => (int) date('n')];
    if (isset($get['tipo']) && isset(TIPOS_RELATORIO[$get['tipo']])) $f['tipo'] = $get['tipo'];
    if (isset($get['ano']) && ctype_digit((string) $get['ano']) && (int) $get['ano'] > 1990 && (int) $get['ano'] < 2200) $f['ano'] = (int) $get['ano'];
    if (isset($get['mes']) && ctype_digit((string) $get['mes']) && (int) $get['mes'] <= 12) $f['mes'] = (int) $get['mes'];
    if (isset($get['mover'])) {
        $d = (int) $get['mover'] < 0 ? -1 : 1;
        if ($f['mes'] === 0) $f['ano'] += $d;
        else {
            $f['mes'] += $d;
            if ($f['mes'] < 1) { $f['mes'] = 12; $f['ano']--; }
            if ($f['mes'] > 12) { $f['mes'] = 1; $f['ano']++; }
        }
    }
    if (isset($get['atual'])) { $f['ano'] = (int) date('Y'); $f['mes'] = (int) date('n'); }
    $_SESSION['filtro_relatorio'] = $f;
    return $f;
}

function intervalo_relatorio(int $ano, int $mes): array
{
    if ($mes === 0) return ["$ano-01-01", "$ano-12-31"];
    $ini = sprintf('%04d-%02d-01', $ano, $mes);
    return [$ini, date('Y-m-t', strtotime($ini))];
}

/** Agrupa por mês (ano inteiro) ou por dia (um mês). */
function por_periodo(array $linhas, string $campoData, ?string $campoValor, bool $anoInteiro): array
{
    $m = [];
    foreach ($linhas as $r) {
        $x = (string) $r[$campoData];
        $k = $anoInteiro ? substr($x, 5, 2) : substr($x, 8, 2);
        $m[$k] = ($m[$k] ?? 0) + ($campoValor ? (float) $r[$campoValor] : 1);
    }
    ksort($m);
    $out = [];
    foreach ($m as $k => $v) $out[$anoInteiro ? mb_substr(MESES[(int) $k - 1], 0, 3) : $k] = $v;
    return $out;
}

function rota_relatorios(): never
{
    $p = pid();
    $f = filtro_relatorio($_GET);
    [$de, $ate] = intervalo_relatorio($f['ano'], $f['mes']);
    [$pde, $pate] = $f['mes'] === 0 ? intervalo_relatorio($f['ano'] - 1, 0) : intervalo_relatorio($f['mes'] === 1 ? $f['ano'] - 1 : $f['ano'], $f['mes'] === 1 ? 12 : $f['mes'] - 1);
    $rotulo = $f['mes'] === 0 ? "Ano de {$f['ano']}" : MESES[$f['mes'] - 1] . " de {$f['ano']}";
    $d = ['fin' => [], 'ind' => [], 'leite' => [], 'nasc' => [], 'compras' => [], 'vendas' => []];
    if (in_array($f['tipo'], ['geral', 'financeiro'], true)) {
        $d['fin'] = todos('SELECT id, tipo, categoria, descricao, valor, data FROM movimentacoes_financeiras WHERE propriedade_id = ? AND data BETWEEN ? AND ? ORDER BY data, id LIMIT 20000', [$p, $de, $ate]);
        $d['ant'] = array_column(todos('SELECT tipo, SUM(valor) AS t FROM movimentacoes_financeiras WHERE propriedade_id = ? AND data BETWEEN ? AND ? GROUP BY tipo', [$p, $pde, $pate]), 't', 'tipo');
    }
    if ($f['tipo'] === 'geral') {
        $s = fn(string $sql) => (float) valor($sql, [$p, $de, $ate]);
        $d['ind'] = [
            'nascidos' => $s('SELECT COUNT(*) FROM nascimentos WHERE propriedade_id = ? AND data_nascimento BETWEEN ? AND ?'),
            'litros' => $s('SELECT COALESCE(SUM(litros), 0) FROM producao_leite WHERE propriedade_id = ? AND data BETWEEN ? AND ?'),
            'vendas_valor' => $s('SELECT COALESCE(SUM(valor), 0) FROM vendas WHERE propriedade_id = ? AND data BETWEEN ? AND ?'),
            'compras_valor' => $s('SELECT COALESCE(SUM(valor), 0) FROM compras WHERE propriedade_id = ? AND data BETWEEN ? AND ?'),
        ];
    }
    if ($f['tipo'] === 'leite') {
        $d['leite'] = todos('SELECT p.id, p.data, p.litros, p.valor_total, a.numero_brinco, a.nome, l.nome AS lote FROM producao_leite p
            LEFT JOIN animais a ON a.id = p.animal_id LEFT JOIN lotes l ON l.id = p.lote_id
            WHERE p.propriedade_id = ? AND p.data BETWEEN ? AND ? ORDER BY p.data, p.id LIMIT 20000', [$p, $de, $ate]);
    }
    if ($f['tipo'] === 'rebanho') {
        $d['nasc'] = todos('SELECT n.id, n.data_nascimento, n.sexo, v.numero_brinco AS vaca_brinco, v.nome AS vaca_nome, b.numero_brinco AS bez_brinco, b.nome AS bez_nome, l.nome AS lote
            FROM nascimentos n LEFT JOIN animais v ON v.id = n.vaca_id LEFT JOIN animais b ON b.id = n.bezerro_id LEFT JOIN lotes l ON l.id = n.lote_id
            WHERE n.propriedade_id = ? AND n.data_nascimento BETWEEN ? AND ? ORDER BY n.data_nascimento, n.id LIMIT 20000', [$p, $de, $ate]);
    }
    if ($f['tipo'] === 'comercial') {
        $d['compras'] = todos('SELECT id, data, produto_animal, quantidade, valor, fornecedor, categoria FROM compras WHERE propriedade_id = ? AND data BETWEEN ? AND ? ORDER BY data, id LIMIT 20000', [$p, $de, $ate]);
        $d['vendas'] = todos('SELECT id, data, item, quantidade, valor, comprador, categoria FROM vendas WHERE propriedade_id = ? AND data BETWEEN ? AND ? ORDER BY data, id LIMIT 20000', [$p, $de, $ate]);
    }
    $truncado = max(array_map(fn($x) => is_array($x) && array_is_list($x) ? count($x) : 0, $d)) >= 20000;
    if (($_GET['exportar'] ?? '') === 'csv') exportar_relatorio($f, $d, $de, $ate);
    tela('relatorios', compact('f', 'de', 'ate', 'rotulo', 'd', 'truncado') + ['titulo' => 'Relatórios', 'secao' => 'relatorios']);
}

/** Planilha do relatório escolhido (recurso Premium no original). */
function exportar_relatorio(array $f, array $d, string $de, string $ate): never
{
    if (!premium_ativo()) {
        aviso('erro', 'A exportação de dados é um recurso do plano Premium.');
        redirecionar('app/relatorios');
    }
    $lista = match ($f['tipo']) {
        'leite' => $d['leite'],
        'rebanho' => $d['nasc'],
        'comercial' => array_merge(array_map(fn($r) => ['tipo' => 'compra'] + $r, $d['compras']), array_map(fn($r) => ['tipo' => 'venda'] + $r, $d['vendas'])),
        default => $d['fin'],
    };
    if (!$lista) {
        aviso('erro', 'Não há dados no período selecionado.');
        redirecionar('app/relatorios');
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="relatorio-' . $f['tipo'] . "-{$de}_{$ate}.csv\"");
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    $chaves = array_keys($lista[0]);
    fputcsv($out, $chaves, ';', '"', '');
    foreach ($lista as $r) {
        fputcsv($out, array_map(function ($c) use ($r) {
            $v = (string) ($r[$c] ?? '');
            return preg_match('/^[=+\-@]/', $v) && !is_numeric($v) ? "'" . $v : $v;
        }, $chaves), ';', '"', '');
    }
    exit;
}

/** Indicadores (indicadores_propriedade): reprodução, peso, leite e dinheiro. */
function indicadores_propriedade(string $p): array
{
    $h = hoje();
    $ano = date('Y-m-d', strtotime("$h -12 months"));
    $doisAnos = date('Y-m-d', strtotime("$h -24 months"));
    $mesIni = date('Y-m-01');
    $ins = array_column(todos('SELECT situacao, COUNT(*) AS q FROM inseminacoes WHERE propriedade_id = ? AND data_inseminacao >= ? GROUP BY situacao', [$p, $ano]), 'q', 'situacao');
    $ins = array_map('intval', $ins);
    $totalIns = array_sum($ins);
    $confirmadas = ($ins['confirmada'] ?? 0) + ($ins['parto_realizado'] ?? 0);
    $decididas = $totalIns - ($ins['em_andamento'] ?? 0);

    // Intervalo entre partos (mesma vaca, 250 a 900 dias).
    $partos = todos('SELECT vaca_id, data_nascimento FROM nascimentos WHERE propriedade_id = ? AND data_nascimento >= ? ORDER BY vaca_id, data_nascimento', [$p, $doisAnos]);
    $intervalos = [];
    for ($i = 1; $i < count($partos); $i++) {
        if ($partos[$i]['vaca_id'] !== $partos[$i - 1]['vaca_id']) continue;
        $dias = dias_entre($partos[$i - 1]['data_nascimento'], $partos[$i]['data_nascimento']);
        if ($dias >= 250 && $dias <= 900) $intervalos[] = $dias;
    }
    // Idade ao primeiro parto.
    $idades = [];
    foreach (todos('SELECT MIN(n.data_nascimento) AS parto, a.data_nascimento AS nasceu FROM nascimentos n JOIN animais a ON a.id = n.vaca_id
        WHERE n.propriedade_id = ? AND a.data_nascimento IS NOT NULL GROUP BY a.id, a.data_nascimento', [$p]) as $r) {
        $dias = dias_entre($r['nasceu'], $r['parto']);
        if ($dias >= 400 && $dias <= 2200) $idades[] = $dias / 30.4375;
    }
    // Ganho de peso: primeira e última pesagem de cada animal no ano (20 dias ou mais).
    $pes = todos('SELECT animal_id, data, peso_kg FROM pesagens WHERE propriedade_id = ? AND data >= ? AND peso_kg > 0 ORDER BY animal_id, data, created_at', [$p, $ano]);
    $porAnimal = [];
    foreach ($pes as $r) $porAnimal[$r['animal_id']][] = $r;
    $ganhos = [];
    $ultimos = [];
    foreach ($porAnimal as $lista) {
        $ultimos[] = (float) end($lista)['peso_kg'];
        if (count($lista) < 2) continue;
        $dias = dias_entre($lista[0]['data'], end($lista)['data']);
        if ($dias >= 20) $ganhos[] = ((float) end($lista)['peso_kg'] - (float) $lista[0]['peso_kg']) / $dias;
    }
    $leite = um('SELECT COALESCE(SUM(litros), 0) AS l, COUNT(DISTINCT animal_id) AS vacas, COUNT(DISTINCT data) AS dias, COALESCE(SUM(valor_total), 0) AS v
        FROM producao_leite WHERE propriedade_id = ? AND data > ? AND data <= ?', [$p, somar_dias($h, -30), $h]);
    $litrosMes = (float) valor('SELECT COALESCE(SUM(litros), 0) FROM producao_leite WHERE propriedade_id = ? AND data >= ? AND data <= ?', [$p, $mesIni, $h]);
    $din = array_map('floatval', array_column(todos('SELECT tipo, SUM(valor) AS t FROM movimentacoes_financeiras WHERE propriedade_id = ? AND data >= ? AND data <= ? GROUP BY tipo', [$p, $mesIni, $h]), 't', 'tipo'));
    $reb = um("SELECT COUNT(*) AS ativos, SUM(CASE WHEN sexo = 'F' THEN 1 ELSE 0 END) AS femeas, SUM(CASE WHEN sexo = 'M' THEN 1 ELSE 0 END) AS machos,
        SUM(CASE WHEN data_nascimento >= ? THEN 1 ELSE 0 END) AS crias_ano,
        SUM(CASE WHEN data_nascimento IS NOT NULL AND data_nascimento < ? AND sexo = 'F' THEN 1 ELSE 0 END) AS matrizes
        FROM animais WHERE propriedade_id = ? AND situacao = 'ativo'", [$ano, $doisAnos, $p]);
    $reb = array_map('intval', $reb);
    $perdas = (int) valor("SELECT COUNT(*) FROM animais WHERE propriedade_id = ? AND situacao IN ('perdido','falecido') AND updated_at >= ?", [$p, $ano]);
    $nascidos = (int) valor('SELECT COUNT(*) FROM nascimentos WHERE propriedade_id = ? AND data_nascimento >= ?', [$p, $ano]);
    $media = fn(array $x) => $x ? array_sum($x) / count($x) : null;
    $vacas = (int) $leite['vacas'];
    $diasL = (int) $leite['dias'];
    $l30 = (float) $leite['l'];
    $receitas = $din['receita'] ?? 0.0;
    $despesas = $din['despesa'] ?? 0.0;
    return [
        'hoje' => $h,
        'reproducao' => [
            'inseminacoes_ano' => $totalIns, 'confirmadas' => $confirmadas, 'falhou' => $ins['falhou'] ?? 0, 'em_andamento' => $ins['em_andamento'] ?? 0,
            'taxa_prenhez' => $decididas > 0 ? round(100 * $confirmadas / $decididas, 1) : null,
            'intervalo_partos_dias' => $intervalos ? round($media($intervalos)) : null,
            'idade_primeiro_parto_meses' => $idades ? round($media($idades), 1) : null,
            'nascidos_ano' => $nascidos,
            'taxa_natalidade' => $reb['matrizes'] > 0 ? round(100 * $nascidos / $reb['matrizes'], 1) : null,
        ],
        'peso' => ['ganho_kg_dia' => $ganhos ? round($media($ganhos), 3) : null, 'animais_pesados' => count($ganhos), 'peso_medio_kg' => $ultimos ? round($media($ultimos), 1) : null],
        'leite' => [
            'litros_30d' => round($l30, 1), 'litros_dia' => $diasL > 0 ? round($l30 / $diasL, 1) : null, 'vacas_ordenhadas' => $vacas,
            'litros_por_vaca_dia' => $vacas > 0 && $diasL > 0 ? round($l30 / $vacas / $diasL, 1) : null,
            'preco_medio_litro' => $l30 > 0 && (float) $leite['v'] > 0 ? round((float) $leite['v'] / $l30, 2) : null,
            'litros_mes' => round($litrosMes, 1), 'custo_por_litro' => $litrosMes > 0 ? round($despesas / $litrosMes, 2) : null,
        ],
        'dinheiro' => ['receitas_mes' => $receitas, 'despesas_mes' => $despesas, 'investimentos_mes' => $din['investimento'] ?? 0.0, 'resultado_mes' => $receitas - $despesas],
        'rebanho' => $reb + ['perdas_ano' => $perdas, 'taxa_mortalidade' => $reb['ativos'] + $perdas > 0 ? round(100 * $perdas / ($reb['ativos'] + $perdas), 2) : null],
    ];
}

function rota_indicadores(): never
{
    $d = indicadores_propriedade(pid());
    tela('indicadores', ['d' => $d, 'titulo' => 'Indicadores', 'secao' => 'indicadores']);
}

function rota_diagnostico(): never
{
    $p = pid();
    $linhas = [];
    foreach (['animais', 'lotes', 'pesagens', 'vacinacoes', 'inseminacoes', 'nascimentos', 'producao_leite', 'anotacoes_producao', 'movimentacoes_financeiras', 'investimentos', 'compras', 'vendas', 'tipos_vacinas'] as $t) {
        $ini = hrtime(true);
        $r = um("SELECT COUNT(*) AS n, MAX(updated_at) AS u FROM $t WHERE propriedade_id = ?", [$p]);
        $linhas[] = ['tabela' => $t, 'contagem' => (int) $r['n'], 'ultima' => $r['u'], 'ms' => (hrtime(true) - $ini) / 1e6];
    }
    usort($linhas, fn($a, $b) => $b['ms'] <=> $a['ms']);
    $checagens = [];
    $q = (int) valor('SELECT COUNT(*) FROM nascimentos WHERE propriedade_id = ? AND vaca_id = bezerro_id', [$p]);
    if ($q) $checagens[] = ['checagem' => 'vaca igual ao bezerro', 'qtd' => $q];
    $lim = somar_dias(hoje(), 1);
    $q = (int) valor('SELECT (SELECT COUNT(*) FROM pesagens WHERE propriedade_id = ? AND data > ?) + (SELECT COUNT(*) FROM producao_leite WHERE propriedade_id = ? AND data > ?) + (SELECT COUNT(*) FROM movimentacoes_financeiras WHERE propriedade_id = ? AND data > ?)', [$p, $lim, $p, $lim, $p, $lim]);
    if ($q) $checagens[] = ['checagem' => 'registros com data no futuro', 'qtd' => $q];
    $q = (int) valor("SELECT COUNT(*) FROM vacinacoes WHERE propriedade_id = ? AND animal_id IS NULL AND lote_id IS NULL", [$p]);
    if ($q) $checagens[] = ['checagem' => 'vacinação sem animal e sem lote', 'qtd' => $q];
    tela('diagnostico', compact('linhas', 'checagens') + ['titulo' => 'Diagnóstico', 'secao' => 'diagnostico']);
}

function rota_ferramentas(): never
{
    tela('ferramentas', ['prop' => propriedade(), 'titulo' => 'Calculadoras e clima', 'secao' => 'ferramentas']);
}

/** Pedido ao Open-Meteo (gratuito, sem chave), guardado por 1 hora para não repetir. */
function open_meteo(string $url, string $chave): ?array
{
    $pasta = RAIZ . '/storage/cache';
    $arq = "$pasta/" . hash('sha256', $chave) . '.json';
    if (is_file($arq) && filemtime($arq) > time() - 3600) {
        $d = json_decode((string) file_get_contents($arq), true);
        if (is_array($d)) return $d;
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_HTTPHEADER => ['Accept: application/json']]);
    $corpo = curl_exec($ch);
    $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($corpo === false || $codigo !== 200) return null;
    $d = json_decode((string) $corpo, true);
    if (!is_array($d)) return null;
    if (!is_dir($pasta)) @mkdir($pasta, 0770, true);
    @file_put_contents($arq, json_encode($d));
    return $d;
}

function rota_api_clima(string $o_que): never
{
    $u = exigir_login();
    if (!dentro_do_limite('clima:' . $u['id'], 30, 1)) responder_json(['ok' => false, 'erro' => 'Muitos pedidos. Espere um minuto.'], 429);
    registrar_tentativa('clima:' . $u['id']);
    if ($o_que === 'lugares') {
        $nome = mb_substr(trim((string) ($_GET['nome'] ?? '')), 0, 60);
        if (mb_strlen($nome) < 3) responder_json(['ok' => false, 'erro' => 'Escreva pelo menos 3 letras do nome da cidade.'], 400);
        $nome = trim(explode(',', $nome)[0]);
        $d = open_meteo('https://geocoding-api.open-meteo.com/v1/search?name=' . rawurlencode($nome) . '&count=8&language=pt&format=json&country=BR', 'lugar:' . mb_strtolower($nome));
        if ($d === null) responder_json(['ok' => false, 'erro' => 'Não foi possível buscar a cidade agora.'], 502);
        responder_json(['ok' => true, 'lugares' => array_map(fn($l) => ['nome' => $l['name'], 'estado' => $l['admin1'] ?? null, 'lat' => round($l['latitude'], 3), 'lon' => round($l['longitude'], 3)], array_slice($d['results'] ?? [], 0, 8))]);
    }
    $lat = (float) ($_GET['lat'] ?? 999);
    $lon = (float) ($_GET['lon'] ?? 999);
    if (abs($lat) > 90 || abs($lon) > 180) responder_json(['ok' => false, 'erro' => 'Lugar inválido.'], 400);
    $rl = round($lat, 2);
    $rn = round($lon, 2);
    $d = open_meteo("https://api.open-meteo.com/v1/forecast?latitude=$rl&longitude=$rn&current=temperature_2m,relative_humidity_2m,precipitation,wind_speed_10m,weather_code"
        . '&daily=weather_code,temperature_2m_max,temperature_2m_min,precipitation_sum,precipitation_probability_max&timezone=America%2FSao_Paulo&forecast_days=7', "clima:$rl,$rn");
    if ($d === null) responder_json(['ok' => false, 'erro' => 'Não foi possível ver o tempo agora.'], 502);
    $c = $d['current'] ?? null;
    $di = $d['daily'] ?? [];
    $dias = [];
    foreach ($di['time'] ?? [] as $i => $dia) {
        $dias[] = ['dia' => $dia, 'codigo' => $di['weather_code'][$i] ?? null, 'max' => $di['temperature_2m_max'][$i] ?? null, 'min' => $di['temperature_2m_min'][$i] ?? null,
            'chuva_mm' => $di['precipitation_sum'][$i] ?? null, 'chance_chuva' => $di['precipitation_probability_max'][$i] ?? null];
    }
    responder_json(['ok' => true, 'agora' => $c ? ['temperatura' => $c['temperature_2m'] ?? null, 'umidade' => $c['relative_humidity_2m'] ?? null, 'chuva' => $c['precipitation'] ?? null,
        'vento' => $c['wind_speed_10m'] ?? null, 'codigo' => $c['weather_code'] ?? null] : null, 'dias' => $dias]);
}
