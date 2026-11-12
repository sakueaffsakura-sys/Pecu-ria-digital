<section class="heroi">
  <h1>A fazenda inteira na palma da mão</h1>
  <p>Rebanho, vacinas, reprodução, pesagens, leite e dinheiro num lugar só. Funciona no celular, no tablet e no computador.</p>
  <p class="acoes">
    <?php if (config('cadastro_aberto')): ?><a class="botao botao-grande" href="<?= e(url('cadastro')) ?>">Criar conta grátis</a><?php endif; ?>
    <a class="botao botao-leve botao-grande" href="<?= e(url('entrar')) ?>">Já tenho conta</a>
  </p>
</section>
<section class="recursos">
  <article><h2>Rebanho</h2><p>Cadastro por brinco, lotes, ficha de cada animal com pesagens, vacinas, crias e ganho de peso por dia.</p></article>
  <article><h2>Sanidade</h2><p>Vacinas por animal ou por lote, com a próxima dose calculada e aviso do que está vencendo.</p></article>
  <article><h2>Reprodução</h2><p>Inseminações, diagnóstico de prenhez, previsão de parto e nascimentos com cadastro automático da cria.</p></article>
  <article><h2>Leite</h2><p>Produção por ordenha ou por vaca, com gráfico dos últimos dias e totais por mês.</p></article>
  <article><h2>Financeiro</h2><p>Entradas, saídas, compras e vendas. Cada compra e venda já entra no caixa sozinha.</p></article>
  <article><h2>Equipe</h2><p>Convide quem trabalha com você: quem pode lançar e quem só consulta.</p></article>
</section>
