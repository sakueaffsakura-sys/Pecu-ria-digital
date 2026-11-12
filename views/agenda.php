<?php
$nomes = ['vacina' => 'Vacina', 'parto' => 'Parto', 'cio' => 'Cio'];
$secaoTipo = ['vacina' => 'vacinacao', 'parto' => 'inseminacao', 'cio' => 'inseminacao'];
$tipos = [['vacina', 'Vacinas', 'syringe'], ['parto', 'Partos', 'heart-pulse'], ['cio', 'Retorno de cio', 'refresh-cw']];
$hoje = $dados['hoje'];
$rotuloPeriodo = $modo === 'mes' ? nome_mes((int) substr($de, 5, 2)) . ' de ' . substr($de, 0, 4) : substr(data_br($de), 0, 5) . ' a ' . substr(data_br($ate), 0, 5);
$params = fn(array $mudar) => url('app/agenda', array_filter(array_merge(['modo' => $modo, 'ref' => $ref, 'ocultar' => $ocultos ?: null], $mudar), fn($v) => $v !== null && $v !== []));
$ics = url('agenda.ics', array_filter(['modo' => $modo, 'ref' => $ref, 'ocultar' => $ocultos ?: null]));
?>
<?= titulo_pagina('Agenda', 'Vacinas a vencer, partos previstos e retorno de cio. Toque num item para abrir o registro.',
    $eventos ? botao_link($ics, 'Adicionar ao calendário do celular', 'calendar-plus', 'contorno', ['download' => 'agenda-pecuaria-digital.ics'])
        : '<span class="botao botao-contorno desligado">' . icone('calendar-plus') . '<span>Adicionar ao calendário do celular</span></span>') ?>

<div class="barra-agenda">
  <div role="group" aria-label="Período" class="segmentado">
    <a href="<?= e($params(['modo' => 'semana'])) ?>" aria-pressed="<?= $modo === 'semana' ? 'true' : 'false' ?>">Semana</a>
    <a href="<?= e($params(['modo' => 'mes'])) ?>" aria-pressed="<?= $modo === 'mes' ? 'true' : 'false' ?>">Mês</a>
  </div>
  <div class="navega-periodo">
    <a class="botao-icone botao-contorno-q" href="<?= e($params(['ref' => $anterior])) ?>" aria-label="<?= $modo === 'semana' ? 'Semana anterior' : 'Mês anterior' ?>"><?= icone('chevron-left') ?></a>
    <span class="periodo-rotulo" aria-live="polite"><?= e(mb_strtoupper(mb_substr($rotuloPeriodo, 0, 1)) . mb_substr($rotuloPeriodo, 1)) ?></span>
    <a class="botao-icone botao-contorno-q" href="<?= e($params(['ref' => $proximo])) ?>" aria-label="<?= $modo === 'semana' ? 'Próxima semana' : 'Próximo mês' ?>"><?= icone('chevron-right') ?></a>
  </div>
  <a class="botao botao-fantasma" href="<?= e($params(['ref' => hoje()])) ?>">Hoje</a>
  <div role="group" aria-label="Mostrar" class="pilulas pilulas-direita">
    <?php foreach ($tipos as [$t, $rot, $ic]):
      $ativo = !in_array($t, $ocultos, true);
      $novo = $ativo ? array_merge($ocultos, [$t]) : array_values(array_diff($ocultos, [$t])); ?>
      <a href="<?= e($params(['ocultar' => $novo ?: null])) ?>" aria-pressed="<?= $ativo ? 'true' : 'false' ?>"><?= icone($ic) ?><?= e($rot) ?></a>
    <?php endforeach; ?>
  </div>
</div>

<div class="grade-inicio">
  <section class="painel p-painel min0" aria-label="Compromissos do período">
    <?php if (!$porDia): ?>
      <?= vazio($ocultos ? 'Nada neste período com os filtros escolhidos.' : 'Nada marcado neste período.') ?>
    <?php else: ?>
      <ol class="dias-agenda">
        <?php foreach ($porDia as $dia => $lista): $t = data_extenso($dia); ?>
          <li>
            <h2 class="dia-titulo<?= $dia === $hoje ? ' hoje' : '' ?>"><?= $dia === $hoje ? 'Hoje, ' : '' ?><?= e($dia === $hoje ? $t : mb_strtoupper(mb_substr($t, 0, 1)) . mb_substr($t, 1)) ?></h2>
            <ul class="caixa-eventos">
              <?php foreach ($lista as $ev): ?>
                <li><a class="evento" href="<?= e(url('app/' . $secaoTipo[$ev['tipo']], ['editar' => $ev['registro_id']])) ?>">
                  <?= $ev['brinco'] ? brinco($ev['brinco']) : '<span class="espaco-brinco"></span>' ?>
                  <span class="min0 cresce"><strong class="truncar bloco"><?= e($ev['titulo']) ?></strong><span class="truncar bloco pequeno texto-suave"><?= e($nomes[$ev['tipo']]) ?> · <?= e($ev['detalhe'] ?? '—') ?></span></span>
                  <?= icone('chevron-right', 'ic ic-suave') ?>
                </a></li>
              <?php endforeach; ?>
            </ul>
          </li>
        <?php endforeach; ?>
      </ol>
    <?php endif; ?>
    <?php if ($dados['truncado']): ?><p class="pequeno texto-suave mt-3">Mostrando os primeiros 500 compromissos. Use a visão de semana para ver o resto.</p><?php endif; ?>
  </section>

  <aside class="pilha-6 min0">
    <section class="painel p-painel">
      <h2 class="titulo-secao">Vacinas atrasadas</h2>
      <?php if (!$dados['atrasadas']): ?><p class="texto-suave mt-3">Nenhuma vacina atrasada.</p><?php else: ?>
        <ul class="lista-divisoria mt-3">
          <?php foreach ($dados['atrasadas'] as $x): ?>
            <li><a class="evento" href="<?= e(url('app/vacinacao', ['editar' => $x['registro_id']])) ?>">
              <?= $x['brinco'] ? brinco($x['brinco']) : '<span class="espaco-brinco"></span>' ?>
              <span class="min0 cresce"><strong class="truncar bloco"><?= e($x['titulo']) ?></strong><span class="truncar bloco pequeno texto-alerta">Venceu em <?= e(data_br($x['data'])) ?></span></span></a></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
    <section class="painel p-painel">
      <h2 class="titulo-secao">Sem pesagem há 60 dias</h2>
      <p class="pequeno texto-suave">Animais com até 2 anos.</p>
      <?php if (!$dados['sem_pesagem']): ?><p class="texto-suave mt-3">Todos os animais jovens foram pesados.</p><?php else: ?>
        <ul class="lista-divisoria mt-3">
          <?php foreach (array_slice($dados['sem_pesagem'], 0, 12) as $x): ?>
            <li class="item-parto"><?= brinco($x['brinco']) ?><span class="min0 cresce"><strong class="truncar bloco"><?= e($x['nome'] ?: 'Brinco ' . $x['brinco']) ?></strong>
              <span class="truncar bloco pequeno texto-suave"><?= e($x['ultima_pesagem'] ? 'Última pesagem em ' . data_br($x['ultima_pesagem']) : 'Nunca pesado') ?></span></span></li>
          <?php endforeach; ?>
        </ul>
        <?php if (pode_editar()): ?><a class="botao botao-primario botao-largo mt-3" href="/app/bezerros?novo=1"><?= icone('scale') ?>Registrar pesagens</a><?php endif; ?>
      <?php endif; ?>
    </section>
  </aside>
</div>
