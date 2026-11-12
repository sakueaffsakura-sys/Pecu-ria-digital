<?php
declare(strict_types=1);

// ---------------------------------------------------------------------------
// Conta, login e propriedades (quem pode ver e mexer em quê).
// ---------------------------------------------------------------------------

const PAPEIS = ['dono' => 'Dono', 'editor' => 'Pode lançar e editar', 'leitor' => 'Só consulta'];

function usuario(): ?array
{
    static $u = false;
    if ($u === false) {
        $u = null;
        $id = (int) ($_SESSION['usuario_id'] ?? 0);
        if ($id > 0) {
            $u = um('SELECT id, nome, email, admin, bloqueado, propriedade_ativa_id FROM usuarios WHERE id = ?', [$id]);
            if (!$u || (int) $u['bloqueado'] === 1) {
                $u = null;
                $_SESSION = [];
            }
        }
    }
    return $u;
}

function exigir_login(): array
{
    $u = usuario();
    if (!$u) redirecionar('entrar');
    return $u;
}

function exigir_admin(): void
{
    if ((int) (exigir_login()['admin']) !== 1) pagina_erro(403, 'Esta área é só para administradores do sistema.');
}

function entrar_como(int $usuarioId): void
{
    session_regenerate_id(true);
    $_SESSION = ['usuario_id' => $usuarioId];
    q('UPDATE usuarios SET ultimo_login = ? WHERE id = ?', [date('Y-m-d H:i:s'), $usuarioId]);
}

function sair(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function motivo_senha_fraca(string $senha, string $email = ''): ?string
{
    if (mb_strlen($senha) < 8) return 'A senha precisa ter pelo menos 8 caracteres.';
    if (mb_strlen($senha) > 200) return 'A senha é longa demais.';
    if ($email !== '' && stripos($senha, strtok($email, '@')) !== false) return 'A senha não pode conter o seu e-mail.';
    if (preg_match('/^(\d)\1+$|^(12345678|123456789|87654321|senha123|password)$/i', $senha)) return 'Essa senha é fácil demais de adivinhar.';
    return null;
}

// ----- Propriedades --------------------------------------------------------

function propriedades_do_usuario(): array
{
    $u = usuario();
    if (!$u) return [];
    return todos(
        'SELECT p.*, m.papel FROM propriedades p JOIN propriedade_membros m ON m.propriedade_id = p.id
         WHERE m.usuario_id = ? ORDER BY p.nome',
        [$u['id']]
    );
}

/** Propriedade em uso agora (com o papel da pessoa nela) ou null se ela ainda não tem nenhuma. */
function propriedade(): ?array
{
    static $p = false;
    if ($p === false) {
        $p = null;
        $u = usuario();
        if (!$u) return null;
        $lista = propriedades_do_usuario();
        foreach ($lista as $item) {
            if ((int) $item['id'] === (int) $u['propriedade_ativa_id']) $p = $item;
        }
        if (!$p && $lista) {
            $p = $lista[0];
            q('UPDATE usuarios SET propriedade_ativa_id = ? WHERE id = ?', [$p['id'], $u['id']]);
        }
    }
    return $p;
}

function exigir_propriedade(): array
{
    exigir_login();
    $p = propriedade();
    if (!$p) {
        aviso('info', 'Cadastre a sua primeira propriedade para começar.');
        redirecionar('propriedades');
    }
    return $p;
}

function pode_editar(): bool
{
    return in_array(propriedade()['papel'] ?? '', ['dono', 'editor'], true);
}

function exigir_edicao(): void
{
    if (!pode_editar()) pagina_erro(403, 'Você só tem acesso de consulta nesta propriedade.');
}

function eh_dono(): bool
{
    return (propriedade()['papel'] ?? '') === 'dono';
}
