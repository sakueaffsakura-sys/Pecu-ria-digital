<?php
declare(strict_types=1);

// Pedaços de HTML reaproveitados pelas telas.

function rotulo_erro(string $nome, ?string $erro): string
{
    return $erro ? '<p class="erro-campo" id="erro-' . e($nome) . '">' . e($erro) . '</p>' : '';
}

/** Campo simples de formulário (telas fora do CRUD). */
function entrada(string $nome, string $rotulo, mixed $valor, ?string $erro = null, array $attrs = []): string
{
    $tipo = $attrs['type'] ?? 'text';
    unset($attrs['type']);
    $extra = '';
    foreach ($attrs as $k => $v) $extra .= $v === true ? ' ' . e($k) : ' ' . e($k) . '="' . e($v) . '"';
    if ($erro) $extra .= ' aria-invalid="true" aria-describedby="erro-' . e($nome) . '"';
    return '<div class="campo"><label for="c-' . e($nome) . '">' . e($rotulo) . '</label>'
        . '<input id="c-' . e($nome) . '" name="' . e($nome) . '" type="' . e($tipo) . '" value="' . e($valor) . '"' . $extra . '>'
        . rotulo_erro($nome, $erro) . '</div>';
}

/** Campo de um módulo do CRUD. */
function campo_modulo(string $nome, array $c, mixed $valor, ?string $erro, array $listas): string
{
    $id = 'c-' . $nome;
    $req = !empty($c['obrigatorio']) ? ' required' : '';
    $inv = $erro ? ' aria-invalid="true" aria-describedby="erro-' . e($nome) . '"' : '';
    $html = '<div class="campo' . ($c['tipo'] === 'area' ? ' campo-largo' : '') . '"><label for="' . e($id) . '">' . e($c['rotulo'])
        . (!empty($c['obrigatorio']) ? ' <span class="obrig" aria-hidden="true">*</span>' : '') . '</label>';
    $valor = (string) $valor;
    switch ($c['tipo']) {
        case 'area':
            $html .= '<textarea id="' . e($id) . '" name="' . e($nome) . '" rows="4"' . $req . $inv . '>' . e($valor) . '</textarea>';
            break;
        case 'opcao':
            $html .= '<select id="' . e($id) . '" name="' . e($nome) . '"' . $req . $inv . '><option value="">Escolha…</option>';
            foreach ($c['opcoes'] as $k => $r) $html .= '<option value="' . e($k) . '"' . ((string) $k === $valor ? ' selected' : '') . '>' . e($r) . '</option>';
            $html .= '</select>';
            break;
        case 'ref':
            $html .= '<select id="' . e($id) . '" name="' . e($nome) . '"' . $req . $inv . '><option value="">' . (empty($c['obrigatorio']) ? 'Nenhum' : 'Escolha…') . '</option>';
            foreach ($listas[$nome] ?? [] as $k => $r) $html .= '<option value="' . e($k) . '"' . ((string) $k === $valor ? ' selected' : '') . '>' . e($r) . '</option>';
            $html .= '</select>';
            if (empty($listas[$nome])) $html .= '<p class="ajuda">Nada cadastrado ainda. <a href="' . e(url('m/' . $c['ref'] . '/novo')) . '">Cadastrar</a></p>';
            break;
        case 'animal':
            $lista = !empty($c['sexo']) ? 'lista-animais-' . $c['sexo'] : 'lista-animais';
            $html .= '<input id="' . e($id) . '" name="' . e($nome) . '" type="text" list="' . e($lista) . '" autocomplete="off" inputmode="text" maxlength="40" value="' . e($valor) . '" placeholder="Digite o brinco"' . $req . $inv . '>';
            break;
        case 'data':
            $html .= '<input id="' . e($id) . '" name="' . e($nome) . '" type="date" value="' . e($valor) . '"' . (!empty($c['nao_futura']) ? ' max="' . hoje() . '"' : '') . $req . $inv . '>';
            break;
        case 'inteiro':
            $html .= '<input id="' . e($id) . '" name="' . e($nome) . '" type="number" step="1" inputmode="numeric" value="' . e($valor) . '"'
                . (isset($c['min']) ? ' min="' . e($c['min']) . '"' : '') . (isset($c['max']) ? ' max="' . e($c['max']) . '"' : '') . $req . $inv . '>';
            break;
        case 'decimal':
        case 'dinheiro':
            $html .= '<input id="' . e($id) . '" name="' . e($nome) . '" type="text" inputmode="decimal" value="' . e($valor) . '" placeholder="' . ($c['tipo'] === 'dinheiro' ? '0,00' : '0') . '"' . $req . $inv . '>';
            break;
        default:
            $lista = !empty($c['sugestoes']) ? ' list="sug-' . e($nome) . '"' : '';
            $html .= '<input id="' . e($id) . '" name="' . e($nome) . '" type="text" value="' . e($valor) . '"' . (isset($c['max']) ? ' maxlength="' . (int) $c['max'] . '"' : '') . $lista . $req . $inv . '>';
            if ($lista) {
                $html .= '<datalist id="sug-' . e($nome) . '">';
                foreach ($c['sugestoes'] as $s) $html .= '<option value="' . e($s) . '">';
                $html .= '</datalist>';
            }
    }
    if (!empty($c['ajuda'])) $html .= '<p class="ajuda">' . e($c['ajuda']) . '</p>';
    return $html . rotulo_erro($nome, $erro) . '</div>';
}

function lista_brincos(string $id, ?string $sexo): string
{
    $html = '<datalist id="' . e($id) . '">';
    foreach (brincos($sexo) as $a) $html .= '<option value="' . e($a['brinco']) . '">' . e($a['nome'] ?? '') . '</option>';
    return $html . '</datalist>';
}

function selo(string $texto, string $tipo = ''): string
{
    return '<span class="selo' . ($tipo ? ' selo-' . e($tipo) : '') . '">' . e($texto) . '</span>';
}

function paginacao(int $pagina, int $total, array $get): string
{
    $paginas = (int) ceil($total / POR_PAGINA);
    if ($paginas <= 1) return '';
    $caminho = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $link = fn(int $n) => e($caminho . '?' . http_build_query(['pagina' => $n] + $get));
    $html = '<nav class="paginacao" aria-label="Páginas">';
    if ($pagina > 1) $html .= '<a class="botao botao-leve" href="' . $link($pagina - 1) . '">← Anterior</a>';
    $html .= '<span>Página ' . $pagina . ' de ' . $paginas . '</span>';
    if ($pagina < $paginas) $html .= '<a class="botao botao-leve" href="' . $link($pagina + 1) . '">Próxima →</a>';
    return $html . '</nav>';
}

/** Itens do menu lateral. */
function menu(): array
{
    return [
        '' => [['painel', 'Início'], ['agenda', 'Agenda']],
        'Rebanho' => [['m/animais', 'Animais'], ['m/lotes', 'Lotes'], ['m/pesagens', 'Pesagens'], ['importar', 'Importar planilha']],
        'Sanidade' => [['m/vacinacoes', 'Vacinações'], ['m/tipos_vacinas', 'Vacinas e remédios']],
        'Reprodução' => [['m/inseminacoes', 'Inseminações'], ['m/nascimentos', 'Nascimentos']],
        'Leite' => [['m/producao_leite', 'Produção de leite']],
        'Financeiro' => [['m/movimentacoes', 'Entradas e saídas'], ['m/compras', 'Compras'], ['m/vendas', 'Vendas']],
        'Geral' => [['m/anotacoes', 'Anotações'], ['relatorios', 'Relatórios'], ['propriedades', 'Propriedades']],
    ];
}
