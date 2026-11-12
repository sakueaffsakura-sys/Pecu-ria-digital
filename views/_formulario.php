<?php
/** Formulário de registro (novo ou edição), num quadro por cima da lista, como o diálogo do original. */
$linhaF = $form['linha'];
$val = $form['valores'];
$errosF = $form['erros'];
$ehNota = $m['tabela'] === 'anotacoes_producao';
$somenteLeitura = !pode_editar();
$avancados = campos_avancados($m);
$abrirDetalhes = $avancados && (array_filter(array_intersect_key($val, $avancados), fn($x) => trim((string) $x) !== '') || array_intersect_key($errosF, $avancados));
$titulo = $ehNota ? ($linhaF ? 'Editar anotação' : 'Nova anotação') : ($linhaF ? 'Editar ' : 'Novo ') . $m['singular'];
$fechar = url("app/$secao", array_filter(['aba' => $aba] + $get));
$listas = [];
foreach ($m['campos'] as $k => $c) {
    $t = $c['tipo'] ?? 'text';
    if ($t === 'animal') $listas['animais-' . ($c['sexo'] ?? 'todos') . (!empty($c['so_ativos']) ? '-ativos' : '')] = [$c['sexo'] ?? null, !empty($c['so_ativos'])];
    if ($t === 'lote') $listas['lotes'] = true;
    if ($t === 'vacina') $listas['vacinas'] = true;
}
$campo = function (string $k, array $c) use ($val, $errosF, $ehNota): string {
    $t = $c['tipo'] ?? 'text';
    $id = 'campo-' . $k;
    $v = (string) ($val[$k] ?? '');
    $req = !empty($c['obrigatorio']) ? ' required' : '';
    $inv = isset($errosF[$k]) ? ' aria-invalid="true" aria-describedby="erro-' . $k . '"' : '';
    $dica = isset($c['dica']) ? ' placeholder="' . e($c['dica']) . '"' : '';
    $h = '<div class="campo' . ($t === 'textarea' ? ' campo-largo' : '') . '"><label for="' . $id . '">' . e($c['rotulo'])
        . ($ehNota && !empty($c['obrigatorio']) ? ' <span class="obrig" aria-hidden="true">*</span>' : '') . '</label>';
    switch ($t) {
        case 'textarea':
            $h .= '<textarea id="' . $id . '" name="' . $k . '" rows="' . ($k === 'observacao' && $ehNota ? 5 : 3) . '"' . $req . $inv . $dica . '>' . e($v) . '</textarea>';
            break;
        case 'select':
            $h .= '<select id="' . $id . '" name="' . $k . '"' . $req . $inv . '><option value="">Escolha…</option>';
            foreach ($c['opcoes'] as $o => $rot) $h .= '<option value="' . e($o) . '"' . ((string) $o === $v || $rot === $v ? ' selected' : '') . '>' . e($rot) . '</option>';
            $h .= '</select>';
            break;
        case 'animal':
            $lista = 'animais-' . ($c['sexo'] ?? 'todos') . (!empty($c['so_ativos']) ? '-ativos' : '');
            $h .= '<input id="' . $id . '" name="' . $k . '" type="text" list="' . $lista . '" autocomplete="off" value="' . e($v) . '"' . $req . $inv
                . ' placeholder="' . e($c['dica'] ?? 'Digite o brinco ou nome do animal') . '">';
            break;
        case 'lote':
        case 'vacina':
            $h .= '<input id="' . $id . '" name="' . $k . '" type="text" list="' . ($t === 'lote' ? 'lotes' : 'vacinas') . '" autocomplete="off" value="' . e($v) . '"' . $req . $inv
                . ' placeholder="' . e($c['dica'] ?? ($t === 'lote' ? 'Digite o nome do lote' : 'Digite o nome da vacina')) . '">';
            break;
        case 'date':
            $h .= '<input id="' . $id . '" name="' . $k . '" type="date" value="' . e($v) . '"' . $req . $inv . '>';
            if (isset($c['dica'])) $h .= '<p class="ajuda">' . e($c['dica']) . '</p>';
            break;
        case 'number':
            $h .= '<input id="' . $id . '" name="' . $k . '" type="text" inputmode="decimal" value="' . e($v) . '"' . $req . $inv . $dica . '>';
            break;
        default:
            $h .= '<input id="' . $id . '" name="' . $k . '" type="text" value="' . e($v) . '"' . (isset($c['max']) ? ' maxlength="' . (int) $c['max'] . '"' : '') . $req . $inv . $dica . '>';
    }
    if (isset($errosF[$k])) $h .= '<p class="erro-campo" id="erro-' . $k . '">' . e($errosF[$k]) . '</p>';
    return $h . '</div>';
};
?>
<dialog class="dialogo dialogo-form<?= $ehNota ? ' dialogo-nota' : '' ?>" data-auto aria-labelledby="form-titulo" data-fechar-para="<?= e($fechar) ?>" open>
  <div class="dialogo-cabeca">
    <?php if ($ehNota): ?><p class="selo-secao"><?= icone('notebook-pen') ?>Produção · Anotações</p><?php endif; ?>
    <h2 id="form-titulo" class="titulo-secao"><?= e($titulo) ?></h2>
    <p class="descricao">Os dados serão gravados na propriedade selecionada.</p>
    <a class="dialogo-x" href="<?= e($fechar) ?>" data-fechar aria-label="Fechar"><?= icone('x') ?></a>
  </div>
  <?php if (!empty($errosF['_geral'])): ?><p class="erro-forte" role="alert"><?= e($errosF['_geral']) ?></p><?php endif; ?>
  <?php if (count(array_diff_key($errosF, ['_geral' => 1]))): ?><p class="erro-forte" role="alert">Confira os campos marcados.</p><?php endif; ?>
  <form method="post" action="<?= e(url("app/$secao")) ?>">
    <?= csrf_campo() ?>
    <input type="hidden" name="acao" value="salvar">
    <?php if ($aba): ?><input type="hidden" name="aba" value="<?= e($aba) ?>"><?php endif; ?>
    <?php if ($linhaF): ?><input type="hidden" name="id" value="<?= e($linhaF['id']) ?>"><?php endif; ?>
    <fieldset class="grade-form"<?= $somenteLeitura ? ' disabled' : '' ?>>
      <legend class="so-leitor"><?= e($titulo) ?></legend>
      <?php foreach (campos_basicos($m) as $k => $c) echo $campo($k, $c); ?>
      <?php if ($avancados): ?>
        <details class="mais-detalhes campo-largo"<?= $abrirDetalhes ? ' open' : '' ?>>
          <summary class="botao botao-contorno"><?= icone('plus') ?>Mais detalhes (<?= e(implode(', ', array_slice(array_map(fn($c) => mb_strtolower($c['rotulo']), $avancados), 0, 2))) ?>…)</summary>
          <div class="grade-form">
            <?php foreach ($avancados as $k => $c) echo $campo($k, $c); ?>
          </div>
        </details>
      <?php endif; ?>
      <?php if (!empty($m['somente_leitura']) && $linhaF): ?>
        <div class="campo"><span class="rotulo">Total</span><p class="valor-lido"><?= e(dinheiro($linhaF['valor_total'])) ?></p></div>
      <?php endif; ?>
    </fieldset>
    <?php if (isset($listas['lotes'])): ?><datalist id="lotes"><?php foreach (todos('SELECT nome FROM lotes WHERE propriedade_id = ? ORDER BY nome LIMIT 2000', [pid()]) as $x): ?><option value="<?= e($x['nome']) ?>"><?php endforeach; ?></datalist><?php endif; ?>
    <?php if (isset($listas['vacinas'])): ?><datalist id="vacinas"><?php foreach (todos('SELECT nome FROM tipos_vacinas WHERE propriedade_id = ? ORDER BY nome LIMIT 2000', [pid()]) as $x): ?><option value="<?= e($x['nome']) ?>"><?php endforeach; ?></datalist><?php endif; ?>
    <?php foreach ($listas as $idLista => $cfg): if (!str_starts_with($idLista, 'animais-')) continue; [$sx, $ativos] = $cfg; ?>
      <datalist id="<?= e($idLista) ?>">
        <?php foreach ($lista_animais as $a): if ($sx && $a['sexo'] !== $sx) continue; if ($ativos && $a['situacao'] !== 'ativo') continue; ?>
          <option value="<?= e($a['numero_brinco']) ?>"><?= e($a['nome'] ?? '') ?></option>
        <?php endforeach; ?>
      </datalist>
    <?php endforeach; ?>
    <div class="dialogo-rodape">
      <a class="botao botao-contorno" href="<?= e($fechar) ?>" data-fechar>Cancelar</a>
      <?php if (!$somenteLeitura): ?><button type="submit" class="botao botao-primario"><?= $ehNota ? icone('save') : '' ?><?= $ehNota ? 'Salvar anotação' : 'Salvar' ?></button><?php endif; ?>
    </div>
  </form>
</dialog>
