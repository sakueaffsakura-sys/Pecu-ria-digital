<?php
declare(strict_types=1);

/**
 * Confere se o servidor está pronto para o Pecuária Digital.
 *
 *   php scripts/verificar.php
 *
 * Mostra ✔ (certo), ! (atenção) ou ✘ (precisa corrigir) para cada item:
 * versão do PHP, extensões, arquivo .env, conexão com o banco, tabelas,
 * pasta storage e e-mail. Sai com código 1 se algo precisar ser corrigido.
 */

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$raiz = dirname(__DIR__);
$falhas = 0;
$atencao = 0;
$cor = stream_isatty(STDOUT);
$linha = function (string $tipo, string $texto, string $dica = '') use (&$falhas, &$atencao, $cor): void {
    [$simbolo, $c] = match ($tipo) { 'ok' => ['✔', '32'], 'aviso' => ['!', '33'], default => ['✘', '31'] };
    if ($tipo === 'erro') $falhas++;
    if ($tipo === 'aviso') $atencao++;
    echo ($cor ? "\033[{$c}m$simbolo\033[0m" : $simbolo) . " $texto\n";
    if ($dica !== '') echo "    → $dica\n";
};
$titulo = fn(string $t) => print("\n" . ($cor ? "\033[1m$t\033[0m" : $t) . "\n");

$titulo('PHP');
PHP_VERSION_ID >= 80100
    ? $linha('ok', 'PHP ' . PHP_VERSION)
    : $linha('erro', 'PHP ' . PHP_VERSION . ' é antigo demais', 'Precisa de PHP 8.1 ou mais novo (Ubuntu 22.04+ ou Debian 12).');
$extensoes = [
    'pdo_mysql' => ['erro', 'apt install php-mysql'],
    'mbstring' => ['erro', 'apt install php-mbstring'],
    'curl' => ['erro', 'apt install php-curl (usado pela previsão do tempo e pelo Mercado Pago)'],
    'openssl' => ['erro', 'vem junto com o PHP; reinstale o php-cli'],
    'json' => ['erro', 'vem junto com o PHP'],
    'fileinfo' => ['erro', 'apt install php-common (confere o tipo das imagens enviadas)'],
    'gd' => ['aviso', 'apt install php-gd (reduz e converte as fotos de perfil, logo e banner)'],
    'zip' => ['aviso', 'apt install php-zip (arquivo "Baixar meus dados")'],
];
foreach ($extensoes as $ext => [$nivel, $dica]) {
    extension_loaded($ext) ? $linha('ok', "Extensão $ext") : $linha($nivel, "Falta a extensão $ext", $dica);
}
foreach (['upload_max_filesize' => 3, 'post_max_size' => 4] as $diretiva => $minMb) {
    $v = ini_get($diretiva) ?: '0';
    $mb = (int) $v * match (strtoupper(substr($v, -1))) { 'G' => 1024, 'K' => 1 / 1024, default => 1 };
    $mb >= $minMb ? $linha('ok', "$diretiva = $v") : $linha('aviso', "$diretiva = $v (recomendado {$minMb}M)", 'O deploy/instalar.sh ajusta isso no PHP-FPM.');
}

$titulo('Configuração (.env)');
$env = getenv('PECUARIA_ENV') ?: "$raiz/.env";
if (!is_file($env) && is_file("$raiz/config/config.php")) {
    $linha('aviso', 'Usando o formato antigo config/config.php', 'Funciona, mas prefira o .env: cp .env.example .env e copie os dados do banco.');
} elseif (!is_file($env)) {
    $linha('erro', 'Arquivo .env não encontrado', 'cp .env.example .env e preencha o bloco BANCO DE DADOS (docs/CONFIGURACAO.md).');
} else {
    $linha('ok', ".env encontrado ($env)");
    $perm = fileperms($env) & 0777;
    ($perm & 0007)
        ? $linha('aviso', sprintf('.env pode ser lido por qualquer usuário do servidor (permissão %o)', $perm), "chmod 640 $env && chown root:www-data $env")
        : $linha('ok', sprintf('Permissão do .env: %o', $perm));
}

require "$raiz/app/nucleo.php";
ob_start();
$cfg = null;
try { $cfg = config(); } catch (Throwable) {}
ob_end_clean();
if (!is_array($cfg)) {
    $linha('erro', 'Não foi possível ler a configuração');
} else {
    $url = (string) ($cfg['url'] ?? '');
    if (!preg_match('#^https?://[^/]+#', $url)) $linha('erro', "APP_URL inválido: \"$url\"", 'Use o endereço completo, ex.: https://pecuariadigital.com.br');
    elseif (str_contains($url, 'seu-dominio')) $linha('aviso', "APP_URL ainda é o exemplo ($url)", 'Troque pelo seu domínio ou pelo IP da VPS.');
    elseif (str_starts_with($url, 'http://') && !preg_match('#//(localhost|127\.|\d+\.\d+\.\d+\.\d+)#', $url)) $linha('aviso', "APP_URL sem HTTPS ($url)", 'Rode o certbot (o instalar.sh faz isso) e troque para https://.');
    else $linha('ok', "APP_URL = $url");
    empty($cfg['debug']) ? $linha('ok', 'APP_DEBUG desligado') : $linha('aviso', 'APP_DEBUG=true', 'Em produção deixe false: com true, detalhes de erro aparecem para os visitantes.');
    try { new DateTimeZone((string) $cfg['fuso']); $linha('ok', "Fuso: {$cfg['fuso']}"); }
    catch (Throwable) { $linha('erro', "APP_FUSO inválido: {$cfg['fuso']}", 'Use, por exemplo, America/Sao_Paulo ou America/Manaus.'); }
    if (($cfg['db']['senha'] ?? '') === 'troque-esta-senha') $linha('erro', 'DB_SENHA ainda é a do exemplo', 'Coloque a senha do usuário do banco (veja docs/BANCO-DE-DADOS.md).');
}

$titulo('Banco de dados');
$pdo = null;
if (is_array($cfg)) {
    try {
        $pdo = db();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $versao = (string) $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
        $linha('ok', "Conectado ($driver $versao)");
        if ($driver === 'mysql') {
            $maria = stripos($versao, 'maria') !== false;
            $num = (float) preg_replace('/^(\d+\.\d+).*/', '$1', $versao);
            ($maria ? $num >= 10.5 : $num >= 8.0)
                ? $linha('ok', 'Versão do banco compatível')
                : $linha('erro', 'Versão do banco antiga', 'Use MySQL 8+ ou MariaDB 10.5+.');
            $cs = (string) $pdo->query('SELECT @@character_set_database')->fetchColumn();
            str_starts_with($cs, 'utf8mb4') ? $linha('ok', "Codificação $cs") : $linha('aviso', "Codificação do banco: $cs", 'Recrie o banco com CHARACTER SET utf8mb4 (database/criar-banco.sql).');
        }
    } catch (Throwable $e) {
        $msg = $e->getMessage();
        $dica = match (true) {
            str_contains($msg, 'Access denied') => 'Usuário ou senha errados em DB_USUARIO/DB_SENHA.',
            str_contains($msg, 'Unknown database') => 'O banco não existe: rode database/criar-banco.sql (docs/BANCO-DE-DADOS.md).',
            str_contains($msg, 'Connection refused'), str_contains($msg, 'No such file') => 'O MySQL/MariaDB está parado? systemctl start mariadb',
            str_contains($msg, 'could not find driver') => 'Falta a extensão pdo_mysql: apt install php-mysql',
            default => 'Confira o bloco BANCO DE DADOS do .env.',
        };
        $linha('erro', 'Não conectou: ' . preg_replace('/\s+/', ' ', $msg), $dica);
    }
}
if ($pdo) {
    preg_match_all('/CREATE TABLE IF NOT EXISTS\s+`?(\w+)`?/i', (string) file_get_contents("$raiz/database/schema.sql"), $m);
    $esperadas = $m[1];
    $faltando = [];
    foreach ($esperadas as $t) {
        try { $pdo->query("SELECT 1 FROM $t LIMIT 1"); } catch (Throwable) { $faltando[] = $t; }
    }
    $faltando
        ? $linha('erro', count($faltando) . ' de ' . count($esperadas) . ' tabelas faltando: ' . implode(', ', array_slice($faltando, 0, 8)) . (count($faltando) > 8 ? '…' : ''), 'mysql -u root pecuaria < database/schema.sql')
        : $linha('ok', 'Todas as ' . count($esperadas) . ' tabelas existem');
    if (!$faltando) {
        $contas = (int) $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
        $admins = (int) $pdo->query("SELECT COUNT(*) FROM user_roles WHERE role = 'administrador'")->fetchColumn();
        $contas ? $linha('ok', "$contas conta(s), $admins administrador(es)") : $linha('aviso', 'Nenhuma conta ainda', 'Abra o site e clique em "Criar conta": a primeira conta vira a administradora.');
    }
}

$titulo('Arquivos');
$storage = "$raiz/storage";
if (!is_dir($storage) && !@mkdir($storage, 0770, true)) $linha('erro', 'Pasta storage/ não existe', "mkdir -p $storage && chown root:www-data $storage && chmod 770 $storage");
else {
    $teste = "$storage/.teste-" . bin2hex(random_bytes(4));
    if (@file_put_contents($teste, 'ok') !== false) { unlink($teste); $linha('ok', 'storage/ aceita gravação (por este usuário)'); }
    else $linha('erro', 'storage/ não aceita gravação', "chown -R root:www-data $storage && chmod -R 770 $storage");
    if (function_exists('posix_getpwuid') && function_exists('posix_geteuid') && posix_geteuid() === 0) {
        $grupo = @filegroup($storage);
        $nome = $grupo !== false && function_exists('posix_getgrgid') ? (posix_getgrgid($grupo)['name'] ?? '') : '';
        $nome === 'www-data' || $nome === 'nginx' ? $linha('ok', "storage/ pertence ao grupo $nome") : $linha('aviso', "storage/ pertence ao grupo \"$nome\"", "O PHP-FPM roda como www-data: chown -R root:www-data $storage");
    }
}
foreach (['public/index.php', 'database/schema.sql', 'public/assets/app.css', 'public/assets/icones.svg'] as $f) {
    is_file("$raiz/$f") ? null : $linha('erro', "Falta o arquivo $f", 'Copie o projeto inteiro de novo (git pull).');
}
$linha('ok', 'Arquivos principais presentes');

$titulo('E-mail');
if (is_array($cfg) && !empty($cfg['email']['host'])) {
    $e = $cfg['email'];
    $alvo = ($e['seguranca'] === 'ssl' ? 'ssl://' : 'tcp://') . $e['host'] . ':' . $e['porta'];
    $s = @stream_socket_client($alvo, $errno, $errstr, 6);
    if ($s) { fclose($s); $linha('ok', "Servidor de e-mail responde ({$e['host']}:{$e['porta']})"); }
    else $linha('aviso', "Não alcançou {$e['host']}:{$e['porta']} ($errstr)", 'Confira MAIL_HOST/MAIL_PORTA. Algumas VPS bloqueiam a porta 25: use 587 ou 465.');
    filter_var($e['remetente'], FILTER_VALIDATE_EMAIL) ? null : $linha('aviso', 'MAIL_REMETENTE não é um e-mail válido');
} else {
    $linha('aviso', 'E-mail não configurado (MAIL_HOST vazio)', 'Sem isso, "Esqueci a senha" não consegue mandar o link. Veja docs/CONFIGURACAO.md.');
}

echo "\n" . ($falhas ? "$falhas item(ns) precisam ser corrigidos" : 'Tudo certo para funcionar') . ($atencao ? " · $atencao aviso(s)" : '') . ".\n";
exit($falhas ? 1 : 0);
