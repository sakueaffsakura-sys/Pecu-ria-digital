<?php
declare(strict_types=1);

// ---------------------------------------------------------------------------
// Conta, sessão (aparelhos conectados) e propriedades: quem pode ver e mexer em quê.
// Mesmas regras do original: vê a propriedade quem é dono ou membro; administra quem é dono,
// "proprietario" ou "administrador" nela. "visualizador" só consulta.
// ---------------------------------------------------------------------------

const PAPEIS = ['proprietario' => 'Proprietário', 'administrador' => 'Administrador', 'colaborador' => 'Colaborador', 'visualizador' => 'Só consulta'];

function usuario(): ?array
{
    static $u = false;
    if ($u !== false) return $u;
    $u = null;
    $id = (string) ($_SESSION['usuario_id'] ?? '');
    $sessao = (string) ($_SESSION['sessao_id'] ?? '');
    if ($id === '' || $sessao === '') return null;
    $u = um(
        'SELECT u.id, u.email, u.totp_ativo, p.nome_completo, p.nome_exibicao, p.avatar_url, p.bloqueado, s.duas_etapas_ok, s.visto_em
         FROM usuarios u JOIN profiles p ON p.id = u.id JOIN sessoes s ON s.id = ? AND s.user_id = u.id AND s.encerrada_em IS NULL
         WHERE u.id = ?',
        [$sessao, $id]
    );
    if (!$u || (int) $u['bloqueado'] === 1) {
        $motivo = $u ? 'conta-bloqueada' : 'sessao-expirada';
        $u = null;
        $_SESSION = [];
        if (PHP_SAPI !== 'cli' && !eh_pedido_json()) redirecionar('', ['motivo' => $motivo]);
        return null;
    }
    // Duas etapas pendentes: a sessão só vale depois do código.
    if ((int) $u['totp_ativo'] === 1 && (int) $u['duas_etapas_ok'] !== 1) {
        $_SESSION['pendente_2fa'] = $u['id'];
        $u = null;
        return null;
    }
    if (strtotime((string) $u['visto_em']) < time() - 300) {
        q('UPDATE sessoes SET visto_em = ? WHERE id = ?', [agora(), $sessao]);
    }
    return $u;
}

function nome_do_usuario(?array $u = null): string
{
    $u ??= usuario();
    return (string) ($u['nome_exibicao'] ?: $u['nome_completo'] ?: 'Minha conta');
}

function eh_admin(): bool
{
    static $r = null;
    if ($r === null) {
        $u = usuario();
        $r = $u && valor("SELECT 1 FROM user_roles WHERE user_id = ? AND role = 'administrador'", [$u['id']]) !== null;
    }
    return $r;
}

function exigir_login(): array
{
    $u = usuario();
    if (!$u) {
        if (!empty($_SESSION['pendente_2fa'])) redirecionar('', ['etapa' => 'codigo']);
        $volta = (string) ($_SERVER['REQUEST_URI'] ?? '/app/inicio');
        redirecionar('', ['redirect' => str_starts_with($volta, '/') ? $volta : null]);
    }
    return $u;
}

function exigir_admin(): void
{
    exigir_login();
    if (!eh_admin()) pagina_erro(403, 'Esta área é só para administradores do sistema.');
}

/** Abre uma sessão nova (um "aparelho conectado"). */
function entrar_como(string $usuarioId, bool $duasEtapasOk = false): void
{
    session_regenerate_id(true);
    $sid = bin2hex(random_bytes(32));
    $agente = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 160);
    inserir('sessoes', ['id' => $sid, 'user_id' => $usuarioId, 'aparelho' => $agente, 'ip' => ip_cliente(), 'duas_etapas_ok' => $duasEtapasOk ? 1 : 0, 'criado_em' => agora(), 'visto_em' => agora()], false);
    $_SESSION = ['usuario_id' => $usuarioId, 'sessao_id' => $sid];
    q('UPDATE usuarios SET ultimo_login = ? WHERE id = ?', [agora(), $usuarioId]);
}

function sair(): void
{
    if (!empty($_SESSION['sessao_id'])) q('UPDATE sessoes SET encerrada_em = ? WHERE id = ?', [agora(), $_SESSION['sessao_id']]);
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
}

/** Regras de senha do original: 8+ caracteres, letra e número, sem o e-mail, nada óbvio. */
function motivo_senha_fraca(string $senha, string $email = ''): ?string
{
    if (mb_strlen($senha) < 8) return 'A senha precisa ter pelo menos 8 caracteres.';
    if (mb_strlen($senha) > 128) return 'A senha pode ter no máximo 128 caracteres.';
    if (!preg_match('/\pL/u', $senha) || !preg_match('/\d/', $senha)) return 'Use letras e números na senha.';
    $usuarioEmail = mb_strtolower((string) strtok($email, '@'));
    if ($usuarioEmail !== '' && mb_strlen($usuarioEmail) >= 3 && str_contains(mb_strtolower($senha), $usuarioEmail)) return 'A senha não pode conter o seu e-mail.';
    if (preg_match('/^(.)\1+$|12345678|87654321|senha123|password|qwerty|abc12345/i', $senha)) return 'Essa senha é fácil demais de adivinhar.';
    return null;
}

/** Cria a conta (usuário + perfil). A primeira conta do site vira administradora. */
function criar_conta(string $nome, string $email, string $senha): string
{
    return transacao(function () use ($nome, $email, $senha) {
        $primeira = (int) valor('SELECT COUNT(*) FROM usuarios') === 0;
        $id = inserir('usuarios', ['email' => $email, 'senha_hash' => password_hash($senha, PASSWORD_DEFAULT), 'criado_em' => agora()]);
        inserir('profiles', ['id' => $id, 'nome_completo' => $nome, 'created_at' => agora(), 'updated_at' => agora()]);
        if ($primeira) inserir('user_roles', ['user_id' => $id, 'role' => 'administrador', 'created_at' => agora()]);
        return $id;
    });
}

// ----- Propriedades --------------------------------------------------------

function propriedades_do_usuario(): array
{
    static $lista = null;
    if ($lista !== null) return $lista;
    $u = usuario();
    if (!$u) return $lista = [];
    return $lista = todos(
        "SELECT p.*, CASE WHEN p.proprietario_id = ? THEN 'proprietario' ELSE m.role END AS papel
         FROM propriedades p LEFT JOIN propriedade_membros m ON m.propriedade_id = p.id AND m.user_id = ?
         WHERE p.ativo = 1 AND (p.proprietario_id = ? OR m.user_id IS NOT NULL)
         ORDER BY p.created_at",
        [$u['id'], $u['id'], $u['id']]
    );
}

/** Propriedade em uso (guardada na sessão e num cookie, como o original guardava no aparelho). */
function propriedade(): ?array
{
    static $p = false;
    if ($p !== false) return $p;
    $p = null;
    $lista = propriedades_do_usuario();
    if (!$lista) return null;
    $salva = (string) ($_SESSION['propriedade_id'] ?? $_COOKIE['pd_propriedade'] ?? '');
    foreach ($lista as $item) if ($item['id'] === $salva) $p = $item;
    $p ??= $lista[0];
    $_SESSION['propriedade_id'] = $p['id'];
    return $p;
}

function escolher_propriedade(string $id): bool
{
    foreach (propriedades_do_usuario() as $item) {
        if ($item['id'] === $id) {
            $_SESSION['propriedade_id'] = $id;
            setcookie('pd_propriedade', $id, ['expires' => time() + 31536000, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => str_starts_with((string) config('url'), 'https://')]);
            return true;
        }
    }
    return false;
}

function pid(): string
{
    if (PHP_SAPI === 'cli' && !empty($GLOBALS['PID_CLI'])) return $GLOBALS['PID_CLI'];
    return (string) (propriedade()['id'] ?? '');
}

function pode_editar(): bool
{
    return in_array(propriedade()['papel'] ?? '', ['proprietario', 'administrador', 'colaborador'], true);
}

function pode_administrar(): bool
{
    return in_array(propriedade()['papel'] ?? '', ['proprietario', 'administrador'], true);
}

function exigir_edicao(): void
{
    if (!pode_editar()) pagina_erro(403, 'Você só tem acesso de consulta nesta propriedade.');
}

function criar_propriedade(string $nome, ?string $cidade, ?string $estado): string
{
    $u = usuario();
    return transacao(function () use ($u, $nome, $cidade, $estado) {
        $id = inserir('propriedades', ['proprietario_id' => $u['id'], 'nome' => $nome, 'cidade' => $cidade, 'estado' => $estado, 'created_at' => agora(), 'updated_at' => agora()]);
        inserir('propriedade_membros', ['propriedade_id' => $id, 'user_id' => $u['id'], 'role' => 'proprietario', 'created_at' => agora()]);
        return $id;
    });
}
