<div class="cabecalho"><h1>Administração do site</h1></div>
<?php if (!empty($_SESSION['senha_temporaria'])): $st = $_SESSION['senha_temporaria']; unset($_SESSION['senha_temporaria']); ?>
  <div class="aviso aviso-ok" role="status">Senha provisória de <strong><?= e($st['email']) ?></strong>: <code class="destaque"><?= e($st['senha']) ?></code><br>Passe para a pessoa e peça que troque em "Minha conta". Ela não aparece de novo.</div>
<?php endif; ?>
<section class="cartoes">
  <div class="numero"><span class="n"><?= num_br($totais['usuarios']) ?></span><span>contas</span></div>
  <div class="numero"><span class="n"><?= num_br($totais['propriedades']) ?></span><span>propriedades</span></div>
  <div class="numero"><span class="n"><?= num_br($totais['animais']) ?></span><span>animais ativos</span></div>
</section>
<form class="filtros" method="get" action="<?= e(url('admin')) ?>">
  <div class="campo"><label for="a-busca">Buscar conta</label><input id="a-busca" type="search" name="busca" value="<?= e($busca) ?>"></div>
  <div class="campo campo-botoes"><button class="botao" type="submit">Buscar</button></div>
</form>
<div class="tabela-rolagem"><table class="tabela"><thead><tr><th>Nome</th><th>E-mail</th><th class="num">Propriedades</th><th>Criada</th><th>Último acesso</th><th>Estado</th><th></th></tr></thead><tbody>
<?php foreach ($usuarios as $x): $eu_mesmo = (int) $x['id'] === (int) $eu['id']; ?>
  <tr>
    <td data-rotulo="Nome"><?= e($x['nome']) ?><?= (int) $x['admin'] ? ' ' . selo('admin', 'ativo') : '' ?></td>
    <td data-rotulo="E-mail"><?= e($x['email']) ?></td>
    <td class="num" data-rotulo="Propriedades"><?= (int) $x['propriedades'] ?></td>
    <td data-rotulo="Criada"><?= data_br($x['criado_em']) ?></td>
    <td data-rotulo="Último acesso"><?= data_br($x['ultimo_login']) ?></td>
    <td data-rotulo="Estado"><?= (int) $x['bloqueado'] ? selo('bloqueada', 'alerta') : selo('ativa', 'ativo') ?></td>
    <td class="acoes-linha">
      <?php if (!$eu_mesmo): ?>
        <?php foreach ([
            (int) $x['bloqueado'] ? ['desbloquear', 'Desbloquear', ''] : ['bloquear', 'Bloquear', 'Bloquear ' . $x['email'] . '? A pessoa não consegue mais entrar.'],
            (int) $x['admin'] ? ['remover_admin', 'Tirar admin', 'Tirar o acesso de administrador?'] : ['tornar_admin', 'Tornar admin', 'Dar acesso de administrador do site para ' . $x['email'] . '?'],
            ['nova_senha', 'Nova senha', 'Criar uma senha provisória para ' . $x['email'] . '? A senha atual deixa de valer.'],
        ] as [$acao, $rot, $conf]): ?>
          <form method="post" action="<?= e(url('admin')) ?>" class="em-linha"<?= $conf ? ' data-confirmar="' . e($conf) . '"' : '' ?>><?= csrf_campo() ?>
            <input type="hidden" name="usuario_id" value="<?= (int) $x['id'] ?>"><input type="hidden" name="acao" value="<?= $acao ?>"><input type="hidden" name="busca" value="<?= e($busca) ?>">
            <button class="link" type="submit"><?= $rot ?></button></form>
        <?php endforeach; ?>
      <?php else: ?>você<?php endif; ?>
    </td>
  </tr>
<?php endforeach; ?>
</tbody></table></div>
