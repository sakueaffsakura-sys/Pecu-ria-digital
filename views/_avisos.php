<?php $lista = pegar_avisos(); if ($lista): ?>
<div class="toasts" role="status" aria-live="polite">
  <?php foreach ($lista as $a): ?>
    <div class="toast toast-<?= e($a['tipo']) ?>" data-toast>
      <?= icone($a['tipo'] === 'erro' ? 'circle-x' : ($a['tipo'] === 'ok' ? 'circle-check' : 'info')) ?>
      <span><?= e($a['msg']) ?></span>
      <button type="button" class="toast-fechar" data-fechar-toast aria-label="Fechar aviso"><?= icone('x') ?></button>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
