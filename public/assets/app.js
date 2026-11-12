// Pecuária Digital — comportamentos da tela. O site funciona sem este arquivo; ele deixa tudo mais rápido de usar.
(function () {
  'use strict';
  const $ = (s, el = document) => el.querySelector(s);
  const $$ = (s, el = document) => Array.from(el.querySelectorAll(s));

  // ---------- Diálogos ----------
  function abrir(d) {
    if (!d) return;
    if (d.open) d.close();
    try { d.showModal(); } catch (e) { d.setAttribute('open', ''); }
    const foco = d.querySelector('[autofocus], input:not([type=hidden]):not([disabled]), textarea, select');
    if (foco && d.classList.contains('dialogo-form')) foco.focus();
  }
  document.addEventListener('click', (ev) => {
    const alvo = ev.target.closest('[data-abrir]');
    if (alvo) {
      ev.preventDefault();
      $$('details.suspenso[open]').forEach((x) => x.removeAttribute('open'));
      abrir($(alvo.getAttribute('data-abrir')));
      return;
    }
    const fechar = ev.target.closest('[data-fechar]');
    if (fechar) {
      const d = fechar.closest('dialog');
      if (d) { ev.preventDefault(); d.close(); }
    }
  });
  $$('dialog').forEach((d) => {
    d.addEventListener('close', () => {
      const para = d.getAttribute('data-fechar-para');
      if (para) history.replaceState(null, '', para);
    });
    // Clique fora da caixa fecha (como no original).
    d.addEventListener('click', (ev) => { if (ev.target === d) d.close(); });
  });
  $$('dialog[data-auto]').forEach((d) => abrir(d));
  $$('[data-abrir-ao-carregar]').forEach((b) => abrir($(b.getAttribute('data-abrir'))));

  // Importar planilha: "Trocar arquivo" volta para a escolha do arquivo.
  document.addEventListener('click', (ev) => {
    const b = ev.target.closest('[data-trocar-arquivo]');
    if (!b) return;
    const d = b.closest('dialog');
    d.querySelectorAll('.grade-stat, .caixa-problemas, .dialogo-rodape, p.pequeno').forEach((x) => { if (!x.closest('[data-form-arquivo]')) x.hidden = true; });
    const f = d.querySelector('[data-form-arquivo]');
    if (f) f.hidden = false;
  });

  // ---------- Menus suspensos (detalhes) ----------
  document.addEventListener('toggle', (ev) => {
    const d = ev.target;
    if (!(d instanceof HTMLDetailsElement) || !d.classList.contains('suspenso') || !d.open) return;
    $$('details.suspenso[open]').forEach((x) => { if (x !== d) x.removeAttribute('open'); });
  }, true);
  document.addEventListener('click', (ev) => {
    $$('details.suspenso[open]').forEach((d) => { if (!d.contains(ev.target)) d.removeAttribute('open'); });
  });
  document.addEventListener('keydown', (ev) => {
    if (ev.key !== 'Escape') return;
    $$('details.suspenso[open]').forEach((d) => { d.removeAttribute('open'); const s = d.querySelector('summary'); if (s) s.focus(); });
    fecharMenu();
  });

  // ---------- Menu lateral no celular ----------
  const menu = $('[data-menu]');
  const sombra = $('.menu-sombra');
  function abrirMenu() { if (!menu) return; menu.classList.add('aberto'); if (sombra) sombra.hidden = false; const a = menu.querySelector('a, button'); if (a) a.focus(); }
  function fecharMenu() { if (!menu || !menu.classList.contains('aberto')) return; menu.classList.remove('aberto'); if (sombra) sombra.hidden = true; }
  document.addEventListener('click', (ev) => {
    if (ev.target.closest('[data-abrir-menu]')) abrirMenu();
    else if (ev.target.closest('[data-fechar-menu]')) fecharMenu();
  });

  // ---------- Enviar ao mudar ----------
  document.addEventListener('change', (ev) => {
    const el = ev.target;
    if (el.matches && el.matches('[data-enviar]') && el.form) {
      el.form.requestSubmit ? el.form.requestSubmit() : el.form.submit();
    }
  });

  // Botões de período (Tudo, Hoje, Este mês…) apagam o dia escolhido no calendário.
  document.addEventListener('click', (ev) => {
    const b = ev.target.closest('button[name=periodo]');
    if (!b || !b.form) return;
    const dia = b.form.querySelector('input[name=dia]');
    if (dia) dia.value = '';
  });

  // ---------- Evita gravar duas vezes com toque duplo ----------
  document.addEventListener('submit', (ev) => {
    const f = ev.target;
    if (!(f instanceof HTMLFormElement) || (f.method || '').toLowerCase() !== 'post') return;
    if (f.dataset.enviando === '1') { ev.preventDefault(); return; }
    f.dataset.enviando = '1';
    const b = f.querySelector('button[type=submit]');
    if (b && !b.dataset.texto) { b.dataset.texto = b.innerHTML; }
    setTimeout(() => { delete f.dataset.enviando; }, 6000);
  });

  // ---------- Tema ----------
  function aplicarTema(t) {
    const escuro = t === 'escuro' || (t === 'automatico' && matchMedia('(prefers-color-scheme: dark)').matches);
    document.documentElement.classList.toggle('dark', escuro);
    $$('[data-tema]').forEach((b) => b.setAttribute('aria-pressed', String(b.getAttribute('data-tema') === t)));
  }
  let tema = 'automatico';
  try { tema = localStorage.getItem('pd-tema') || 'automatico'; } catch (e) { /* */ }
  aplicarTema(tema);
  document.addEventListener('click', (ev) => {
    const b = ev.target.closest('[data-tema]');
    if (!b) return;
    tema = b.getAttribute('data-tema');
    try { localStorage.setItem('pd-tema', tema); } catch (e) { /* */ }
    aplicarTema(tema);
  });
  matchMedia('(prefers-color-scheme: dark)').addEventListener?.('change', () => aplicarTema(tema));

  // ---------- Avisos rápidos ----------
  $$('[data-toast]').forEach((t, i) => setTimeout(() => t.remove(), 5000 + i * 800));
  document.addEventListener('click', (ev) => { const b = ev.target.closest('[data-fechar-toast]'); if (b) b.closest('[data-toast]').remove(); });

  // ---------- Senha: mostrar e regras ----------
  document.addEventListener('click', (ev) => {
    const b = ev.target.closest('[data-mostrar-senha]');
    if (!b) return;
    const campo = document.getElementById(b.getAttribute('data-mostrar-senha'));
    const mostrar = campo.type === 'password';
    campo.type = mostrar ? 'text' : 'password';
    b.setAttribute('aria-pressed', String(mostrar));
    b.setAttribute('aria-label', mostrar ? 'Esconder a senha' : 'Mostrar a senha');
    b.querySelector('use').setAttribute('href', '/assets/icones.svg?v=1#' + (mostrar ? 'eye-off' : 'eye'));
  });
  $$('[data-regras-senha]').forEach((lista) => {
    const campo = document.getElementById(lista.getAttribute('data-regras-senha'));
    const conferir = () => {
      const v = campo.value;
      lista.querySelector('[data-regra=tamanho]').classList.toggle('ok', v.length >= 8);
      lista.querySelector('[data-regra=letra-numero]').classList.toggle('ok', /\p{L}/u.test(v) && /\d/.test(v));
    };
    campo.addEventListener('input', conferir);
    conferir();
  });

  // ---------- Busca (Ctrl K) ----------
  const dBusca = $('#dialogo-busca');
  const campoBusca = $('[data-busca]');
  const resultados = $('[data-busca-resultados]');
  let selecionado = -1;
  let espera = null;
  let ultimo = '';
  const esc = (t) => String(t ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  function itemHtml(r) {
    const icone = r.tipo === 'animal'
      ? '<span class="brinco-caixa"><span class="brinco brinco-pequeno"><svg aria-hidden="true" viewBox="0 0 100 116" preserveAspectRatio="none"><path d="M36 7Q36 1 42 1H58Q64 1 64 7V17Q64 23 71 25L89 30Q98 33 98 42V103Q98 115 86 115H14Q2 115 2 103V42Q2 33 11 30L29 25Q36 23 36 17Z" class="brinco-corpo"/><ellipse cx="50" cy="11" rx="5.5" ry="4.8" class="brinco-furo"/></svg><span class="brinco-numero ' + (r.brinco.length > 6 ? 't-p3' : r.brinco.length > 4 ? 't-p2' : 't-p1') + '">' + esc(r.brinco.length > 8 ? '…' + r.brinco.slice(-6) : r.brinco) + '</span></span></span>'
      : '<svg class="ic ic-verde" aria-hidden="true"><use href="/assets/icones.svg?v=1#' + (r.tipo === 'lote' ? 'warehouse' : 'file-text') + '"/></svg>';
    return '<a class="busca-item" href="' + esc(r.url) + '" role="option">' + icone + '<span class="min0"><strong class="truncar bloco">' + esc(r.rotulo) + '</strong><small class="truncar">' + esc(r.frase) + '</small></span></a>';
  }
  async function buscar() {
    const q = campoBusca.value.trim();
    if (q === ultimo && resultados.innerHTML) return;
    ultimo = q;
    try {
      const r = await fetch('/api/busca?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      if (!r.ok) throw new Error('falhou');
      const d = await r.json();
      let h = '';
      if (d.animais.length) h += '<p class="busca-grupo">Animais</p>' + d.animais.map(itemHtml).join('');
      if (d.lotes.length) h += '<p class="busca-grupo">Lotes</p>' + d.lotes.map(itemHtml).join('');
      if (d.paginas.length) h += '<p class="busca-grupo">Páginas</p>' + d.paginas.map(itemHtml).join('');
      resultados.innerHTML = h || '<p class="avisos-vazio">Nada encontrado com “' + esc(q) + '”.</p>';
      selecionado = -1;
    } catch (e) {
      resultados.innerHTML = '<p class="avisos-vazio">A busca não respondeu. Tente de novo.</p>';
    }
  }
  function abrirBusca() {
    if (!dBusca) return;
    fecharMenu();
    abrir(dBusca);
    campoBusca.value = '';
    ultimo = null;
    buscar();
    campoBusca.focus();
  }
  document.addEventListener('click', (ev) => { if (ev.target.closest('[data-abrir-busca]')) abrirBusca(); });
  document.addEventListener('keydown', (ev) => {
    if ((ev.ctrlKey || ev.metaKey) && ev.key.toLowerCase() === 'k') { ev.preventDefault(); abrirBusca(); }
  });
  if (campoBusca) {
    campoBusca.addEventListener('input', () => { clearTimeout(espera); espera = setTimeout(buscar, 200); });
    campoBusca.addEventListener('keydown', (ev) => {
      const itens = $$('.busca-item', resultados);
      if (ev.key === 'ArrowDown' || ev.key === 'ArrowUp') {
        ev.preventDefault();
        selecionado = (selecionado + (ev.key === 'ArrowDown' ? 1 : -1) + itens.length) % Math.max(1, itens.length);
        itens.forEach((it, i) => it.setAttribute('aria-selected', String(i === selecionado)));
        if (itens[selecionado]) itens[selecionado].scrollIntoView({ block: 'nearest' });
      } else if (ev.key === 'Enter') {
        ev.preventDefault();
        const it = itens[Math.max(0, selecionado)];
        if (it) location.href = it.getAttribute('href');
      }
    });
    const form = campoBusca.closest('form');
    if (form) form.addEventListener('submit', (ev) => ev.preventDefault());
  }
})();
