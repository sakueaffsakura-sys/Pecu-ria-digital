<main class="entrar">
  <section class="entrar-marca">
    <div class="entrar-logo">
      <img src="/assets/img/marca-branca.webp" alt="" width="44" height="44">
      <span>Pecuária Digital</span>
    </div>
    <div class="entrar-frase">
      <h1>Sua fazenda, anotada.</h1>
      <p>Rebanho, vacinas, reprodução, leite e dinheiro num lugar só. No computador e no celular, com aplicativo que funciona sem internet.</p>
    </div>
  </section>

  <section class="entrar-form">
    <div class="entrar-caixa">
      <?php if ($motivo): ?>
        <div class="faixa faixa-<?= e($motivo[0]) ?>" role="status"><?= e($motivo[1]) ?></div>
      <?php endif; ?>

      <?php if ($etapa === 'codigo'): ?>
        <form method="post" action="<?= e(url('', ['redirect' => $destino])) ?>" novalidate>
          <?= csrf_campo() ?><input type="hidden" name="acao" value="codigo">
          <h2 class="titulo-pagina">Código de verificação</h2>
          <p class="descricao">Abra o aplicativo autenticador no celular e digite o código de 6 números.</p>
          <div class="campo">
            <label for="codigo">Código</label>
            <input id="codigo" name="codigo" inputmode="numeric" autocomplete="one-time-code" autofocus maxlength="7" class="campo-codigo"<?= $erro ? ' aria-invalid="true" aria-describedby="erro-login"' : '' ?>>
          </div>
          <?php if ($erro): ?><p id="erro-login" role="alert" class="erro-forte"><?= e($erro) ?></p><?php endif; ?>
          <button class="botao botao-primario botao-largo" type="submit">Confirmar e entrar</button>
        </form>
        <form method="post" action="<?= e(url('')) ?>"><?= csrf_campo() ?><input type="hidden" name="acao" value="outra_conta">
          <button class="botao botao-fantasma botao-largo" type="submit">Entrar com outra conta</button></form>
      <?php else: ?>
        <nav class="abas abas-largas" aria-label="Entrar ou criar conta">
          <a href="<?= e(url('', ['redirect' => $destino !== '/app/inicio' ? $destino : null])) ?>"<?= $aba === 'entrar' ? ' aria-current="page"' : '' ?>>Entrar</a>
          <a href="<?= e(url('', ['aba' => 'criar', 'redirect' => $destino !== '/app/inicio' ? $destino : null])) ?>"<?= $aba === 'criar' ? ' aria-current="page"' : '' ?>>Criar conta</a>
        </nav>
        <h2 class="titulo-pagina entrar-titulo"><?= $aba === 'entrar' ? 'Entre na sua conta' : 'Crie sua conta grátis' ?></h2>
        <p class="descricao"><?= $aba === 'entrar' ? 'Use o e-mail e a senha do cadastro.' : 'Leva um minuto. Depois é só cadastrar a fazenda.' ?></p>
        <form method="post" action="<?= e(url('', ['redirect' => $destino])) ?>" class="pilha" novalidate>
          <?= csrf_campo() ?>
          <input type="hidden" name="acao" value="<?= $aba === 'criar' ? 'criar' : 'entrar' ?>">
          <input type="hidden" name="aba" value="<?= e($aba) ?>">
          <?php if ($aba === 'criar'): ?>
            <input type="hidden" name="inicio" value="<?= time() ?>">
            <div class="campo"><label for="nome">Seu nome</label><input id="nome" name="nome" autocomplete="name" required maxlength="120" value="<?= e($v['nome']) ?>"></div>
          <?php endif; ?>
          <div class="campo"><label for="email">E-mail</label><input id="email" name="email" type="email" inputmode="email" autocomplete="email" required maxlength="254" placeholder="voce@exemplo.com" value="<?= e($v['email']) ?>"></div>
          <div class="campo">
            <label for="senha">Senha</label>
            <div class="campo-senha">
              <input id="senha" name="senha" type="password" required autocomplete="<?= $aba === 'entrar' ? 'current-password' : 'new-password' ?>" maxlength="128">
              <button type="button" class="mostrar-senha" data-mostrar-senha="senha" aria-label="Mostrar a senha" aria-pressed="false"><?= icone('eye') ?></button>
            </div>
            <?php if ($aba === 'criar'): ?>
              <ul class="regras-senha" data-regras-senha="senha">
                <li data-regra="tamanho">Pelo menos 8 caracteres</li>
                <li data-regra="letra-numero">Letras e números</li>
              </ul>
            <?php endif; ?>
          </div>
          <div class="armadilha" aria-hidden="true"><label for="site">Não preencha</label><input id="site" name="site" tabindex="-1" autocomplete="off"></div>
          <?php if ($erro): ?><p role="alert" class="erro-forte"><?= e($erro) ?></p><?php endif; ?>
          <button class="botao botao-primario botao-largo" type="submit"><?= $aba === 'entrar' ? 'Entrar' : 'Criar conta' ?></button>
        </form>
      <?php endif; ?>
      <p class="entrar-links"><a href="/privacidade">Privacidade</a><a href="/termos">Termos de uso</a></p>
    </div>
  </section>
</main>
