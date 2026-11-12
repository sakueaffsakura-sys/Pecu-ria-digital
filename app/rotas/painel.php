<?php
declare(strict_types=1);

/** Última aplicação de cada vacina (por animal/lote) cuja próxima dose vence até $ate. */
function vacinas_pendentes(string $ate, int $limite = 200): array
{
    return todos(
        "SELECT v.id, v.data, v.proxima_dose, tv.nome AS vacina, a.id AS animal_id, a.brinco, l.nome AS lote
         FROM vacinacoes v
         JOIN tipos_vacinas tv ON tv.id = v.tipo_vacina_id
         LEFT JOIN animais a ON a.id = v.animal_id
         LEFT JOIN lotes l ON l.id = v.lote_id
         WHERE v.propriedade_id = ? AND v.proxima_dose IS NOT NULL AND v.proxima_dose <= ?
           AND (v.animal_id IS NULL OR a.situacao = 'ativo')
           AND NOT EXISTS (
             SELECT 1 FROM vacinacoes v2
             WHERE v2.propriedade_id = v.propriedade_id AND v2.tipo_vacina_id = v.tipo_vacina_id
               AND COALESCE(v2.animal_id, 0) = COALESCE(v.animal_id, 0) AND COALESCE(v2.lote_id, 0) = COALESCE(v.lote_id, 0)
               AND (v2.data > v.data OR (v2.data = v.data AND v2.id > v.id))
           )
         ORDER BY v.proxima_dose LIMIT $limite",
        [propriedade()['id'], $ate]
    );
}

/** Partos previstos até $ate de fêmeas prenhes ou aguardando diagnóstico que ainda não pariram. */
function partos_previstos(string $ate): array
{
    return todos(
        "SELECT i.id, i.data, i.previsao_parto, i.resultado, a.id AS animal_id, a.brinco, a.nome
         FROM inseminacoes i JOIN animais a ON a.id = i.animal_id
         WHERE i.propriedade_id = ? AND i.resultado IN ('pendente', 'prenhe') AND a.situacao = 'ativo'
           AND i.previsao_parto IS NOT NULL AND i.previsao_parto >= ? AND i.previsao_parto <= ?
           AND NOT EXISTS (SELECT 1 FROM nascimentos n WHERE n.mae_id = i.animal_id AND n.data >= i.data)
           AND NOT EXISTS (SELECT 1 FROM inseminacoes i2 WHERE i2.animal_id = i.animal_id AND i2.data > i.data)
         ORDER BY i.previsao_parto LIMIT 200",
        [propriedade()['id'], somar_dias(hoje(), -30), $ate]
    );
}

/** Inseminações com mais de 30 dias ainda sem diagnóstico de prenhez. */
function diagnosticos_pendentes(): array
{
    return todos(
        "SELECT i.id, i.data, a.id AS animal_id, a.brinco FROM inseminacoes i JOIN animais a ON a.id = i.animal_id
         WHERE i.propriedade_id = ? AND i.resultado = 'pendente' AND i.data <= ? AND a.situacao = 'ativo'
         ORDER BY i.data LIMIT 200",
        [propriedade()['id'], somar_dias(hoje(), -30)]
    );
}

function rota_painel(): never
{
    $prop = exigir_propriedade();
    $p = $prop['id'];
    $h = hoje();

    $porSexo = array_column(todos("SELECT sexo, COUNT(*) AS n FROM animais WHERE propriedade_id = ? AND situacao = 'ativo' GROUP BY sexo", [$p]), 'n', 'sexo');
    $vacinas = vacinas_pendentes(somar_dias($h, 30), 500);
    $vencidas = array_filter($vacinas, fn($v) => $v['proxima_dose'] < $h);
    $partos = partos_previstos(somar_dias($h, 60));
    $diagnosticos = diagnosticos_pendentes();

    // Leite: últimos 14 dias, dia a dia.
    $leite = array_column(todos('SELECT data, SUM(litros) AS l FROM producao_leite WHERE propriedade_id = ? AND data > ? AND data <= ? GROUP BY data', [$p, somar_dias($h, -14), $h]), 'l', 'data');
    $leiteDias = [];
    for ($i = 13; $i >= 0; $i--) {
        $d = somar_dias($h, -$i);
        $leiteDias[substr($d, 8, 2) . '/' . substr($d, 5, 2)] = (float) ($leite[$d] ?? 0);
    }
    $leite30 = (float) valor('SELECT COALESCE(SUM(litros), 0) FROM producao_leite WHERE propriedade_id = ? AND data > ? AND data <= ?', [$p, somar_dias($h, -30), $h]);

    // Financeiro: últimos 6 meses.
    $inicio6 = date('Y-m-01', strtotime(date('Y-m-01') . ' -5 months'));
    $fin = todos('SELECT SUBSTR(data, 1, 7) AS mes, tipo, SUM(valor) AS total FROM movimentacoes WHERE propriedade_id = ? AND data >= ? AND data <= ? GROUP BY SUBSTR(data, 1, 7), tipo', [$p, $inicio6, $h]);
    $meses = [];
    for ($i = 5; $i >= 0; $i--) $meses[date('Y-m', strtotime(date('Y-m-01') . " -$i months"))] = [0.0, 0.0];
    foreach ($fin as $f) {
        if (isset($meses[$f['mes']])) $meses[$f['mes']][$f['tipo'] === 'entrada' ? 0 : 1] = (float) $f['total'];
    }
    $mesAtual = end($meses);
    $finMeses = [];
    foreach ($meses as $ym => $v) $finMeses[mes_br($ym)] = $v;

    $anotacoes = todos('SELECT id, data, titulo FROM anotacoes WHERE propriedade_id = ? ORDER BY data DESC, id DESC LIMIT 5', [$p]);
    $lotes = (int) valor('SELECT COUNT(*) FROM lotes WHERE propriedade_id = ?', [$p]);

    tela('painel', compact('prop', 'porSexo', 'vacinas', 'vencidas', 'partos', 'diagnosticos', 'leiteDias', 'leite30', 'finMeses', 'mesAtual', 'anotacoes', 'lotes') + ['titulo' => 'Início']);
}

function rota_agenda(): never
{
    exigir_propriedade();
    $dias = (int) ($_GET['dias'] ?? 60);
    if (!in_array($dias, [30, 60, 90, 180], true)) $dias = 60;
    $ate = somar_dias(hoje(), $dias);
    $itens = [];
    foreach (vacinas_pendentes($ate) as $v) {
        $itens[] = ['data' => $v['proxima_dose'], 'tipo' => 'Vacina', 'texto' => $v['vacina'] . ' — ' . ($v['brinco'] ? 'animal ' . $v['brinco'] : 'lote ' . $v['lote']),
            'link' => $v['animal_id'] ? url('animal/' . $v['animal_id']) : url('m/vacinacoes/' . $v['id'])];
    }
    foreach (partos_previstos($ate) as $i) {
        $itens[] = ['data' => $i['previsao_parto'], 'tipo' => 'Parto previsto', 'texto' => 'Vaca ' . $i['brinco'] . ($i['nome'] ? ' (' . $i['nome'] . ')' : '') . ($i['resultado'] === 'pendente' ? ' — prenhez não confirmada' : ''),
            'link' => url('animal/' . $i['animal_id'])];
    }
    foreach (diagnosticos_pendentes() as $i) {
        $itens[] = ['data' => somar_dias($i['data'], 30), 'tipo' => 'Diagnóstico', 'texto' => 'Confirmar prenhez da vaca ' . $i['brinco'] . ' (inseminada em ' . data_br($i['data']) . ')',
            'link' => url('m/inseminacoes/' . $i['id'])];
    }
    usort($itens, fn($a, $b) => strcmp($a['data'], $b['data']));
    tela('agenda', ['titulo' => 'Agenda', 'itens' => $itens, 'dias' => $dias]);
}

function rota_ficha_animal(int $id): never
{
    $prop = exigir_propriedade();
    $p = $prop['id'];
    $a = um('SELECT a.*, l.nome AS lote, m.brinco AS mae_brinco FROM animais a LEFT JOIN lotes l ON l.id = a.lote_id LEFT JOIN animais m ON m.id = a.mae_id WHERE a.id = ? AND a.propriedade_id = ?', [$id, $p]);
    if (!$a) pagina_erro(404, 'Animal não encontrado nesta propriedade.');

    $pesagens = todos('SELECT id, data, peso_kg, observacoes FROM pesagens WHERE animal_id = ? AND propriedade_id = ? ORDER BY data', [$id, $p]);
    $gmd = null;
    if (count($pesagens) >= 2) {
        $pri = $pesagens[0];
        $ult = end($pesagens);
        $dias = dias_entre($pri['data'], $ult['data']);
        if ($dias > 0) $gmd = ((float) $ult['peso_kg'] - (float) $pri['peso_kg']) / $dias;
    }
    $vacinas = todos(
        'SELECT v.id, v.data, v.proxima_dose, v.dose, tv.nome AS vacina, v.lote_id FROM vacinacoes v JOIN tipos_vacinas tv ON tv.id = v.tipo_vacina_id
         WHERE v.propriedade_id = ? AND (v.animal_id = ? OR (v.animal_id IS NULL AND v.lote_id = ?)) ORDER BY v.data DESC LIMIT 100',
        [$p, $id, $a['lote_id'] ?? 0]
    );
    $inseminacoes = todos('SELECT id, data, tipo, touro_semen, resultado, previsao_parto FROM inseminacoes WHERE animal_id = ? AND propriedade_id = ? ORDER BY data DESC', [$id, $p]);
    $filhos = todos('SELECT id, brinco, nome, sexo, data_nascimento, situacao FROM animais WHERE mae_id = ? AND propriedade_id = ? ORDER BY data_nascimento DESC', [$id, $p]);
    $leite30 = (float) valor('SELECT COALESCE(SUM(litros), 0) FROM producao_leite WHERE animal_id = ? AND propriedade_id = ? AND data > ?', [$id, $p, somar_dias(hoje(), -30)]);
    $graficoPeso = [];
    foreach (array_slice($pesagens, -12) as $pg) $graficoPeso[data_br($pg['data'])] = (float) $pg['peso_kg'];

    tela('ficha', compact('a', 'pesagens', 'gmd', 'vacinas', 'inseminacoes', 'filhos', 'leite30', 'graficoPeso') + ['titulo' => 'Animal ' . $a['brinco']]);
}

function rota_relatorios(): never
{
    $prop = exigir_propriedade();
    $p = $prop['id'];
    $de = ler_data($_GET['de'] ?? null) ?? date('Y-m-01');
    $ate = ler_data($_GET['ate'] ?? null) ?? hoje();
    if ($de > $ate) [$de, $ate] = [$ate, $de];

    $rebanho = todos("SELECT COALESCE(categoria, '') AS categoria, sexo, COUNT(*) AS n FROM animais WHERE propriedade_id = ? AND situacao = 'ativo' GROUP BY categoria, sexo ORDER BY categoria, sexo", [$p]);
    $porLote = todos("SELECT COALESCE(l.nome, 'Sem lote') AS lote, COUNT(*) AS n FROM animais a LEFT JOIN lotes l ON l.id = a.lote_id WHERE a.propriedade_id = ? AND a.situacao = 'ativo' GROUP BY l.nome ORDER BY n DESC", [$p]);
    $nasc = array_column(todos('SELECT sexo, COUNT(*) AS n FROM nascimentos WHERE propriedade_id = ? AND data >= ? AND data <= ? GROUP BY sexo', [$p, $de, $ate]), 'n', 'sexo');
    $insem = array_column(todos('SELECT resultado, COUNT(*) AS n FROM inseminacoes WHERE propriedade_id = ? AND data >= ? AND data <= ? GROUP BY resultado', [$p, $de, $ate]), 'n', 'resultado');
    $vac = (int) valor('SELECT COUNT(*) FROM vacinacoes WHERE propriedade_id = ? AND data >= ? AND data <= ?', [$p, $de, $ate]);

    // Ganho médio diário de quem foi pesado ao menos duas vezes no período.
    $pes = todos('SELECT animal_id, data, peso_kg FROM pesagens WHERE propriedade_id = ? AND data >= ? AND data <= ? ORDER BY animal_id, data', [$p, $de, $ate]);
    $porAnimal = [];
    foreach ($pes as $r) $porAnimal[$r['animal_id']][] = $r;
    $gmds = [];
    foreach ($porAnimal as $lista) {
        if (count($lista) < 2) continue;
        $dias = dias_entre($lista[0]['data'], end($lista)['data']);
        if ($dias > 0) $gmds[] = ((float) end($lista)['peso_kg'] - (float) $lista[0]['peso_kg']) / $dias;
    }
    $pesagens = ['n' => count($pes), 'media' => $pes ? array_sum(array_column($pes, 'peso_kg')) / count($pes) : null, 'gmd' => $gmds ? array_sum($gmds) / count($gmds) : null, 'animais_gmd' => count($gmds)];

    $leiteTotal = (float) valor('SELECT COALESCE(SUM(litros), 0) FROM producao_leite WHERE propriedade_id = ? AND data >= ? AND data <= ?', [$p, $de, $ate]);
    $leiteMeses = [];
    foreach (todos('SELECT SUBSTR(data, 1, 7) AS mes, SUM(litros) AS l FROM producao_leite WHERE propriedade_id = ? AND data >= ? AND data <= ? GROUP BY SUBSTR(data, 1, 7) ORDER BY mes', [$p, $de, $ate]) as $r) {
        $leiteMeses[mes_br($r['mes'])] = (float) $r['l'];
    }
    $diasPeriodo = dias_entre($de, $ate) + 1;

    $categorias = todos('SELECT tipo, categoria, SUM(valor) AS total, COUNT(*) AS n FROM movimentacoes WHERE propriedade_id = ? AND data >= ? AND data <= ? GROUP BY tipo, categoria ORDER BY tipo, total DESC', [$p, $de, $ate]);
    $entradas = array_sum(array_map(fn($c) => $c['tipo'] === 'entrada' ? (float) $c['total'] : 0, $categorias));
    $saidas = array_sum(array_map(fn($c) => $c['tipo'] === 'saida' ? (float) $c['total'] : 0, $categorias));
    $finMeses = [];
    foreach (todos('SELECT SUBSTR(data, 1, 7) AS mes, tipo, SUM(valor) AS t FROM movimentacoes WHERE propriedade_id = ? AND data >= ? AND data <= ? GROUP BY SUBSTR(data, 1, 7), tipo ORDER BY mes', [$p, $de, $ate]) as $r) {
        $finMeses[mes_br($r['mes'])] ??= [0.0, 0.0];
        $finMeses[mes_br($r['mes'])][$r['tipo'] === 'entrada' ? 0 : 1] = (float) $r['t'];
    }
    $vendas = um('SELECT COUNT(*) AS n, COALESCE(SUM(quantidade), 0) AS q, COALESCE(SUM(valor_total), 0) AS v FROM vendas WHERE propriedade_id = ? AND data >= ? AND data <= ?', [$p, $de, $ate]);
    $compras = um('SELECT COUNT(*) AS n, COALESCE(SUM(quantidade), 0) AS q, COALESCE(SUM(valor_total), 0) AS v FROM compras WHERE propriedade_id = ? AND data >= ? AND data <= ?', [$p, $de, $ate]);

    tela('relatorios', compact('prop', 'de', 'ate', 'rebanho', 'porLote', 'nasc', 'insem', 'vac', 'pesagens', 'leiteTotal', 'leiteMeses', 'diasPeriodo', 'categorias', 'entradas', 'saidas', 'finMeses', 'vendas', 'compras') + ['titulo' => 'Relatórios']);
}
