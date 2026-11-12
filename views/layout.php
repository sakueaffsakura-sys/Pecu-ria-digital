<?php
/** @var string $conteudo @var string $titulo */
$u = usuario();
$prop = $u ? propriedade() : null;
$props = $u ? propriedades_do_usuario() : [];
$atual = '/' . trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
$nav = function () use ($u, $atual): string {
    $h = '';
    foreach (menu() as $grupo => $itens) {
        $h .= '<div class="menu-grupo">' . ($grupo ? '<p class="menu-titulo">' . e($grupo) . '</p>' : '') . '<ul>';
        foreach ($itens as [$cam, $rot]) {
            $ativo = $atual === '/' . $cam || str_starts_with($atual, '/' . $cam . '/');
            $h .= '<li><a href="' . e(url($cam)) . '"' . ($ativo ? ' aria-current="page"' : '') . '>' . e($rot) . '</a></li>';
        }
        $h .= '</ul></div>';
    }
    if ((int) ($u['admin'] ?? 0) === 1) {
        $h .= '<div class="menu-grupo"><ul><li><a href="' . e(url('admin')) . '"' . ($atual === '/admin' ? ' aria-current="page"' : '') . '>Administração</a></li></ul></div>';
    }
    return $h;
};
?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#2f5d3a">
<title><?= e($titulo ?? '') ?> · <?= e(config('nome_site')) ?></title>
<link rel="icon" href="/assets/icone.svg" type="image/svg+xml">
<link rel="stylesheet" href="/assets/app.css?v=1">
<script src="/assets/app.js?v=1" defer></script>
</head>
<body>
<a class="pular" href="#conteudo">Pular para o conteúdo</a>
<header class="topo">
  <a class="marca" href="<?= e(url('painel')) ?>"><img src="/assets/icone.svg" alt="" width="32" height="32"> <span><?= e(config('nome_site')) ?></span></a>
  <?php if ($props): ?>
  <form class="troca-prop" method="post" action="<?= e(url('propriedades/ativar')) ?>">
    <?= csrf_campo() ?>
    <label class="so-leitor" for="troca-prop">Propriedade em uso</label>
    <select id="troca-prop" name="id" data-enviar-ao-mudar>
      <?php foreach ($props as $p): ?>
        <option value="<?= (int) $p['id'] ?>"<?= (int) $p['id'] === (int) ($prop['id'] ?? 0) ? ' selected' : '' ?>><?= e($p['nome']) ?></option>
      <?php endforeach; ?>
    </select>
    <noscript><button class="botao botao-leve" type="submit">Trocar</button></noscript>
  </form>
  <?php endif; ?>
  <div class="conta">
    <a href="<?= e(url('perfil')) ?>" title="Minha conta"><?= e(strtok((string) $u['nome'], ' ')) ?></a>
    <form method="post" action="<?= e(url('sair')) ?>"><?= csrf_campo() ?><button class="link" type="submit">Sair</button></form>
  </div>
</header>
<details class="menu-movel">
  <summary>Menu</summary>
  <nav aria-label="Menu principal"><?= $nav() ?></nav>
</details>
<div class="casca">
  <nav class="lateral" aria-label="Menu principal"><?= $nav() ?></nav>
  <main id="conteudo">
    <?php if ($prop && ($prop['papel'] ?? '') === 'leitor'): ?>
      <p class="faixa">Você está em modo de consulta nesta propriedade.</p>
    <?php endif; ?>
    <?php foreach (pegar_avisos() as $a): ?>
      <div class="aviso aviso-<?= e($a['tipo']) ?>" role="status"><?= e($a['msg']) ?></div>
    <?php endforeach; ?>
    <?= $conteudo ?>
  </main>
</div>
</body>
</html>
