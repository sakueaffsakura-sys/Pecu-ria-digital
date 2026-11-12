<div class="cabecalho"><h1>Propriedades</h1></div>
<?php if ($lista): ?>
<section class="cartao">
  <h2>Suas propriedades</h2>
  <div class="tabela-rolagem"><table class="tabela"><thead><tr><th>Nome</th><th>Cidade</th><th>Seu acesso</th><th></th></tr></thead><tbody>
    <?php foreach ($lista as $p): ?>
      <tr><td data-rotulo="Nome"><strong><?= e($p['nome']) ?></strong><?= (int) $p['id'] === (int) (propriedade()['id'] ?? 0) ? ' ' . selo('em uso', 'ativo') : '' ?></td>
        <td data-rotulo="Cidade"><?= e(trim(($p['cidade'] ?? '') . ($p['estado'] ? ' - ' . $p['estado'] : ''), ' -') ?: '—') ?></td>
        <td data-rotulo="Acesso"><?= e(PAPEIS[$p['papel']] ?? $p['papel']) ?></td>
        <td class="acoes-linha">
          <form method="post" action="<?= e(url('propriedades/ativar')) ?>" class="em-linha"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button class="link" type="submit">Usar</button></form>
          · <a href="<?= e(url('propriedades/' . $p['id'])) ?>"><?= $p['papel'] === 'dono' ? 'Configurar' : 'Detalhes' ?></a>
        </td></tr>
    <?php endforeach; ?>
  </tbody></table></div>
</section>
<?php endif; ?>

<section class="cartao">
  <h2><?= $lista ? 'Cadastrar outra propriedade' : 'Cadastre a sua propriedade' ?></h2>
  <form method="post" action="<?= e(url('propriedades')) ?>" class="form">
    <?= csrf_campo() ?>
    <?php require RAIZ . '/views/_campos_propriedade.php'; ?>
    <button class="botao botao-grande" type="submit">Cadastrar propriedade</button>
  </form>
</section>
