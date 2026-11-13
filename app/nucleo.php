<?php
declare(strict_types=1);

// ---------------------------------------------------------------------------
// Configuração, banco, sessão e funções de apoio usadas em todo o site.
// ---------------------------------------------------------------------------

const RAIZ = __DIR__ . '/..';

function config(?string $chave = null): mixed
{
    static $cfg = null;
    if ($cfg === null) {
        // Ordem: caminho indicado no servidor (PECUARIA_ENV / PECUARIA_CONFIG), depois .env na raiz,
        // depois o formato antigo config/config.php.
        $env = getenv('PECUARIA_ENV') ?: null;
        $antigo = getenv('PECUARIA_CONFIG') ?: null;
        if (!$env && !$antigo) {
            if (is_file(RAIZ . '/.env')) $env = RAIZ . '/.env';
            elseif (is_file(RAIZ . '/config/config.php')) $antigo = RAIZ . '/config/config.php';
        }
        if ($env && is_file($env)) {
            $cfg = config_do_env(ler_env($env));
        } elseif ($antigo && is_file($antigo)) {
            $cfg = require $antigo;
        } else {
            http_response_code(500);
            exit('Falta o arquivo .env na pasta do site. Copie .env.example para .env e preencha os dados do banco (veja docs/CONFIGURACAO.md).');
        }
    }
    return $chave === null ? $cfg : ($cfg[$chave] ?? null);
}

/**
 * Lê um arquivo .env simples: CHAVE=valor, linhas com # são comentário,
 * valores podem vir entre aspas simples ou duplas. Variáveis de ambiente
 * do servidor com o mesmo nome têm prioridade sobre o arquivo.
 */
function ler_env(string $arquivo): array
{
    $vars = [];
    foreach (file($arquivo, FILE_IGNORE_NEW_LINES) ?: [] as $linha) {
        $linha = trim($linha);
        if ($linha === '' || $linha[0] === '#' || !str_contains($linha, '=')) continue;
        [$k, $v] = array_map('trim', explode('=', $linha, 2));
        if (str_starts_with($k, 'export ')) $k = trim(substr($k, 7));
        if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $k)) continue;
        if (strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'") && $v[-1] === $v[0]) {
            $aspas = $v[0];
            $v = substr($v, 1, -1);
            if ($aspas === '"') $v = strtr($v, ['\\n' => "\n", '\\"' => '"', '\\\\' => '\\']);
        } elseif (($p = strpos($v, ' #')) !== false) {
            $v = rtrim(substr($v, 0, $p)); // comentário no fim da linha
        }
        $vars[$k] = $v;
    }
    foreach ($vars as $k => $_) {
        $doServidor = getenv($k);
        if ($doServidor !== false && $doServidor !== '') $vars[$k] = $doServidor;
    }
    return $vars;
}

/** Converte as variáveis do .env no formato usado pelo resto do site. */
function config_do_env(array $e): array
{
    $sim = fn(string $k, bool $padrao = false) => isset($e[$k]) && $e[$k] !== '' ? in_array(strtolower($e[$k]), ['1', 'true', 'sim', 'yes', 'on'], true) : $padrao;
    $tipo = strtolower($e['DB_CONEXAO'] ?? 'mysql');
    if ($tipo === 'sqlite') {
        $dsn = 'sqlite:' . ($e['DB_BANCO'] ?? RAIZ . '/storage/banco.sqlite');
    } else {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $e['DB_HOST'] ?? '127.0.0.1', (int) ($e['DB_PORTA'] ?? 3306), $e['DB_BANCO'] ?? 'pecuaria');
        if (!empty($e['DB_SOCKET'])) $dsn = sprintf('mysql:unix_socket=%s;dbname=%s;charset=utf8mb4', $e['DB_SOCKET'], $e['DB_BANCO'] ?? 'pecuaria');
    }
    return [
        'nome_site' => $e['APP_NOME'] ?? 'Pecuária Digital',
        'url' => rtrim($e['APP_URL'] ?? 'http://localhost:8000', '/'),
        'debug' => $sim('APP_DEBUG'),
        'fuso' => $e['APP_FUSO'] ?? 'America/Sao_Paulo',
        'db' => ['dsn' => $dsn, 'usuario' => $e['DB_USUARIO'] ?? null, 'senha' => $e['DB_SENHA'] ?? null],
        'email' => [
            'host' => $e['MAIL_HOST'] ?? '', 'porta' => (int) ($e['MAIL_PORTA'] ?? 587), 'seguranca' => strtolower($e['MAIL_SEGURANCA'] ?? 'tls'),
            'usuario' => $e['MAIL_USUARIO'] ?? '', 'senha' => $e['MAIL_SENHA'] ?? '',
            'remetente' => $e['MAIL_REMETENTE'] ?? '', 'nome_remetente' => $e['MAIL_NOME_REMETENTE'] ?? ($e['APP_NOME'] ?? 'Pecuária Digital'),
        ],
        'mercado_pago' => ['token' => $e['MERCADOPAGO_ACCESS_TOKEN'] ?? '', 'chave_publica' => $e['MERCADOPAGO_PUBLIC_KEY'] ?? '', 'segredo_webhook' => $e['MERCADOPAGO_WEBHOOK_SECRET'] ?? ''],
        'ia' => ['chave' => $e['IA_CHAVE_API'] ?? '', 'modelo' => $e['IA_MODELO'] ?? '', 'url' => $e['IA_URL'] ?? ''],
    ];
}

// ----- Banco ---------------------------------------------------------------

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = config('db');
        $pdo = new PDO($c['dsn'], $c['usuario'] ?? null, $c['senha'] ?? null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        if (str_starts_with($c['dsn'], 'sqlite:')) {
            $pdo->exec('PRAGMA foreign_keys = ON');
        } else {
            // Mesmo fuso do PHP para CURRENT_TIMESTAMP.
            $pdo->exec("SET time_zone = '" . (new DateTime('now'))->format('P') . "'");
        }
    }
    return $pdo;
}

function q(string $sql, array $p = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute(array_values($p));
    return $st;
}

function todos(string $sql, array $p = []): array
{
    return q($sql, $p)->fetchAll();
}

function um(string $sql, array $p = []): ?array
{
    $r = q($sql, $p)->fetch();
    return $r === false ? null : $r;
}

function valor(string $sql, array $p = []): mixed
{
    $r = q($sql, $p)->fetchColumn();
    return $r === false ? null : $r;
}

/** Insere e devolve o id (gera um UUID quando a tabela usa UUID). As colunas vêm sempre do código. */
function inserir(string $tabela, array $dados, bool $gerarId = true): string
{
    if ($gerarId && !array_key_exists('id', $dados)) $dados = ['id' => uuid()] + $dados;
    $cols = array_keys($dados);
    $sql = 'INSERT INTO ' . $tabela . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')';
    q($sql, $dados);
    return $gerarId ? (string) $dados['id'] : (string) db()->lastInsertId();
}

function atualizar(string $tabela, array $dados, string $where, array $p): int
{
    $sets = implode(', ', array_map(fn($c) => "$c = ?", array_keys($dados)));
    return q("UPDATE $tabela SET $sets WHERE $where", [...array_values($dados), ...$p])->rowCount();
}

/** UUID v4 (mesmo formato dos identificadores do sistema original). */
function uuid(): string
{
    $b = random_bytes(16);
    $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
    $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
    $h = bin2hex($b);
    return substr($h, 0, 8) . '-' . substr($h, 8, 4) . '-' . substr($h, 12, 4) . '-' . substr($h, 16, 4) . '-' . substr($h, 20);
}

function eh_uuid(mixed $v): bool
{
    return is_string($v) && (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $v);
}

function agora(): string
{
    return date('Y-m-d H:i:s');
}

function transacao(callable $fn): mixed
{
    $pdo = db();
    if ($pdo->inTransaction()) return $fn();
    $pdo->beginTransaction();
    try {
        $r = $fn();
        $pdo->commit();
        return $r;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** Erro de chave única ou de ligação entre tabelas? */
function erro_de_integridade(Throwable $e): ?string
{
    if (!$e instanceof PDOException) return null;
    $m = $e->getMessage();
    if ($e->getCode() === '23000' || str_contains($m, 'constraint') || str_contains($m, 'UNIQUE')) {
        if (stripos($m, 'Duplicate') !== false || stripos($m, 'UNIQUE') !== false) return 'duplicado';
        return 'ligado';
    }
    return null;
}

// ----- Datas e números (padrão brasileiro) ---------------------------------

function hoje(): string
{
    return date('Y-m-d');
}

function somar_dias(string $ymd, int $dias): string
{
    return (new DateTimeImmutable($ymd))->modify(($dias >= 0 ? '+' : '') . $dias . ' days')->format('Y-m-d');
}

function dias_entre(string $de, string $ate): int
{
    return (int) (new DateTimeImmutable($de))->diff(new DateTimeImmutable($ate))->format('%r%a');
}

/** Aceita 2026-11-12 ou 12/11/2026. Devolve Y-m-d ou null. */
function ler_data(?string $s): ?string
{
    $s = trim((string) $s);
    if ($s === '') return null;
    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $s, $m)) {
        $s = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    }
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) return null;
    return $s;
}

/** Aceita "1.234,56", "1234,56" ou "1234.56". */
function ler_decimal(?string $s): ?float
{
    $s = trim(str_replace(['R$', ' '], '', (string) $s));
    if ($s === '') return null;
    if (str_contains($s, ',')) $s = str_replace(['.', ','], ['', '.'], $s);
    elseif (preg_match('/^-?\d{1,3}(\.\d{3})+$/', $s)) $s = str_replace('.', '', $s); // 1.000 = mil
    return is_numeric($s) ? (float) $s : null;
}

function data_br(?string $ymd): string
{
    if (!$ymd) return '—';
    $d = substr($ymd, 0, 10);
    [$a, $m, $dia] = explode('-', $d) + [null, null, null];
    return $dia ? "$dia/$m/$a" : $ymd;
}

function num_br(float|int|string|null $n, int $casas = 0): string
{
    return $n === null || $n === '' ? '—' : number_format((float) $n, $casas, ',', '.');
}

function dinheiro(float|int|string|null $n): string
{
    $v = (float) $n;
    return ($v < 0 ? '-' : '') . 'R$ ' . num_br(abs($v), 2);
}

function mes_br(string $ym): string
{
    $nomes = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
    return $nomes[(int) substr($ym, 5, 2) - 1] . '/' . substr($ym, 2, 2);
}

function mes_curto(string $ym): string
{
    return ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'][(int) substr($ym, 5, 2) - 1];
}

/** "quarta-feira, 11 de novembro" */
function data_extenso(string $ymd, bool $diaSemana = true): string
{
    $t = strtotime($ymd);
    $sem = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'][(int) date('w', $t)];
    $mes = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'][(int) date('n', $t) - 1];
    return ($diaSemana ? $sem . ', ' : '') . (int) date('j', $t) . ' de ' . $mes;
}

function nome_mes(int $m): string
{
    return ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'][$m - 1];
}

/** Número curto: 7,2 mil · 1,5 mi */
function compacto(float $v): string
{
    $a = abs($v);
    if ($a >= 1e6) return num_br($v / 1e6, fmod($v / 1e6, 1) == 0.0 ? 0 : 1) . ' mi';
    if ($a >= 1e3) return num_br($v / 1e3, fmod($v / 1e3, 1) == 0.0 ? 0 : 1) . ' mil';
    return num_br($v, fmod($v, 1) == 0.0 ? 0 : 1);
}

function plural(int $n, string $um, ?string $varios = null): string
{
    return num_br($n) . ' ' . ($n === 1 ? $um : ($varios ?? $um . 's'));
}

/** Texto para a tela: códigos do banco ("em_andamento") perdem o "_"; o que a pessoa digitou fica igual. */
function bonito(mixed $v): string
{
    if ($v === null || $v === '') return '—';
    $t = (string) $v;
    return preg_match('/^[a-z0-9]+(_[a-z0-9]+)+$/', $t) ? str_replace('_', ' ', $t) : $t;
}

function idade(?string $nascimento): string
{
    if (!$nascimento) return '—';
    $d = (new DateTimeImmutable($nascimento))->diff(new DateTimeImmutable(hoje()));
    if ($d->invert) return '—';
    if ($d->y >= 2) return $d->y . ' anos';
    $meses = $d->y * 12 + $d->m;
    if ($meses >= 1) return $meses . ($meses === 1 ? ' mês' : ' meses');
    return $d->d . ' dias';
}

// ----- Saída HTML ----------------------------------------------------------

function e(mixed $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $caminho = '', array $params = []): string
{
    $u = '/' . ltrim($caminho, '/');
    $params = array_filter($params, fn($v) => $v !== null && $v !== '');
    return $params ? $u . '?' . http_build_query($params) : $u;
}

function redirecionar(string $caminho, array $params = []): never
{
    header('Location: ' . url($caminho, $params), true, 303);
    exit;
}

function aviso(string $tipo, string $msg): void
{
    $_SESSION['avisos'][] = ['tipo' => $tipo, 'msg' => $msg];
}

function pegar_avisos(): array
{
    $a = $_SESSION['avisos'] ?? [];
    unset($_SESSION['avisos']);
    return $a;
}

/** Desenha uma tela dentro do layout. */
function tela(string $nome, array $vars = [], string $layout = 'layout'): never
{
    extract($vars, EXTR_SKIP);
    ob_start();
    require RAIZ . "/views/$nome.php";
    $conteudo = ob_get_clean();
    require RAIZ . "/views/$layout.php";
    exit;
}

function pagina_erro(int $codigo, string $msg): never
{
    http_response_code($codigo);
    if (eh_pedido_json()) responder_json(['erro' => $msg], $codigo);
    tela('erro', ['titulo' => $codigo === 404 ? 'Página não encontrada' : 'Não deu certo', 'mensagem' => $msg], 'layout_simples');
}

function eh_pedido_json(): bool
{
    return str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') || str_starts_with((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/api/');
}

function responder_json(mixed $dados, int $codigo = 200): never
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ----- Sessão e proteção ---------------------------------------------------

function iniciar_sessao(): void
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || str_starts_with((string) config('url'), 'https://');
    session_name('pecuaria');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', '28800');
    session_start();
}

function cabecalhos_seguranca(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; style-src 'self'; font-src 'self'; script-src 'self'; connect-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'; object-src 'none'");
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function csrf_campo(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function conferir_csrf(): void
{
    $enviado = (string) ($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? '');
    if ($enviado === '' || !hash_equals(csrf_token(), $enviado)) {
        pagina_erro(419, 'A página ficou aberta por muito tempo. Volte, recarregue e tente de novo.');
    }
}

function eh_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function ip_cliente(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'desconhecido'), 0, 45);
}

/** Limite de tentativas guardado no banco (vale para todos os processos do PHP). */
function dentro_do_limite(string $chave, int $max, int $minutos): bool
{
    $desde = date('Y-m-d H:i:s', time() - $minutos * 60);
    $n = (int) valor('SELECT COUNT(*) FROM rate_limits WHERE chave = ? AND criado_em > ?', [$chave, $desde]);
    return $n < $max;
}

function registrar_tentativa(string $chave): void
{
    q('INSERT INTO rate_limits (chave, criado_em) VALUES (?, ?)', [substr($chave, 0, 190), date('Y-m-d H:i:s')]);
    // Limpeza ocasional das tentativas antigas.
    if (random_int(1, 50) === 1) {
        q('DELETE FROM rate_limits WHERE criado_em < ?', [date('Y-m-d H:i:s', time() - 86400)]);
    }
}
