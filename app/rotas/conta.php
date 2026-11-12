<?php
declare(strict_types=1);

const MOTIVOS_ENTRADA = [
    'sessao-expirada' => ['info', 'Sua sessão terminou. Entre de novo.'],
    'conta-bloqueada' => ['erro', 'Esta conta foi bloqueada pelo administrador.'],
    'senha-alterada' => ['ok', 'Senha alterada. Entre com a senha nova.'],
    'saiu-de-todos' => ['ok', 'Você saiu de todos os aparelhos.'],
    'duas-etapas' => ['info', 'Falta o código do aplicativo autenticador para entrar.'],
];

/** Só volta para páginas deste site (nada de redirecionar para fora). */
function destino_seguro(?string $r): string
{
    $r = (string) $r;
    return preg_match('#^/(?!/)[A-Za-z0-9/_\-?=&.%]*$#', $r) && !str_starts_with($r, '/sair') ? $r : '/app/inicio';
}

function rota_entrar(): never
{
    $destino = destino_seguro($_GET['redirect'] ?? $_POST['redirect'] ?? null);
    if (usuario()) {
        header('Location: ' . $destino, true, 303);
        exit;
    }
    $aba = ($_GET['aba'] ?? $_POST['aba'] ?? 'entrar') === 'criar' ? 'criar' : 'entrar';
    $etapa = !empty($_SESSION['pendente_2fa']) ? 'codigo' : 'senha';
    $erro = null;
    $v = ['nome' => '', 'email' => ''];

    if (eh_post()) {
        conferir_csrf();
        $acao = (string) ($_POST['acao'] ?? '');
        $ip = ip_cliente();
        if ($acao === 'outra_conta') {
            sair();
            redirecionar('');
        }
        if ($acao === 'codigo' && $etapa === 'codigo') {
            $uid = (string) $_SESSION['pendente_2fa'];
            $segredo = (string) valor('SELECT totp_segredo FROM usuarios WHERE id = ? AND totp_ativo = 1', [$uid]);
            if (!dentro_do_limite("2fa:$uid", 8, 15)) {
                $erro = 'Muitas tentativas. Espere 15 minutos.';
            } elseif ($segredo !== '' && totp_conferir($segredo, (string) ($_POST['codigo'] ?? ''))) {
                q('UPDATE sessoes SET duas_etapas_ok = 1 WHERE id = ?', [$_SESSION['sessao_id'] ?? '']);
                unset($_SESSION['pendente_2fa']);
                session_regenerate_id(true);
                header('Location: ' . $destino, true, 303);
                exit;
            } else {
                registrar_tentativa("2fa:$uid");
                $erro = 'Código errado ou vencido. Digite o código que aparece agora no aplicativo.';
            }
        } elseif ($acao === 'entrar' || $acao === 'criar') {
            $v['email'] = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
            $v['nome'] = preg_replace('/\s+/', ' ', trim((string) ($_POST['nome'] ?? '')));
            $senha = (string) ($_POST['senha'] ?? '');
            if ($acao === 'criar') {
                $aba = 'criar';
                $rapido = (int) ($_POST['inicio'] ?? 0) > 0 && time() - (int) $_POST['inicio'] < 2;
                if (trim((string) ($_POST['site'] ?? '')) !== '' || $rapido) $erro = 'Não foi possível criar a conta agora. Tente de novo.';
                elseif (mb_strlen($v['nome']) < 2 || mb_strlen($v['nome']) > 120) $erro = 'Digite o seu nome.';
                elseif (!filter_var($v['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($v['email']) > 254) $erro = 'Digite um e-mail válido.';
                elseif ($m = motivo_senha_fraca($senha, $v['email'])) $erro = $m;
                elseif (!dentro_do_limite("cad-ip:$ip", 5, 60)) $erro = 'Muitos cadastros deste aparelho. Tente de novo daqui a uma hora.';
                elseif (valor('SELECT 1 FROM usuarios WHERE email = ?', [$v['email']])) $erro = 'Não foi possível criar a conta com esse e-mail. Se você já tem conta, entre ou recupere a senha.';
                else {
                    registrar_tentativa("cad-ip:$ip");
                    $id = criar_conta($v['nome'], $v['email'], $senha);
                    entrar_como($id, true);
                    aviso('ok', 'Conta criada. Bem-vindo(a)!');
                    header('Location: ' . $destino, true, 303);
                    exit;
                }
            } else {
                if (!dentro_do_limite("login-ip:$ip", 30, 10) || !dentro_do_limite('login-email:' . $v['email'], 10, 15)) {
                    $erro = 'Muitas tentativas. Aguarde alguns minutos.';
                } else {
                    $u = um('SELECT u.id, u.senha_hash, u.totp_ativo, p.bloqueado FROM usuarios u JOIN profiles p ON p.id = u.id WHERE u.email = ?', [$v['email']]);
                    // Confere uma senha mesmo sem conta: o tempo de resposta não revela quem tem cadastro.
                    $ok = password_verify($senha, $u['senha_hash'] ?? '$2y$10$.vGA1O9wmRjrwAVXD98HNOgsNpDczlqm3Jq7KnEd1rVAGv3Fykk1a');
                    if ($u && $ok && (int) $u['bloqueado'] === 1) {
                        $erro = 'Esta conta está bloqueada.';
                    } elseif ($u && $ok) {
                        if (password_needs_rehash($u['senha_hash'], PASSWORD_DEFAULT)) q('UPDATE usuarios SET senha_hash = ? WHERE id = ?', [password_hash($senha, PASSWORD_DEFAULT), $u['id']]);
                        entrar_como($u['id'], (int) $u['totp_ativo'] !== 1);
                        if ((int) $u['totp_ativo'] === 1) {
                            $_SESSION['pendente_2fa'] = $u['id'];
                            redirecionar('', ['redirect' => $destino]);
                        }
                        header('Location: ' . $destino, true, 303);
                        exit;
                    } else {
                        registrar_tentativa("login-ip:$ip");
                        registrar_tentativa('login-email:' . $v['email']);
                        $erro = 'E-mail ou senha errados.';
                    }
                }
            }
        }
    }
    $motivo = MOTIVOS_ENTRADA[$_GET['motivo'] ?? ''] ?? null;
    tela('entrar', compact('aba', 'etapa', 'erro', 'v', 'destino', 'motivo') + ['titulo' => 'Sua fazenda, anotada'], 'layout_simples');
}

function rota_sair(): never
{
    if (eh_post()) {
        conferir_csrf();
        sair();
    }
    redirecionar('');
}
