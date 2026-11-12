<div class="cabecalho">
  <h1>Agenda</h1>
  <form method="get" action="<?= e(url('agenda')) ?>" class="acoes">
    <label for="a-dias">Mostrar até</label>
    <select id="a-dias" name="dias" data-enviar-ao-mudar>
      <?php foreach ([30, 60, 90, 180] as $d): ?><option value="<?= $d ?>"<?= $d === $dias ? ' selected' : '' ?>><?= $d ?> dias</option><?php endforeach; ?>
    </select>
    <noscript><button class="botao botao-leve">Ver</button></noscript>
  </form>
</div>
<p class="ajuda">Vacinas com próxima dose, partos previstos e diagnósticos de prenhez pendentes. O que está atrasado aparece primeiro.</p>
<?php if (!$itens): ?>
  <div class="vazio-grande"><p>Nada agendado para este período.</p></div>
<?php else: ?>
  <ul class="agenda">
    <?php foreach ($itens as $i): $atrasado = $i['data'] < hoje(); ?>
      <li class="<?= $atrasado ? 'atrasado' : '' ?>">
        <span class="data"><?= data_br($i['data']) ?><?= $atrasado ? ' · atrasado' : '' ?></span>
        <?= selo($i['tipo']) ?>
        <a href="<?= e($i['link']) ?>"><?= e($i['texto']) ?></a>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
