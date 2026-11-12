<?php
declare(strict_types=1);

// Porta de entrada única: o Nginx manda para cá todo endereço que não é arquivo (css, js, imagens).

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
    match (true) {
        $n === 0 => rota_entrar(),
        $a === 'sair' && $n === 1 => rota_sair(),
        $a === 'app' && $n === 1 => redirecionar('app/inicio'),
        $a === 'app' && $n === 2 => rota_app($p[1]),
        $a === 'propriedade' && $n === 2 && $p[1] === 'trocar' => rota_trocar_propriedade(),
        $a === 'propriedade' && $n === 2 && $p[1] === 'nova' => rota_nova_propriedade(),
        $a === 'avisos' && $n === 2 && $p[1] === 'visto' => rota_aviso_visto(),
        $a === 'api' && $n === 2 && $p[1] === 'busca' => rota_api_busca(),
        $a === 'api' && $n === 3 && $p[1] === 'clima' && in_array($p[2], ['lugares', 'agora'], true) => rota_api_clima($p[2]),
        $a === 'agenda.ics' && $n === 1 => rota_agenda_ics(),
        default => pagina_erro(404, 'O endereço pode estar errado. Volte ao Início e use o menu.'),
    };
}
