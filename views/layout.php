<?php
/** @var string $conteudo */
$u = usuario();
$props = propriedades_do_usuario();
$prop = propriedade();
$secao = $secao ?? '';
$prontas = secoes_prontas();
$avisosTopo = $prop ? central_avisos($prop['id']) : [];
$notas = notificacoes_abertas();
$totalAvisos = count($avisosTopo) + count($notas);
$aqui = (string) ($_SERVER['REQUEST_URI'] ?? '/app/inicio');
$nome = nome_do_usuario($u);
$inicial = mb_strtoupper(mb_substr($nome, 0, 1));
?><!doctype html>
<html lang="pt-BR">
<head>
<?php require RAIZ . '/views/_cabeca.php'; ?>
</head>
<body class="casca">
<a href="#conteudo" class="pular">Pular para o conteúdo</a>

<aside id="menu-principal" class="menu-curral" aria-label="Menu principal" data-menu>
  <div class="menu-marca">
    <img src="/assets/img/marca-branca.webp" alt="" width="40" height="40">
    <span>Pecuária Digital</span>
    <button type="button" class="menu-fechar" data-fechar-menu aria-label="Fechar menu"><?= icone('x') ?></button>
  </div>
  <div class="menu-busca-movel">
    <button type="button" data-abrir-busca><?= icone('search') ?>Buscar animal ou página</button>
  </div>
  <nav aria-label="Áreas da fazenda" class="menu-nav">
    <?php foreach (grupos_menu() as $i => $g): if (!empty($g['oculto'])) continue;
      $itens = array_filter($g['itens'], fn($it) => in_array($it[0], $prontas, true)); if (!$itens) continue; ?>
      <div class="menu-grupo">
        <?php if ($g['rotulo']): ?><p class="menu-grupo-titulo"><?= e($g['rotulo']) ?></p><?php endif; ?>
        <ul>
          <?php foreach ($itens as [$chave, $rotulo, $ic]): ?>
            <li><a href="<?= e(url("app/$chave")) ?>"<?= $chave === $secao ? ' aria-current="page"' : '' ?>><?= icone($ic) ?><span><?= e($rotulo) ?></span></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endforeach; ?>
  </nav>
</aside>
<div class="menu-sombra" data-fechar-menu hidden></div>

<div class="area">
  <header class="barra-topo">
    <details class="suspenso suspenso-prop">
      <summary aria-label="<?= $prop ? 'Propriedade: ' . e($prop['nome']) . '. Trocar de propriedade' : 'Escolher propriedade' ?>">
        <span class="prop-texto"><span class="prop-rotulo">Propriedade</span><span class="prop-nome"><?= e($prop['nome'] ?? 'Escolher') ?></span></span>
        <?= icone('chevron-down', 'ic ic-suave') ?>
      </summary>
      <div class="suspenso-caixa">
        <p class="suspenso-titulo">Suas propriedades</p>
        <?php foreach ($props as $p): ?>
          <form method="post" action="/propriedade/trocar"><?= csrf_campo() ?>
            <input type="hidden" name="id" value="<?= e($p['id']) ?>"><input type="hidden" name="volta" value="<?= e($aqui) ?>">
            <button type="submit" class="suspenso-item"><?= icone('check', 'ic ' . ($p['id'] === ($prop['id'] ?? '') ? '' : 'invisivel')) ?><span class="truncar"><?= e($p['nome']) ?></span></button>
          </form>
        <?php endforeach; ?>
        <?php if ($props): ?><hr><?php endif; ?>
        <?php if ($prop): ?><a class="suspenso-item" href="/app/perfil-propriedade"><?= icone('pencil') ?>Editar dados da propriedade</a><?php endif; ?>
        <button type="button" class="suspenso-item" data-abrir="#dialogo-propriedade"><?= icone('plus') ?>Cadastrar outra propriedade</button>
      </div>
    </details>

    <button type="button" class="botao-busca" data-abrir-busca aria-label="Buscar animal, lote ou página (Ctrl+K)">
      <?= icone('search') ?><span class="busca-texto">Buscar animal, lote ou página</span><kbd>Ctrl K</kbd>
    </button>

    <div class="topo-acoes">
      <details class="suspenso suspenso-avisos">
        <summary class="botao-icone-grande" aria-label="<?= $totalAvisos ? 'Avisos: ' . $totalAvisos . ' para ver' : 'Avisos: nada pendente' ?>">
          <?= icone('bell', 'ic ic-6') ?><?php if ($totalAvisos): ?><span class="contador"><?= $totalAvisos > 9 ? '9+' : $totalAvisos ?></span><?php endif; ?>
        </summary>
        <div class="suspenso-caixa caixa-avisos">
          <div class="avisos-cabeca"><h2 class="titulo-secao">Avisos</h2><p>O que precisa de atenção na propriedade.</p></div>
          <div class="avisos-lista">
            <?php if (!$totalAvisos): ?>
              <p class="avisos-vazio">Nada pendente. Quando uma vacina atrasar ou um parto se aproximar, o aviso aparece aqui.</p>
            <?php endif; ?>
            <?php foreach ($avisosTopo as $a): ?>
              <div class="aviso-barra cor-<?= e($a['nivel']) ?>">
                <?= icone($a['nivel'] === 'alerta' ? 'triangle-exclamation-point' : ($a['nivel'] === 'aviso' ? 'calendar-clock' : 'info'), 'ic ic-aviso') ?>
                <div>
                  <p class="aviso-titulo"><?= e($a['titulo']) ?></p>
                  <p class="aviso-texto"><?= e($a['texto']) ?></p>
                  <div class="aviso-botoes">
                    <a class="botao botao-primario botao-p" href="<?= e(url('app/' . $a['secao'])) ?>">Abrir</a>
                    <form method="post" action="/avisos/visto"><?= csrf_campo() ?><input type="hidden" name="chave" value="<?= e($a['chave']) ?>"><input type="hidden" name="volta" value="<?= e($aqui) ?>">
                      <button class="botao botao-contorno botao-p" type="submit">Marcar como visto</button></form>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
            <?php foreach ($notas as $n): ?>
              <div class="aviso-barra cor-neutro">
                <div>
                  <p class="aviso-titulo"><?= e($n['titulo']) ?></p>
                  <p class="aviso-texto"><?= e($n['mensagem']) ?></p>
                  <form method="post" action="/avisos/visto"><?= csrf_campo() ?><input type="hidden" name="notificacao" value="<?= e($n['id']) ?>"><input type="hidden" name="volta" value="<?= e($aqui) ?>">
                    <button class="botao botao-contorno botao-p" type="submit">Marcar como lida</button></form>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </details>

      <details class="suspenso suspenso-conta">
        <summary class="botao-icone-grande" aria-label="Minha conta"><span class="avatar"><?= e($inicial) ?></span></summary>
        <div class="suspenso-caixa">
          <p class="suspenso-titulo conta-nome"><strong class="truncar"><?= e($nome) ?></strong><span class="truncar"><?= e($u['email']) ?></span></p>
          <hr>
          <?php foreach ([['perfil', 'Meu perfil', 'user-round'], ['seguranca', 'Senha e segurança', 'key-round'], ['assinatura', 'Assinatura', 'crown'], ['diagnostico', 'Diagnóstico dos dados', 'gauge']] as [$c, $r, $ic]):
            if (!in_array($c, $prontas, true)) continue; ?>
            <a class="suspenso-item" href="<?= e(url("app/$c")) ?>"><?= icone($ic) ?><?= e($r) ?></a>
          <?php endforeach; ?>
          <?php if ($prop): ?><a class="suspenso-item" href="/app/perfil-propriedade"><?= icone('warehouse') ?>Dados da propriedade</a><?php endif; ?>
          <button type="button" class="suspenso-item" data-abrir="#dialogo-sobre"><?= icone('info') ?>Sobre o sistema</button>
          <hr>
          <p class="suspenso-titulo">Aparência</p>
          <?php foreach ([['claro', 'Claro', 'sun'], ['escuro', 'Escuro', 'moon'], ['automatico', 'Igual ao aparelho', 'monitor']] as [$t, $r, $ic]): ?>
            <button type="button" class="suspenso-item" data-tema="<?= $t ?>" aria-pressed="false"><?= icone($ic) ?><?= e($r) ?><?= icone('check', 'ic marca-tema') ?></button>
          <?php endforeach; ?>
          <?php if (eh_admin()): ?><hr><a class="suspenso-item" href="/admin"><?= icone('shield-check') ?>Área do administrador</a><?php endif; ?>
          <hr>
          <form method="post" action="/sair"><?= csrf_campo() ?><button type="submit" class="suspenso-item"><?= icone('log-out') ?>Sair</button></form>
        </div>
      </details>
    </div>
  </header>

  <main id="conteudo" tabindex="-1">
    <?php if ($prop && $prop['papel'] === 'visualizador'): ?><p class="faixa faixa-info">Você tem acesso só de consulta nesta propriedade.</p><?php endif; ?>
    <?= $conteudo ?>
  </main>
</div>

<nav aria-label="Atalhos" class="barra-inferior">
  <a href="/app/inicio"<?= $secao === 'inicio' ? ' aria-current="page"' : '' ?>><?= icone('layout-dashboard', 'ic ic-6') ?><span>Início</span></a>
  <a href="/app/rebanho"<?= $secao === 'rebanho' ? ' aria-current="page"' : '' ?>><?= icone('rebanho', 'ic ic-6') ?><span>Rebanho</span></a>
  <button type="button" class="lancar" data-abrir="#dialogo-lancar" aria-haspopup="dialog"><span class="lancar-bola"><?= icone('plus', 'ic ic-7') ?></span>Lançar</button>
  <a href="/app/agenda"<?= $secao === 'agenda' ? ' aria-current="page"' : '' ?>><?= icone('calendar-days', 'ic ic-6') ?><span>Agenda</span></a>
  <button type="button" data-abrir-menu aria-controls="menu-principal"><?= icone('menu', 'ic ic-6') ?><span>Mais</span></button>
</nav>

<dialog id="dialogo-lancar" class="dialogo folha-baixo" aria-labelledby="lancar-titulo">
  <div class="dialogo-cabeca"><h2 id="lancar-titulo" class="titulo-secao">O que você quer lançar?</h2><p class="descricao">Abre o formulário certo, já na página dele.</p>
    <button type="button" class="dialogo-x" data-fechar aria-label="Fechar"><?= icone('x') ?></button></div>
  <ul class="grade-lancar">
    <?php foreach (lancamentos() as [$r, $f, $s, $ic]):
      $href = $s === 'anotacoes' ? '/app/producao?aba=anotacoes&novo=1' : "/app/$s?novo=1"; ?>
      <li><a href="<?= e($href) ?>"><?= icone($ic, 'ic ic-6 ic-verde') ?><span><strong><?= e($r) ?></strong><span><?= e($f) ?></span></span></a></li>
    <?php endforeach; ?>
  </ul>
</dialog>

<dialog id="dialogo-busca" class="dialogo dialogo-busca" aria-label="Buscar">
  <form class="busca-campo" role="search" data-busca-form>
    <?= icone('search') ?>
    <input type="search" id="busca-q" placeholder="Brinco, nome do animal, lote ou página" autocomplete="off" aria-label="Buscar" data-busca>
    <kbd>Esc</kbd>
  </form>
  <div class="busca-resultados" data-busca-resultados aria-live="polite"></div>
</dialog>

<dialog id="dialogo-propriedade" class="dialogo" aria-labelledby="prop-titulo">
  <div class="dialogo-cabeca"><h2 id="prop-titulo" class="titulo-secao">Cadastre a propriedade</h2><p class="descricao">Os animais, o leite e o dinheiro ficam guardados dentro dela.</p>
    <button type="button" class="dialogo-x" data-fechar aria-label="Fechar"><?= icone('x') ?></button></div>
  <form method="post" action="/propriedade/nova" class="pilha"><?= csrf_campo() ?>
    <div class="campo"><label for="prop-nome">Nome da propriedade</label><input id="prop-nome" name="nome" required maxlength="120" placeholder="Ex.: Fazenda Santa Clara"></div>
    <div class="grade-cidade">
      <div class="campo"><label for="prop-cidade">Cidade</label><input id="prop-cidade" name="cidade" maxlength="80"></div>
      <div class="campo"><label for="prop-uf">Estado</label><input id="prop-uf" name="estado" maxlength="2" placeholder="MG"></div>
    </div>
    <div class="dialogo-rodape"><button type="submit" class="botao botao-primario">Cadastrar propriedade</button></div>
  </form>
</dialog>

<dialog id="dialogo-sobre" class="dialogo" aria-labelledby="sobre-titulo">
  <div class="dialogo-cabeca"><h2 id="sobre-titulo" class="titulo-secao">Sobre o sistema</h2><p class="descricao">Pecuária Digital: a fazenda anotada num lugar só.</p>
    <button type="button" class="dialogo-x" data-fechar aria-label="Fechar"><?= icone('x') ?></button></div>
  <div class="texto-suave pilha">
    <p>Rebanho, vacinas, reprodução, leite e dinheiro da fazenda, com os dados guardados com segurança.</p>
    <ul class="lista-pontos">
      <li>Ficha de cada animal com todo o histórico</li>
      <li>Vacinas com a próxima dose e aviso de atraso</li>
      <li>Inseminação com previsão de parto</li>
      <li>Leite por vaca e resultado do mês</li>
      <li>Relatórios por mês e por ano</li>
    </ul>
    <p class="pequeno">Cada conta só vê as próprias propriedades.</p>
  </div>
  <div class="dialogo-rodape"><button type="button" class="botao botao-primario" data-fechar>Fechar</button></div>
</dialog>

<?php require RAIZ . '/views/_avisos.php'; ?>
</body>
</html>
