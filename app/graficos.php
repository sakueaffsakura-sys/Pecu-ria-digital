<?php
declare(strict_types=1);

/**
 * Gráfico de barras em SVG, desenhado no servidor (sem biblioteca e sem script).
 * $series = ['rótulo' => valor] ou, com duas séries, ['rótulo' => [valorA, valorB]].
 */
function grafico_barras(array $series, callable $formato, array $legenda = [], string $titulo = ''): string
{
    if (!$series) return '<p class="vazio">Sem dados no período.</p>';
    $duplo = is_array(reset($series));
    $max = 0.0;
    foreach ($series as $v) $max = max($max, ...array_map('floatval', (array) $v));
    if ($max <= 0) $max = 1;

    $n = count($series);
    $larg = 640;
    $alt = 220;
    $base = 180;
    $topo = 34;
    $passo = $larg / $n;
    $barra = min(48, $passo * ($duplo ? 0.36 : 0.6));
    $svg = '<svg class="grafico" viewBox="-24 0 ' . ($larg + 48) . ' ' . $alt . '" role="img" aria-label="' . e($titulo) . '">';
    $svg .= '<line class="eixo" x1="0" y1="' . $base . '" x2="' . $larg . '" y2="' . $base . '"/>';
    $i = 0;
    foreach ($series as $rotulo => $v) {
        $vals = array_map('floatval', (array) $v);
        $centro = $passo * $i + $passo / 2;
        foreach ($vals as $k => $valor) {
            $h = ($base - $topo) * ($valor / $max);
            $x = $duplo ? $centro + ($k === 0 ? -$barra - 1 : 1) : $centro - $barra / 2;
            $classe = $k === 0 ? 'barra' : 'barra barra-2';
            $svg .= sprintf('<rect class="%s" x="%.1f" y="%.1f" width="%.1f" height="%.1f" rx="3"><title>%s: %s</title></rect>',
                $classe, $x, $base - $h, $barra, max($h, 0.5), e($rotulo), e($formato($valor)));
            if (!$duplo && $n <= 8 && $valor > 0) {
                $svg .= sprintf('<text class="valor" x="%.1f" y="%.1f" text-anchor="middle">%s</text>', $centro, $base - $h - 8, e($formato($valor)));
            }
        }
        if ($n <= 7 || $i % (int) ceil($n / 7) === 0) {
            $svg .= sprintf('<text class="rotulo" x="%.1f" y="%d" text-anchor="middle">%s</text>', $centro, $base + 28, e((string) $rotulo));
        }
        $i++;
    }
    $svg .= '</svg>';
    if ($legenda) {
        $svg .= '<p class="legenda"><span class="ponto"></span> ' . e($legenda[0]) . (isset($legenda[1]) ? ' <span class="ponto ponto-2"></span> ' . e($legenda[1]) : '') . '</p>';
    }
    return $svg;
}
