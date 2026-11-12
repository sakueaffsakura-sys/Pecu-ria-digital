<?php
$a = $ficha['animal'];
$fechar = url("app/$secao", array_filter($get));
$pai = fn(?string $nome, ?string $br) => $br ? ($nome ?: 'Brinco ' . $br) : '—';
?>
<dialog class="dialogo dialogo-ficha" data-auto aria-labelledby="ficha-titulo" data-fechar-para="<?= e($fechar) ?>" open>
  <div class="dialogo-cabeca ficha-cabeca">
    <?= brinco($a['numero_brinco'], 'grande', null, 'Brinco ' . $a['numero_brinco']) ?>
    <div class="min0">
      <h2 id="ficha-titulo" class="ficha-nome"><?= e($a['nome'] ?: 'Brinco ' . $a['numero_brinco']) ?></h2>
      <p class="descricao">Ficha do animal: cadastro, família e tudo que aconteceu com ele.</p>
    </div>
    <a class="dialogo-x" href="<?= e($fechar) ?>" data-fechar aria-label="Fechar"><?= icone('x') ?></a>
  </div>
  <dl class="fatos">
    <div><dt>Situação</dt><dd><?= selo($a['situacao']) ?></dd></div>
    <div><dt>Lote</dt><dd><?= e($a['lote'] ?? '—') ?></dd></div>
    <div><dt>Nascimento</dt><dd><?= e(data_br($a['data_nascimento'])) ?></dd></div>
    <div><dt>Sexo</dt><dd><?= e(SEXOS[$a['sexo']] ?? '—') ?></dd></div>
    <div><dt>Raça</dt><dd><?= e(bonito($a['raca'])) ?></dd></div>
    <div><dt>Mãe</dt><dd><?= e($pai($a['mae_nome'], $a['mae_brinco'])) ?></dd></div>
    <div><dt>Pai</dt><dd><?= e($pai($a['pai_nome'], $a['pai_brinco'])) ?></dd></div>
  </dl>
  <div class="ficha-colunas">
    <section>
      <h3 class="titulo-secao menor">Linha do tempo</h3>
      <?php if (!$ficha['linha']): ?>
        <?= vazio('Nada registrado para este animal ainda.') ?>
      <?php else: ?>
        <ol class="linha-tempo">
          <?php foreach ($ficha['linha'] as $ev): ?>
            <li><time datetime="<?= e($ev['data']) ?>"><?= e(data_br($ev['data'])) ?></time>
              <div class="min0"><strong class="bloco"><?= e($ev['titulo']) ?></strong><?php if ($ev['detalhe']): ?><span class="pequeno texto-suave"><?= e($ev['detalhe']) ?></span><?php endif; ?></div></li>
          <?php endforeach; ?>
        </ol>
      <?php endif; ?>
    </section>
    <section>
      <h3 class="titulo-secao menor">Filhos</h3>
      <?php if (!$ficha['filhos']): ?>
        <p class="texto-suave">Nenhum filho registrado.</p>
      <?php else: ?>
        <ul class="lista-divisoria">
          <?php foreach ($ficha['filhos'] as $f): ?>
            <li class="item-parto"><?= brinco($f['numero_brinco']) ?><a class="min0" href="<?= e(url('app/rebanho', ['animal' => $f['id']])) ?>">
              <strong class="truncar bloco"><?= e($f['nome'] ?: 'Brinco ' . $f['numero_brinco']) ?></strong>
              <span class="pequeno texto-suave"><?= e($f['data_nascimento'] ? data_br($f['data_nascimento']) : 'Data não informada') ?> · <?= e(SEXOS[$f['sexo']] ?? '') ?></span></a></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </div>
  <?php if (pode_editar()): ?>
    <div class="dialogo-rodape">
      <a class="botao botao-contorno" href="<?= e(url('app/bezerros', ['novo' => '1', 'animal_id' => $a['numero_brinco']])) ?>"><?= icone('scale') ?>Pesagem</a>
      <a class="botao botao-contorno" href="<?= e(url('app/vacinacao', ['novo' => '1', 'animal_id' => $a['numero_brinco']])) ?>"><?= icone('syringe') ?>Vacina</a>
      <a class="botao botao-primario" href="<?= e(url('app/rebanho', ['editar' => $a['id']])) ?>"><?= icone('pencil') ?>Editar</a>
    </div>
  <?php endif; ?>
</dialog>
