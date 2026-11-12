<div class="cabecalho"><h1>Minha conta</h1></div>
<div class="duas-colunas">
  <section class="cartao">
    <h2>Seus dados</h2>
    <form method="post" action="<?= e(url('perfil')) ?>" class="form">
      <?= csrf_campo() ?><input type="hidden" name="acao" value="nome">
      <?= entrada('nome', 'Nome', $_POST['nome'] ?? $u['nome'], $erros['nome'] ?? null, ['required' => true, 'maxlength' => 120, 'autocomplete' => 'name']) ?>
      <div class="campo"><label for="c-email">E-mail</label><input id="c-email" value="<?= e($u['email']) ?>" disabled></div>
      <button class="botao" type="submit">Salvar</button>
    </form>
  </section>
  <section class="cartao">
    <h2>Trocar senha</h2>
    <form method="post" action="<?= e(url('perfil')) ?>" class="form">
      <?= csrf_campo() ?><input type="hidden" name="acao" value="senha">
      <?= entrada('atual', 'Senha atual', '', $erros['atual'] ?? null, ['type' => 'password', 'required' => true, 'autocomplete' => 'current-password']) ?>
      <?= entrada('nova', 'Nova senha (mínimo 8)', '', $erros['nova'] ?? null, ['type' => 'password', 'required' => true, 'minlength' => 8, 'autocomplete' => 'new-password']) ?>
      <?= entrada('nova2', 'Repita a nova senha', '', $erros['nova2'] ?? null, ['type' => 'password', 'required' => true, 'minlength' => 8, 'autocomplete' => 'new-password']) ?>
      <button class="botao" type="submit">Trocar senha</button>
    </form>
  </section>
</div>
