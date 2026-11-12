<div class="grade">
  <?= entrada('nome', 'Nome da fazenda / sítio', $v['nome'] ?? '', $erros['nome'] ?? null, ['required' => true, 'maxlength' => 120]) ?>
  <?= entrada('cidade', 'Cidade', $v['cidade'] ?? '', $erros['cidade'] ?? null, ['maxlength' => 120]) ?>
  <div class="campo"><label for="c-estado">Estado</label>
    <select id="c-estado" name="estado"><option value="">—</option>
      <?php foreach (ESTADOS as $uf): ?><option<?= ($v['estado'] ?? '') === $uf ? ' selected' : '' ?>><?= $uf ?></option><?php endforeach; ?>
    </select></div>
  <?= entrada('area_ha', 'Área (hectares)', is_numeric($v['area_ha'] ?? null) ? str_replace('.', ',', (string) (float) $v['area_ha']) : (string) ($v['area_ha'] ?? ''), $erros['area_ha'] ?? null, ['inputmode' => 'decimal']) ?>
  <?= entrada('periodo_gestacao_dias', 'Gestação (dias)', $v['periodo_gestacao_dias'] ?? 283, $erros['periodo_gestacao_dias'] ?? null, ['type' => 'number', 'min' => 200, 'max' => 340, 'required' => true]) ?>
</div>
