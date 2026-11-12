<?php
$total = array_sum(array_column($linhas, 'contagem'));
$tempo = array_sum(array_column($linhas, 'ms'));
$inconsistentes = array_sum(array_column($checagens, 'qtd'));
?>
<?= titulo_pagina('Diagnóstico', 'Saúde dos dados e desempenho das consultas da propriedade.', botao_link('/app/diagnostico', 'Atualizar', 'refresh-cw', 'contorno')) ?>
<div class="duas-colunas">
  <section class="painel p-painel">
    <?= painel_titulo('Contagens por módulo', 'Contagens reais servidas do banco') ?>
    <div class="lista-divisoria">
      <div class="linha-entre pequeno negrito texto-suave diag-linha"><span><?= icone('clock-3', 'ic-selo') ?> Tempo total: <?= e(num_br($tempo, 1)) ?> ms</span><span>Total: <?= e(num_br($total)) ?> registros</span></div>
      <?php foreach ($linhas as $r): ?>
        <div class="linha-entre diag-linha">
          <div><strong class="bloco pequeno primeira-maiuscula"><?= e(str_replace('_', ' ', $r['tabela'])) ?></strong><span class="pequeno texto-suave">Última atualização: <?= e($r['ultima'] ? data_br($r['ultima']) . ' ' . substr($r['ultima'], 11, 5) : '—') ?></span></div>
          <div class="direita"><strong class="bloco pequeno"><?= e(num_br($r['contagem'])) ?></strong><span class="pequeno texto-suave"><?= e(num_br($r['ms'], 1)) ?> ms</span></div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
  <section class="painel p-painel">
    <?= painel_titulo('Revisão de consistência', $inconsistentes === 1 ? '1 possível irregularidade — nenhum dado é apagado' : num_br($inconsistentes) . ' possíveis irregularidades — nenhum dado é apagado') ?>
    <?php if (!$inconsistentes): ?>
      <?= vazio('Nenhuma inconsistência detectada.') ?>
    <?php else: foreach ($checagens as $c): ?>
      <div class="checagem"><?= icone('triangle-exclamation-point', 'ic ic-aviso cor-aviso') ?><span class="negrito"><?= e(mb_strtoupper(mb_substr($c['checagem'], 0, 1)) . mb_substr($c['checagem'], 1)) ?></span><span class="selo selo-alerta"><?= e(num_br($c['qtd'])) ?></span></div>
    <?php endforeach; endif; ?>
  </section>
</div>
