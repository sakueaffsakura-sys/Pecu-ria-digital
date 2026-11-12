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
        $arquivo = getenv('PECUARIA_CONFIG') ?: RAIZ . '/config/config.php';
        if (!is_file($arquivo)) {
            http_response_code(500);
            exit('Falta o arquivo config/config.php. Copie config/config.example.php e preencha.');
        }
        $cfg = require $arquivo;
    }
    return $chave === null ? $cfg : ($cfg[$chave] ?? null);
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

/** Insere e devolve o id. As colunas vêm sempre do código (nunca do formulário). */
function inserir(string $tabela, array $dados): int
{
    $cols = array_keys($dados);
    $sql = 'INSERT INTO ' . $tabela . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')';
    q($sql, $dados);
    return (int) db()->lastInsertId();
}

function atualizar(string $tabela, array $dados, string $where, array $p): int
{
    $sets = implode(', ', array_map(fn($c) => "$c = ?", array_keys($dados)));
    return q("UPDATE $tabela SET $sets WHERE $where", [...array_values($dados), ...$p])->rowCount();
}

function transacao(callable $fn): mixed
{
    $pdo = db();
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
    return 'R$ ' . num_br((float) $n, 2);
}

function mes_br(string $ym): string
{
    $nomes = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
    return $nomes[(int) substr($ym, 5, 2) - 1] . '/' . substr($ym, 2, 2);
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
    tela('erro', ['titulo' => 'Ops', 'mensagem' => $msg], usuario() ? 'layout' : 'layout_publico');
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
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
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
    $enviado = (string) ($_POST['_csrf'] ?? '');
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
    $n = (int) valor('SELECT COUNT(*) FROM tentativas_login WHERE chave = ? AND criado_em > ?', [$chave, $desde]);
    return $n < $max;
}

function registrar_tentativa(string $chave): void
{
    q('INSERT INTO tentativas_login (chave, criado_em) VALUES (?, ?)', [substr($chave, 0, 190), date('Y-m-d H:i:s')]);
    // Limpeza ocasional das tentativas antigas.
    if (random_int(1, 50) === 1) {
        q('DELETE FROM tentativas_login WHERE criado_em < ?', [date('Y-m-d H:i:s', time() - 86400)]);
    }
}

function registrar_atividade(string $acao, string $tabela, ?int $id): void
{
    try {
        q('INSERT INTO registro_atividades (propriedade_id, usuario_id, acao, tabela, registro_id, criado_em) VALUES (?, ?, ?, ?, ?, ?)',
            [propriedade()['id'] ?? null, usuario()['id'] ?? null, $acao, $tabela, $id, date('Y-m-d H:i:s')]);
    } catch (Throwable) {
        // O registro de atividade nunca impede o trabalho.
    }
}
