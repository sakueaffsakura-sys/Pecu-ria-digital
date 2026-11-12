<?php
declare(strict_types=1);

/** Passo "redondo" para a escala (1, 2, 2,5 ou 5 vezes uma potência de 10). */
function passo_redondo(float $bruto): float
{
    if ($bruto <= 0) return 1;
    $pot = 10 ** floor(log10($bruto));
    foreach ([1, 2, 2.5, 5, 10] as $m) if ($bruto <= $m * $pot) return $m * $pot;
    return 10 * $pot;
}

/**
 * Colunas por mês (ou por dia) com o período atual em destaque e os outros num tom mais claro,
 * como no original. Valores negativos descem abaixo da linha do zero (mês no prejuízo).
 * $dados: lista de ['rotulo' => 'dez', 'valor' => 123.4, 'atual' => bool, 'dica' => 'texto do tooltip'].
 * $marcas: índices que recebem rótulo no eixo de baixo (null = todos).
 */
function grafico_colunas(array $dados, callable $formato, string $aria, ?array $marcas = null): string
{
    $L = 400; $A = 224; $esq = 52; $dir = 8; $topo = 10; $base = 26;
    $vals = array_map(fn($d) => (float) $d['valor'], $dados);
    $max = max(0.0, ...$vals);
    $min = min(0.0, ...$vals);
    if ($max == $min) $max = $min + 1;
    $passo = passo_redondo(($max - $min) / 4);
    $yMax = ceil($max / $passo) * $passo;
    $yMin = floor($min / $passo) * $passo;
    $altUtil = $A - $topo - $base;
    $y = fn(float $v) => $topo + ($yMax - $v) / ($yMax - $yMin) * $altUtil;
    $n = count($dados);
    $larg = ($L - $esq - $dir) / max(1, $n);
    $barra = min(24, $larg * 0.64);

    $svg = '<svg class="grafico" viewBox="0 0 ' . $L . ' ' . $A . '" role="img" aria-label="' . e($aria) . '">';
    for ($v = $yMin; $v <= $yMax + $passo / 2; $v += $passo) {
        $yy = round($y($v), 1);
        $svg .= '<line class="grade" x1="' . $esq . '" x2="' . ($L - $dir) . '" y1="' . $yy . '" y2="' . $yy . '"/>';
        $svg .= '<text class="eixo" x="' . ($esq - 8) . '" y="' . ($yy + 4) . '" text-anchor="end">' . e(compacto($v)) . '</text>';
    }
    if ($yMin < 0) $svg .= '<line class="zero" x1="' . $esq . '" x2="' . ($L - $dir) . '" y1="' . round($y(0), 1) . '" y2="' . round($y(0), 1) . '"/>';
    foreach ($dados as $i => $d) {
        $v = (float) $d['valor'];
        $cx = $esq + $larg * $i + $larg / 2;
        $y0 = $y(0);
        $y1 = $y($v);
        $alto = max(abs($y0 - $y1), $v != 0.0 ? 1.5 : 0);
        $classe = ($v < 0 ? 'col-neg' : 'col-pos') . (empty($d['atual']) ? ' fraco' : '');
        $svg .= sprintf('<rect class="%s" x="%.1f" y="%.1f" width="%.1f" height="%.1f" rx="3"><title>%s</title></rect>',
            $classe, $cx - $barra / 2, min($y0, $y1), $barra, $alto, e($d['rotulo'] . ': ' . ($d['dica'] ?? $formato($v))));
        if ($marcas === null || in_array($i, $marcas, true)) {
            $svg .= '<text class="eixo" x="' . round($cx, 1) . '" y="' . ($A - 6) . '" text-anchor="middle">' . e($d['rotulo']) . '</text>';
        }
    }
    return $svg . '</svg>';
}

/** Colunas de uma série (relatórios): ['rótulo' => total]. $cor: classe CSS (c1…c5). */
function grafico_simples(array $serie, callable $formato, string $aria, string $cor = 'c1'): string
{
    if (!$serie) return vazio('Sem dados no período.');
    $dados = [];
    foreach ($serie as $r => $v) $dados[] = ['rotulo' => (string) $r, 'valor' => (float) $v, 'atual' => true];
    $n = count($dados);
    $marcas = $n <= 12 ? null : array_values(array_filter(range(0, $n - 1), fn($i) => $i % (int) ceil($n / 10) === 0));
    return '<div class="caixa-grafico grafico-' . e($cor) . '">' . grafico_colunas($dados, $formato, $aria, $marcas) . '</div>';
}

/** Duas séries lado a lado (receitas x despesas): ['rótulo' => [a, b]]. */
function grafico_agrupado(array $serie, array $nomes, callable $formato, string $aria): string
{
    if (!$serie) return vazio('Sem lançamentos financeiros no período.');
    $L = 400; $A = 240; $esq = 52; $dir = 8; $topo = 10; $base = 26;
    $max = 0.0;
    foreach ($serie as $v) $max = max($max, (float) $v[0], (float) $v[1]);
    $passo = passo_redondo(max($max, 1) / 4);
    $yMax = max($passo, ceil($max / $passo) * $passo);
    $alt = $A - $topo - $base;
    $n = count($serie);
    $larg = ($L - $esq - $dir) / $n;
    $barra = min(14, $larg * 0.36);
    $svg = '<svg class="grafico" viewBox="0 0 ' . $L . ' ' . $A . '" role="img" aria-label="' . e($aria) . '">';
    for ($v = 0; $v <= $yMax + $passo / 2; $v += $passo) {
        $y = round($topo + ($yMax - $v) / $yMax * $alt, 1);
        $svg .= '<line class="grade" x1="' . $esq . '" x2="' . ($L - $dir) . '" y1="' . $y . '" y2="' . $y . '"/><text class="eixo" x="' . ($esq - 8) . '" y="' . ($y + 4) . '" text-anchor="end">' . e(compacto($v)) . '</text>';
    }
    $i = 0;
    $passoRotulo = $n <= 12 ? 1 : (int) ceil($n / 10);
    foreach ($serie as $rot => [$a, $b]) {
        $cx = $esq + $larg * $i + $larg / 2;
        foreach ([[$a, 'col-pos', -1, $nomes[0]], [$b, 'col-neg', 1, $nomes[1]]] as [$v, $cl, $lado, $nome]) {
            $h = $yMax > 0 ? (float) $v / $yMax * $alt : 0;
            $x = $lado < 0 ? $cx - $barra - 1 : $cx + 1;
            $svg .= sprintf('<rect class="%s" x="%.1f" y="%.1f" width="%.1f" height="%.1f" rx="2"><title>%s</title></rect>', $cl, $x, $topo + $alt - $h, $barra, max($h, (float) $v > 0 ? 1.5 : 0), e("$rot · $nome: " . $formato((float) $v)));
        }
        if ($i % $passoRotulo === 0) $svg .= '<text class="eixo" x="' . round($cx, 1) . '" y="' . ($A - 6) . '" text-anchor="middle">' . e((string) $rot) . '</text>';
        $i++;
    }
    $svg .= '</svg>';
    return '<div class="caixa-grafico">' . $svg . '<p class="legenda"><span class="ponto ponto-pos"></span>' . e($nomes[0]) . '<span class="ponto ponto-neg"></span>' . e($nomes[1]) . '</p></div>';
}

/** Barras deitadas (por categoria): ['nome' => total]. */
function grafico_horizontal(array $serie, callable $formato, string $aria): string
{
    if (!$serie) return vazio('Sem dados no período.');
    $max = max(array_map('floatval', $serie)) ?: 1;
    $h = '<ul class="barras-deitadas" aria-label="' . e($aria) . '">';
    foreach ($serie as $nome => $v) {
        $pct = max(1, (int) round((float) $v / $max * 100));
        $h .= '<li><span class="bd-nome truncar">' . e((string) $nome) . '</span><span class="bd-trilho"><span class="bd-barra largura-' . $pct . '"></span></span><span class="bd-valor">' . e($formato((float) $v)) . '</span></li>';
    }
    return $h . '</ul>';
}
