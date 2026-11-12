<?php
declare(strict_types=1);

// Porta de entrada única do site: o Nginx manda para cá todo endereço que não é arquivo (css, js, imagens).

// Servidor embutido do PHP (php -S): entrega os arquivos estáticos direto.
if (PHP_SAPI === 'cli-server') {
    $arquivo = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($arquivo !== __DIR__ . '/' && is_file($arquivo)) return false;
}

require __DIR__ . '/../app/iniciar.php';

$caminho = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
$partes = $caminho === '' ? [] : explode('/', $caminho);

try {
    rotear($partes);
} catch (Throwable $e) {
    error_log('[pecuaria] ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine());
    if (config('debug')) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo $e;
        exit;
    }
    pagina_erro(500, 'Algo deu errado do nosso lado. Tente de novo em instantes.');
}

function rotear(array $p): never
{
    $n = count($p);
    $a = $p[0] ?? '';
    $id = fn(int $i): int => isset($p[$i]) && ctype_digit($p[$i]) ? (int) $p[$i] : 0;

    match (true) {
        $n === 0 => usuario() ? redirecionar('painel') : tela('inicio', ['titulo' => 'Gestão do seu rebanho'], 'layout_publico'),
        $a === 'entrar' && $n === 1 => rota_entrar(),
        $a === 'cadastro' && $n === 1 => rota_cadastro(),
        $a === 'sair' && $n === 1 => rota_sair(),
        $a === 'painel' && $n === 1 => rota_painel(),
        $a === 'perfil' && $n === 1 => rota_perfil(),
        $a === 'relatorios' && $n === 1 => rota_relatorios(),
        $a === 'agenda' && $n === 1 => rota_agenda(),
        $a === 'importar' && $n === 1 => rota_importar(),
        $a === 'propriedades' && $n === 1 => rota_propriedades(),
        $a === 'propriedades' && $n === 2 && $p[1] === 'ativar' => rota_ativar_propriedade(),
        $a === 'propriedades' && $n === 2 && $id(1) > 0 => rota_propriedade($id(1)),
        $a === 'animal' && $n === 2 && $id(1) > 0 => rota_ficha_animal($id(1)),
        $a === 'admin' && $n === 1 => rota_admin(),
        $a === 'm' && $n === 2 => rota_lista($p[1]),
        $a === 'm' && $n === 3 && $p[2] === 'novo' => rota_form($p[1], null),
        $a === 'm' && $n === 3 && $p[2] === 'csv' => rota_csv($p[1]),
        $a === 'm' && $n === 3 && $id(2) > 0 => rota_form($p[1], $id(2)),
        $a === 'm' && $n === 4 && $id(2) > 0 && $p[3] === 'excluir' => rota_excluir($p[1], $id(2)),
        default => pagina_erro(404, 'Página não encontrada.'),
    };
}
