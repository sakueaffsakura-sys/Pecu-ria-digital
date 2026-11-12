<main class="centro-vazio tela-cheia">
  <div class="largura-texto">
    <img src="/assets/img/marca-escura.webp" alt="" width="56" height="56" class="marca-erro">
    <h1 class="titulo-pagina"><?= e($titulo) ?></h1>
    <p class="descricao"><?= e($mensagem) ?></p>
    <a class="botao botao-primario mt-5" href="<?= usuario() ? '/app/inicio' : '/' ?>"><?= usuario() ? 'Voltar ao início' : 'Ir para a entrada' ?></a>
  </div>
</main>
