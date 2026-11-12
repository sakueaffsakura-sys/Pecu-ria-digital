// Aplica o tema (claro, escuro ou igual ao aparelho) antes de a página aparecer, sem piscar.
(function () {
  var t = 'automatico';
  try { t = localStorage.getItem('pd-tema') || 'automatico'; } catch (e) { /* sem armazenamento */ }
  var escuro = t === 'escuro' || (t === 'automatico' && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
  document.documentElement.classList.toggle('dark', escuro);
})();
