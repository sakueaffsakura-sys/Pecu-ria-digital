<?php
$campos = modulos()['animais']['campos'];
$volta = '/animal/' . $a['id'];
$novo = fn(string $mod, string $campo) => url("m/$mod/novo", [$campo => $a['brinco'], 'voltar' => $volta]);
$pesoAtual = $pesagens ? end($pesagens) : null;
$femea = $a['sexo'] === 'F';
?>
<div class="cabecalho">
  <h1>Animal <?= e($a['brinco']) ?><?= $a['nome'] ? ' · ' . e($a['nome']) : '' ?></h1>
  <div class="acoes">
    <?php if (pode_editar()): ?><a class="botao" href="<?= e(url('m/animais/' . $a['id'])) ?>">Editar</a><?php endif; ?>
    <a class="botao botao-leve" href="<?= e(url('m/animais')) ?>">Todos os animais</a>
  </div>
</div>

<section class="cartoes">
  <div class="numero"><span class="n"><?= $pesoAtual ? num_br($pesoAtual['peso_kg'], 1) . ' kg' : '—' ?></span><span>peso atual</span><small><?= $pesoAtual ? 'em ' . data_br($pesoAtual['data']) : 'sem pesagem' ?></small></div>
  <div class="numero"><span class="n"><?= $gmd === null ? '—' : num_br($gmd * 1000) . ' g' ?></span><span>ganho por dia</span><small>da 1ª à última pesagem</small></div>
  <div class="numero"><span class="n"><?= e(idade($a['data_nascimento'])) ?></span><span>idade</span><small>nasceu em <?= data_br($a['data_nascimento']) ?></small></div>
  <?php if ($femea): ?><div class="numero"><span class="n"><?= count($filhos) ?></span><span>crias</span><small><?= num_br($leite30) ?> L de leite em 30 dias</small></div><?php endif; ?>
</section>

<section class="cartao">
  <h2>Dados</h2>
  <dl class="dados">
    <div><dt>Sexo</dt><dd><?= e(SEXOS[$a['sexo']] ?? $a['sexo']) ?></dd></div>
    <div><dt>Categoria</dt><dd><?= e($campos['categoria']['opcoes'][$a['categoria']] ?? '—') ?></dd></div>
    <div><dt>Raça</dt><dd><?= e($a['raca'] ?: '—') ?></dd></div>
    <div><dt>Lote</dt><dd><?= e($a['lote'] ?: '—') ?></dd></div>
    <div><dt>Mãe</dt><dd><?= $a['mae_id'] ? '<a href="' . e(url('animal/' . $a['mae_id'])) . '">' . e($a['mae_brinco']) . '</a>' : '—' ?></dd></div>
    <div><dt>Pai / sêmen</dt><dd><?= e($a['pai'] ?: '—') ?></dd></div>
    <div><dt>Origem</dt><dd><?= e($campos['origem']['opcoes'][$a['origem']] ?? $a['origem']) ?></dd></div>
    <div><dt>Situação</dt><dd><?= selo($campos['situacao']['opcoes'][$a['situacao']] ?? $a['situacao'], $a['situacao']) ?></dd></div>
  </dl>
  <?php if ($a['observacoes']): ?><p class="obs"><?= nl2br(e($a['observacoes'])) ?></p><?php endif; ?>
</section>

<section class="cartao">
  <div class="cabecalho-cartao"><h2>Pesagens</h2><?php if (pode_editar()): ?><a class="botao botao-leve" href="<?= e($novo('pesagens', 'animal_id')) ?>">+ Pesagem</a><?php endif; ?></div>
  <?php if (!$pesagens): ?><p class="vazio">Nenhuma pesagem.</p><?php else: ?>
    <?= count($graficoPeso) >= 2 ? grafico_barras($graficoPeso, fn($v) => num_br($v) . ' kg', [], 'Peso') : '' ?>
    <div class="tabela-rolagem"><table class="tabela"><thead><tr><th>Data</th><th class="num">Peso (kg)</th><th class="num">Ganho/dia</th><th>Obs.</th><th></th></tr></thead><tbody>
      <?php $ant = null; foreach (array_reverse($pesagens, true) as $k => $pg): $ant = $pesagens[$k - 1] ?? null; $d = $ant ? dias_entre($ant['data'], $pg['data']) : 0; ?>
        <tr><td data-rotulo="Data"><?= data_br($pg['data']) ?></td><td class="num" data-rotulo="Peso"><?= num_br($pg['peso_kg'], 1) ?></td>
          <td class="num" data-rotulo="Ganho/dia"><?= $ant && $d > 0 ? num_br((($pg['peso_kg'] - $ant['peso_kg']) / $d) * 1000) . ' g' : '—' ?></td>
          <td data-rotulo="Obs."><?= e($pg['observacoes'] ?: '') ?></td><td class="acoes-linha"><a href="<?= e(url('m/pesagens/' . $pg['id'], ['voltar' => $volta])) ?>">Abrir</a></td></tr>
      <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</section>

<section class="cartao">
  <div class="cabecalho-cartao"><h2>Vacinas e remédios</h2><?php if (pode_editar()): ?><a class="botao botao-leve" href="<?= e($novo('vacinacoes', 'animal_id')) ?>">+ Vacinação</a><?php endif; ?></div>
  <?php if (!$vacinas): ?><p class="vazio">Nenhuma aplicação.</p><?php else: ?>
    <div class="tabela-rolagem"><table class="tabela"><thead><tr><th>Data</th><th>Vacina</th><th>Dose</th><th>Próxima</th><th></th></tr></thead><tbody>
      <?php foreach ($vacinas as $v): ?>
        <tr><td data-rotulo="Data"><?= data_br($v['data']) ?></td><td data-rotulo="Vacina"><?= e($v['vacina']) ?><?= $v['lote_id'] ? ' <small>(no lote)</small>' : '' ?></td>
          <td data-rotulo="Dose"><?= e($v['dose'] ?: '—') ?></td><td data-rotulo="Próxima"><?= data_br($v['proxima_dose']) ?></td>
          <td class="acoes-linha"><a href="<?= e(url('m/vacinacoes/' . $v['id'], ['voltar' => $volta])) ?>">Abrir</a></td></tr>
      <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</section>

<?php if ($femea): ?>
<section class="cartao">
  <div class="cabecalho-cartao"><h2>Reprodução</h2>
    <?php if (pode_editar()): ?><span><a class="botao botao-leve" href="<?= e($novo('inseminacoes', 'animal_id')) ?>">+ Inseminação</a> <a class="botao botao-leve" href="<?= e($novo('nascimentos', 'mae_id')) ?>">+ Parto</a></span><?php endif; ?></div>
  <?php if (!$inseminacoes): ?><p class="vazio">Nenhuma inseminação.</p><?php else: ?>
    <div class="tabela-rolagem"><table class="tabela"><thead><tr><th>Data</th><th>Tipo</th><th>Touro / sêmen</th><th>Diagnóstico</th><th>Parto previsto</th><th></th></tr></thead><tbody>
      <?php $opsR = modulos()['inseminacoes']['campos']['resultado']['opcoes']; foreach ($inseminacoes as $i): ?>
        <tr><td data-rotulo="Data"><?= data_br($i['data']) ?></td><td data-rotulo="Tipo"><?= e($i['tipo']) ?></td><td data-rotulo="Touro"><?= e($i['touro_semen'] ?: '—') ?></td>
          <td data-rotulo="Diagnóstico"><?= selo($opsR[$i['resultado']] ?? $i['resultado'], $i['resultado']) ?></td><td data-rotulo="Parto"><?= data_br($i['previsao_parto']) ?></td>
          <td class="acoes-linha"><a href="<?= e(url('m/inseminacoes/' . $i['id'], ['voltar' => $volta])) ?>">Abrir</a></td></tr>
      <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
  <h3>Crias</h3>
  <?php if (!$filhos): ?><p class="vazio">Nenhuma cria cadastrada.</p><?php else: ?>
    <ul class="lista-simples">
      <?php foreach ($filhos as $f): ?><li><a href="<?= e(url('animal/' . $f['id'])) ?>"><?= e($f['brinco']) ?></a> · <?= e(SEXOS[$f['sexo']] ?? '') ?> · nasceu em <?= data_br($f['data_nascimento']) ?> · <?= e($f['situacao']) ?></li><?php endforeach; ?>
    </ul>
  <?php endif; ?>
  <?php if (pode_editar()): ?><p><a class="botao botao-leve" href="<?= e($novo('producao_leite', 'animal_id')) ?>">+ Lançar leite desta vaca</a></p><?php endif; ?>
</section>
<?php endif; ?>
