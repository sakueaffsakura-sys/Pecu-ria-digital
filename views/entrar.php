<section class="cartao cartao-estreito">
  <h1>Entrar</h1>
  <?php if ($erro): ?><div class="aviso aviso-erro" role="alert"><?= e($erro) ?></div><?php endif; ?>
  <form method="post" action="<?= e(url('entrar')) ?>" class="form">
    <?= csrf_campo() ?>
    <?= entrada('email', 'E-mail', $email, null, ['type' => 'email', 'required' => true, 'autocomplete' => 'email', 'autofocus' => true]) ?>
    <?= entrada('senha', 'Senha', '', null, ['type' => 'password', 'required' => true, 'autocomplete' => 'current-password']) ?>
    <button class="botao botao-grande" type="submit">Entrar</button>
  </form>
  <?php if (config('cadastro_aberto')): ?><p>Ainda não tem conta? <a href="<?= e(url('cadastro')) ?>">Criar conta</a></p><?php endif; ?>
  <p class="ajuda">Esqueceu a senha? Peça ao administrador do site uma senha provisória.</p>
</section>
