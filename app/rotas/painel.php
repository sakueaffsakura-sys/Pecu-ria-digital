<?php
declare(strict_types=1);

function rota_painel(): never
{
    $prop = propriedade();
    $p = $prop['id'];
    $h = hoje();
    $resumo = dashboard_resumo($p);
    $desde = date('Y-m-01', strtotime(date('Y-m-01') . ' -11 months'));

    $meses = [];
    for ($i = 11; $i >= 0; $i--) {
        $ym = date('Y-m', strtotime(date('Y-m-01') . " -$i months"));
        $meses[$ym] = ['rotulo' => mes_curto($ym), 'atual' => $i === 0, 'leite' => 0.0, 'receitas' => 0.0, 'despesas' => 0.0];
    }
    foreach (todos('SELECT SUBSTR(data, 1, 7) AS mes, SUM(litros) AS l FROM producao_leite WHERE propriedade_id = ? AND data >= ? GROUP BY SUBSTR(data, 1, 7)', [$p, $desde]) as $r) {
        if (isset($meses[$r['mes']])) $meses[$r['mes']]['leite'] = (float) $r['l'];
    }
    foreach (todos("SELECT SUBSTR(data, 1, 7) AS mes, SUM(CASE WHEN tipo = 'receita' THEN valor ELSE 0 END) AS r, SUM(CASE WHEN tipo <> 'receita' THEN valor ELSE 0 END) AS d
        FROM movimentacoes_financeiras WHERE propriedade_id = ? AND data >= ? GROUP BY SUBSTR(data, 1, 7)", [$p, $desde]) as $r) {
        if (isset($meses[$r['mes']])) {
            $meses[$r['mes']]['receitas'] = (float) $r['r'];
            $meses[$r['mes']]['despesas'] = (float) $r['d'];
        }
    }
    $meses = array_values($meses);
    $semana = array_slice(agenda_periodo($p, $h, somar_dias($h, 7))['eventos'], 0, 6);
    $partos = todos("SELECT i.id, i.previsao_parto, a.id AS animal_id, a.nome, a.numero_brinco FROM inseminacoes i JOIN animais a ON a.id = i.animal_id
        WHERE i.propriedade_id = ? AND i.situacao IN ('em_andamento','confirmada') AND i.previsao_parto >= ? ORDER BY i.previsao_parto LIMIT 5", [$p, $h]);
    $avisos = central_avisos($p);
    tela('painel', compact('prop', 'resumo', 'meses', 'semana', 'partos', 'avisos') + ['titulo' => 'Início', 'secao' => 'inicio']);
}

/** Período da agenda: semana (segunda a domingo) ou mês. */
function periodo_agenda(string $modo, string $ref): array
{
    if ($modo === 'semana') {
        $ini = somar_dias($ref, -(((int) date('w', strtotime($ref)) + 6) % 7));
        return [$ini, somar_dias($ini, 6)];
    }
    return [date('Y-m-01', strtotime($ref)), date('Y-m-t', strtotime($ref))];
}

function rota_agenda(): never
{
    $prop = propriedade();
    $modo = ($_GET['modo'] ?? 'semana') === 'mes' ? 'mes' : 'semana';
    $ref = ler_data($_GET['ref'] ?? null) ?? hoje();
    [$de, $ate] = periodo_agenda($modo, $ref);
    $ocultos = array_values(array_intersect((array) ($_GET['ocultar'] ?? []), ['vacina', 'parto', 'cio']));
    $dados = agenda_periodo($prop['id'], $de, $ate);
    $eventos = array_values(array_filter($dados['eventos'], fn($e) => !in_array($e['tipo'], $ocultos, true)));
    $porDia = [];
    foreach ($eventos as $e) $porDia[$e['data']][] = $e;
    $anterior = $modo === 'semana' ? somar_dias($de, -7) : date('Y-m-01', strtotime("$de -1 month"));
    $proximo = $modo === 'semana' ? somar_dias($de, 7) : date('Y-m-01', strtotime("$de +1 month"));
    tela('agenda', compact('prop', 'modo', 'ref', 'de', 'ate', 'ocultos', 'dados', 'eventos', 'porDia', 'anterior', 'proximo') + ['titulo' => 'Agenda', 'secao' => 'agenda']);
}

/** Arquivo .ics (calendário do celular) com os compromissos do período, como dia inteiro. */
function rota_agenda_ics(): never
{
    exigir_login();
    $prop = propriedade();
    if (!$prop) redirecionar('app/inicio');
    $modo = ($_GET['modo'] ?? 'semana') === 'mes' ? 'mes' : 'semana';
    [$de, $ate] = periodo_agenda($modo, ler_data($_GET['ref'] ?? null) ?? hoje());
    $ocultos = (array) ($_GET['ocultar'] ?? []);
    $nomes = ['vacina' => 'Vacina', 'parto' => 'Parto', 'cio' => 'Cio'];
    $esc = fn(string $t) => str_replace(["\\", ';', ',', "\n"], ['\\\\', '\\;', '\\,', '\\n'], $t);
    $linhas = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Pecuaria Digital//Agenda//PT-BR', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH'];
    $carimbo = gmdate('Ymd\THis\Z');
    foreach (agenda_periodo($prop['id'], $de, $ate)['eventos'] as $e) {
        if (in_array($e['tipo'], $ocultos, true)) continue;
        $dia = str_replace('-', '', $e['data']);
        array_push($linhas, 'BEGIN:VEVENT', "UID:{$e['tipo']}-{$e['registro_id']}-$dia@pecuaria-digital", "DTSTAMP:$carimbo", "DTSTART;VALUE=DATE:$dia",
            'DTEND;VALUE=DATE:' . str_replace('-', '', somar_dias($e['data'], 1)),
            'SUMMARY:' . $esc($nomes[$e['tipo']] . ': ' . $e['titulo'] . ($e['detalhe'] ? ' — ' . $e['detalhe'] : '')),
            'DESCRIPTION:' . $esc($prop['nome'] . '. Lembrete do Pecuária Digital.'), 'END:VEVENT');
    }
    $linhas[] = 'END:VCALENDAR';
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="agenda-pecuaria-digital.ics"');
    echo implode("\r\n", $linhas);
    exit;
}
