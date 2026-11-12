<?php
/** @var array $m @var ?array $linha @var array $valores @var array $erros @var array $listas @var ?string $travado @var string $voltar */
$slug = $m['slug'];
$novo = $linha === null;
$campos = campos_do_form($m, $novo);
$leitura = !pode_editar() || $travado;
$acao = $novo ? url("m/$slug/novo") : url("m/$slug/" . $linha['id']);
$listasAnimais = [];
foreach ($campos as $c) if ($c['tipo'] === 'animal') $listasAnimais[!empty($c['sexo']) ? 'lista-animais-' . $c['sexo'] : 'lista-animais'] = $c['sexo'] ?? null;
?>
<div class="cabecalho">
  <h1><?= e($titulo) ?></h1>
  <div class="acoes">
    <?php if (!$novo && !empty($m['ficha'])): ?><a class="botao botao-leve" href="<?= e(url('animal/' . $linha['id'])) ?>">Ver ficha</a><?php endif; ?>
    <a class="botao botao-leve" href="<?= e($voltar ?: url("m/$slug")) ?>">Voltar</a>
  </div>
</div>

<?php if ($travado): ?><div class="aviso aviso-info"><?= e($travado) ?></div><?php endif; ?>
<?php if (!empty($erros['_geral'])): ?><div class="aviso aviso-erro" role="alert"><?= e($erros['_geral']) ?></div><?php endif; ?>
<?php if (count(array_diff_key($erros, ['_geral' => 1])) > 0): ?><div class="aviso aviso-erro" role="alert">Confira os campos marcados abaixo.</div><?php endif; ?>

<form method="post" action="<?= e($acao) ?>" class="form cartao">
  <?= csrf_campo() ?>
  <?php if ($voltar): ?><input type="hidden" name="voltar" value="<?= e($voltar) ?>"><?php endif; ?>
  <fieldset class="grade"<?= $leitura ? ' disabled' : '' ?>>
    <legend class="so-leitor"><?= e($titulo) ?></legend>
    <?php foreach ($campos as $nome => $c): ?>
      <?= campo_modulo($nome, $c, $valores[$nome] ?? '', $erros[$nome] ?? null, $listas) ?>
    <?php endforeach; ?>
  </fieldset>
  <?php foreach ($listasAnimais as $id => $sexo) echo lista_brincos($id, $sexo); ?>
  <?php if (!$leitura): ?>
    <div class="acoes-form">
      <button class="botao botao-grande" type="submit">Salvar</button>
      <?php if ($novo): ?><button class="botao botao-leve botao-grande" type="submit" name="e_outro" value="1">Salvar e lançar outro</button><?php endif; ?>
    </div>
  <?php endif; ?>
</form>

<?php if (!$novo && pode_editar() && !$travado): ?>
  <form method="post" action="<?= e(url("m/$slug/" . $linha['id'] . '/excluir')) ?>" class="zona-perigo" data-confirmar="Excluir este registro? Não dá para desfazer.">
    <?= csrf_campo() ?>
    <button class="botao botao-perigo" type="submit">Excluir <?= e($m['singular']) ?></button>
    <?php if ($slug === 'animais'): ?><p class="ajuda">Excluir apaga também pesagens, vacinas e inseminações deste animal. Se ele foi vendido ou morreu, prefira mudar a situação.</p><?php endif; ?>
  </form>
<?php endif; ?>
