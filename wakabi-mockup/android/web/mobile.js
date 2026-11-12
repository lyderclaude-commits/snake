/* Wakabi 2.0 — mode application : navigation cliquable entre les 29 écrans */
(function () {
  document.body.classList.add('app');
  document.querySelectorAll('a[href]').forEach(a => a.addEventListener('click', e => e.preventDefault()));

  const cols = [...document.querySelectorAll('.col')];
  const N = cols.length;
  const title = i => cols[i - 1].querySelector('.label').textContent.replace(/^\d+\s·\s/, '');
  let cur = 0;
  const hist = [];

  // ---------- mise à l'échelle ----------
  function fit() {
    const s = Math.min(window.innerWidth / 368, window.innerHeight / 822);
    document.documentElement.style.setProperty('--s', s);
  }
  window.addEventListener('resize', fit); fit();

  // ---------- navigation ----------
  function show(n, dir) {
    if (n < 1 || n > N || n === cur) return;
    if (cur) cols[cur - 1].classList.remove('cur', 'in-r', 'in-l');
    const c = cols[n - 1];
    c.classList.remove('in-r', 'in-l'); void c.offsetWidth;
    c.classList.add('cur', dir === 'l' ? 'in-l' : 'in-r');
    cur = n;
    btnNum.textContent = String(n).padStart(2, '0') + '/' + N;
  }
  function go(n) { if (n === cur) return; hist.push(cur); show(n, n > cur ? 'r' : 'l'); }
  function back() {
    if (menu.classList.contains('open')) return closeMenu();
    if (hist.length) show(hist.pop(), 'l');
    else if (window.Android) Android.exit();
  }
  window.appBack = back;

  // ---------- toast ----------
  const toastEl = document.createElement('div'); toastEl.id = 'wk-toast'; document.body.appendChild(toastEl);
  let tt;
  function toast(msg) { toastEl.textContent = msg; toastEl.classList.add('show'); clearTimeout(tt); tt = setTimeout(() => toastEl.classList.remove('show'), 1800); }

  // ---------- liens (hotspots) ----------
  const S = n => cols[n - 1];
  function mark(n, sel, target) { S(n).querySelectorAll(sel).forEach(e => e.dataset.go = target); }
  function markText(n, text, target, up) {
    [...S(n).querySelectorAll('*')].filter(e => e.children.length === 0 && e.textContent.trim() === text)
      .forEach(e => ((up && e.closest(up)) || e).dataset.go = target);
  }
  function markAll(sel, target) { document.querySelectorAll(sel).forEach(e => e.dataset.go = target); }

  // retour / fermer (générique)
  document.querySelectorAll('i[data-i="back"], i[data-i="close"]').forEach(i => {
    const b = i.closest('.bk, .glass, .iconbtn, .fab') || i; b.dataset.go = 'back';
  });
  markAll('.dim', 'back');
  // barre d'onglets
  document.querySelectorAll('nav.tabbar').forEach(nav => {
    const t = nav.children; [6, 9, 26, 10, 17].forEach((g, k) => t[k] && (t[k].dataset.go = g));
  });

  mark(1, '.splash', 2);
  mark(2, '.btn', 3); mark(2, '.link', 3); markText(2, 'Passer', 6);
  mark(3, '.btn.w', 4); mark(3, '.btn.ghost', 5);
  mark(4, '.otp', 5); mark(4, '.kbd > div', 5);
  mark(5, '.btn', 6); mark(5, '.interest > div', 'toggle');
  // Accueil
  mark(6, '.search', 7); mark(6, '.flt', 8); mark(6, '.ev', 11); mark(6, '.hero', 11); mark(6, '.pl', 19);
  mark(6, '.iconbtn', 24); mark(6, '.avatar', 22); markText(6, 'Voir tout', 9); markText(6, 'Carte', 10);
  markText(6, '🍸 Bars', 18, '.chip'); mark(6, '.chip', 18);
  // Recherche / filtres / agenda / carte
  mark(7, '.res', 11); mark(7, '.hdr .bk', 8);
  mark(8, '.btn', 7); mark(8, '.c2', 'toggle'); mark(8, '.sw2', 'switch');
  mark(9, '.tl .card', 11); mark(9, '.cal .d:not(.o)', 'day');
  mark(10, '.mapcard', 19); mark(10, '.mpin', 19); mark(10, '.inp', 7);
  // Événement
  mark(11, '.btn', 14); mark(11, '.tier', 14); mark(11, '.gal', 12); mark(11, '.cta .sec-btn', 'cal');
  markText(11, 'Le Mur', 13); markText(11, 'Galerie', 12);
  mark(13, '.post .act span', 'like');
  // Billetterie
  mark(14, '.btn', 15); mark(15, '.btn', 16); mark(15, '.pay', 'pay');
  mark(16, '.btn.w', 17); mark(16, '.btn.ghost', 'cal');
  mark(17, '.tk-actions > div', 'soon');
  // Lieux
  mark(18, '.pc', 19); mark(18, '.hdr .bk:last-child', 10);
  mark(19, '.btn', 20); mark(19, '.gal', 12); markText(19, 'Avis', 21); markText(19, 'Le Mur', 13);
  mark(19, '.cta .sec-btn', 'soon');
  mark(20, '.btn', 'resa'); mark(20, '.days > div', 'pick'); mark(20, '.slots > div:not(.x)', 'pick'); mark(20, '.c2', 'pick');
  mark(21, '.btn', 'soon');
  // Compte
  const li22 = S(22).querySelectorAll('.li'); [17, 17, 23, 'soon', 'soon'].forEach((g, k) => li22[k] && (li22[k].dataset.go = g));
  mark(22, '.promo', 28); S(22).querySelectorAll('.iconbtn')[1].dataset.go = 25;
  mark(23, '.ev', 11);
  const nt = S(24).querySelectorAll('.nt'); [17, 13, 17, 11, 21].forEach((g, k) => nt[k] && (nt[k].dataset.go = g));
  mark(25, '.sw2', 'switch');
  // Créer / organisateur
  mark(26, '.ctile > div', 27); S(26).querySelector('.fab').dataset.go = 'back';
  mark(27, '.btn:not(.ghost)', 28); mark(27, '.btn.ghost', 'back');
  mark(28, '.qa > div', 'soon'); S(28).querySelector('.qa > div').dataset.go = 29;
  mark(29, '.btn', 'scan');

  function radio(el, sel, on) { el.parentElement.querySelectorAll(':scope > ' + sel).forEach(x => x.classList.remove(on)); el.classList.add(on); }
  const actions = {
    back, toggle: e => e.classList.toggle('on'), switch: e => e.classList.toggle('on'),
    like: e => { e.style.color = e.style.color ? '' : '#E11D48'; },
    day: e => radio(e, '.d', 's'),
    pick: e => radio(e, e.classList.contains('c2') ? '.c2' : 'div', 'on'),
    pay: e => { radio(e, '.pay', 'on'); e.parentElement.querySelectorAll('.radio').forEach(r => r.classList.remove('on')); e.querySelector('.radio').classList.add('on'); },
    cal: () => toast('📅 Ajouté à votre calendrier'),
    resa: () => { toast('✅ Table réservée · Ven. 13 à 21:00'); setTimeout(() => go(17), 900); },
    scan: () => toast('📷 Prêt à scanner le billet suivant'),
    soon: () => toast('Écran non inclus dans cette maquette')
  };

  // ---------- clics ----------
  document.addEventListener('click', e => {
    if (menu.contains(e.target) || e.target.closest('#wk-menu-btn')) return;
    const el = e.target.closest('[data-go]'); if (!el) return;
    const g = el.dataset.go;
    if (actions[g]) actions[g](el); else go(+g);
  });

  // ---------- glisser pour changer d'écran ----------
  let x0 = null, y0 = 0;
  document.addEventListener('touchstart', e => { if (menu.classList.contains('open')) return; x0 = e.touches[0].clientX; y0 = e.touches[0].clientY; }, { passive: true });
  document.addEventListener('touchend', e => {
    if (x0 === null) return;
    const dx = e.changedTouches[0].clientX - x0, dy = e.changedTouches[0].clientY - y0; x0 = null;
    if (Math.abs(dx) > 70 && Math.abs(dy) < 60) { if (dx < 0) go(cur + 1); else go(cur - 1); }
  }, { passive: true });

  // ---------- menu des écrans ----------
  const btn = document.createElement('div'); btn.id = 'wk-menu-btn';
  btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg><span>01/29</span>';
  document.body.appendChild(btn); const btnNum = btn.querySelector('span');
  const menu = document.createElement('div'); menu.id = 'wk-menu';
  let html = '<div class="panel"><div class="grab"></div><h3>Wakabi 2.0 · Maquette</h3><p>' + N + ' écrans · touchez pour y aller</p>';
  let idx = 0;
  document.querySelectorAll('.group, .col').forEach(e => {
    if (e.classList.contains('group')) html += '<h4>' + e.querySelector('h2').textContent + '</h4>';
    else { idx++; html += '<div class="it" data-n="' + idx + '"><b>' + String(idx).padStart(2, '0') + '</b>' + title(idx) + '</div>'; }
  });
  menu.innerHTML = html + '</div>'; document.body.appendChild(menu);
  function closeMenu() { menu.classList.remove('open'); }
  btn.addEventListener('click', () => {
    menu.querySelectorAll('.it').forEach(i => i.classList.toggle('on', +i.dataset.n === cur));
    menu.classList.add('open');
    const on = menu.querySelector('.it.on'); on && on.scrollIntoView({ block: 'center' });
  });
  menu.addEventListener('click', e => {
    const it = e.target.closest('.it'); if (it) { closeMenu(); go(+it.dataset.n); return; }
    if (e.target === menu) closeMenu();
  });

  // ---------- aide au premier lancement ----------
  const hint = document.createElement('div'); hint.id = 'wk-hint';
  hint.innerHTML = '<div class="c"><h3>Bienvenue dans la maquette 👋</h3><ul><li><b>Touchez</b> les boutons, cartes et onglets pour naviguer comme dans la vraie app.</li><li><b>Glissez</b> à gauche / à droite pour passer à l\'écran suivant ou précédent.</li><li>Le bouton <b>≡ en haut</b> liste les 29 écrans.</li><li>Le bouton <b>retour</b> du téléphone revient à l\'écran précédent.</li></ul><div class="ok">C\'est parti</div></div>';
  document.body.appendChild(hint);
  hint.querySelector('.ok').addEventListener('click', () => { hint.classList.remove('show'); try { localStorage.setItem('wk-hint', '1'); } catch (_) {} });

  show(1);
  setTimeout(() => {
    if (cur === 1) go(2);
    let seen = false; try { seen = localStorage.getItem('wk-hint'); } catch (_) {}
    if (!seen) hint.classList.add('show');
  }, 1600);
})();
