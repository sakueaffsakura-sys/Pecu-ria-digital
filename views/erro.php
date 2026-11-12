<section class="cartao cartao-estreito">
  <h1><?= e($titulo) ?></h1>
  <p><?= e($mensagem) ?></p>
  <p><a class="botao" href="<?= e(url(usuario() ? 'painel' : '')) ?>">Voltar ao início</a></p>
</section>
