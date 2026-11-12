<?php
declare(strict_types=1);

function rota_admin(): never
{
    exigir_admin();
    $eu = usuario();
    if (eh_post()) {
        conferir_csrf();
        $alvo = (int) ($_POST['usuario_id'] ?? 0);
        $acao = (string) ($_POST['acao'] ?? '');
        if ($alvo === (int) $eu['id']) {
            aviso('erro', 'Você não pode bloquear nem tirar o acesso de administrador da sua própria conta.');
        } elseif ($acao === 'nova_senha') {
            $email = valor('SELECT email FROM usuarios WHERE id = ?', [$alvo]);
            if ($email) {
                // 12 caracteres sem letras parecidas (0/O, 1/l), fácil de ditar por telefone.
                $alfabeto = 'abcdefghjkmnpqrstuvwxyz23456789';
                $senha = '';
                for ($i = 0; $i < 12; $i++) $senha .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
                q('UPDATE usuarios SET senha_hash = ? WHERE id = ?', [password_hash($senha, PASSWORD_DEFAULT), $alvo]);
                $_SESSION['senha_temporaria'] = ['email' => $email, 'senha' => $senha];
            }
        } elseif (in_array($acao, ['bloquear', 'desbloquear', 'tornar_admin', 'remover_admin'], true)) {
            [$coluna, $valor] = match ($acao) {
                'bloquear' => ['bloqueado', 1], 'desbloquear' => ['bloqueado', 0],
                'tornar_admin' => ['admin', 1], 'remover_admin' => ['admin', 0],
            };
            q("UPDATE usuarios SET $coluna = ? WHERE id = ?", [$valor, $alvo]);
            aviso('ok', 'Conta atualizada.');
        }
        redirecionar('admin', ['busca' => $_POST['busca'] ?? '']);
    }
    $busca = trim((string) ($_GET['busca'] ?? ''));
    $where = '';
    $p = [];
    if ($busca !== '') {
        $where = 'WHERE u.nome LIKE ? OR u.email LIKE ?';
        $p = ['%' . addcslashes($busca, '%_\\') . '%', '%' . addcslashes($busca, '%_\\') . '%'];
    }
    $usuarios = todos("SELECT u.id, u.nome, u.email, u.admin, u.bloqueado, u.criado_em, u.ultimo_login,
        (SELECT COUNT(*) FROM propriedade_membros m WHERE m.usuario_id = u.id) AS propriedades
        FROM usuarios u $where ORDER BY u.criado_em DESC LIMIT 200", $p);
    $totais = [
        'usuarios' => (int) valor('SELECT COUNT(*) FROM usuarios'),
        'propriedades' => (int) valor('SELECT COUNT(*) FROM propriedades'),
        'animais' => (int) valor("SELECT COUNT(*) FROM animais WHERE situacao = 'ativo'"),
    ];
    tela('admin', ['titulo' => 'Administração', 'usuarios' => $usuarios, 'totais' => $totais, 'busca' => $busca, 'eu' => $eu]);
}
