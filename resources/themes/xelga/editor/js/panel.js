/*
 * The theme editor app. The server's shell (views/editor/shell.blade.php)
 * loads this entry with editor/css/panel.css and hands over the boot payload
 * as JSON in <script type="application/json" id="xelga-boot">.
 */

import { readBoot, BootError } from './modules/boot.js';
import { createApi } from './modules/api.js';
import { startEditor } from './modules/app.js';

function fail(message) {
  const box = document.createElement('div');
  box.className = 'boot-error';
  box.setAttribute('role', 'alert');
  const title = document.createElement('h1');
  title.textContent = 'The theme editor could not start';
  const text = document.createElement('p');
  text.textContent = `${message} Reload the page. If this keeps happening, the theme may need to be reinstalled.`;
  box.append(title, text);
  (document.getElementById('xelga-editor') || document.body).replaceChildren(box);
}

function boot() {
  try {
    const payload = readBoot();
    window.xelgaEditor = startEditor({ boot: payload, api: createApi(payload) });
  } catch (error) {
    console.error(error);
    fail(error instanceof BootError ? error.message : 'Something went wrong while starting it.');
  }
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
else boot();
