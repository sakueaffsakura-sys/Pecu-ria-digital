<?php
declare(strict_types=1);

const ESTADOS = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];

function ler_propriedade(array $post): array
{
    $d = [
        'nome' => preg_replace('/\s+/', ' ', trim((string) ($post['nome'] ?? ''))),
        'cidade' => trim((string) ($post['cidade'] ?? '')) ?: null,
        'estado' => in_array($post['estado'] ?? '', ESTADOS, true) ? $post['estado'] : null,
        'area_ha' => ler_decimal($post['area_ha'] ?? ''),
        'periodo_gestacao_dias' => (int) ($post['periodo_gestacao_dias'] ?? 283),
    ];
    $erros = [];
    if (mb_strlen($d['nome']) < 2 || mb_strlen($d['nome']) > 120) $erros['nome'] = 'Digite o nome da propriedade.';
    if ($d['cidade'] !== null && mb_strlen($d['cidade']) > 120) $erros['cidade'] = 'Nome da cidade longo demais.';
    if ($d['area_ha'] !== null && ($d['area_ha'] < 0 || $d['area_ha'] > 10000000)) $erros['area_ha'] = 'Área inválida.';
    if ($d['periodo_gestacao_dias'] < 200 || $d['periodo_gestacao_dias'] > 340) $erros['periodo_gestacao_dias'] = 'Use entre 200 e 340 dias (vaca: cerca de 283; búfala: cerca de 310).';
    return [$d, $erros];
}

function rota_propriedades(): never
{
    $u = exigir_login();
    $erros = [];
    $v = ['nome' => '', 'cidade' => '', 'estado' => '', 'area_ha' => '', 'periodo_gestacao_dias' => 283];
    if (eh_post()) {
        conferir_csrf();
        [$d, $erros] = ler_propriedade($_POST);
        $v = array_merge($v, array_map(fn($x) => (string) $x, $_POST));
        if (!$erros) {
            $id = transacao(function () use ($d, $u) {
                $id = inserir('propriedades', $d + ['proprietario_id' => $u['id'], 'criado_em' => date('Y-m-d H:i:s')]);
                inserir('propriedade_membros', ['propriedade_id' => $id, 'usuario_id' => $u['id'], 'papel' => 'dono', 'criado_em' => date('Y-m-d H:i:s')]);
                // Vacinas mais comuns já cadastradas, para facilitar o começo.
                foreach ([['Febre aftosa', 180], ['Brucelose', null], ['Raiva', 365], ['Clostridioses', 365], ['Vermífugo', 120]] as [$nome, $dias]) {
                    inserir('tipos_vacinas', ['propriedade_id' => $id, 'nome' => $nome, 'intervalo_dias' => $dias]);
                }
                q('UPDATE usuarios SET propriedade_ativa_id = ? WHERE id = ?', [$id, $u['id']]);
                return $id;
            });
            aviso('ok', 'Propriedade cadastrada.');
            redirecionar('painel');
        }
    }
    tela('propriedades', ['titulo' => 'Propriedades', 'lista' => propriedades_do_usuario(), 'erros' => $erros, 'v' => $v]);
}

function rota_ativar_propriedade(): never
{
    $u = exigir_login();
    if (eh_post()) {
        conferir_csrf();
        $id = (int) ($_POST['id'] ?? 0);
        $membro = valor('SELECT 1 FROM propriedade_membros WHERE propriedade_id = ? AND usuario_id = ?', [$id, $u['id']]);
        if ($membro) q('UPDATE usuarios SET propriedade_ativa_id = ? WHERE id = ?', [$id, $u['id']]);
    }
    redirecionar('painel');
}

function rota_propriedade(int $id): never
{
    $u = exigir_login();
    $prop = um('SELECT p.*, m.papel FROM propriedades p JOIN propriedade_membros m ON m.propriedade_id = p.id WHERE p.id = ? AND m.usuario_id = ?', [$id, $u['id']]);
    if (!$prop) pagina_erro(404, 'Propriedade não encontrada.');
    $dono = $prop['papel'] === 'dono';
    $erros = [];
    $v = $prop;

    if (eh_post()) {
        conferir_csrf();
        $acao = (string) ($_POST['acao'] ?? '');
        if ($acao === 'sair_da_propriedade' && !$dono) {
            q('DELETE FROM propriedade_membros WHERE propriedade_id = ? AND usuario_id = ?', [$id, $u['id']]);
            aviso('ok', 'Você saiu da propriedade ' . $prop['nome'] . '.');
            redirecionar('propriedades');
        }
        if (!$dono) pagina_erro(403, 'Só o dono pode alterar a propriedade.');

        if ($acao === 'dados') {
            [$d, $erros] = ler_propriedade($_POST);
            $v = array_merge($v, array_map(fn($x) => (string) $x, $_POST));
            if (!$erros) {
                atualizar('propriedades', $d, 'id = ?', [$id]);
                aviso('ok', 'Dados da propriedade salvos.');
                redirecionar("propriedades/$id");
            }
        } elseif ($acao === 'adicionar_membro') {
            $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
            $papel = in_array($_POST['papel'] ?? '', ['editor', 'leitor'], true) ? $_POST['papel'] : 'leitor';
            $outro = um('SELECT id, nome FROM usuarios WHERE email = ?', [$email]);
            if (!$outro) {
                $erros['email'] = 'Não há conta com este e-mail. Peça para a pessoa criar uma conta no site primeiro.';
            } elseif (valor('SELECT 1 FROM propriedade_membros WHERE propriedade_id = ? AND usuario_id = ?', [$id, $outro['id']])) {
                $erros['email'] = 'Esta pessoa já faz parte da propriedade.';
            } else {
                inserir('propriedade_membros', ['propriedade_id' => $id, 'usuario_id' => $outro['id'], 'papel' => $papel, 'criado_em' => date('Y-m-d H:i:s')]);
                aviso('ok', $outro['nome'] . ' agora tem acesso à propriedade.');
                redirecionar("propriedades/$id");
            }
        } elseif ($acao === 'mudar_papel' || $acao === 'remover_membro') {
            $alvo = (int) ($_POST['usuario_id'] ?? 0);
            if ($alvo === (int) $u['id']) pagina_erro(400, 'Você não pode mudar o seu próprio acesso de dono.');
            if ($acao === 'remover_membro') {
                q("DELETE FROM propriedade_membros WHERE propriedade_id = ? AND usuario_id = ? AND papel <> 'dono'", [$id, $alvo]);
                aviso('ok', 'Acesso removido.');
            } else {
                $papel = in_array($_POST['papel'] ?? '', ['editor', 'leitor'], true) ? $_POST['papel'] : 'leitor';
                q("UPDATE propriedade_membros SET papel = ? WHERE propriedade_id = ? AND usuario_id = ? AND papel <> 'dono'", [$papel, $id, $alvo]);
                aviso('ok', 'Acesso alterado.');
            }
            redirecionar("propriedades/$id");
        } elseif ($acao === 'excluir') {
            if (trim((string) ($_POST['confirmar'] ?? '')) !== $prop['nome']) {
                $erros['confirmar'] = 'Digite o nome exato da propriedade para confirmar.';
            } else {
                q('DELETE FROM propriedades WHERE id = ?', [$id]);
                aviso('ok', 'Propriedade excluída com todos os dados.');
                redirecionar('propriedades');
            }
        }
    }

    $membros = todos('SELECT u.id, u.nome, u.email, m.papel FROM propriedade_membros m JOIN usuarios u ON u.id = m.usuario_id WHERE m.propriedade_id = ? ORDER BY m.papel, u.nome', [$id]);
    tela('propriedade', ['titulo' => $prop['nome'], 'prop' => $prop, 'dono' => $dono, 'membros' => $membros, 'erros' => $erros, 'v' => $v, 'u' => $u]);
}
