<?php
$premium = premium_ativo();
$ano = $f['mes'] === 0;
$prevLabel = $ano ? 'vs ano anterior' : 'vs mês anterior';
$soma = fn(array $l, string $k) => array_sum(array_map(fn($r) => (float) $r[$k], $l));
$porTipo = fn(string $t) => array_sum(array_map(fn($r) => $r['tipo'] === $t ? (float) $r['valor'] : 0, $d['fin']));
$receitas = $porTipo('receita'); $despesas = $porTipo('despesa'); $invest = $porTipo('investimento');
$ant = array_map('floatval', $d['ant'] ?? []);
$semDados = fn(string $msg) => '<div class="sem-dados">' . icone('calendar-days') . '<p class="negrito">Nenhum registro em ' . e($rotulo) . '</p><p class="pequeno texto-suave mt-1">' . e($msg) . ' Escolha outro período ou tipo de relatório.</p></div>';
$tabela = function (array $cols, array $lista): string {
    $h = '<div class="tabela-relatorio"><table><thead><tr>';
    foreach ($cols as [$t]) $h .= '<th>' . e($t) . '</th>';
    $h .= '</tr></thead><tbody>';
    foreach ($lista as $r) {
        $h .= '<tr>';
        foreach ($cols as [, $fn]) $h .= '<td>' . e($fn($r)) . '</td>';
        $h .= '</tr>';
    }
    return $h . '</tbody></table></div>';
};
$animal = fn(?string $br, ?string $nome) => $br ? $br . ($nome ? " · $nome" : '') : '—';
$anos = range((int) date('Y'), (int) date('Y') - 10);
if (!in_array($f['ano'], $anos, true)) array_unshift($anos, $f['ano']);
$acoes = (!$premium ? '<span class="selo-premium">' . icone('lock', 'ic-selo') . 'Premium</span>' : '')
    . botao_link(url('app/relatorios', ['exportar' => 'csv']), 'Baixar planilha (CSV)', 'download', 'contorno');
?>
<?= titulo_pagina('Relatórios', 'Escolha o relatório e o período. Só aparecem registros do período escolhido.', $acoes) ?>

<form method="get" action="/app/relatorios" class="painel filtro-relatorio">
  <div>
    <p class="filtro-passo">1. Tipo de relatório</p>
    <div class="linha-botoes">
      <?php foreach (TIPOS_RELATORIO as $t => [$rot, $ic]): ?>
        <button type="submit" name="tipo" value="<?= e($t) ?>" class="botao botao-contorno botao-escolha" aria-pressed="<?= $f['tipo'] === $t ? 'true' : 'false' ?>"><?= icone($ic) ?><?= e($rot) ?></button>
      <?php endforeach; ?>
    </div>
  </div>
  <div>
    <p class="filtro-passo">2. Período</p>
    <div class="linha-botoes">
      <button type="submit" name="mover" value="-1" class="botao-icone botao-contorno-q" aria-label="Período anterior"><?= icone('chevron-left') ?></button>
      <label class="so-leitor" for="r-mes">Mês</label>
      <select id="r-mes" name="mes" data-enviar>
        <option value="0"<?= $f['mes'] === 0 ? ' selected' : '' ?>>Ano inteiro</option>
        <?php foreach (MESES as $i => $mn): ?><option value="<?= $i + 1 ?>"<?= $f['mes'] === $i + 1 ? ' selected' : '' ?>><?= e($mn) ?></option><?php endforeach; ?>
      </select>
      <label class="so-leitor" for="r-ano">Ano</label>
      <select id="r-ano" name="ano" data-enviar>
        <?php foreach ($anos as $a): ?><option<?= $a === $f['ano'] ? ' selected' : '' ?>><?= $a ?></option><?php endforeach; ?>
      </select>
      <button type="submit" name="mover" value="1" class="botao-icone botao-contorno-q" aria-label="Próximo período"><?= icone('chevron-right') ?></button>
      <button type="submit" name="atual" value="1" class="botao botao-fantasma">Mês atual</button>
      <span class="chip-periodo"><?= e(TIPOS_RELATORIO[$f['tipo']][0]) ?> · <?= e($rotulo) ?></span>
      <noscript><button class="botao botao-contorno">Ver</button></noscript>
    </div>
  </div>
</form>

<?php if ($truncado): ?><p role="alert" class="faixa faixa-info mt-5">Este período tem mais de 20.000 registros e só os primeiros 20.000 entraram na conta. Escolha um mês (em vez do ano inteiro) para ver tudo.</p><?php endif; ?>

<div class="pilha-6 mt-5">
<?php if ($f['tipo'] === 'geral'):
  $ind = $d['ind'];
  if (!$d['fin'] && !array_filter($ind)): echo $semDados('Não há lançamentos, nascimentos ou produção neste período.');
  else:
    $evo = [];
    foreach ($d['fin'] as $r) {
        $k = $ano ? mb_substr(MESES[(int) substr($r['data'], 5, 2) - 1], 0, 3) : substr($r['data'], 8, 2);
        $evo[$k] ??= [0.0, 0.0];
        $evo[$k][$r['tipo'] === 'receita' ? 0 : 1] += (float) $r['valor'];
    }
    $lucro = $receitas - $despesas - $invest; ?>
  <div class="grade-metricas">
    <?= metrica('Receitas', dinheiro($receitas), 'trending-up', delta(variacao($receitas, $ant['receita'] ?? 0), false, $prevLabel)) ?>
    <?= metrica('Despesas', dinheiro($despesas), 'arrow-up-right', delta(variacao($despesas, $ant['despesa'] ?? 0), true, $prevLabel)) ?>
    <?= metrica('Investimentos', dinheiro($invest), 'circle-dollar-sign') ?>
    <?= metrica('Lucro', dinheiro($lucro), 'scale', '', $lucro < 0 ? 'alerta' : '') ?>
    <?= metrica('Nascimentos', num_br($ind['nascidos']), 'bezerro') ?>
    <?= metrica('Produção de leite', num_br($ind['litros'], fmod($ind['litros'], 1) == 0.0 ? 0 : 1) . ' L', 'milk') ?>
    <?= metrica('Vendas', dinheiro($ind['vendas_valor']), 'shopping-cart') ?>
    <?= metrica('Compras', dinheiro($ind['compras_valor']), 'shopping-cart') ?>
  </div>
  <section class="painel p-painel"><?= painel_titulo('Receitas x despesas', ($ano ? 'Por mês · ' : 'Por dia · ') . $rotulo) ?>
    <?= grafico_agrupado($evo, ['Receitas', 'Despesas + investimentos'], fn($v) => dinheiro($v), 'Receitas e despesas') ?></section>
  <?php endif; ?>

<?php elseif ($f['tipo'] === 'financeiro'):
  if (!$d['fin']): echo $semDados('Não há receitas, despesas ou investimentos lançados.');
  else:
    $cat = [];
    foreach ($d['fin'] as $r) { $k = bonito($r['categoria']) . ' (' . $r['tipo'] . ')'; $cat[$k] = ($cat[$k] ?? 0) + (float) $r['valor']; }
    arsort($cat); ?>
  <div class="grade-metricas">
    <?= metrica('Receitas', dinheiro($receitas), 'trending-up', delta(variacao($receitas, $ant['receita'] ?? 0), false, $prevLabel)) ?>
    <?= metrica('Despesas', dinheiro($despesas), 'arrow-up-right', delta(variacao($despesas, $ant['despesa'] ?? 0), true, $prevLabel)) ?>
    <?= metrica('Investimentos', dinheiro($invest), 'circle-dollar-sign') ?>
    <?= metrica('Lucro', dinheiro($receitas - $despesas - $invest), 'scale', '', $receitas - $despesas - $invest < 0 ? 'alerta' : '') ?>
  </div>
  <div class="duas-colunas">
    <section class="painel p-painel"><?= painel_titulo('Por categoria', $rotulo) ?><?= grafico_horizontal(array_slice($cat, 0, 8, true), fn($v) => dinheiro($v), 'Por categoria') ?></section>
    <section class="painel p-painel"><?= painel_titulo($ano ? 'Movimento por mês' : 'Movimento por dia', 'Soma dos lançamentos') ?><?= grafico_simples(por_periodo($d['fin'], 'data', 'valor', $ano), fn($v) => dinheiro($v), 'Movimento') ?></section>
  </div>
  <section class="painel p-painel"><?= painel_titulo('Lançamentos', plural(count($d['fin']), 'registro', 'registros')) ?>
    <?= $tabela([['Data', fn($r) => data_br($r['data'])], ['Tipo', fn($r) => bonito($r['tipo'])], ['Categoria', fn($r) => bonito($r['categoria'])], ['Descrição', fn($r) => bonito($r['descricao'])], ['Valor', fn($r) => dinheiro($r['valor'])]], $d['fin']) ?></section>
  <?php endif; ?>

<?php elseif ($f['tipo'] === 'leite'):
  $milk = $d['leite'];
  if (!$milk): echo $semDados('Não há produção de leite registrada.');
  else:
    $litros = $soma($milk, 'litros');
    $dias = count(array_unique(array_column($milk, 'data')));
    $lote = [];
    foreach ($milk as $r) { $k = $r['lote'] ?? 'Sem lote'; $lote[$k] = ($lote[$k] ?? 0) + (float) $r['litros']; } ?>
  <div class="grade-metricas">
    <?= metrica('Total de litros', num_br($litros, 1) . ' L', 'milk') ?>
    <?= metrica('Média diária', num_br($litros / max($dias, 1), 1) . ' L', 'chart-line') ?>
    <?= metrica('Valor total', dinheiro($soma($milk, 'valor_total')), 'circle-dollar-sign') ?>
    <?= metrica('Registros', num_br(count($milk)), 'list-filter') ?>
  </div>
  <div class="duas-colunas">
    <section class="painel p-painel"><?= painel_titulo($ano ? 'Litros por mês' : 'Litros por dia', $rotulo) ?><?= grafico_simples(por_periodo($milk, 'data', 'litros', $ano), fn($v) => num_br($v, 1) . ' L', 'Litros', 'c3') ?></section>
    <section class="painel p-painel"><?= painel_titulo('Por lote', 'Litros no período') ?><?= grafico_simples($lote, fn($v) => num_br($v, 1) . ' L', 'Por lote', 'c2') ?></section>
  </div>
  <section class="painel p-painel"><?= painel_titulo('Registros de produção', plural(count($milk), 'registro', 'registros')) ?>
    <?= $tabela([['Data', fn($r) => data_br($r['data'])], ['Animal', fn($r) => $animal($r['numero_brinco'], $r['nome'])], ['Lote', fn($r) => bonito($r['lote'])], ['Litros', fn($r) => num_br((float) $r['litros'], 2) . ' L'], ['Valor', fn($r) => dinheiro($r['valor_total'])]], $milk) ?></section>
  <?php endif; ?>

<?php elseif ($f['tipo'] === 'rebanho'):
  $b = $d['nasc'];
  if (!$b): echo $semDados('Não há nascimentos registrados.');
  else: ?>
  <div class="grade-metricas tres">
    <?= metrica('Nascimentos', num_br(count($b)), 'bezerro') ?>
    <?= metrica('Machos', num_br(count(array_filter($b, fn($r) => $r['sexo'] === 'M'))), 'bezerro') ?>
    <?= metrica('Fêmeas', num_br(count(array_filter($b, fn($r) => $r['sexo'] === 'F'))), 'bezerro') ?>
  </div>
  <section class="painel p-painel"><?= painel_titulo($ano ? 'Nascimentos por mês' : 'Nascimentos por dia', $rotulo) ?><?= grafico_simples(por_periodo($b, 'data_nascimento', null, $ano), fn($v) => num_br($v), 'Nascimentos') ?></section>
  <section class="painel p-painel"><?= painel_titulo('Nascimentos', plural(count($b), 'registro', 'registros')) ?>
    <?= $tabela([['Data', fn($r) => data_br($r['data_nascimento'])], ['Vaca', fn($r) => $animal($r['vaca_brinco'], $r['vaca_nome'])], ['Bezerro', fn($r) => $animal($r['bez_brinco'], $r['bez_nome'])], ['Sexo', fn($r) => $r['sexo'] === 'M' ? 'Macho' : 'Fêmea'], ['Lote', fn($r) => bonito($r['lote'])]], $b) ?></section>
  <?php endif; ?>

<?php else:
  $c = $d['compras']; $v = $d['vendas'];
  if (!$c && !$v): echo $semDados('Não há compras ou vendas registradas.');
  else: $saldo = $soma($v, 'valor') - $soma($c, 'valor'); ?>
  <div class="grade-metricas">
    <?= metrica('Vendas', dinheiro($soma($v, 'valor')), 'trending-up') ?>
    <?= metrica('Compras', dinheiro($soma($c, 'valor')), 'arrow-up-right') ?>
    <?= metrica('Saldo', dinheiro($saldo), 'scale', '', $saldo < 0 ? 'alerta' : '') ?>
    <?= metrica('Operações', num_br(count($c) + count($v)), 'list-filter') ?>
  </div>
  <div class="duas-colunas">
    <section class="painel p-painel"><?= painel_titulo('Vendas', plural(count($v), 'registro', 'registros')) ?>
      <?= $v ? $tabela([['Data', fn($r) => data_br($r['data'])], ['Item', fn($r) => bonito($r['item'])], ['Qtd.', fn($r) => num_br((float) $r['quantidade'], fmod((float) $r['quantidade'], 1) == 0.0 ? 0 : 2)], ['Comprador', fn($r) => bonito($r['comprador'])], ['Valor', fn($r) => dinheiro($r['valor'])]], $v) : vazio('Sem vendas no período.') ?></section>
    <section class="painel p-painel"><?= painel_titulo('Compras', plural(count($c), 'registro', 'registros')) ?>
      <?= $c ? $tabela([['Data', fn($r) => data_br($r['data'])], ['Produto', fn($r) => bonito($r['produto_animal'])], ['Qtd.', fn($r) => num_br((float) $r['quantidade'], fmod((float) $r['quantidade'], 1) == 0.0 ? 0 : 2)], ['Fornecedor', fn($r) => bonito($r['fornecedor'])], ['Valor', fn($r) => dinheiro($r['valor'])]], $c) : vazio('Sem compras no período.') ?></section>
  </div>
  <?php endif; ?>
<?php endif; ?>
</div>
