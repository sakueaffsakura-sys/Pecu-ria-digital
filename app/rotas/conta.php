<?php
declare(strict_types=1);

function rota_entrar(): never
{
    if (usuario()) redirecionar('painel');
    $erro = null;
    $email = '';
    if (eh_post()) {
        conferir_csrf();
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $senha = (string) ($_POST['senha'] ?? '');
        $ip = ip_cliente();
        if (!dentro_do_limite("ip:$ip", 20, 15) || !dentro_do_limite("email:$email", 8, 15)) {
            $erro = 'Muitas tentativas. Espere 15 minutos e tente de novo.';
        } else {
            $u = um('SELECT id, senha_hash, bloqueado FROM usuarios WHERE email = ?', [$email]);
            // Confere a senha mesmo sem conta, para o tempo de resposta não revelar quem tem cadastro.
            $ok = password_verify($senha, $u['senha_hash'] ?? '$2y$10$.vGA1O9wmRjrwAVXD98HNOgsNpDczlqm3Jq7KnEd1rVAGv3Fykk1a');
            if ($u && $ok && (int) $u['bloqueado'] === 0) {
                if (password_needs_rehash($u['senha_hash'], PASSWORD_DEFAULT)) {
                    q('UPDATE usuarios SET senha_hash = ? WHERE id = ?', [password_hash($senha, PASSWORD_DEFAULT), $u['id']]);
                }
                entrar_como((int) $u['id']);
                redirecionar('painel');
            }
            registrar_tentativa("ip:$ip");
            registrar_tentativa("email:$email");
            $erro = $u && $ok ? 'Esta conta está bloqueada. Fale com o administrador.' : 'E-mail ou senha incorretos.';
        }
    }
    tela('entrar', ['titulo' => 'Entrar', 'erro' => $erro, 'email' => $email], 'layout_publico');
}

function rota_cadastro(): never
{
    if (usuario()) redirecionar('painel');
    if (!config('cadastro_aberto') && (int) valor('SELECT COUNT(*) FROM usuarios') > 0) {
        pagina_erro(403, 'O cadastro de novas contas está fechado. Peça um convite ao dono da propriedade.');
    }
    $erros = [];
    $v = ['nome' => '', 'email' => ''];
    if (eh_post()) {
        conferir_csrf();
        $v['nome'] = preg_replace('/\s+/', ' ', trim((string) ($_POST['nome'] ?? '')));
        $v['email'] = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $senha = (string) ($_POST['senha'] ?? '');
        $ip = ip_cliente();
        // Campo escondido "site": pessoas não veem e não preenchem; robôs costumam preencher.
        if (trim((string) ($_POST['site'] ?? '')) !== '') $erros['_geral'] = 'Não foi possível criar a conta agora.';
        if (mb_strlen($v['nome']) < 2 || mb_strlen($v['nome']) > 120) $erros['nome'] = 'Digite o seu nome.';
        if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($v['email']) > 190) $erros['email'] = 'Digite um e-mail válido.';
        if ($m = motivo_senha_fraca($senha, $v['email'])) $erros['senha'] = $m;
        if ($senha !== (string) ($_POST['senha2'] ?? '')) $erros['senha2'] = 'As duas senhas não são iguais.';
        if (!$erros && !dentro_do_limite("cadastro:$ip", 5, 60)) $erros['_geral'] = 'Muitos cadastros deste aparelho. Tente daqui a uma hora.';
        if (!$erros) {
            registrar_tentativa("cadastro:$ip");
            try {
                $primeiro = (int) valor('SELECT COUNT(*) FROM usuarios') === 0;
                $id = inserir('usuarios', [
                    'nome' => $v['nome'], 'email' => $v['email'], 'senha_hash' => password_hash($senha, PASSWORD_DEFAULT),
                    // A primeira conta do site vira administradora.
                    'admin' => $primeiro ? 1 : 0, 'criado_em' => date('Y-m-d H:i:s'),
                ]);
                entrar_como($id);
                aviso('ok', 'Conta criada. Agora cadastre a sua propriedade.');
                redirecionar('propriedades');
            } catch (PDOException $e) {
                if (erro_de_integridade($e) !== 'duplicado') throw $e;
                $erros['email'] = 'Já existe uma conta com este e-mail. Entre com a sua senha.';
            }
        }
    }
    tela('cadastro', ['titulo' => 'Criar conta', 'erros' => $erros, 'v' => $v], 'layout_publico');
}

function rota_sair(): never
{
    if (eh_post()) {
        conferir_csrf();
        sair();
    }
    redirecionar('');
}

function rota_perfil(): never
{
    $u = exigir_login();
    $erros = [];
    if (eh_post()) {
        conferir_csrf();
        $acao = $_POST['acao'] ?? '';
        if ($acao === 'nome') {
            $nome = preg_replace('/\s+/', ' ', trim((string) ($_POST['nome'] ?? '')));
            if (mb_strlen($nome) < 2 || mb_strlen($nome) > 120) {
                $erros['nome'] = 'Digite o seu nome.';
            } else {
                q('UPDATE usuarios SET nome = ? WHERE id = ?', [$nome, $u['id']]);
                aviso('ok', 'Nome atualizado.');
                redirecionar('perfil');
            }
        } elseif ($acao === 'senha') {
            $atual = (string) ($_POST['atual'] ?? '');
            $nova = (string) ($_POST['nova'] ?? '');
            $hash = (string) valor('SELECT senha_hash FROM usuarios WHERE id = ?', [$u['id']]);
            if (!dentro_do_limite('senha:' . $u['id'], 6, 15)) {
                $erros['atual'] = 'Muitas tentativas. Espere 15 minutos.';
            } elseif (!password_verify($atual, $hash)) {
                registrar_tentativa('senha:' . $u['id']);
                $erros['atual'] = 'A senha atual está errada.';
            } elseif ($m = motivo_senha_fraca($nova, $u['email'])) {
                $erros['nova'] = $m;
            } elseif ($nova !== (string) ($_POST['nova2'] ?? '')) {
                $erros['nova2'] = 'As duas senhas não são iguais.';
            } else {
                q('UPDATE usuarios SET senha_hash = ? WHERE id = ?', [password_hash($nova, PASSWORD_DEFAULT), $u['id']]);
                session_regenerate_id(true);
                aviso('ok', 'Senha trocada.');
                redirecionar('perfil');
            }
        }
    }
    tela('perfil', ['titulo' => 'Minha conta', 'u' => $u, 'erros' => $erros]);
}
