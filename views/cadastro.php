<section class="cartao cartao-estreito">
  <h1>Criar conta</h1>
  <?php if (!empty($erros['_geral'])): ?><div class="aviso aviso-erro" role="alert"><?= e($erros['_geral']) ?></div><?php endif; ?>
  <form method="post" action="<?= e(url('cadastro')) ?>" class="form">
    <?= csrf_campo() ?>
    <?= entrada('nome', 'Seu nome', $v['nome'], $erros['nome'] ?? null, ['required' => true, 'autocomplete' => 'name', 'maxlength' => 120]) ?>
    <?= entrada('email', 'E-mail', $v['email'], $erros['email'] ?? null, ['type' => 'email', 'required' => true, 'autocomplete' => 'email', 'maxlength' => 190]) ?>
    <?= entrada('senha', 'Senha (mínimo 8 caracteres)', '', $erros['senha'] ?? null, ['type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'minlength' => 8]) ?>
    <?= entrada('senha2', 'Repita a senha', '', $erros['senha2'] ?? null, ['type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'minlength' => 8]) ?>
    <div class="armadilha" aria-hidden="true"><label for="c-site">Não preencha</label><input id="c-site" name="site" tabindex="-1" autocomplete="off"></div>
    <button class="botao botao-grande" type="submit">Criar conta</button>
  </form>
  <p>Já tem conta? <a href="<?= e(url('entrar')) ?>">Entrar</a></p>
</section>
