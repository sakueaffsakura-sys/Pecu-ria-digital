<?php
$gest = (int) ($prop['periodo_gestacao_dias'] ?? 283);
$cidade = trim(($prop['cidade'] ?? '') . (!empty($prop['estado']) ? ', ' . $prop['estado'] : ''), ', ');
/** Uma calculadora: título, campos e resultado (as contas rodam na tela, nada é gravado). */
$conta = function (string $id, string $titulo, string $ic, string $explica, array $campos) use ($gest): string {
    $h = '<section class="painel p-painel conta" data-conta="' . e($id) . '"' . ($id === 'parto' ? ' data-gestacao="' . $gest . '"' : '') . '>'
        . '<h3 class="titulo-conta">' . icone($ic, 'ic ic-verde') . e($titulo) . '</h3><p class="pequeno texto-suave mt-1">' . e($explica) . '</p><div class="grade-conta">';
    foreach ($campos as [$nome, $rot, $valor, $sufixo, $tipo]) {
        $h .= '<div class="campo"><label for="c-' . e("$id-$nome") . '">' . e($rot) . ($sufixo ? ' (' . e($sufixo) . ')' : '') . '</label>'
            . '<input id="c-' . e("$id-$nome") . '" name="' . e($nome) . '" type="' . ($tipo === 'date' ? 'date' : 'text') . '"' . ($tipo === 'date' ? '' : ' inputmode="' . ($tipo === 'int' ? 'numeric' : 'decimal') . '"') . ' value="' . e($valor) . '"></div>';
    }
    return $h . '</div><output class="resultado-conta" aria-live="polite"><span class="rc-valor" data-resultado>—</span><span class="rc-detalhe" data-detalhe>Precisa do JavaScript ligado para calcular.</span></output></section>';
};
?>
<?= titulo_pagina('Calculadoras e clima', 'As contas do dia a dia, prontas. Mude os números e o resultado muda na hora.') ?>
<section class="mb-8">
  <h2 class="titulo-secao titulo-com-icone"><?= icone('cloud-sun') ?>Previsão do tempo</h2>
  <div class="painel p-painel mt-3" data-clima data-cidade="<?= e($cidade) ?>">
    <form class="linha-clima" data-clima-form>
      <label class="campo cresce"><span data-clima-rotulo>Qual é a sua cidade?</span>
        <span class="campo-busca sem-limite"><?= icone('search', 'ic ic-busca') ?><input type="search" name="nome" placeholder="Ex.: Patos de Minas" value="<?= e($cidade) ?>" data-clima-busca></span></label>
      <button type="submit" class="botao botao-primario"><?= icone('map-pin') ?>Ver o tempo</button>
    </form>
    <ul class="opcoes-clima" data-clima-opcoes hidden></ul>
    <div data-clima-saida><p class="texto-suave mt-3">Escolha a cidade para ver o tempo de hoje e dos próximos dias.</p></div>
  </div>
</section>
<h2 class="titulo-secao titulo-com-icone"><?= icone('droplets') ?>Calculadoras</h2>
<div class="grade-contas mt-3">
  <?= $conta('dose', 'Dose pelo peso', 'syringe', 'Para remédio que vem como “1 mL para cada X kg”. Confira sempre a bula.', [['peso', 'Peso do animal', '320', 'kg', 'dec'], ['kgml', '1 mL para cada', '50', 'kg', 'dec']]) ?>
  <?= $conta('parto', 'Data do parto', 'heart-pulse', "Dia da cobertura mais os dias de gestação ($gest dias nesta propriedade).", [['data', 'Dia da cobertura', hoje(), '', 'date'], ['dias', 'Dias de gestação', (string) $gest, '', 'int']]) ?>
  <?= $conta('mistura', 'Mistura de ração (quadrado de Pearson)', 'sprout', 'Quanto usar de cada alimento para chegar na proteína que você quer.', [['alto', 'Proteína do alimento forte', '45', '%', 'dec'], ['baixo', 'Proteína do alimento fraco', '9', '%', 'dec'], ['alvo', 'Proteína que você quer', '18', '%', 'dec'], ['total', 'Quanto vai misturar', '100', 'kg', 'dec']]) ?>
  <?= $conta('racao', 'Ração por dia', 'sprout', 'Pelo peso vivo. Gado de corte no cocho costuma comer de 2% a 3% do peso.', [['peso', 'Peso médio', '450', 'kg', 'dec'], ['pct', 'Do peso vivo', '2,5', '%', 'dec'], ['qtd', 'Quantos animais', '20', '', 'int']]) ?>
  <?= $conta('lotacao', 'Lotação do pasto', 'scale', 'Uma unidade animal (UA) são 450 kg de peso vivo.', [['qtd', 'Quantos animais', '50', '', 'int'], ['peso', 'Peso médio', '450', 'kg', 'dec'], ['ha', 'Área de pasto', '25', 'hectares', 'dec']]) ?>
  <?= $conta('calagem', 'Calcário por hectare', 'sprout', 'Use os números da análise de solo (CTC e saturação por bases V%) e o PRNT do saco.', [['ctc', 'CTC', '8', 'cmolc/dm³', 'dec'], ['prnt', 'PRNT do calcário', '85', '%', 'dec'], ['v1', 'V atual', '40', '%', 'dec'], ['v2', 'V desejado', '70', '%', 'dec'], ['ha', 'Área', '10', 'hectares', 'dec']]) ?>
  <?= $conta('arroba', 'Arroba e peso vivo', 'calculator', 'Uma arroba são 15 kg de carcaça. O rendimento é quanto do peso vivo vira carcaça.', [['peso', 'Peso vivo', '480', 'kg', 'dec'], ['rend', 'Rendimento de carcaça', '52', '%', 'dec'], ['preco', 'Preço da arroba', '320', 'R$', 'dec'], ['arrobas', 'Ou converter de volta', '16', '@', 'dec']]) ?>
</div>
<p class="pequeno texto-suave mt-5">As contas são uma ajuda, não substituem o veterinário nem o agrônomo. Para remédios, siga sempre a bula e a receita.</p>
<script src="/assets/ferramentas.js?v=<?= e((string) @filemtime(RAIZ . '/public/assets/ferramentas.js')) ?>" defer></script>
