// Calculadoras e previsão do tempo (mesmas contas do original). Nada é gravado.
(function () {
  'use strict';
  const lerNumero = (t) => {
    const limpo = String(t || '').trim().replace(/\s/g, '');
    if (!limpo) return null;
    const n = Number(limpo.replace(/\.(?=\d{3}(\D|$))/g, '').replace(',', '.'));
    return Number.isFinite(n) ? n : null;
  };
  const n = (v, casas = 2) => (v == null ? '—' : Number(v).toLocaleString('pt-BR', { maximumFractionDigits: casas }));
  const money = (v) => Number(v || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
  const ok = (...v) => v.every((x) => typeof x === 'number' && Number.isFinite(x));
  const plural = (q, um, varios) => `${q.toLocaleString('pt-BR')} ${q === 1 ? um : varios || um + 's'}`;
  const dataBR = (iso) => iso.split('-').reverse().join('/');
  const hojeISO = () => { const d = new Date(); return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`; };

  const contas = {
    dose(v) {
      const p = lerNumero(v.peso), k = lerNumero(v.kgml);
      if (!ok(p, k) || p <= 0 || k <= 0) return ['—', 'Preencha o peso e a proporção da bula.'];
      return [`${n(p / k, 2)} mL`, `Animal de ${n(p, 0)} kg, 1 mL para cada ${n(k, 0)} kg.`];
    },
    parto(v, el) {
      const g = Math.round(lerNumero(v.dias) ?? Number(el.dataset.gestacao));
      const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(v.data || '');
      if (!m || g < 150 || g > 400) return ['—', 'Confira a data e os dias de gestação.'];
      const d = new Date(Date.UTC(+m[1], +m[2] - 1, +m[3]));
      d.setUTCDate(d.getUTCDate() + g);
      const prev = d.toISOString().slice(0, 10);
      const faltam = Math.round((Date.parse(prev + 'T00:00:00Z') - Date.parse(hojeISO() + 'T00:00:00Z')) / 86400000);
      return [dataBR(prev), faltam === 0 ? 'É hoje.' : faltam > 0 ? `${faltam === 1 ? 'Falta' : 'Faltam'} ${plural(faltam, 'dia')}.` : `Já passou ${plural(-faltam, 'dia')} da previsão.`];
    },
    mistura(v) {
      const alto = lerNumero(v.alto), baixo = lerNumero(v.baixo), alvo = lerNumero(v.alvo), kg = lerNumero(v.total) ?? 0;
      if (!ok(alto, baixo, alvo) || alto <= baixo || alvo < baixo || alvo > alto) return ['—', 'A proteína desejada precisa ficar entre a do alimento forte e a do fraco.'];
      const a = (alvo - baixo) / (alto - baixo), b = 1 - a;
      return [`${n(a * 100, 1)}% + ${n(b * 100, 1)}%`, `Em ${n(kg, 0)} kg de mistura: ${n(a * kg, 1)} kg do alimento forte e ${n(b * kg, 1)} kg do fraco.`];
    },
    racao(v) {
      const p = lerNumero(v.peso), pct = lerNumero(v.pct), q = Math.max(1, Math.round(lerNumero(v.qtd) ?? 1));
      if (!ok(p, pct) || p <= 0 || pct <= 0) return ['—', 'Preencha peso e porcentagem.'];
      const por = (p * pct) / 100;
      return [`${n(por * q, 1)} kg/dia`, `${n(por, 2)} kg por animal, por dia, para ${plural(q, 'animal', 'animais')}.`];
    },
    lotacao(v) {
      const q = lerNumero(v.qtd), p = lerNumero(v.peso), ha = lerNumero(v.ha);
      if (!ok(q, p, ha) || q < 0 || p <= 0 || ha <= 0) return ['—', 'Preencha quantos animais, o peso médio e a área.'];
      const ua = (q * p) / 450;
      return [`${n(ua / ha, 2)} UA/ha`, `${n(ua, 1)} UA no total · ${n((q * p) / ha, 0)} kg de peso vivo por hectare.`];
    },
    calagem(v) {
      const ctc = lerNumero(v.ctc), v1 = lerNumero(v.v1), v2 = lerNumero(v.v2), prnt = lerNumero(v.prnt), ha = lerNumero(v.ha) ?? 0;
      if (!ok(ctc, v1, v2, prnt) || ctc <= 0 || prnt <= 0) return ['—', 'Preencha a análise de solo e o PRNT.'];
      const t = v2 <= v1 ? 0 : ((((v2 - v1) * ctc) / 100) * 100) / prnt;
      return [`${n(t, 2)} t/ha`, t === 0 ? 'A saturação já está no ponto: não precisa calcário.' : `Para ${n(ha, 1)} ha: ${n(t * ha, 1)} toneladas no total.`];
    },
    arroba(v) {
      const p = lerNumero(v.peso), r = lerNumero(v.rend), preco = lerNumero(v.preco), arr = lerNumero(v.arrobas);
      if (!ok(p, r) || p <= 0 || r <= 0 || r > 100) return ['—', 'Preencha o peso vivo e o rendimento.'];
      const carcaca = (p * r) / 100, arrobas = carcaca / 15;
      const valor = ok(preco) && preco >= 0 ? arrobas * preco : null;
      const volta = ok(arr) && arr > 0 ? (arr * 15 * 100) / r : null;
      return [`${n(arrobas, 2)} @`, `${n(carcaca, 1)} kg de carcaça${valor == null ? '' : ` · ${money(valor)} com a arroba a ${money(preco)}`}. ${volta == null ? '' : `Na volta: ${n(arr, 1)} @ equivalem a ${n(volta, 0)} kg de peso vivo.`}`];
    },
  };
  document.querySelectorAll('[data-conta]').forEach((el) => {
    const calc = () => {
      const v = {};
      el.querySelectorAll('input').forEach((i) => { v[i.name] = i.value; });
      const [res, det] = contas[el.dataset.conta](v, el);
      el.querySelector('[data-resultado]').textContent = res;
      el.querySelector('[data-detalhe]').textContent = det;
    };
    el.addEventListener('input', calc);
    calc();
  });

  // ---------- Previsão do tempo (Open-Meteo, pedido feito pelo servidor) ----------
  const caixa = document.querySelector('[data-clima]');
  if (!caixa) return;
  const CHAVE = 'pd-lugar-clima';
  const saida = caixa.querySelector('[data-clima-saida]');
  const opcoes = caixa.querySelector('[data-clima-opcoes]');
  const busca = caixa.querySelector('[data-clima-busca]');
  const rotulo = caixa.querySelector('[data-clima-rotulo]');
  const esc = (t) => String(t ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const icone = (nome, cls = 'ic') => `<svg class="${cls}" aria-hidden="true"><use href="/assets/icones.svg?v=1#${nome}"/></svg>`;
  const tempo = (c) => {
    c = c ?? -1;
    if (c === 0) return ['Céu limpo', 'sun'];
    if (c <= 2) return ['Sol entre nuvens', 'cloud-sun'];
    if (c === 3) return ['Nublado', 'cloud-sun'];
    if (c <= 48) return ['Névoa', 'wind'];
    if (c <= 57) return ['Garoa', 'cloud-rain'];
    if (c <= 67) return ['Chuva', 'cloud-rain'];
    if (c <= 77) return ['Neve', 'cloud-rain'];
    if (c <= 82) return ['Pancadas de chuva', 'cloud-rain'];
    if (c <= 86) return ['Neve', 'cloud-rain'];
    if (c <= 99) return ['Temporal com raios', 'cloud-rain'];
    return ['Sem informação', 'cloud-sun'];
  };
  const grau = (v) => (v == null ? '—' : `${Math.round(v)}°`);
  const diaSemana = (iso) => {
    if (iso === hojeISO()) return 'Hoje';
    return new Date(iso + 'T12:00:00').toLocaleDateString('pt-BR', { weekday: 'short' }).replace('.', '');
  };
  async function pedir(caminho) {
    const r = await fetch(caminho, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    const corpo = await r.json().catch(() => ({}));
    if (!r.ok || corpo.ok !== true) throw new Error(corpo.erro || 'Não foi possível ver o tempo agora.');
    return corpo;
  }
  async function mostrar(lugar) {
    rotulo.textContent = 'Trocar de cidade';
    saida.innerHTML = `<p class="mt-3 texto-suave">${icone('loader-circle', 'ic gira')} Vendo o tempo em ${esc(lugar.nome)}…</p>`;
    try {
      const p = await pedir(`/api/clima/agora?lat=${lugar.lat}&lon=${lugar.lon}`);
      const a = p.agora || {};
      const [texto, ic] = tempo(a.codigo);
      saida.innerHTML = `<div class="clima-agora">${icone(ic, 'ic clima-ic')}<div class="min0"><p class="clima-grau">${grau(a.temperatura)}</p>
        <p class="negrito">${esc(texto)} em ${esc(lugar.nome)}${lugar.estado ? ', ' + esc(lugar.estado) : ''}</p>
        <p class="pequeno texto-suave">Umidade ${a.umidade == null ? '—' : Math.round(a.umidade) + '%'} · Vento ${a.vento == null ? '—' : Math.round(a.vento) + ' km/h'} · Chuva agora ${a.chuva == null ? '—' : a.chuva.toLocaleString('pt-BR') + ' mm'}</p></div></div>
        <ul class="clima-dias">${(p.dias || []).map((d) => { const [t, i] = tempo(d.codigo); return `<li><p class="negrito primeira-maiuscula">${esc(diaSemana(d.dia))}</p>${icone(i, 'ic clima-dia-ic')}<span class="so-leitor">${esc(t)}</span>
          <p><strong>${grau(d.max)}</strong> <span class="texto-suave">${grau(d.min)}</span></p><p class="pequeno texto-suave">${d.chance_chuva == null ? '—' : d.chance_chuva + '% chuva'}${d.chuva_mm ? ' · ' + d.chuva_mm.toLocaleString('pt-BR') + ' mm' : ''}</p></li>`; }).join('')}</ul>
        <p class="pequeno texto-suave mt-3">Dados do Open-Meteo, atualizados a cada hora. Serve para se programar, não é garantia.</p>`;
    } catch (e) {
      saida.innerHTML = `<p role="alert" class="mt-3 texto-alerta">${esc(e.message)}</p>`;
    }
  }
  function escolher(l) {
    opcoes.hidden = true;
    busca.value = '';
    try { localStorage.setItem(CHAVE, JSON.stringify(l)); } catch (e) { /* */ }
    mostrar(l);
  }
  async function procurar(texto) {
    const nome = texto.trim();
    if (nome.length < 3) { saida.innerHTML = '<p role="alert" class="mt-3 texto-alerta">Escreva pelo menos 3 letras do nome da cidade.</p>'; return; }
    try {
      const r = await pedir('/api/clima/lugares?nome=' + encodeURIComponent(nome));
      if (!r.lugares.length) { saida.innerHTML = '<p role="alert" class="mt-3 texto-alerta">Não achamos essa cidade. Tente escrever de outro jeito.</p>'; return; }
      if (r.lugares.length === 1) { escolher(r.lugares[0]); return; }
      opcoes.innerHTML = r.lugares.map((l, i) => `<li><button type="button" class="botao botao-contorno" data-i="${i}">${esc(l.nome)}${l.estado ? ' · ' + esc(l.estado) : ''}</button></li>`).join('');
      opcoes.hidden = false;
      opcoes.querySelectorAll('button').forEach((b) => b.addEventListener('click', () => escolher(r.lugares[+b.dataset.i])));
    } catch (e) {
      saida.innerHTML = `<p role="alert" class="mt-3 texto-alerta">${esc(e.message)}</p>`;
    }
  }
  caixa.querySelector('[data-clima-form]').addEventListener('submit', (ev) => { ev.preventDefault(); procurar(busca.value); });
  let salvo = null;
  try { salvo = JSON.parse(localStorage.getItem(CHAVE) || 'null'); } catch (e) { /* */ }
  if (salvo && salvo.lat != null) { busca.value = ''; mostrar(salvo); }
})();
