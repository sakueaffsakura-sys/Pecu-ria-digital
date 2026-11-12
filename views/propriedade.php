<div class="cabecalho">
  <h1><?= e($prop['nome']) ?></h1>
  <div class="acoes"><a class="botao botao-leve" href="<?= e(url('propriedades')) ?>">Todas as propriedades</a></div>
</div>

<section class="cartao">
  <h2>Dados da propriedade</h2>
  <?php if ($dono): ?>
    <form method="post" action="<?= e(url('propriedades/' . $prop['id'])) ?>" class="form">
      <?= csrf_campo() ?><input type="hidden" name="acao" value="dados">
      <?php require RAIZ . '/views/_campos_propriedade.php'; ?>
      <button class="botao" type="submit">Salvar</button>
    </form>
  <?php else: ?>
    <dl class="dados">
      <div><dt>Cidade</dt><dd><?= e($prop['cidade'] ?: '—') ?> <?= e($prop['estado'] ?? '') ?></dd></div>
      <div><dt>Área</dt><dd><?= $prop['area_ha'] ? num_br($prop['area_ha'], 2) . ' ha' : '—' ?></dd></div>
      <div><dt>Gestação</dt><dd><?= (int) $prop['periodo_gestacao_dias'] ?> dias</dd></div>
      <div><dt>Seu acesso</dt><dd><?= e(PAPEIS[$prop['papel']] ?? '') ?></dd></div>
    </dl>
  <?php endif; ?>
</section>

<section class="cartao">
  <h2>Quem tem acesso</h2>
  <div class="tabela-rolagem"><table class="tabela"><thead><tr><th>Nome</th><th>E-mail</th><th>Acesso</th><?php if ($dono): ?><th></th><?php endif; ?></tr></thead><tbody>
    <?php foreach ($membros as $mb): ?>
      <tr><td data-rotulo="Nome"><?= e($mb['nome']) ?></td><td data-rotulo="E-mail"><?= e($mb['email']) ?></td>
        <td data-rotulo="Acesso">
          <?php if ($dono && $mb['papel'] !== 'dono'): ?>
            <form method="post" action="<?= e(url('propriedades/' . $prop['id'])) ?>" class="em-linha"><?= csrf_campo() ?>
              <input type="hidden" name="acao" value="mudar_papel"><input type="hidden" name="usuario_id" value="<?= (int) $mb['id'] ?>">
              <label class="so-leitor" for="papel-<?= (int) $mb['id'] ?>">Acesso de <?= e($mb['nome']) ?></label>
              <select id="papel-<?= (int) $mb['id'] ?>" name="papel" data-enviar-ao-mudar>
                <option value="editor"<?= $mb['papel'] === 'editor' ? ' selected' : '' ?>><?= e(PAPEIS['editor']) ?></option>
                <option value="leitor"<?= $mb['papel'] === 'leitor' ? ' selected' : '' ?>><?= e(PAPEIS['leitor']) ?></option>
              </select><noscript><button class="link">Salvar</button></noscript>
            </form>
          <?php else: ?><?= e(PAPEIS[$mb['papel']] ?? $mb['papel']) ?><?php endif; ?>
        </td>
        <?php if ($dono): ?><td class="acoes-linha"><?php if ($mb['papel'] !== 'dono'): ?>
          <form method="post" action="<?= e(url('propriedades/' . $prop['id'])) ?>" class="em-linha" data-confirmar="Tirar o acesso de <?= e($mb['nome']) ?>?"><?= csrf_campo() ?>
            <input type="hidden" name="acao" value="remover_membro"><input type="hidden" name="usuario_id" value="<?= (int) $mb['id'] ?>">
            <button class="link link-perigo" type="submit">Remover</button></form>
        <?php endif; ?></td><?php endif; ?></tr>
    <?php endforeach; ?>
  </tbody></table></div>

  <?php if ($dono): ?>
    <h3>Dar acesso a outra pessoa</h3>
    <p class="ajuda">A pessoa precisa ter criado uma conta no site antes.</p>
    <form method="post" action="<?= e(url('propriedades/' . $prop['id'])) ?>" class="form grade">
      <?= csrf_campo() ?><input type="hidden" name="acao" value="adicionar_membro">
      <?= entrada('email', 'E-mail da pessoa', $_POST['email'] ?? '', $erros['email'] ?? null, ['type' => 'email', 'required' => true]) ?>
      <div class="campo"><label for="c-papel">Acesso</label><select id="c-papel" name="papel">
        <option value="editor"><?= e(PAPEIS['editor']) ?></option><option value="leitor"><?= e(PAPEIS['leitor']) ?></option></select></div>
      <div class="campo campo-botoes"><button class="botao" type="submit">Dar acesso</button></div>
    </form>
  <?php else: ?>
    <form method="post" action="<?= e(url('propriedades/' . $prop['id'])) ?>" data-confirmar="Sair desta propriedade? Você perde o acesso aos dados dela."><?= csrf_campo() ?>
      <input type="hidden" name="acao" value="sair_da_propriedade"><button class="botao botao-leve" type="submit">Sair desta propriedade</button></form>
  <?php endif; ?>
</section>

<?php if ($dono): ?>
<section class="cartao zona-perigo">
  <h2>Excluir propriedade</h2>
  <p>Apaga a propriedade e <strong>todos</strong> os animais, lançamentos e anotações dela. Não dá para desfazer. Baixe as planilhas antes.</p>
  <form method="post" action="<?= e(url('propriedades/' . $prop['id'])) ?>" class="form">
    <?= csrf_campo() ?><input type="hidden" name="acao" value="excluir">
    <?= entrada('confirmar', 'Para confirmar, digite o nome: ' . $prop['nome'], '', $erros['confirmar'] ?? null, ['autocomplete' => 'off']) ?>
    <button class="botao botao-perigo" type="submit">Excluir para sempre</button>
  </form>
</section>
<?php endif; ?>
