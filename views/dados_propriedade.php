<?php $pode = pode_administrar(); ?>
<?= titulo_pagina('Dados da propriedade', 'Nome, cidade e período de gestação da fazenda.') ?>
<section class="painel p-painel largura-form" aria-labelledby="dados-propriedade">
  <h2 id="dados-propriedade" class="titulo-secao">Dados da propriedade</h2>
  <p class="descricao">O nome aparece no topo e nos relatórios; a cidade é usada na previsão do tempo.</p>
  <form method="post" action="/app/perfil-propriedade" class="grade-propriedade mt-5">
    <?= csrf_campo() ?>
    <fieldset class="contents"<?= $pode ? '' : ' disabled' ?>>
      <div class="campo col-3"><label for="cfg-nome">Nome</label><input id="cfg-nome" name="nome" required maxlength="120" value="<?= e($prop['nome']) ?>"<?= isset($erros['nome']) ? ' aria-invalid="true"' : '' ?>><?php if (isset($erros['nome'])): ?><p class="erro-campo"><?= e($erros['nome']) ?></p><?php endif; ?></div>
      <div class="campo col-2"><label for="cfg-cidade">Cidade</label><input id="cfg-cidade" name="cidade" maxlength="80" value="<?= e($prop['cidade'] ?? '') ?>"><?php if (isset($erros['cidade'])): ?><p class="erro-campo"><?= e($erros['cidade']) ?></p><?php endif; ?></div>
      <div class="campo"><label for="cfg-uf">Estado</label><input id="cfg-uf" name="estado" maxlength="2" value="<?= e($prop['estado'] ?? '') ?>"><?php if (isset($erros['estado'])): ?><p class="erro-campo"><?= e($erros['estado']) ?></p><?php endif; ?></div>
      <div class="campo col-3"><label for="cfg-gest">Período de gestação (dias)</label><input id="cfg-gest" name="periodo_gestacao_dias" type="number" min="260" max="300" required value="<?= e((string) $prop['periodo_gestacao_dias']) ?>">
        <p class="ajuda">Usado para calcular a previsão de parto. Vacas: cerca de 283 dias.</p><?php if (isset($erros['periodo_gestacao_dias'])): ?><p class="erro-campo"><?= e($erros['periodo_gestacao_dias']) ?></p><?php endif; ?></div>
      <?php if ($pode): ?><div class="col-3"><button type="submit" class="botao botao-primario">Salvar dados</button></div><?php endif; ?>
    </fieldset>
  </form>
</section>
