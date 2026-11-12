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
