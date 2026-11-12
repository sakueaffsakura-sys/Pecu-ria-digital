<?php
/** Diálogo "Importar planilha" (como o ImportDialog do original). */
$imp = $importacao ?? null;
$passo = $imp['passo'] ?? 'arquivo';
$acaoForm = url("app/$secao", array_filter(['aba' => $aba]));
$plural = mb_substr($m['singular'], -1) === 'l' ? mb_substr($m['singular'], 0, -1) . 'is' : (str_ends_with($m['singular'], 'ão') ? mb_substr($m['singular'], 0, -2) . 'ões' : $m['singular'] . 's');
$stat = fn(string $rot, int $v, string $cor = '') => '<div class="stat-importar"><strong class="' . e($cor) . '">' . e(num_br($v)) . '</strong><span>' . e($rot) . '</span></div>';
?>
<dialog id="dialogo-importar" class="dialogo" aria-labelledby="imp-titulo"<?= $imp ? ' data-auto open' : '' ?>>
  <div class="dialogo-cabeca">
    <h2 id="imp-titulo" class="titulo-secao">Importar <?= e($plural) ?></h2>
    <p class="descricao">Arquivo CSV, TSV ou JSON. Linhas válidas são adicionadas; inválidas são listadas sem apagar nada.</p>
    <button type="button" class="dialogo-x" data-fechar aria-label="Fechar"><?= icone('x') ?></button>
  </div>

  <?php if ($passo === 'previa'): ?>
    <div class="grade-stat"><?= $stat('Total de linhas', $imp['total']) . $stat('Válidas', $imp['validas'], 'texto-verde') . $stat('Com problema', count($imp['problemas']), 'texto-alerta') ?></div>
    <?php if ($imp['ignoradas']): ?><p class="pequeno texto-suave mt-3"><?= e(plural($imp['ignoradas'], 'coluna do arquivo não corresponde', 'colunas do arquivo não correspondem')) ?> a nenhum campo e <?= $imp['ignoradas'] === 1 ? 'será ignorada' : 'serão ignoradas' ?>.</p><?php endif; ?>
    <?php if ($imp['problemas']): ?>
      <div class="caixa-problemas"><p class="pequeno negrito texto-suave">Problemas encontrados (amostra):</p>
        <?php foreach (array_slice($imp['problemas'], 0, 10) as $p): ?><p class="pequeno texto-alerta"><?= icone('circle-x', 'ic-selo') ?> Linha <?= (int) $p['linha'] ?>: <?= e($p['motivo']) ?></p><?php endforeach; ?></div>
    <?php endif; ?>
    <form method="post" action="<?= e($acaoForm) ?>" class="dialogo-rodape"><?= csrf_campo() ?>
      <input type="hidden" name="acao" value="importar_iniciar"><input type="hidden" name="importacao" value="<?= e($imp['id']) ?>"><input type="hidden" name="aba" value="<?= e($aba) ?>">
      <button type="button" class="botao botao-contorno" data-trocar-arquivo>Trocar arquivo</button>
      <button type="submit" class="botao botao-primario"<?= $imp['validas'] ? '' : ' disabled' ?>><?= icone('upload') ?>Importar <?= e(plural($imp['validas'], 'registro')) ?></button>
    </form>
  <?php elseif ($passo === 'pronto'): ?>
    <div class="grade-stat"><?= $stat('Adicionados', $imp['aceitas'], 'texto-verde') . $stat('Duplicados ignorados', $imp['duplicadas'], 'texto-aviso') . $stat('Rejeitados', $imp['rejeitadas'], 'texto-alerta') ?></div>
    <?php if ($imp['motivos']): ?>
      <div class="caixa-problemas alta">
        <?php foreach ($imp['motivos'] as $p): ?><p class="pequeno texto-suave"><?= icone('circle-x', 'ic-selo texto-alerta') ?> Linha <?= (int) $p['linha'] ?>: <?= e($p['motivo']) ?></p><?php endforeach; ?>
        <?php if (count($imp['motivos']) >= 200): ?><p class="pequeno texto-suave">…demais itens suprimidos para manter o registro compacto.</p><?php endif; ?>
      </div>
    <?php endif; ?>
    <div class="dialogo-rodape"><a class="botao botao-primario" href="<?= e($acaoForm) ?>"><?= icone('circle-check') ?>Concluir</a></div>
  <?php endif; ?>

  <form method="post" action="<?= e($acaoForm) ?>" enctype="multipart/form-data" class="pilha"<?= in_array($passo, ['previa', 'pronto'], true) ? ' hidden data-form-arquivo' : ' data-form-arquivo' ?>>
    <?= csrf_campo() ?><input type="hidden" name="acao" value="importar_analisar"><input type="hidden" name="aba" value="<?= e($aba) ?>">
    <?php if ($passo === 'erro'): ?><p class="erro-forte" role="alert"><?= e($imp['erro']) ?></p><?php endif; ?>
    <?php if (!premium_ativo()): ?><p class="faixa faixa-info">A importação de planilhas é um recurso do plano Premium.</p><?php endif; ?>
    <div class="campo"><label for="imp-arquivo">Arquivo</label><input id="imp-arquivo" type="file" name="arquivo" accept=".csv,.tsv,.json,.txt" required></div>
    <p class="caixa-colunas">Colunas esperadas: <strong><?= e(implode(', ', array_keys($m['campos']))) ?></strong></p>
    <div class="dialogo-rodape"><button type="submit" class="botao botao-primario"><?= icone('upload') ?>Analisar arquivo</button></div>
  </form>
</dialog>
