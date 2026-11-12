<?php
declare(strict_types=1);

function modulo_ou_404(string $slug): array
{
    $m = preg_match('/^[a-z_]+$/', $slug) ? modulo($slug) : null;
    if (!$m) pagina_erro(404, 'Página não encontrada.');
    return $m;
}

function rota_lista(string $slug): never
{
    exigir_propriedade();
    $m = modulo_ou_404($slug);
    $r = crud_listar($m, $_GET);
    $opcoesFiltro = [];
    foreach ($m['filtros'] ?? [] as $f) {
        $c = $m['campos'][$f];
        $opcoesFiltro[$f] = $c['tipo'] === 'ref' ? opcoes_ref($c['ref']) : $c['opcoes'];
    }
    tela('lista', ['titulo' => $m['titulo'], 'm' => $m, 'r' => $r, 'opcoesFiltro' => $opcoesFiltro]);
}

function rota_form(string $slug, ?int $id): never
{
    exigir_propriedade();
    $m = modulo_ou_404($slug);
    $linha = null;
    if ($id) {
        $linha = crud_buscar($m, $id);
        if (!$linha) pagina_erro(404, 'Registro não encontrado nesta propriedade.');
    }
    $travado = $linha && isset($m['travado']) ? $m['travado']($linha) : null;
    $erros = [];
    $valores = valores_iniciais($m, $linha, $_GET);
    $voltar = $_GET['voltar'] ?? $_POST['voltar'] ?? '';
    $voltar = is_string($voltar) && preg_match('#^/[a-z0-9/_-]*$#', $voltar) ? $voltar : '';

    if (eh_post()) {
        conferir_csrf();
        exigir_edicao();
        if ($travado) pagina_erro(403, $travado);
        $r = crud_salvar($m, $linha, $_POST);
        if (isset($r['_id'])) {
            aviso('ok', ucfirst($m['singular']) . ($id ? ' atualizad' : ' cadastrad') . g($m) . '.');
            if (!$id && isset($_POST['e_outro'])) redirecionar("m/$slug/novo", ['voltar' => $voltar]);
            if ($voltar) {
                header('Location: ' . $voltar, true, 303);
                exit;
            }
            if (!empty($m['ficha']) && !$id) redirecionar('animal/' . $r['_id']);
            redirecionar("m/$slug");
        }
        $erros = $r;
        foreach ($m['campos'] as $nome => $_) $valores[$nome] = (string) ($_POST[$nome] ?? '');
    }

    $listas = [];
    foreach (campos_do_form($m, !$linha) as $nome => $c) {
        if ($c['tipo'] === 'ref') $listas[$nome] = opcoes_ref($c['ref']);
    }
    $titulo = ($linha ? 'Editar ' : 'Nov' . g($m) . ' ') . $m['singular'];
    tela('form', compact('titulo', 'm', 'linha', 'valores', 'erros', 'listas', 'travado', 'voltar'));
}

function rota_excluir(string $slug, int $id): never
{
    exigir_propriedade();
    $m = modulo_ou_404($slug);
    if (!eh_post()) redirecionar("m/$slug/$id");
    conferir_csrf();
    exigir_edicao();
    $linha = crud_buscar($m, $id);
    if (!$linha) pagina_erro(404, 'Registro não encontrado nesta propriedade.');
    $erro = crud_excluir($m, $linha);
    if ($erro) {
        aviso('erro', $erro);
        redirecionar("m/$slug/$id");
    }
    aviso('ok', ucfirst($m['singular']) . ' excluíd' . g($m) . '.');
    redirecionar("m/$slug");
}

function rota_csv(string $slug): never
{
    exigir_propriedade();
    crud_csv(modulo_ou_404($slug), $_GET);
}
