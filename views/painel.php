<?php
$brincos = [
    [$resumo['animais_ativos'], 'animais ativos', 'rebanho', false],
    [$resumo['leite_hoje'], 'litros de leite hoje', 'producao', true],
    [$resumo['vacinas_vencidas'], 'vacinas atrasadas', 'vacinacao', false],
    [$resumo['partos_30d'], 'partos em 30 dias', 'agenda', false],
];
$fmtBrinco = fn($v, bool $compacto) => $compacto ? compacto((float) $v) : num_br((float) $v);
$leite = array_map(fn($m) => ['rotulo' => $m['rotulo'], 'valor' => $m['leite'], 'atual' => $m['atual'], 'dica' => num_br(round($m['leite'])) . ' L'], $meses);
$resultado = array_map(fn($m) => ['rotulo' => $m['rotulo'], 'valor' => $m['receitas'] - $m['despesas'], 'atual' => $m['atual'],
    'dica' => dinheiro($m['receitas'] - $m['despesas']) . ' (receitas ' . dinheiro($m['receitas']) . ', despesas ' . dinheiro($m['despesas']) . ')'], $meses);
$temLeite = array_sum(array_column($meses, 'leite')) > 0;
$temDinheiro = array_sum(array_column($meses, 'receitas')) + array_sum(array_column($meses, 'despesas')) > 0;
$mesAtual = end($meses);
$nomesTipo = ['vacina' => 'Vacina', 'parto' => 'Parto', 'cio' => 'Cio'];
$secaoTipo = ['vacina' => 'vacinacao', 'parto' => 'inseminacao', 'cio' => 'inseminacao'];
$hoje = data_extenso(hoje());
?>
<div class="pilha-6">
  <div class="cabeca-pagina">
    <div class="cabeca-texto">
      <h1 class="titulo-pagina"><?= e($prop['nome']) ?></h1>
      <p class="descricao"><?= e(mb_strtoupper(mb_substr($hoje, 0, 1)) . mb_substr($hoje, 1)) ?>. O resumo da fazenda hoje.</p>
    </div>
    <?php if (pode_editar()): ?><div class="cabeca-acoes so-tela-media"><?= botao_link('/app/producao?novo=1', 'Registrar leite', 'milk') ?></div><?php endif; ?>
  </div>

  <div class="grade-inicio">
    <div class="pilha-6 min0">
      <section aria-label="Números principais" class="painel brincos">
        <?php foreach ($brincos as $i => [$valor, $legenda, $destino, $compacto]): ?>
          <a href="<?= e(url("app/$destino")) ?>" class="brinco-link" aria-label="<?= e(num_br((float) $valor, $compacto ? 1 : 0) . " $legenda. Abrir.") ?>">
            <?= brinco($fmtBrinco($valor, $compacto), 'grande', $legenda, null, $i) ?>
          </a>
        <?php endforeach; ?>
      </section>

      <section class="painel p-painel" aria-labelledby="atencao">
        <h2 id="atencao" class="titulo-secao">Precisa de atenção</h2>
        <?php if (!$avisos): ?>
          <p class="nada-atrasado"><?= icone('circle-check', 'ic ic-verde') ?>Nada atrasado. Bom trabalho.</p>
        <?php else: ?>
          <ul class="lista-atencao">
            <?php foreach ($avisos as $a): ?>
              <li class="aviso-barra cor-<?= e($a['nivel']) ?> fundo-suave">
                <div class="atencao-texto">
                  <?= icone($a['nivel'] === 'alerta' ? 'triangle-exclamation-point' : ($a['nivel'] === 'aviso' ? 'calendar-clock' : 'info'), 'ic ic-aviso') ?>
                  <div><p class="aviso-titulo"><?= e($a['titulo']) ?></p><p class="aviso-texto"><?= e($a['texto']) ?></p></div>
                </div>
                <a class="botao botao-contorno" href="<?= e(url('app/' . $a['secao'])) ?>">Resolver</a>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </section>

      <div class="duas-colunas">
        <section class="painel p-painel min0">
          <h2 class="titulo-secao">Leite por mês</h2>
          <p class="resumo-grafico">Este mês: <?= num_br(round($mesAtual['leite'])) ?> litros</p>
          <?= $temLeite ? '<div class="caixa-grafico">' . grafico_colunas($leite, fn($v) => num_br(round($v)) . ' L', 'Leite por mês', [0, 4, 8, 11]) . '</div>'
              : vazio('Nenhum leite registrado nos últimos 12 meses.', pode_editar() ? 'Registrar o leite de hoje' : null, '/app/producao?novo=1') ?>
        </section>
        <section class="painel p-painel min0">
          <h2 class="titulo-secao">Resultado por mês</h2>
          <p class="resumo-grafico">Este mês: <?= e(dinheiro($mesAtual['receitas'] - $mesAtual['despesas'])) ?></p>
          <p class="pequeno texto-suave">Receitas menos despesas e investimentos. Barra para baixo é mês no prejuízo.</p>
          <?= $temDinheiro ? '<div class="caixa-grafico">' . grafico_colunas($resultado, fn($v) => dinheiro($v), 'Resultado por mês', [0, 4, 8, 11]) . '</div>'
              : vazio('Nenhum lançamento de dinheiro nos últimos 12 meses.', pode_editar() ? 'Lançar uma receita ou despesa' : null, '/app/financeiro?novo=1') ?>
        </section>
      </div>
    </div>

    <aside class="pilha-6 min0" aria-label="Agenda e atalhos">
      <section class="painel p-painel">
        <div class="linha-entre"><h2 class="titulo-secao">Esta semana</h2><a class="botao botao-fantasma" href="/app/agenda">Ver agenda</a></div>
        <?php if (!$semana): ?>
          <p class="texto-suave mt-3">Nada marcado para os próximos 7 dias.</p>
        <?php else: ?>
          <ul class="lista-divisoria mt-3">
            <?php foreach ($semana as $ev): ?>
              <li><a class="item-semana" href="<?= e(url('app/' . $secaoTipo[$ev['tipo']], ['editar' => $ev['registro_id']])) ?>">
                <span class="dia-curto"><strong><?= e(substr(data_br($ev['data']), 0, 5)) ?></strong><small><?= e($nomesTipo[$ev['tipo']]) ?></small></span>
                <span class="min0"><strong class="truncar bloco"><?= e($ev['titulo']) ?></strong><span class="truncar bloco texto-suave pequeno"><?= e($ev['detalhe'] ?? '—') ?></span></span>
              </a></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </section>

      <section class="painel p-painel">
        <h2 class="titulo-secao">Partos previstos</h2>
        <?php if (!$partos): ?>
          <p class="texto-suave mt-3">Nenhuma vaca com parto previsto.</p>
        <?php else: ?>
          <ul class="lista-divisoria mt-3">
            <?php foreach ($partos as $pt): ?>
              <li class="item-parto"><?= brinco($pt['numero_brinco']) ?>
                <span class="min0"><strong class="truncar bloco"><?= e($pt['nome'] ?: 'Brinco ' . $pt['numero_brinco']) ?></strong><span class="pequeno texto-suave">Previsto para <?= e(data_br($pt['previsao_parto'])) ?></span></span></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </section>

      <?php if (pode_editar()): ?>
      <section class="painel p-painel">
        <h2 class="titulo-secao">Atalhos</h2>
        <div class="atalhos">
          <?= botao_link('/app/rebanho?novo=1', 'Cadastrar animal', 'plus', 'contorno') ?>
          <?= botao_link('/app/producao?novo=1', 'Registrar leite', 'milk', 'contorno') ?>
          <?= botao_link('/app/vacinacao?novo=1', 'Registrar vacina', 'syringe', 'contorno') ?>
          <?= botao_link('/app/bezerros?novo=1', 'Registrar pesagem', 'scale', 'contorno') ?>
          <?= botao_link('/app/producao?aba=anotacoes&novo=1', 'Fazer uma anotação', 'notebook-pen', 'contorno') ?>
        </div>
      </section>
      <?php endif; ?>
    </aside>
  </div>
</div>
