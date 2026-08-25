import { start } from './ui';

start().catch((e) => {
  document.body.insertAdjacentHTML(
    'afterbegin',
    `<p style="padding:16px;color:#DC2626">Erreur au démarrage : ${String(e)}</p>`,
  );
});
