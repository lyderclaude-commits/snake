/* Live Y Dream — administration.
   Tout ce qui écrit passe par un formulaire classique ; ce script ajoute
   l'éditeur de texte, le choix d'image et la boucle d'import. */
(function () {
  'use strict';
  var csrf = document.body.dataset.csrf || '';
  var base = document.body.dataset.base || '';

  function poster(url, donnees) {
    var fd = donnees instanceof FormData ? donnees : new FormData();
    if (!(donnees instanceof FormData)) Object.keys(donnees || {}).forEach(function (k) { fd.append(k, donnees[k]); });
    fd.append('_csrf', csrf);
    return fetch(base + '/' + url, { method: 'POST', body: fd, credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) {
        return r.text().then(function (t) {
          try { return JSON.parse(t); } catch (e) { throw new Error('Réponse inattendue du serveur (HTTP ' + r.status + ') : ' + t.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 200)); }
        });
      });
  }

  /* ---- confirmations ---- */
  document.querySelectorAll('form[data-confirmer]').forEach(function (f) {
    f.addEventListener('submit', function (e) { if (!confirm(f.dataset.confirmer)) e.preventDefault(); });
  });

  /* ---- « / » ouvre la recherche du tableau de bord ---- */
  var q = document.getElementById('bord-q');
  if (q) document.addEventListener('keydown', function (e) {
    if (e.key === '/' && !/INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName) && !document.activeElement.isContentEditable) { e.preventDefault(); q.focus(); }
  });

  /* ---- les menus se referment quand on clique ailleurs ---- */
  document.addEventListener('click', function (e) {
    document.querySelectorAll('details.deroulant[open], details.menu[open]').forEach(function (d) { if (!d.contains(e.target)) d.removeAttribute('open'); });
  });

  /* ---- copier une adresse ---- */
  document.querySelectorAll('[data-copier]').forEach(function (b) {
    b.addEventListener('click', function () {
      navigator.clipboard.writeText(b.dataset.copier).then(function () { var t = b.textContent; b.textContent = 'Copiée ✓'; setTimeout(function () { b.textContent = t; }, 1500); });
    });
  });

  /* ---- glisser-déposer dans la médiathèque ---- */
  var depot = document.querySelector('[data-depot]');
  if (depot) {
    var champ = depot.querySelector('input[type=file]');
    ['dragenter', 'dragover'].forEach(function (ev) { depot.addEventListener(ev, function (e) { e.preventDefault(); depot.classList.add('survol'); }); });
    ['dragleave', 'drop'].forEach(function (ev) { depot.addEventListener(ev, function (e) { e.preventDefault(); depot.classList.remove('survol'); }); });
    depot.addEventListener('drop', function (e) { if (e.dataTransfer.files.length) { champ.files = e.dataTransfer.files; depot.submit(); } });
  }

  /* ---- sélecteur de médias ---- */
  var fenetre = document.getElementById('selecteur-medias');
  var auChoix = null;
  function ouvrirSelecteur(rappel) {
    if (!fenetre) return;
    auChoix = rappel;
    var grille = fenetre.querySelector('[data-grille]'), etat = fenetre.querySelector('[data-etat]');
    etat.textContent = 'Chargement…';
    grille.innerHTML = '';
    fetch(base + '/admin/medias/liste.json', { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (d) {
      etat.textContent = d.medias.length ? 'Cliquez sur une image pour la choisir.' : 'La médiathèque est vide : téléversez une image.';
      d.medias.forEach(function (m) { grille.appendChild(vignette(m)); });
    });
    fenetre.showModal();
  }
  function vignette(m) {
    var div = document.createElement('div');
    div.className = 'media';
    var b = document.createElement('button');
    b.type = 'button';
    b.className = 'choisir';
    var img = document.createElement('img');
    img.src = m.url; img.alt = m.nom; img.loading = 'lazy';
    b.appendChild(img);
    var bas = document.createElement('div');
    bas.className = 'bas';
    bas.innerHTML = '<b></b><span></span>';
    bas.querySelector('b').textContent = m.nom;
    bas.querySelector('span').textContent = m.largeur + '×' + m.hauteur;
    div.appendChild(b); div.appendChild(bas);
    b.addEventListener('click', function () { fenetre.close(); if (auChoix) auChoix(m); });
    return div;
  }
  if (fenetre) {
    fenetre.querySelector('[data-fermer]').addEventListener('click', function () { fenetre.close(); });
    fenetre.addEventListener('click', function (e) { if (e.target === fenetre) fenetre.close(); });
    fenetre.querySelector('[data-televerser]').addEventListener('change', function (e) {
      var f = e.target.files[0];
      if (!f) return;
      var etat = fenetre.querySelector('[data-etat]');
      etat.textContent = 'Envoi de ' + f.name + '…';
      var fd = new FormData(); fd.append('fichier', f); fd.append('format', 'json');
      poster('admin/medias/televerser', fd).then(function (d) {
        e.target.value = '';
        if (!d.ok) { etat.textContent = d.erreur || 'Échec de l’envoi.'; return; }
        fenetre.close();
        if (auChoix) auChoix(d.media);
      }).catch(function (err) { etat.textContent = err.message; });
    });
  }

  /* ---- les champs image : couverture d'un article, image ou logo d'une collection ---- */
  document.querySelectorAll('[data-image]').forEach(function (bloc) {
    var cadre = bloc.querySelector('[data-couverture]'), champC = bloc.querySelector('[data-couverture-champ]');
    var choisir = bloc.querySelector('[data-choisir-couverture]'), retirer = bloc.querySelector('[data-retirer-couverture]');
    var vide = cadre.dataset.vide || 'Aucune image';
    choisir.addEventListener('click', function () {
      ouvrirSelecteur(function (m) {
        champC.value = m.chemin;
        cadre.innerHTML = '';
        var img = document.createElement('img'); img.src = m.url; img.alt = '';
        cadre.appendChild(img);
        retirer.hidden = false;
      });
    });
    retirer.addEventListener('click', function () { champC.value = ''; cadre.textContent = vide; retirer.hidden = true; });
  });

  /* ---- adresse déduite du titre ---- */
  var titre = document.getElementById('titre'), slug = document.getElementById('slug'), apercu = document.getElementById('slug-apercu');
  function slugifier(t) { return t.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/œ/g, 'oe').replace(/æ/g, 'ae').replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, ''); }
  if (titre && slug && apercu) {
    var maj = function () { apercu.textContent = slugifier(slug.value) || slugifier(titre.value) || 'votre-titre'; };
    titre.addEventListener('input', maj); slug.addEventListener('input', maj);
  }

  /* ---- l'éditeur ---- */
  document.querySelectorAll('[data-editeur]').forEach(function (ed) {
    var zone = ed.querySelector('.editeur-zone'), source = ed.querySelector('.editeur-source'), cache = ed.querySelector('[data-editeur-champ]');
    var form = ed.closest('form');
    var enSource = false;
    try { document.execCommand('defaultParagraphSeparator', false, 'p'); } catch (e) { /* ancien navigateur */ }

    var vide = function () {
      var t = zone.textContent.trim();
      if (!t && !zone.querySelector('img,iframe')) zone.setAttribute('data-vide', ''); else zone.removeAttribute('data-vide');
    };
    zone.addEventListener('input', vide); vide();

    // Une zone vide commence par un paragraphe, sinon le premier texte tapé flotte hors de tout bloc.
    zone.addEventListener('focus', function () { if (!zone.innerHTML.trim()) zone.innerHTML = '<p><br></p>'; });

    var garderSelection = null;
    zone.addEventListener('blur', function () { var s = getSelection(); if (s.rangeCount) garderSelection = s.getRangeAt(0); });
    var restaurer = function () { zone.focus(); if (garderSelection) { var s = getSelection(); s.removeAllRanges(); s.addRange(garderSelection); } };

    ed.querySelectorAll('[data-cmd]').forEach(function (b) {
      b.addEventListener('mousedown', function (e) { e.preventDefault(); });
      b.addEventListener('click', function () {
        if (enSource) return;
        zone.focus();
        var val = b.dataset.val ? '<' + b.dataset.val + '>' : null;
        document.execCommand(b.dataset.cmd, false, val);
        vide();
      });
    });

    var insererHTML = function (html) { restaurer(); document.execCommand('insertHTML', false, html); vide(); };
    var echap = function (s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };

    ed.querySelector('[data-action=lien]').addEventListener('click', function () {
      if (enSource) return;
      var s = getSelection();
      if (s.rangeCount) garderSelection = s.getRangeAt(0);
      var u = prompt('Adresse du lien (https://…). Vide pour retirer le lien :', 'https://');
      if (u === null) return;
      restaurer();
      if (!u || u === 'https://') document.execCommand('unlink');
      else if (/^(https?:\/\/|mailto:|tel:|\/)/i.test(u)) document.execCommand('createLink', false, u);
      else alert('L’adresse doit commencer par https://, mailto: ou tel:');
    });
    ed.querySelector('[data-action=image]').addEventListener('click', function () {
      if (enSource) return;
      var s = getSelection();
      if (s.rangeCount && zone.contains(s.anchorNode)) garderSelection = s.getRangeAt(0);
      ouvrirSelecteur(function (m) {
        var leg = prompt('Légende de l’image (facultatif) :', '') || '';
        insererHTML('<figure><img src="' + echap(m.url) + '" alt="' + echap(leg) + '">' + (leg ? '<figcaption>' + echap(leg) + '</figcaption>' : '') + '</figure><p><br></p>');
      });
    });
    ed.querySelector('[data-action=video]').addEventListener('click', function () {
      if (enSource) return;
      var s = getSelection();
      if (s.rangeCount && zone.contains(s.anchorNode)) garderSelection = s.getRangeAt(0);
      var u = prompt('Adresse de la vidéo YouTube ou Vimeo :', '');
      if (!u) return;
      var m = /(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|shorts\/|embed\/))([\w-]{6,})/.exec(u), v = /vimeo\.com\/(\d+)/.exec(u);
      var src = m ? 'https://www.youtube-nocookie.com/embed/' + m[1] : v ? 'https://player.vimeo.com/video/' + v[1] : null;
      if (!src) { alert('Seules les vidéos YouTube et Vimeo s’intègrent.'); return; }
      insererHTML('<p><iframe src="' + src + '" title="Vidéo" allowfullscreen></iframe></p><p><br></p>');
    });
    var bSource = ed.querySelector('[data-action=source]');
    bSource.addEventListener('click', function () {
      enSource = !enSource;
      if (enSource) { source.value = zone.innerHTML; } else { zone.innerHTML = source.value; vide(); }
      ed.classList.toggle('en-source', enSource);
      bSource.setAttribute('aria-pressed', enSource ? 'true' : 'false');
    });

    // Sélectionner une image d'un clic, la supprimer au clavier.
    zone.addEventListener('click', function (e) {
      zone.querySelectorAll('img.choisie').forEach(function (i) { i.classList.remove('choisie'); });
      if (e.target.tagName === 'IMG') e.target.classList.add('choisie');
    });
    zone.addEventListener('keydown', function (e) {
      var c = zone.querySelector('img.choisie');
      if (c && (e.key === 'Delete' || e.key === 'Backspace')) { e.preventDefault(); (c.closest('figure') || c).remove(); vide(); }
    });

    // Un collage depuis Word arrive avec ses styles : on ne garde que la structure, le serveur fera le reste.
    zone.addEventListener('paste', function (e) {
      var html = e.clipboardData && e.clipboardData.getData('text/html');
      if (!html) return;
      e.preventDefault();
      var doc = new DOMParser().parseFromString(html, 'text/html');
      doc.querySelectorAll('style,script,meta,link,o\\:p,xml').forEach(function (n) { n.remove(); });
      doc.body.querySelectorAll('*').forEach(function (n) { ['style', 'class', 'id', 'lang', 'face', 'color', 'size', 'align'].forEach(function (a) { n.removeAttribute(a); }); });
      document.execCommand('insertHTML', false, doc.body.innerHTML);
      vide();
    });

    form.addEventListener('submit', function () {
      if (enSource) zone.innerHTML = source.value;
      zone.querySelectorAll('img.choisie').forEach(function (i) { i.classList.remove('choisie'); });
      cache.value = zone.textContent.trim() || zone.querySelector('img,iframe') ? zone.innerHTML : '';
    });

    // Ne pas perdre un texte en quittant la page par mégarde.
    var sale = false;
    form.addEventListener('input', function () { sale = true; });
    zone.addEventListener('input', function () { sale = true; });
    form.addEventListener('submit', function () { sale = false; });
    window.addEventListener('beforeunload', function (e) { if (sale) { e.preventDefault(); e.returnValue = ''; } });
  });

  /* ---- traduction DeepL dans l'éditeur ---- */
  var bTrad = document.querySelector('[data-traduire]');
  if (bTrad) {
    var formA = bTrad.closest('form'), etatT = formA.querySelector('[data-traduire-etat]');
    var zones = formA.querySelectorAll('.editeur-zone');
    bTrad.addEventListener('click', function () {
      var titreFr = formA.querySelector('#titre').value.trim();
      if (!titreFr) { etatT.textContent = 'Écrivez d’abord le titre français.'; return; }
      var deja = formA.querySelector('#titre_en').value.trim() || zones[1].textContent.trim();
      if (deja && !confirm('Remplacer la version anglaise actuelle par une traduction automatique ?')) return;
      bTrad.disabled = true;
      etatT.textContent = 'Traduction en cours…';
      poster('admin/traduction/apercu', { titre: titreFr, extrait: formA.querySelector('#extrait').value, contenu: zones[0].innerHTML }).then(function (d) {
        if (!d.ok) throw new Error(d.erreur || 'DeepL n’a pas répondu.');
        formA.querySelector('#titre_en').value = d.titre_en;
        formA.querySelector('#extrait_en').value = d.extrait_en;
        zones[1].innerHTML = d.contenu_en;
        zones[1].removeAttribute('data-vide');
        zones[1].dispatchEvent(new Event('input', { bubbles: true }));
        formA.querySelector('[data-traduction-auto]').value = '1';
        formA.querySelector('[data-relue]').hidden = false;
        var p = formA.querySelector('[data-etat-traduction]');
        p.className = 'pastille nouveau'; p.textContent = 'Traduction automatique, à relire';
        etatT.textContent = '✓ Traduit (' + d.caracteres.toLocaleString('fr-FR') + ' caractères). Relisez, puis enregistrez.';
      }).catch(function (e) { etatT.textContent = '✗ ' + e.message; }).then(function () { bTrad.disabled = false; });
    });
  }

  /* ---- traduction de tous les articles ---- */
  var lotT = document.querySelector('[data-traduction-lot]');
  if (lotT) {
    var bLot = lotT.querySelector('[data-lancer-traduction]'), etatL = lotT.querySelector('[data-etat-lot]'), jL = lotT.querySelector('[data-journal-lot]');
    var barreL = lotT.querySelector('[data-barre-lot]'), jaugeL = barreL.querySelector('i'), depart = parseInt(lotT.dataset.restants, 10) || 0;
    // Sans clé, ou quand tout est traduit, il n'y a pas de bouton de lancement.
    if (bLot) bLot.addEventListener('click', function () {
      bLot.disabled = true;
      jL.textContent = '';
      barreL.hidden = false;
      var faits = 0, car = 0;
      var tour = function () {
        etatL.textContent = 'Traduction… ' + faits + ' article(s) traduit(s)';
        return poster('admin/traduction/lot', {}).then(function (d) {
          (d.journal || []).forEach(function (l) { jL.textContent += l + '\n'; jL.scrollTop = jL.scrollHeight; });
          faits += d.faits; car += d.caracteres;
          jaugeL.style.width = (depart ? Math.round((depart - d.restants) / depart * 100) : 100) + '%';
          if (!d.ok) throw new Error(d.erreur);
          if (d.restants > 0 && d.faits > 0) return tour();
          etatL.innerHTML = '<b>✓ Terminé.</b> ';
          etatL.appendChild(document.createTextNode(faits + ' article(s) traduit(s), ' + car.toLocaleString('fr-FR') + ' caractères. Ils sont marqués « à relire » dans la liste des articles.'));
        });
      };
      tour().catch(function (e) {
        etatL.innerHTML = '<b>✗ Arrêté.</b> ';
        etatL.appendChild(document.createTextNode(faits + ' article(s) traduit(s) avant l’arrêt. ' + e.message));
      }).then(function () { bLot.disabled = false; });
    });
    var bTest = document.querySelector('[data-tester-deepl]');
    if (bTest) bTest.addEventListener('click', function () {
      var et = document.querySelector('[data-etat-deepl]');
      et.textContent = 'Interrogation de DeepL…';
      poster('admin/traduction/tester', {}).then(function (u) {
        et.textContent = u.ok ? '✓ DeepL répond : ' + u.utilises.toLocaleString('fr-FR') + ' caractères utilisés ce mois-ci sur ' + u.limite.toLocaleString('fr-FR') + '.' : '✗ ' + u.erreur;
      });
    });
  }

  /* ---- notifications push de cet appareil ---- */
  var push = document.querySelector('[data-push]');
  if (push) {
    var etatP = push.querySelector('[data-push-etat]'), bOn = push.querySelector('[data-push-activer]'), bOff = push.querySelector('[data-push-couper]');
    var dire = function (t, genre) { etatP.hidden = false; etatP.className = 'msg ' + (genre || 'info'); etatP.textContent = t; };
    var b64 = function (s) {
      var p = '='.repeat((4 - s.length % 4) % 4), b = atob((s + p).replace(/-/g, '+').replace(/_/g, '/')), u = new Uint8Array(b.length);
      for (var i = 0; i < b.length; i++) u[i] = b.charCodeAt(i);
      return u;
    };
    var ios = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.navigator.standalone;
    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
      if (ios) push.querySelector('[data-push-ios]').hidden = false;
      else dire('Ce navigateur ne sait pas recevoir de notifications. Essayez Chrome, Edge ou Firefox.', 'attention');
    } else if (!window.isSecureContext) {
      dire('Les notifications exigent une adresse en https://. Elles s’activeront une fois le site en ligne avec son certificat.', 'attention');
    } else {
      var enregistrement = navigator.serviceWorker.register(base + '/sw.js', { scope: base + '/' });
      var montrer = function () {
        enregistrement.then(function (r) { return r.pushManager.getSubscription(); }).then(function (ab) {
          bOn.hidden = !!ab; bOff.hidden = !ab;
          if (Notification.permission === 'denied') {
            bOn.hidden = true;
            dire('Les notifications sont bloquées pour ce site dans les réglages du navigateur. Autorisez-les (icône du cadenas, à gauche de l’adresse), puis rechargez la page.', 'attention');
          } else if (ab) {
            dire('Cet appareil reçoit les notifications des nouvelles demandes.', 'ok');
          }
        });
      };
      montrer();
      bOn.addEventListener('click', function () {
        bOn.disabled = true;
        fetch(base + '/admin/push/cle', { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (c) {
          if (!c.ok) throw new Error('Le serveur ne peut pas envoyer de notifications' + (c.manque && c.manque.length ? ' (il manque : ' + c.manque.join(', ') + ')' : '') + '.');
          return Notification.requestPermission().then(function (perm) {
            if (perm !== 'granted') throw new Error('Vous n’avez pas autorisé les notifications.');
            return enregistrement;
          }).then(function (r) {
            return r.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: b64(c.cle) });
          });
        }).then(function (ab) {
          var j = ab.toJSON();
          return poster('admin/push/abonner', { endpoint: ab.endpoint, p256dh: j.keys.p256dh, auth: j.keys.auth });
        }).then(function (d) {
          if (!d.ok) throw new Error(d.erreur || 'L’abonnement a été refusé.');
          location.reload();
        }).catch(function (e) { dire(e.message, 'err'); }).then(function () { bOn.disabled = false; });
      });
      bOff.addEventListener('click', function () {
        enregistrement.then(function (r) { return r.pushManager.getSubscription(); }).then(function (ab) {
          if (!ab) return null;
          return poster('admin/push/desabonner', { endpoint: ab.endpoint, format: 'json' }).then(function () { return ab.unsubscribe(); });
        }).then(function () { location.reload(); });
      });
    }
  }

  /* ---- import WordPress ---- */
  var imp = document.querySelector('[data-import]');
  if (imp) {
    var racine = imp.querySelector('#racine'), etat = imp.querySelector('[data-etat-import]'), journal = imp.querySelector('[data-journal]');
    var barre = imp.querySelector('[data-barre]'), jauge = barre.querySelector('i');
    var bLancer = imp.querySelector('[data-lancer]'), bSonder = imp.querySelector('[data-sonder]'), ecraser = imp.querySelector('[data-ecraser]');
    var ecrire = function (l) { journal.textContent += l + '\n'; journal.scrollTop = journal.scrollHeight; };
    var occupe = function (o) { bLancer.disabled = o; bSonder.disabled = o; };

    bSonder.addEventListener('click', function () {
      occupe(true);
      etat.textContent = 'Connexion à ' + racine.value + '…';
      poster('admin/import/sonder', { racine: racine.value }).then(function (d) {
        etat.innerHTML = '';
        var b = document.createElement('b');
        if (d.ok) { b.textContent = '✓ Connexion réussie : '; etat.appendChild(b); etat.appendChild(document.createTextNode(d.total + ' article(s) publié(s) à importer.')); }
        else { b.textContent = '✗ Échec : '; etat.appendChild(b); etat.appendChild(document.createTextNode(d.erreur || 'WordPress ne répond pas.')); }
      }).catch(function (e) { etat.textContent = e.message; }).then(function () { occupe(false); });
    });

    bLancer.addEventListener('click', function () {
      occupe(true);
      journal.textContent = '';
      barre.hidden = false;
      jauge.style.width = '2%';
      var bilan = { crees: 0, maj: 0, gardes: 0, images: 0, images_echec: 0 };
      var lot = function (page) {
        etat.textContent = 'Lot ' + page + '…';
        return poster('admin/import/lot', { racine: racine.value, page: page, ecraser: ecraser.checked ? '1' : '' }).then(function (d) {
          if (!d.ok) throw new Error(d.erreur || 'échec du lot ' + page);
          (d.journal || []).forEach(ecrire);
          Object.keys(bilan).forEach(function (k) { bilan[k] += d[k] || 0; });
          var pages = Math.max(d.pages || 0, 1);
          jauge.style.width = Math.min(100, Math.round(page / pages * 100)) + '%';
          if (page < pages && (d.journal || []).length) return lot(page + 1);
        });
      };
      lot(1).then(function () {
        jauge.style.width = '100%';
        etat.innerHTML = '<b>✓ Import terminé.</b> ';
        etat.appendChild(document.createTextNode(bilan.crees + ' créé(s), ' + bilan.maj + ' mis à jour, ' + bilan.gardes + ' gardé(s) (retouchés ici), '
          + bilan.images + ' image(s) rapatriée(s)' + (bilan.images_echec ? ', ' + bilan.images_echec + ' image(s) non récupérée(s) — voir l’étape 2' : '') + '.'));
        var n = document.querySelector('[data-n-importes]');
        if (n) n.textContent = parseInt(n.textContent, 10) + bilan.crees;
      }).catch(function (e) {
        etat.innerHTML = '<b>✗ Import interrompu.</b> ';
        etat.appendChild(document.createTextNode(e.message + ' — relancez : les articles déjà importés ne seront pas dupliqués.'));
      }).then(function () { occupe(false); });
    });
  }

  var ims = document.querySelector('[data-images]');
  if (ims) {
    var bR = ims.querySelector('[data-rapatrier]'), etatI = ims.querySelector('[data-etat-images]'), jI = ims.querySelector('[data-journal-images]');
    var reprendre = ims.querySelector('[data-reprendre]'), compte = document.querySelector('[data-n-exterieures]');
    bR.addEventListener('click', function () {
      bR.disabled = true;
      jI.textContent = '';
      var total = 0, premier = true;
      var tour = function () {
        etatI.textContent = 'Téléchargement… ' + total + ' image(s) rapatriée(s)';
        return poster('admin/import/images', { reprendre: premier && reprendre.checked ? '1' : '' }).then(function (d) {
          premier = false;
          total += d.faites;
          (d.echecs || []).forEach(function (l) { jI.textContent += '✗ ' + l + '\n'; });
          if (compte) compte.textContent = d.restantes;
          if (d.restantes > 0 && (d.faites > 0 || (d.echecs || []).length)) return tour();
          etatI.innerHTML = '<b>✓ Terminé.</b> ';
          etatI.appendChild(document.createTextNode(total + ' image(s) rapatriée(s).' + (jI.textContent ? ' Certaines n’ont pas pu être lues : leur site d’origine ne répond pas.' : '')));
        });
      };
      tour().catch(function (e) { etatI.textContent = e.message; }).then(function () { bR.disabled = false; });
    });
  }
})();
