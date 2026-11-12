<?php
/** Mural de anotações (cartões por dia), igual ao original. */
$editar = pode_editar();
$abaNotas = in_array($get['aba_notas'] ?? ($_GET['aba_notas'] ?? 'todas'), ['todas', 'marcadas', 'animais', 'gerais'], true) ? ($_GET['aba_notas'] ?? 'todas') : 'todas';
$_GET['aba'] = 'anotacoes';
$dias = [];
foreach ($r['linhas'] as $l) $dias[$l['data']][] = $l;
$hoje = hoje();
$rotuloDia = function (string $d) use ($hoje): string {
    if ($d === $hoje) return 'Hoje';
    if ($d === somar_dias($hoje, -1)) return 'Ontem';
    $t = data_extenso($d);
    return mb_strtoupper(mb_substr($t, 0, 1)) . mb_substr($t, 1);
};
$getNotas = array_filter(['aba' => 'anotacoes', 'aba_notas' => $abaNotas !== 'todas' ? $abaNotas : null, 'busca' => $get['busca'] ?? null, 'por' => $get['por'] ?? null]);
?>
<nav class="abas" aria-label="Leite">
  <a href="/app/producao">Registros de leite</a>
  <a href="/app/producao?aba=anotacoes" aria-current="page">Anotações</a>
</nav>
<?= titulo_pagina('Anotações', 'O caderno da fazenda: o que aconteceu, com qual animal e em que dia.', $editar ? botao_link('/app/producao?aba=anotacoes&novo=1', 'Nova anotação', 'plus') : '') ?>

<form method="get" action="/app/producao" class="filtros-lista">
  <input type="hidden" name="aba" value="anotacoes">
  <?php if ($abaNotas !== 'todas'): ?><input type="hidden" name="aba_notas" value="<?= e($abaNotas) ?>"><?php endif; ?>
  <label class="campo-busca"><span class="so-leitor">Buscar anotações</span><?= icone('search', 'ic ic-busca') ?>
    <input name="busca" type="search" value="<?= e($get['busca'] ?? '') ?>" placeholder="Texto, brinco ou nome do animal"></label>
  <div role="group" aria-label="Filtrar anotações" class="pilulas">
    <?php foreach (['todas' => 'Todas', 'marcadas' => 'Marcadas', 'animais' => 'De animais', 'gerais' => 'Gerais'] as $k => $rot): ?>
      <a href="<?= e(url('app/producao', array_filter(['aba' => 'anotacoes', 'aba_notas' => $k !== 'todas' ? $k : null, 'busca' => $get['busca'] ?? null]))) ?>" aria-pressed="<?= $k === $abaNotas ? 'true' : 'false' ?>"><?= e($rot) ?></a>
    <?php endforeach; ?>
  </div>
  <span class="pequeno texto-suave contagem"><?= e(plural($r['total'], 'anotação', 'anotações')) ?></span>
</form>

<?php if (!$r['linhas']): ?>
  <?= vazio(!empty($get['busca']) ? 'Nenhuma anotação com essa busca.'
      : ($abaNotas === 'marcadas' ? 'Você ainda não marcou nenhuma anotação. Toque na estrela de uma anotação para guardá-la aqui.'
      : ($abaNotas === 'animais' ? 'Nenhuma anotação ligada a um animal.'
      : ($abaNotas === 'gerais' ? 'Todas as suas anotações estão ligadas a algum animal.' : 'Ainda não há anotações. Escreva o que aconteceu hoje na fazenda.'))),
      empty($get['busca']) && $abaNotas === 'todas' && $editar ? 'Escrever a primeira anotação' : null, '/app/producao?aba=anotacoes&novo=1') ?>
<?php else: ?>
  <div class="pilha-6">
    <?php foreach ($dias as $dia => $doDia): ?>
      <details class="dia-notas" open>
        <summary><?= icone('calendar-days', 'ic ic-verde') ?><span class="truncar"><?= e($rotuloDia($dia)) ?></span><span class="pequeno texto-suave"><?= e(plural(count($doDia), 'anotação', 'anotações')) ?></span><?= icone('chevron-down', 'ic ic-suave seta') ?></summary>
        <ul class="grade-notas">
          <?php foreach ($doDia as $n):
            $marcada = (int) $n['marcada'] === 1;
            $texto = trim((string) $n['observacao']) ?: 'Anotação sem texto';
            $hora = $n['created_at'] ? date('H:i', strtotime($n['created_at'])) : ''; ?>
            <li><article class="painel nota<?= $marcada ? ' marcada' : '' ?>">
              <header class="nota-cabeca">
                <?php if ($n['animal_id']): ?>
                  <?= brinco((string) $n['animal_id__brinco'], 'pequeno', null, 'Anotação do animal ' . $n['animal_id__brinco']) ?>
                <?php else: ?>
                  <span class="etiqueta-geral"><?= icone('tractor') ?>Geral</span>
                <?php endif; ?>
                <div class="min0 nota-quem">
                  <p class="truncar negrito"><?= e($n['animal_id'] ? ($n['animal_id__nome'] ?: $n['animal_id__brinco']) : 'Anotação geral') ?></p>
                  <?php if ($n['lote_id']): ?><p class="truncar pequeno texto-suave"><?= e($n['lote_id__nome']) ?></p><?php endif; ?>
                </div>
                <?php if ($editar): ?>
                  <form method="post" action="/app/producao"><?= csrf_campo() ?>
                    <input type="hidden" name="acao" value="marcar"><input type="hidden" name="id" value="<?= e($n['id']) ?>"><input type="hidden" name="aba" value="anotacoes">
                    <?php if ($abaNotas !== 'todas'): ?><input type="hidden" name="aba_notas" value="<?= e($abaNotas) ?>"><?php endif; ?>
                    <button type="submit" class="botao-icone estrela<?= $marcada ? ' ligada' : '' ?>" aria-pressed="<?= $marcada ? 'true' : 'false' ?>"
                      aria-label="<?= $marcada ? 'Tirar a marca desta anotação' : 'Marcar esta anotação como importante' ?>" title="<?= $marcada ? 'Tirar a marca' : 'Marcar como importante' ?>"><?= icone('star') ?></button>
                  </form>
                <?php endif; ?>
                <details class="suspenso menu-linha">
                  <summary class="botao-icone" aria-label="Mais opções da anotação"><?= icone('ellipsis') ?></summary>
                  <div class="suspenso-caixa">
                    <a class="suspenso-item" href="<?= e(url('app/producao', ['aba' => 'anotacoes', 'editar' => $n['id']])) ?>"><?= icone('pencil') ?>Abrir e editar</a>
                    <?php if ($n['animal_id']): ?><a class="suspenso-item" href="<?= e(url('app/rebanho', ['animal' => $n['animal_id']])) ?>"><?= icone('notebook-pen') ?>Ver a ficha do animal</a><?php endif; ?>
                    <?php if ($editar): ?><button type="button" class="suspenso-item item-perigo" data-abrir="#excluir-<?= e($n['id']) ?>"><?= icone('trash') ?>Excluir</button><?php endif; ?>
                  </div>
                </details>
              </header>
              <p class="nota-texto"><?= e($texto) ?></p>
              <?php if ($n['comportamento'] || $n['alimentacao']): ?>
                <div class="nota-extra">
                  <?php if ($n['comportamento']): ?><p><strong>Comportamento: </strong><?= e(bonito($n['comportamento'])) ?></p><?php endif; ?>
                  <?php if ($n['alimentacao']): ?><p><strong>Alimentação: </strong><?= e(bonito($n['alimentacao'])) ?></p><?php endif; ?>
                </div>
              <?php endif; ?>
              <?php if ($n['informacoes_adicionais']): ?><p class="nota-texto pequeno texto-suave"><?= e($n['informacoes_adicionais']) ?></p><?php endif; ?>
              <footer class="nota-rodape"><span><?= e($hora ?: data_br($n['data'])) ?></span>
                <?php if ((float) $n['producao'] > 0): ?><span class="selo selo-pasto"><?= icone('milk', 'ic-selo') ?><?= e(num_br((float) $n['producao'], fmod((float) $n['producao'], 1) == 0.0 ? 0 : 1)) ?> L</span><?php endif; ?>
              </footer>
              <?php if ($editar): ?>
                <dialog id="excluir-<?= e($n['id']) ?>" class="dialogo dialogo-alerta">
                  <h2 class="titulo-secao">Excluir esta anotação?</h2>
                  <p class="descricao">Isso não pode ser desfeito.</p>
                  <form method="post" action="/app/producao" class="dialogo-rodape"><?= csrf_campo() ?>
                    <input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="<?= e($n['id']) ?>"><input type="hidden" name="aba" value="anotacoes">
                    <button type="button" class="botao botao-contorno" data-fechar>Cancelar</button><button type="submit" class="botao botao-perigo">Excluir</button>
                  </form>
                </dialog>
              <?php endif; ?>
            </article></li>
          <?php endforeach; ?>
        </ul>
      </details>
    <?php endforeach; ?>
  </div>
  <div class="painel rodape-notas"><?= paginacao($r['pagina'], $r['ultima'], $r['total'], $r['por'], $getNotas, '/app/producao') ?></div>
<?php endif; ?>

<?php if ($form) require RAIZ . '/views/_formulario.php'; ?>
