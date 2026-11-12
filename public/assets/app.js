// Pequenas ajudas de tela. O site funciona sem este arquivo; ele só deixa mais prático.
document.addEventListener('change', (ev) => {
  const el = ev.target;
  if (el instanceof HTMLSelectElement && el.hasAttribute('data-enviar-ao-mudar') && el.form) el.form.requestSubmit();
});

document.addEventListener('submit', (ev) => {
  const form = ev.target;
  const msg = form instanceof HTMLFormElement ? form.getAttribute('data-confirmar') : null;
  if (msg && !window.confirm(msg)) {
    ev.preventDefault();
    return;
  }
  // Evita salvar duas vezes com toque duplo.
  if (form instanceof HTMLFormElement && form.method.toLowerCase() === 'post') {
    if (form.dataset.enviando === '1') { ev.preventDefault(); return; }
    form.dataset.enviando = '1';
    setTimeout(() => { delete form.dataset.enviando; }, 4000);
  }
});

document.addEventListener('click', (ev) => {
  const alvo = ev.target instanceof Element ? ev.target.closest('[data-imprimir]') : null;
  if (alvo) window.print();
});
