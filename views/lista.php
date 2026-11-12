<?php
/** @var array $m @var array $r @var array $opcoesFiltro */
$slug = $m['slug'];
$esc = $r['escolhido'];
$filtrando = $esc !== [];
?>
<div class="cabecalho">
  <h1><?= e($m['titulo']) ?></h1>
  <div class="acoes">
    <?php if (pode_editar()): ?><a class="botao" href="<?= e(url("m/$slug/novo")) ?>">+ Nov<?= g($m) ?> <?= e($m['singular']) ?></a><?php endif; ?>
    <a class="botao botao-leve" href="<?= e(url("m/$slug/csv", $esc)) ?>">Baixar planilha</a>
  </div>
</div>

<form class="filtros" method="get" action="<?= e(url("m/$slug")) ?>">
  <?php if (!empty($m['busca'])): ?>
    <div class="campo"><label for="f-busca">Buscar</label><input id="f-busca" name="busca" type="search" value="<?= e($esc['busca'] ?? '') ?>"></div>
  <?php endif; ?>
  <?php if (!empty($m['data'])): ?>
    <div class="campo"><label for="f-de">De</label><input id="f-de" name="de" type="date" value="<?= e($esc['de'] ?? '') ?>"></div>
    <div class="campo"><label for="f-ate">Até</label><input id="f-ate" name="ate" type="date" value="<?= e($esc['ate'] ?? '') ?>"></div>
  <?php endif; ?>
  <?php foreach ($opcoesFiltro as $f => $ops): ?>
    <div class="campo"><label for="f-<?= e($f) ?>"><?= e($m['campos'][$f]['rotulo']) ?></label>
      <select id="f-<?= e($f) ?>" name="<?= e($f) ?>"><option value="">Todos</option>
        <?php foreach ($ops as $k => $rot): ?><option value="<?= e($k) ?>"<?= (string) ($esc[$f] ?? '') === (string) $k ? ' selected' : '' ?>><?= e($rot) ?></option><?php endforeach; ?>
      </select></div>
  <?php endforeach; ?>
  <div class="campo campo-botoes"><button class="botao" type="submit">Filtrar</button>
    <?php if ($filtrando): ?><a class="botao botao-leve" href="<?= e(url("m/$slug")) ?>">Limpar</a><?php endif; ?></div>
</form>

<p class="resumo">
  <?= num_br($r['total']) ?> registro(s)<?= $filtrando ? ' com estes filtros' : '' ?>
  <?php if ($r['soma'] !== null): ?> · Total: <strong><?= $m['campos'][$m['total']]['tipo'] === 'dinheiro' ? dinheiro($r['soma']) : num_br($r['soma'], 1) . ' L' ?></strong><?php endif; ?>
  <?php if ($r['saldo'] !== null): ?> · Entradas <strong><?= dinheiro($r['saldo']['entradas']) ?></strong> · Saídas <strong><?= dinheiro($r['saldo']['saidas']) ?></strong> · Saldo <strong><?= dinheiro($r['saldo']['entradas'] - $r['saldo']['saidas']) ?></strong><?php endif; ?>
</p>

<?php if (!$r['linhas']): ?>
  <div class="vazio-grande">
    <p><?= $filtrando ? 'Nada encontrado com estes filtros.' : 'Nenhum registro ainda.' ?></p>
    <?php if (!$filtrando && pode_editar()): ?><a class="botao" href="<?= e(url("m/$slug/novo")) ?>">Cadastrar o primeiro</a><?php endif; ?>
    <?php if ($slug === 'animais' && !$filtrando && pode_editar()): ?><p>Tem uma planilha? <a href="<?= e(url('importar')) ?>">Importe os animais de uma vez</a>.</p><?php endif; ?>
  </div>
<?php else: ?>
  <div class="tabela-rolagem">
    <table class="tabela">
      <thead><tr>
        <?php foreach ($m['colunas'] as $c): ?><th scope="col"><?= e($m['campos'][$c]['rotulo']) ?></th><?php endforeach; ?>
        <?php if (!empty($m['contagem'])): ?><th scope="col"><?= e($m['contagem']['rotulo']) ?></th><?php endif; ?>
        <th scope="col"><span class="so-leitor">Ações</span></th>
      </tr></thead>
      <tbody>
        <?php foreach ($r['linhas'] as $l): ?>
          <tr>
            <?php foreach ($m['colunas'] as $i => $c): ?>
              <td data-rotulo="<?= e($m['campos'][$c]['rotulo']) ?>"<?= in_array($m['campos'][$c]['tipo'], ['decimal', 'dinheiro', 'inteiro'], true) ? ' class="num"' : '' ?>>
                <?php $txt = mostrar($m, $c, $l); ?>
                <?php if ($i === 0 && !empty($m['ficha'])): ?>
                  <a href="<?= e(url('animal/' . $l['id'])) ?>"><strong><?= e($txt) ?></strong></a>
                <?php elseif ($c === 'situacao' || $c === 'resultado' || $c === 'tipo' && $slug === 'movimentacoes'): ?>
                  <?= selo($txt, (string) $l[$c]) ?>
                <?php else: ?>
                  <?= e($txt) ?>
                <?php endif; ?>
              </td>
            <?php endforeach; ?>
            <?php if (!empty($m['contagem'])): ?><td class="num" data-rotulo="<?= e($m['contagem']['rotulo']) ?>"><?= mostrar($m, 'contagem', $l) ?></td><?php endif; ?>
            <td class="acoes-linha"><a href="<?= e(url("m/$slug/" . $l['id'])) ?>"><?= pode_editar() ? 'Editar' : 'Ver' ?></a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= paginacao($r['pagina'], $r['total'], array_diff_key($_GET, ['pagina' => 1])) ?>
<?php endif; ?>
