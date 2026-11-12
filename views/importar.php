<div class="cabecalho"><h1>Importar animais de uma planilha</h1>
  <div class="acoes"><a class="botao botao-leve" href="/assets/modelo-animais.csv" download>Baixar planilha modelo</a></div></div>

<?php if ($resultado): ?>
  <div class="aviso <?= $resultado['criados'] ? 'aviso-ok' : 'aviso-info' ?>" role="status">
    <?= num_br($resultado['criados']) ?> animal(is) cadastrado(s).
    <?= $resultado['repetidos'] ? num_br($resultado['repetidos']) . ' já existiam (brinco repetido) e foram pulados.' : '' ?>
    <?= $resultado['erros'] ? count($resultado['erros']) . ' linha(s) com problema.' : '' ?>
  </div>
  <?php if ($resultado['erros']): ?>
    <section class="cartao"><h2>Linhas com problema</h2><ul class="lista-simples">
      <?php foreach (array_slice($resultado['erros'], 0, 100) as $er): ?><li><?= e($er) ?></li><?php endforeach; ?>
    </ul><?php if (count($resultado['erros']) > 100): ?><p>… e mais <?= count($resultado['erros']) - 100 ?>.</p><?php endif; ?></section>
  <?php endif; ?>
<?php endif; ?>

<section class="cartao">
  <h2>Como montar a planilha</h2>
  <p>Salve do Excel como <strong>CSV</strong>. A primeira linha tem os nomes das colunas:</p>
  <p><code>brinco;nome;sexo;raca;categoria;nascimento;lote</code></p>
  <ul class="lista-simples">
    <li><strong>brinco</strong> e <strong>sexo</strong> (F ou M) são obrigatórios.</li>
    <li><strong>nascimento</strong> no formato 25/03/2024.</li>
    <li><strong>categoria</strong>: bezerro, novilha, vaca, garrote, boi ou touro.</li>
    <li>Lotes que não existem são criados. Brincos que já existem são pulados.</li>
  </ul>
  <?php if (pode_editar()): ?>
  <form method="post" action="<?= e(url('importar')) ?>" enctype="multipart/form-data" class="form">
    <?= csrf_campo() ?>
    <div class="campo"><label for="c-arquivo">Arquivo CSV (até 2 MB)</label><input id="c-arquivo" type="file" name="arquivo" accept=".csv,text/csv,text/plain"></div>
    <div class="campo campo-largo"><label for="c-texto">Ou cole as linhas aqui</label><textarea id="c-texto" name="texto" rows="6" placeholder="brinco;nome;sexo&#10;101;Mimosa;F"></textarea></div>
    <button class="botao botao-grande" type="submit">Importar</button>
  </form>
  <?php endif; ?>
</section>
