<?php
$cats = modulos()['animais']['campos']['categoria']['opcoes'];
$totalRebanho = array_sum(array_column($rebanho, 'n'));
?>
<div class="cabecalho">
  <h1>Relatórios</h1>
  <div class="acoes"><button class="botao botao-leve" type="button" data-imprimir>Imprimir / salvar PDF</button></div>
</div>
<form class="filtros nao-imprimir" method="get" action="<?= e(url('relatorios')) ?>">
  <div class="campo"><label for="r-de">De</label><input id="r-de" type="date" name="de" value="<?= e($de) ?>"></div>
  <div class="campo"><label for="r-ate">Até</label><input id="r-ate" type="date" name="ate" value="<?= e($ate) ?>"></div>
  <div class="campo campo-botoes"><button class="botao" type="submit">Ver período</button></div>
</form>
<p class="resumo"><strong><?= e($prop['nome']) ?></strong> · período de <?= data_br($de) ?> a <?= data_br($ate) ?> (<?= $diasPeriodo ?> dias)</p>

<section class="cartoes">
  <div class="numero"><span class="n"><?= dinheiro($entradas - $saidas) ?></span><span>resultado</span><small>entradas <?= dinheiro($entradas) ?> · saídas <?= dinheiro($saidas) ?></small></div>
  <div class="numero"><span class="n"><?= num_br($leiteTotal) ?> L</span><span>leite</span><small><?= num_br($leiteTotal / max(1, $diasPeriodo), 1) ?> L por dia</small></div>
  <div class="numero"><span class="n"><?= (int) (($nasc['F'] ?? 0) + ($nasc['M'] ?? 0)) ?></span><span>nascimentos</span><small><?= (int) ($nasc['F'] ?? 0) ?> fêmeas · <?= (int) ($nasc['M'] ?? 0) ?> machos</small></div>
  <div class="numero"><span class="n"><?= $pesagens['gmd'] === null ? '—' : num_br($pesagens['gmd'] * 1000) . ' g' ?></span><span>ganho médio por dia</span><small><?= $pesagens['animais_gmd'] ?> animais pesados 2+ vezes</small></div>
</section>

<div class="duas-colunas">
  <section class="cartao">
    <h2>Rebanho hoje (<?= num_br($totalRebanho) ?> ativos)</h2>
    <table class="tabela"><thead><tr><th>Categoria</th><th>Sexo</th><th class="num">Animais</th></tr></thead><tbody>
      <?php foreach ($rebanho as $r): ?><tr><td data-rotulo="Categoria"><?= e($cats[$r['categoria']] ?? 'Sem categoria') ?></td><td data-rotulo="Sexo"><?= e(SEXOS[$r['sexo']] ?? $r['sexo']) ?></td><td class="num" data-rotulo="Animais"><?= num_br($r['n']) ?></td></tr><?php endforeach; ?>
    </tbody></table>
  </section>
  <section class="cartao">
    <h2>Animais por lote</h2>
    <table class="tabela"><thead><tr><th>Lote</th><th class="num">Animais</th></tr></thead><tbody>
      <?php foreach ($porLote as $r): ?><tr><td data-rotulo="Lote"><?= e($r['lote']) ?></td><td class="num" data-rotulo="Animais"><?= num_br($r['n']) ?></td></tr><?php endforeach; ?>
    </tbody></table>
  </section>
</div>

<div class="duas-colunas">
  <section class="cartao">
    <h2>Reprodução e sanidade no período</h2>
    <dl class="dados">
      <div><dt>Inseminações</dt><dd><?= array_sum($insem) ?></dd></div>
      <div><dt>Prenhes confirmadas</dt><dd><?= (int) ($insem['prenhe'] ?? 0) ?></dd></div>
      <div><dt>Vazias</dt><dd><?= (int) ($insem['vazia'] ?? 0) ?></dd></div>
      <div><dt>Taxa de prenhez</dt><dd><?php $diag = ($insem['prenhe'] ?? 0) + ($insem['vazia'] ?? 0); echo $diag ? num_br(100 * ($insem['prenhe'] ?? 0) / $diag) . '%' : '—'; ?></dd></div>
      <div><dt>Vacinações</dt><dd><?= $vac ?></dd></div>
      <div><dt>Pesagens</dt><dd><?= $pesagens['n'] ?> (média <?= $pesagens['media'] === null ? '—' : num_br($pesagens['media'], 1) . ' kg' ?>)</dd></div>
    </dl>
  </section>
  <section class="cartao">
    <h2>Compras e vendas no período</h2>
    <dl class="dados">
      <div><dt>Vendas</dt><dd><?= (int) $vendas['n'] ?> (<?= num_br($vendas['q']) ?> itens) · <?= dinheiro($vendas['v']) ?></dd></div>
      <div><dt>Compras</dt><dd><?= (int) $compras['n'] ?> (<?= num_br($compras['q']) ?> itens) · <?= dinheiro($compras['v']) ?></dd></div>
    </dl>
  </section>
</div>

<section class="cartao">
  <h2>Leite por mês</h2>
  <?= grafico_barras($leiteMeses, fn($v) => num_br($v) . ' L', [], 'Leite por mês') ?>
</section>

<section class="cartao">
  <h2>Financeiro por mês</h2>
  <?= grafico_barras($finMeses, fn($v) => dinheiro($v), ['Entradas', 'Saídas'], 'Financeiro por mês') ?>
  <h3>Por categoria</h3>
  <?php if (!$categorias): ?><p class="vazio">Nenhum lançamento no período.</p><?php else: ?>
  <div class="tabela-rolagem"><table class="tabela"><thead><tr><th>Tipo</th><th>Categoria</th><th class="num">Lançamentos</th><th class="num">Total</th></tr></thead><tbody>
    <?php foreach ($categorias as $c): ?><tr><td data-rotulo="Tipo"><?= selo($c['tipo'] === 'entrada' ? 'Entrada' : 'Saída', $c['tipo']) ?></td><td data-rotulo="Categoria"><?= e($c['categoria']) ?></td><td class="num" data-rotulo="Lançamentos"><?= (int) $c['n'] ?></td><td class="num" data-rotulo="Total"><?= dinheiro($c['total']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
  <?php endif; ?>
</section>
