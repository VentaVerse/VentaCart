/*
 * Image picker on the store's shared media library (window.VentaCart.media,
 * loaded by <x-media-library /> in the editor shell). The library does the
 * browsing, uploading and permission notes; the field only shows what it
 * holds and stores the chosen file's storage path.
 */

import { h, icon } from '../dom.js';
import { labelOf } from '../state.js';
import { themeGroups } from '../catalog.js';
import { mediaUrl } from '../operations.js';
import { fileName, humanize } from '../util.js';

/** Where the library opens for a theme image the field has never held. */
const FOLDER = 'media/banners';

/** How long to wait for the library's script before saying it is missing. */
const WAIT_MS = 8000;

const UNAVAILABLE = 'The media library is not available on this page.';

/**
 * fctx (optional) is the form's context, { kind, sid, bid, group }, so the
 * library can say which section and field it is choosing for.
 */
export function imagePicker(value, change, setting = {}, fctx = null) {
  const wrap = h('div', { class: 'media' });
  let open = false;

  const draw = (v, note = '') => {
    const thumb = v ? h('img', { class: 'media__thumb', src: mediaUrl(v), alt: '' }) : h('span', { class: 'media__empty' }, icon('image'));
    if (v) {
      thumb.addEventListener('error', () => thumb.replaceWith(h('span', { class: 'media__empty media__empty--missing', title: 'Photo missing' }, icon('alert'))), { once: true });
    }
    const choose = h('button', { type: 'button', class: 'link-btn', 'aria-haspopup': 'dialog' }, v ? 'Change' : 'Choose image');
    choose.addEventListener('click', () => show(choose, v));
    wrap.replaceChildren(
      thumb,
      h('span', { class: 'media__info' },
        h('span', { class: 'media__name', text: v ? fileName(v) : 'No image' }),
        h('span', { class: 'media__actions' },
          choose,
          v ? h('button', { type: 'button', class: 'link-btn', onclick: () => pick('') }, 'Remove') : null),
        note ? h('span', { class: 'media__note', role: 'status', text: note }) : null));
  };

  const pick = (v) => {
    draw(v);
    change(v);
  };

  // The note replaces the field's buttons, so focus goes back to Change.
  const unavailable = (current) => {
    draw(current, UNAVAILABLE);
    wrap.querySelector('button')?.focus?.();
  };

  const show = (opener, current) => {
    if (open) return;
    withLibrary((media) => {
      if (!media) {
        unavailable(current);
        return;
      }
      open = true;
      try {
        media.open({
          mode: 'single',
          value: current || '',
          kind: setting.media_kind || 'theme',
          folder: FOLDER,
          where: whereOf(setting, fctx),
          opener,
          onChoose: ([file] = []) => {
            if (!file?.path) return;
            pick(file.path);
            // The library gave focus back to the button it opened from,
            // which the redraw replaced; Change takes it instead.
            wrap.querySelector('button')?.focus?.();
          },
          onClose: () => { open = false; },
        });
      } catch {
        open = false;
        unavailable(current);
      }
    });
  };

  draw(value);
  return wrap;
}

/**
 * Runs `use` with the library hook. The editor's script can start before
 * the library's, so while the library's settings are on the page its hook
 * is waited for; `use` gets null when there is no library to wait for.
 */
function withLibrary(use) {
  const media = window.VentaCart?.media;
  if (media) {
    use(media);
    return;
  }
  if (!document.querySelector('#media-library-config')) {
    use(null);
    return;
  }
  let done = false;
  const ready = (event) => {
    if (done) return;
    done = true;
    window.clearTimeout(timer);
    use(event?.detail || window.VentaCart?.media || null);
  };
  const timer = window.setTimeout(() => {
    document.removeEventListener('ventacart:media-ready', ready);
    ready(null);
  }, WAIT_MS);
  document.addEventListener('ventacart:media-ready', ready, { once: true });
}

/** The section (or theme settings group) and field, as the library's heading names them. */
function whereOf(setting, fctx) {
  const field = setting.label || humanize(setting.key || 'image');
  let place = '';
  if (fctx?.sid) place = labelOf(fctx.sid);
  else if (fctx?.group) place = themeGroups()?.find((g) => g.group === fctx.group)?.label || '';
  return [place, field].filter(Boolean);
}
