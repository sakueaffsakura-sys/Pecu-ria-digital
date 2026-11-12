<?php
declare(strict_types=1);

// Pedaços de tela reaproveitados (mesmos do original: brinco, selos, títulos, abas, paginação, menu).

/** Ícone da biblioteca Lucide (ou os desenhos próprios de rebanho e bezerro). */
function icone(string $nome, string $classe = 'ic'): string
{
    if ($nome === 'rebanho' || $nome === 'bezerro') {
        return '<span aria-hidden="true" class="' . e($classe) . ' ic-mascara ic-' . $nome . '"></span>';
    }
    return '<svg class="' . e($classe) . '" aria-hidden="true" focusable="false"><use href="/assets/icones.svg?v=1#' . e($nome) . '"/></svg>';
}

/** O brinco amarelo: "grande" nos números do Início, "pequeno" no número do animal nas listas e na ficha. */
function brinco(string $valor, string $tamanho = 'pequeno', ?string $legenda = null, ?string $rotulo = null, int $atraso = 0): string
{
    $grande = $tamanho === 'grande';
    $longo = !$grande && mb_strlen($valor) > 8;
    $texto = $longo ? '…' . mb_substr($valor, -6) : $valor;
    $n = mb_strlen($texto);
    $classeTexto = $grande ? ($n > 6 ? 't-g3' : ($n > 4 ? 't-g2' : 't-g1')) : ($n > 6 ? 't-p3' : ($n > 4 ? 't-p2' : 't-p1'));
    $html = '<span class="brinco-caixa"><span class="brinco brinco-' . ($grande ? 'grande' : 'pequeno') . ($grande ? ' brinco-balanca atraso-' . $atraso : '') . '"'
        . ($rotulo ? ' role="img" aria-label="' . e($rotulo) . '"' : '') . ($longo ? ' title="' . e($valor) . '"' : '') . '>'
        . '<svg aria-hidden="true" viewBox="0 0 100 116" preserveAspectRatio="none"><path d="M36 7Q36 1 42 1H58Q64 1 64 7V17Q64 23 71 25L89 30Q98 33 98 42V103Q98 115 86 115H14Q2 115 2 103V42Q2 33 11 30L29 25Q36 23 36 17Z" class="brinco-corpo"/><ellipse cx="50" cy="11" rx="5.5" ry="4.8" class="brinco-furo"/></svg>'
        . '<span class="brinco-numero ' . $classeTexto . '"' . ($rotulo ? ' aria-hidden="true"' : '') . '>' . e($texto) . '</span></span>';
    if ($legenda !== null) $html .= '<span class="brinco-legenda">' . e($legenda) . '</span>';
    return $html . '</span>';
}

/** Selo de situação: ícone + texto (nunca só cor). */
function selo(string $valor): string
{
    [$rotulo, $cor, $ic] = selos()[$valor] ?? [bonito($valor), 'cinza', 'info'];
    return '<span class="selo selo-' . $cor . '">' . icone($ic, 'ic-selo') . e($rotulo) . '</span>';
}

function titulo_pagina(string $titulo, string $descricao, string $acoes = ''): string
{
    return '<div class="cabeca-pagina"><div class="cabeca-texto"><h1 class="titulo-pagina">' . e($titulo) . '</h1><p class="descricao">' . e($descricao) . '</p></div>'
        . ($acoes ? '<div class="cabeca-acoes">' . $acoes . '</div>' : '') . '</div>';
}

function botao_link(string $href, string $texto, string $ic = '', string $variante = 'primario', array $extra = []): string
{
    $attrs = '';
    foreach ($extra as $k => $v) $attrs .= ' ' . e($k) . '="' . e($v) . '"';
    return '<a class="botao botao-' . $variante . '" href="' . e($href) . '"' . $attrs . '>' . ($ic ? icone($ic) : '') . '<span>' . e($texto) . '</span></a>';
}

/** Estado vazio: uma frase do que falta e, se fizer sentido, UM botão. */
function vazio(string $mensagem, ?string $rotulo = null, ?string $href = null): string
{
    return '<div class="vazio"><div><p>' . e($mensagem) . '</p>' . ($rotulo && $href ? botao_link($href, $rotulo, 'plus') : '') . '</div></div>';
}

/** Abas simples (Aplicações / Tipos de vacina, Compras / Vendas…). */
function abas(array $opcoes, string $atual, string $rotulo): string
{
    $h = '<nav class="abas" aria-label="' . e($rotulo) . '">';
    foreach ($opcoes as $chave => $texto) {
        $h .= '<a href="' . e(url("app/$chave")) . '"' . ($chave === $atual ? ' aria-current="page"' : '') . '>' . e($texto) . '</a>';
    }
    return $h . '</nav>';
}

/** Rodapé das listas: "1–25 de 48", páginas e tamanho da página. */
function paginacao(int $pagina, int $ultima, int $total, int $por, array $get, string $caminho): string
{
    if ($total === 0) return '';
    $de = ($pagina - 1) * $por + 1;
    $ate = min($total, $pagina * $por);
    $link = fn(int $n) => e($caminho . '?' . http_build_query(array_filter(['pagina' => $n] + $get, fn($v) => $v !== '' && $v !== null)));
    $h = '<div class="paginacao"><span class="pag-resumo">' . num_br($de) . '–' . num_br($ate) . ' de <strong>' . num_br($total) . '</strong></span><div class="pag-botoes">';
    if ($ultima > 1) {
        $btn = fn(int $n, string $ic, string $r, bool $off) => $off ? '<span class="botao-icone desligado" aria-hidden="true">' . icone($ic) . '</span>'
            : '<a class="botao-icone" href="' . $link($n) . '" aria-label="' . $r . '">' . icone($ic) . '</a>';
        $h .= $btn(1, 'chevrons-left', 'Primeira página', $pagina <= 1) . $btn($pagina - 1, 'chevron-left', 'Página anterior', $pagina <= 1);
        $ini = max(1, min($pagina - 2, $ultima - 4));
        for ($n = $ini; $n <= min($ultima, $ini + 4); $n++) {
            $h .= $n === $pagina ? '<span class="pag-num atual" aria-current="page">' . $n . '</span>' : '<a class="pag-num" href="' . $link($n) . '">' . $n . '</a>';
        }
        $h .= $btn($pagina + 1, 'chevron-right', 'Próxima página', $pagina >= $ultima) . $btn($ultima, 'chevrons-right', 'Última página', $pagina >= $ultima);
    }
    $h .= '<form method="get" action="' . e($caminho) . '" class="pag-tamanho">';
    foreach ($get as $k => $v) if ($k !== 'por' && $k !== 'pagina' && is_string($v) && $v !== '') $h .= '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
    $h .= '<label class="so-leitor" for="pag-por">Registros por página</label><select id="pag-por" name="por" data-enviar>';
    foreach (TAMANHOS_PAGINA as $t) $h .= '<option value="' . $t . '"' . ($t === $por ? ' selected' : '') . '>' . $t . ' por página</option>';
    return $h . '</select><noscript><button class="botao botao-contorno">Ver</button></noscript></form></div></div>';
}

/** Menu do site, agrupado como o produtor pensa a fazenda (igual ao original). */
function grupos_menu(): array
{
    return [
        ['rotulo' => null, 'itens' => [
            ['inicio', 'Início', 'layout-dashboard', 'O resumo da fazenda hoje.'],
            ['agenda', 'Agenda', 'calendar-days', 'Vacinas, partos e pesagens da semana e do mês.'],
        ]],
        ['rotulo' => 'Rebanho', 'itens' => [
            ['rebanho', 'Meu rebanho', 'rebanho', 'Todos os animais, com brinco, lote e situação.'],
            ['lotes', 'Lotes', 'warehouse', 'Grupos de animais por pasto ou finalidade.'],
            ['bezerros', 'Evolução dos bezerros', 'bezerro', 'Pesagens e ganho de peso dos animais jovens.'],
        ]],
        ['rotulo' => 'Sanidade', 'itens' => [['vacinacao', 'Vacinação', 'syringe', 'Aplicações, próximas doses e vacinas atrasadas.']]],
        ['rotulo' => 'Reprodução', 'itens' => [
            ['inseminacao', 'Inseminação', 'flask-conical', 'Inseminações e previsão de parto.'],
            ['nascimentos', 'Nascimentos', 'heart-pulse', 'Quem nasceu, de qual vaca e quando.'],
        ]],
        ['rotulo' => 'Produção', 'itens' => [['producao', 'Leite e anotações', 'milk', 'Litros por vaca e observações da ordenha.']]],
        ['rotulo' => 'Dinheiro', 'itens' => [
            ['financeiro', 'Lucros e investimentos', 'circle-dollar-sign', 'Receitas, despesas e investimentos lançados à mão.'],
            ['comercial', 'Compra e venda', 'shopping-cart', 'O que entrou e saiu; o valor vai sozinho para o financeiro.'],
            ['relatorios', 'Relatórios', 'scroll-text', 'Totais e listas por mês ou ano.'],
            ['indicadores', 'Indicadores', 'chart-line', 'Prenhez, partos, ganho de peso e custo por litro.'],
        ]],
        ['rotulo' => 'Mais', 'itens' => [
            ['ferramentas', 'Calculadoras e clima', 'calculator', 'Dose pelo peso, ração, calagem, lotação e a previsão do tempo.'],
            ['biblioteca', 'Biblioteca', 'book-open-text', 'eBooks para assinantes.'],
            ['assinatura', 'Assinatura', 'crown', 'Seu plano, pagamentos e benefícios.'],
            ['aplicativo', 'Use no celular', 'smartphone', 'O aplicativo Android, que funciona sem internet.'],
            ['ajuda', 'Ajuda', 'life-buoy', 'Perguntas frequentes e falar com o suporte.'],
        ]],
        ['rotulo' => 'Conta', 'oculto' => true, 'itens' => [
            ['perfil', 'Meu perfil', 'user-round', 'Seus dados e a foto do perfil.'],
            ['perfil-propriedade', 'Dados da propriedade', 'warehouse', 'Nome, cidade, logo, banner e contato da fazenda.'],
            ['seguranca', 'Senha e segurança', 'key-round', 'Senha, duas etapas, aparelhos conectados e seus dados.'],
            ['diagnostico', 'Diagnóstico dos dados', 'gauge', 'Conferência técnica dos dados da propriedade.'],
        ]],
    ];
}

/** Seções que já existem nesta versão (as outras não aparecem no menu nem na busca). */
function secoes_prontas(): array
{
    return ['inicio', 'agenda', 'rebanho', 'lotes', 'bezerros', 'vacinacao', 'inseminacao', 'nascimentos', 'producao', 'financeiro', 'comercial', 'perfil-propriedade'];
}

function item_menu(string $chave): ?array
{
    foreach (grupos_menu() as $g) foreach ($g['itens'] as $i) if ($i[0] === $chave) return $i;
    return null;
}

/** Atalhos do botão "Lançar" (barra de baixo do celular). */
function lancamentos(): array
{
    return [
        ['Leite do dia', 'Litros de uma vaca', 'producao', 'milk'],
        ['Animal', 'Cadastrar no rebanho', 'rebanho', 'rebanho'],
        ['Vacina', 'Registrar aplicação', 'vacinacao', 'syringe'],
        ['Pesagem', 'Peso de um animal', 'bezerros', 'scale'],
        ['Anotação', 'Observação da ordenha', 'anotacoes', 'notebook-pen'],
    ];
}
