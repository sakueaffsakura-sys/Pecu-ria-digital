<?php
$femeas = (int) ($porSexo['F'] ?? 0);
$machos = (int) ($porSexo['M'] ?? 0);
$saldo = $mesAtual[0] - $mesAtual[1];
?>
<div class="cabecalho">
  <h1><?= e($prop['nome']) ?></h1>
  <?php if (pode_editar()): ?>
  <div class="acoes">
    <a class="botao" href="<?= e(url('m/animais/novo')) ?>">+ Animal</a>
    <a class="botao botao-leve" href="<?= e(url('m/pesagens/novo')) ?>">+ Pesagem</a>
    <a class="botao botao-leve" href="<?= e(url('m/producao_leite/novo')) ?>">+ Leite</a>
    <a class="botao botao-leve" href="<?= e(url('m/vacinacoes/novo')) ?>">+ Vacina</a>
  </div>
  <?php endif; ?>
</div>

<section class="cartoes" aria-label="Números principais">
  <a class="numero" href="<?= e(url('m/animais', ['situacao' => 'ativo'])) ?>"><span class="n"><?= num_br($femeas + $machos) ?></span><span>animais ativos</span><small><?= num_br($femeas) ?> fêmea<?= $femeas === 1 ? '' : 's' ?> · <?= num_br($machos) ?> macho<?= $machos === 1 ? '' : 's' ?> · <?= num_br($lotes) ?> lote<?= $lotes === 1 ? '' : 's' ?></small></a>
  <a class="numero<?= $vencidas ? ' alerta' : '' ?>" href="<?= e(url('agenda')) ?>"><span class="n"><?= count($vencidas) ?></span><span>vacinas vencidas</span><small><?= count($vacinas) - count($vencidas) ?> vencem em 30 dias</small></a>
  <a class="numero" href="<?= e(url('agenda')) ?>"><span class="n"><?= count($partos) ?></span><span>partos previstos</span><small>próximos 60 dias · <?= count($diagnosticos) ?> diagnósticos a fazer</small></a>
  <a class="numero" href="<?= e(url('m/producao_leite')) ?>"><span class="n"><?= num_br($leite30) ?> L</span><span>leite em 30 dias</span><small>média de <?= num_br($leite30 / 30, 1) ?> L por dia</small></a>
  <a class="numero<?= $saldo < 0 ? ' alerta' : '' ?>" href="<?= e(url('m/movimentacoes', ['de' => date('Y-m-01')])) ?>"><span class="n"><?= dinheiro($saldo) ?></span><span>saldo do mês</span><small>entrou <?= dinheiro($mesAtual[0]) ?> · saiu <?= dinheiro($mesAtual[1]) ?></small></a>
</section>

<?php if ($femeas + $machos === 0): ?>
  <section class="cartao boas-vindas">
    <h2>Primeiros passos</h2>
    <ol>
      <li><a href="<?= e(url('m/lotes/novo')) ?>">Crie os lotes</a> (pastos ou grupos de animais).</li>
      <li><a href="<?= e(url('m/animais/novo')) ?>">Cadastre os animais</a> ou <a href="<?= e(url('importar')) ?>">importe de uma planilha</a>.</li>
      <li>Lance pesagens, vacinas, inseminações e a produção de leite.</li>
      <li>Em <a href="<?= e(url('propriedades/' . $prop['id'])) ?>">Propriedade</a>, dê acesso a quem trabalha com você.</li>
    </ol>
  </section>
<?php endif; ?>

<div class="duas-colunas">
  <section class="cartao">
    <h2>Leite nos últimos 14 dias</h2>
    <?= grafico_barras($leiteDias, fn($v) => num_br($v) . ' L', [], 'Litros por dia') ?>
  </section>
  <section class="cartao">
    <h2>Entradas e saídas (6 meses)</h2>
    <?= grafico_barras($finMeses, fn($v) => dinheiro($v), ['Entradas', 'Saídas'], 'Financeiro por mês') ?>
  </section>
</div>

<div class="duas-colunas">
  <section class="cartao">
    <h2>Vacinas a aplicar</h2>
    <?php if (!$vacinas): ?><p class="vazio">Nenhuma vacina vencendo nos próximos 30 dias.</p><?php else: ?>
      <ul class="lista-simples">
        <?php foreach (array_slice($vacinas, 0, 8) as $v): ?>
          <li><?= $v['proxima_dose'] < hoje() ? selo('vencida', 'alerta') : selo(data_br($v['proxima_dose'])) ?>
            <?= e($v['vacina']) ?> — <?= $v['brinco'] ? 'animal <a href="' . e(url('animal/' . $v['animal_id'])) . '">' . e($v['brinco']) . '</a>' : 'lote ' . e($v['lote']) ?></li>
        <?php endforeach; ?>
      </ul>
      <p><a href="<?= e(url('agenda')) ?>">Ver agenda completa</a></p>
    <?php endif; ?>
  </section>
  <section class="cartao">
    <h2>Partos previstos</h2>
    <?php if (!$partos): ?><p class="vazio">Nenhum parto previsto para os próximos 60 dias.</p><?php else: ?>
      <ul class="lista-simples">
        <?php foreach (array_slice($partos, 0, 8) as $pt): ?>
          <li><?= selo(data_br($pt['previsao_parto']), $pt['previsao_parto'] < hoje() ? 'alerta' : '') ?> vaca <a href="<?= e(url('animal/' . $pt['animal_id'])) ?>"><?= e($pt['brinco']) ?></a><?= $pt['resultado'] === 'pendente' ? ' <small>(prenhez não confirmada)</small>' : '' ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</div>

<section class="cartao">
  <h2>Últimas anotações</h2>
  <?php if (!$anotacoes): ?><p class="vazio">Nenhuma anotação. <a href="<?= e(url('m/anotacoes/novo')) ?>">Escrever a primeira</a></p><?php else: ?>
    <ul class="lista-simples">
      <?php foreach ($anotacoes as $n): ?><li><?= selo(data_br($n['data'])) ?> <a href="<?= e(url('m/anotacoes/' . $n['id'])) ?>"><?= e($n['titulo']) ?></a></li><?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
