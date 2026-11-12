<?php
$r = $d['reproducao']; $p = $d['peso']; $l = $d['leite']; $m = $d['dinheiro']; $reb = $d['rebanho'];
$num = fn($v, int $casas = 1) => $v === null ? '—' : num_br((float) $v, fmod((float) $v, 1) == 0.0 ? 0 : $casas);
$pct = fn($v) => $v === null ? '—' : $num($v, 1) . '%';
$pl = fn(int $n, string $um, ?string $varios = null) => $n === 1 ? $um : ($varios ?? $um . 's');
$ind = function (string $titulo, string $valor, ?string $unidade, string $explica, ?string $leitura = null, ?bool $bom = null): string {
    return '<li class="painel"><p class="pequeno negrito texto-suave">' . e($titulo) . '</p><p class="ind-valor">' . e($valor)
        . ($unidade && $valor !== '—' ? '<small>' . e($unidade) . '</small>' : '') . '</p><p class="pequeno texto-suave mt-2">' . e($explica) . '</p>'
        . ($leitura ? '<p class="ind-leitura' . ($bom === null ? '' : ($bom ? ' bom' : ' ruim')) . '">' . e($leitura) . '</p>' : '') . '</li>';
};
$secao = fn(string $titulo, string $ic, string $itens) => '<section class="secao-ind"><h2 class="titulo-secao titulo-com-icone">' . icone($ic) . e($titulo) . '</h2><ul class="indicadores">' . $itens . '</ul></section>';
?>
<?= titulo_pagina('Indicadores', 'As contas que mostram se a fazenda está indo bem. Os números vêm do que você já lançou.') ?>
<?php if ($reb['ativos'] === 0): ?>
  <?= vazio('Ainda não há animais cadastrados nesta propriedade. Cadastre o rebanho e lance pesagens, leite e vacinas para os indicadores aparecerem.') ?>
<?php else: ?>
<?= $secao('Reprodução', 'heart-pulse',
    $ind('Taxa de prenhez (12 meses)', $pct($r['taxa_prenhez']), null,
        'De ' . plural($r['inseminacoes_ano'], 'inseminação', 'inseminações') . " no ano, {$r['confirmadas']} " . $pl($r['confirmadas'], 'deu', 'deram') . " prenhez e {$r['falhou']} " . $pl($r['falhou'], 'falhou', 'falharam') . ". {$r['em_andamento']} ainda " . $pl($r['em_andamento'], 'aguarda', 'aguardam') . ' resultado.',
        $r['taxa_prenhez'] === null ? null : ($r['taxa_prenhez'] >= 50 ? 'Bom: metade ou mais das coberturas pegou.' : 'Abaixo de 50%: vale rever a hora do cio, o sêmen e a nutrição.'),
        $r['taxa_prenhez'] === null ? null : $r['taxa_prenhez'] >= 50)
    . $ind('Intervalo entre partos', $num($r['intervalo_partos_dias'], 0), 'dias', 'Tempo médio entre uma cria e a seguinte, na mesma vaca.',
        $r['intervalo_partos_dias'] === null ? 'Precisa de pelo menos duas crias da mesma vaca registradas.' : ($r['intervalo_partos_dias'] <= 420 ? 'Bom: até 420 dias é um intervalo saudável.' : 'Acima de 420 dias: a vaca está demorando a emprenhar de novo.'),
        $r['intervalo_partos_dias'] === null ? null : $r['intervalo_partos_dias'] <= 420)
    . $ind('Idade ao primeiro parto', $num($r['idade_primeiro_parto_meses'], 1), 'meses', 'Com quantos meses, em média, a novilha teve a primeira cria.',
        $r['idade_primeiro_parto_meses'] === null ? 'Precisa da data de nascimento da vaca e do registro da primeira cria.' : ($r['idade_primeiro_parto_meses'] <= 30 ? 'Bom: até 30 meses a novilha começa a se pagar cedo.' : 'Acima de 30 meses: a novilha fica muito tempo sem produzir.'),
        $r['idade_primeiro_parto_meses'] === null ? null : $r['idade_primeiro_parto_meses'] <= 30)
    . $ind('Crias no ano', $num($r['nascidos_ano'], 0), $pl($r['nascidos_ano'], 'cria'),
        'Nascimentos registrados nos últimos 12 meses, com ' . plural($reb['matrizes'], 'matriz', 'matrizes') . ' no rebanho.',
        $r['taxa_natalidade'] === null ? null : $pct($r['taxa_natalidade']) . ' em relação às matrizes.', $r['taxa_natalidade'] === null ? null : $r['taxa_natalidade'] >= 70)) ?>
<?= $secao('Peso', 'scale',
    $ind('Ganho de peso por dia', $num($p['ganho_kg_dia'], 3), 'kg/dia', 'Média entre a primeira e a última pesagem de ' . plural($p['animais_pesados'], 'animal', 'animais') . ' no último ano.',
        $p['ganho_kg_dia'] === null ? 'Pese cada animal pelo menos duas vezes, com 20 dias ou mais de diferença.' : ($p['ganho_kg_dia'] >= 0.5 ? 'Bom: meio quilo por dia ou mais é um ganho firme.' : 'Abaixo de 0,5 kg/dia: vale olhar pasto, ração e vermifugação.'),
        $p['ganho_kg_dia'] === null ? null : $p['ganho_kg_dia'] >= 0.5)
    . $ind('Peso médio atual', $num($p['peso_medio_kg'], 1), 'kg', 'Média da última pesagem de cada animal pesado no ano.')
    . $ind('Mortes e perdas no ano', $num($reb['perdas_ano'], 0), $pl($reb['perdas_ano'], 'animal', 'animais'), 'Animais marcados como mortos ou perdidos nos últimos 12 meses.',
        $reb['taxa_mortalidade'] === null ? null : $pct($reb['taxa_mortalidade']) . ' do rebanho.', $reb['taxa_mortalidade'] === null ? null : $reb['taxa_mortalidade'] <= 3)) ?>
<?= $secao('Leite', 'droplets',
    $ind('Litros por vaca por dia', $num($l['litros_por_vaca_dia'], 1), 'L', plural($l['vacas_ordenhadas'], 'vaca') . ' ' . $pl($l['vacas_ordenhadas'], 'ordenhada') . ' nos últimos 30 dias.',
        $l['litros_por_vaca_dia'] === null ? 'Lance o leite por animal para ver a média por vaca.' : ($l['litros_por_vaca_dia'] >= 12 ? 'Bom para rebanho leiteiro mestiço.' : 'Abaixo de 12 L: olhe alimentação, água e intervalo entre ordenhas.'),
        $l['litros_por_vaca_dia'] === null ? null : $l['litros_por_vaca_dia'] >= 12)
    . $ind('Leite nos últimos 30 dias', $num($l['litros_30d'], 0), 'L', $l['litros_dia'] === null ? 'Nada lançado nos últimos 30 dias.' : 'Média de ' . $num($l['litros_dia'], 1) . ' L por dia de entrega.')
    . $ind('Custo por litro (mês)', $l['custo_por_litro'] === null ? '—' : dinheiro($l['custo_por_litro']), null, 'Todas as despesas do mês divididas pelos litros do mês.',
        $l['custo_por_litro'] === null || $l['preco_medio_litro'] === null ? 'Lance as despesas e o valor do litro para comparar.'
            : ($l['preco_medio_litro'] > $l['custo_por_litro'] ? 'Você recebe ' . dinheiro($l['preco_medio_litro']) . ' por litro: sobra ' . dinheiro($l['preco_medio_litro'] - $l['custo_por_litro']) . '.'
            : 'Você recebe ' . dinheiro($l['preco_medio_litro']) . ' por litro: está saindo mais caro do que o leite paga.'),
        $l['custo_por_litro'] === null || $l['preco_medio_litro'] === null ? null : $l['preco_medio_litro'] > $l['custo_por_litro'])) ?>
<?= $secao('Dinheiro do mês', 'circle-dollar-sign',
    $ind('Receitas', dinheiro($m['receitas_mes']), null, 'Tudo que entrou neste mês.')
    . $ind('Despesas', dinheiro($m['despesas_mes']), null, 'Tudo que saiu neste mês, sem contar investimentos.')
    . $ind('Resultado', dinheiro($m['resultado_mes']), null, 'Receitas menos despesas. Investimentos no mês: ' . dinheiro($m['investimentos_mes']) . '.',
        $m['resultado_mes'] >= 0 ? 'No azul neste mês.' : 'No vermelho neste mês.', $m['resultado_mes'] >= 0)) ?>
<?php if ($m['receitas_mes'] || $m['despesas_mes']): ?>
  <section class="painel p-painel mt-5">
    <h3 class="titulo-secao menor">Entradas e saídas do mês</h3>
    <?= '<div class="caixa-grafico">' . grafico_colunas([
        ['rotulo' => 'Receitas', 'valor' => $m['receitas_mes'], 'atual' => true],
        ['rotulo' => 'Despesas', 'valor' => -$m['despesas_mes'], 'atual' => true, 'dica' => dinheiro($m['despesas_mes'])],
        ['rotulo' => 'Resultado', 'valor' => $m['resultado_mes'], 'atual' => true],
    ], fn($v) => dinheiro(abs($v)), 'Entradas e saídas do mês') . '</div>' ?>
  </section>
<?php endif; ?>
<p class="pequeno texto-suave mt-5">Contas feitas com os registros até <?= e(data_br($d['hoje'])) ?>. Quanto mais você lançar (pesagens, leite, vacinas, nascimentos e dinheiro), mais certos ficam os números.</p>
<?php endif; ?>
