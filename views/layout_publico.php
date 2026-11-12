<?php /** @var string $conteudo @var string $titulo */ ?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#2f5d3a">
<meta name="description" content="Pecuária Digital: controle do rebanho, vacinas, reprodução, leite e financeiro da sua fazenda, no celular ou no computador.">
<title><?= e($titulo ?? '') ?> · <?= e(config('nome_site')) ?></title>
<link rel="icon" href="/assets/icone.svg" type="image/svg+xml">
<link rel="stylesheet" href="/assets/app.css?v=1">
</head>
<body class="publico">
<header class="topo">
  <a class="marca" href="<?= e(url('')) ?>"><img src="/assets/icone.svg" alt="" width="32" height="32"> <span><?= e(config('nome_site')) ?></span></a>
  <nav class="conta">
    <?php if (usuario()): ?>
      <a href="<?= e(url('painel')) ?>">Abrir o sistema</a>
    <?php else: ?>
      <a href="<?= e(url('entrar')) ?>">Entrar</a>
      <?php if (config('cadastro_aberto')): ?><a class="botao" href="<?= e(url('cadastro')) ?>">Criar conta</a><?php endif; ?>
    <?php endif; ?>
  </nav>
</header>
<main id="conteudo" class="publico-conteudo">
  <?php foreach (pegar_avisos() as $a): ?>
    <div class="aviso aviso-<?= e($a['tipo']) ?>" role="status"><?= e($a['msg']) ?></div>
  <?php endforeach; ?>
  <?= $conteudo ?>
</main>
<footer class="rodape">© <?= date('Y') ?> <?= e(config('nome_site')) ?></footer>
</body>
</html>
