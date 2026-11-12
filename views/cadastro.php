<?php
/** @var array $m @var array $r @var ?array $form @var ?array $ficha @var array $get @var string $base @var string $aba */
$editar = pode_editar();
$ehAnimais = $m['tabela'] === 'animais';
[$modo] = periodo_escolhido($get);
$abas = abas_da_secao($secao);
$comAba = fn(array $extra = []) => url("app/$secao", array_filter(['aba' => $aba] + $extra));
$acoes = '';
if ($editar) {
    $acoes .= '<span class="botao botao-contorno desligado" title="Importar planilha chega na próxima etapa">' . icone('upload') . '<span>Importar planilha</span></span>';
    $acoes .= botao_link($comAba(['novo' => '1']), $m['novo'], 'plus');
}
?>
<?php if ($abas): ?>
  <nav class="abas" aria-label="<?= e($m['titulo']) ?>">
    <?php foreach ($abas as [$rot, $valor]): ?>
      <a href="<?= e(url("app/$secao", array_filter(['aba' => $valor]))) ?>"<?= $valor === $aba ? ' aria-current="page"' : '' ?>><?= e($rot) ?></a>
    <?php endforeach; ?>
  </nav>
<?php endif; ?>

<?= titulo_pagina($m['titulo'], $m['descricao'], $acoes) ?>

<form method="get" action="<?= e($base) ?>" class="filtros-lista">
  <?php if ($aba): ?><input type="hidden" name="aba" value="<?= e($aba) ?>"><?php endif; ?>
  <?php if (!empty($get['por'])): ?><input type="hidden" name="por" value="<?= e($get['por']) ?>"><?php endif; ?>
  <label class="campo-busca">
    <span class="so-leitor">Buscar em <?= e(mb_strtolower($m['titulo'])) ?></span>
    <?= icone('search', 'ic ic-busca') ?>
    <input name="busca" type="search" value="<?= e($get['busca'] ?? '') ?>" placeholder="<?= e($m['dica_busca']) ?>">
  </label>
  <div role="group" aria-label="Período" class="segmentado">
    <?php foreach (['todos' => 'Tudo', 'dia' => 'Hoje', 'mes' => 'Este mês', 'ano' => 'Este ano'] as $valor => $rot): ?>
      <button type="submit" name="periodo" value="<?= $valor ?>" aria-pressed="<?= $modo === $valor && ($valor !== 'dia' || empty($get['dia'])) ? 'true' : 'false' ?>"><?= e($rot) ?></button>
    <?php endforeach; ?>
    <input type="date" name="dia" value="<?= e($get['dia'] ?? '') ?>" aria-label="Escolher um dia" title="Escolher um dia" data-enviar class="<?= !empty($get['dia']) ? 'escolhido' : '' ?>">
  </div>
  <?php if ($modo !== 'todos'): ?><span class="pequeno texto-suave">Pela <?= e(mb_strtolower($m['campos'][campo_data($m)]['rotulo'] ?? 'data de cadastro')) ?></span><?php endif; ?>
  <noscript><button class="botao botao-contorno">Buscar</button></noscript>
</form>

<section class="painel tabela-painel">
  <?php if (!$r['linhas']): ?>
    <?= vazio($r['filtrando'] ? 'Nada encontrado com essa busca ou período.' : 'Nenhum registro em ' . $m['titulo'] . ' ainda.',
        !$r['filtrando'] && $editar ? $m['primeiro'] : null, $comAba(['novo' => '1'])) ?>
  <?php else: ?>
    <div class="tabela-rolagem">
      <table class="tabela">
        <thead><tr>
          <?php foreach ($m['colunas'] as $col => $rot): ?><th scope="col"><?= e($rot) ?></th><?php endforeach; ?>
          <th scope="col" class="col-acoes"><span class="so-leitor">Ações</span></th>
        </tr></thead>
        <tbody>
          <?php foreach ($r['linhas'] as $l):
            $rotuloLinha = $ehAnimais ? 'animal ' . $l['numero_brinco'] : $m['singular']; ?>
            <tr>
              <?php $cols = array_keys($m['colunas']); $lider = $cols[0] === 'numero_brinco';
                $primeira = $ehAnimais ? $comAba(['animal' => $l['id']] + array_filter($get)) : $comAba(['editar' => $l['id']] + array_filter($get));
                foreach ($cols as $i => $col): $rot = $m['colunas'][$col];
                  $classe = $lider && $i === 0 ? 'td-lider' : (($lider ? 1 : 0) === $i ? 'td-titulo' : 'td-detalhe'); ?>
                <td class="<?= $classe ?>" data-rotulo="<?= e($rot) ?>"><span class="so-leitor so-celular"><?= e($rot) ?>: </span><?php if ($classe === 'td-titulo'): ?><a class="link-linha" href="<?= e($primeira) ?>"><?= celula($m, $col, $l) ?></a><?php else: ?><?= celula($m, $col, $l) ?><?php endif; ?></td>
              <?php endforeach; ?>
              <td class="col-acoes">
                <details class="suspenso menu-linha">
                  <summary class="botao-icone" aria-label="Ações do <?= e($rotuloLinha) ?>"><?= icone('ellipsis') ?></summary>
                  <div class="suspenso-caixa">
                    <?php if ($ehAnimais): ?><a class="suspenso-item" href="<?= e($comAba(['animal' => $l['id']] + array_filter($get))) ?>"><?= icone('file-text') ?>Ver ficha</a><?php endif; ?>
                    <a class="suspenso-item" href="<?= e($comAba(['editar' => $l['id']] + array_filter($get))) ?>"><?= icone('pencil') ?><?= $editar ? 'Editar' : 'Ver' ?></a>
                    <?php if ($editar): ?>
                      <button type="button" class="suspenso-item item-perigo" data-abrir="#excluir-<?= e($l['id']) ?>"><?= icone('trash') ?>Excluir</button>
                    <?php endif; ?>
                  </div>
                </details>
                <?php if ($editar): ?>
                  <dialog id="excluir-<?= e($l['id']) ?>" class="dialogo dialogo-alerta" aria-labelledby="excluir-t-<?= e($l['id']) ?>">
                    <h2 id="excluir-t-<?= e($l['id']) ?>" class="titulo-secao">Excluir este <?= e($m['singular']) ?>?</h2>
                    <p class="descricao">O registro sai da lista e dos relatórios. Isso não pode ser desfeito.</p>
                    <form method="post" action="<?= e($base) ?>" class="dialogo-rodape"><?= csrf_campo() ?>
                      <input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="<?= e($l['id']) ?>"><input type="hidden" name="aba" value="<?= e($aba) ?>">
                      <button type="button" class="botao botao-contorno" data-fechar>Cancelar</button>
                      <button type="submit" class="botao botao-perigo">Excluir</button>
                    </form>
                  </dialog>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
  <?= paginacao($r['pagina'], $r['ultima'], $r['total'], $r['por'], $get, $base) ?>
</section>

<?php if ($form) require RAIZ . '/views/_formulario.php'; ?>
<?php if ($ficha) require RAIZ . '/views/_ficha.php'; ?>
