/*
 * Image picker on core's media library (routes admin.media.browse and
 * admin.media.upload, permissions media.view and media.create). The setting
 * stores the file's storage path. A user without media access is told whom
 * to ask instead of seeing an error.
 */

import { h, icon, toast } from '../dom.js';
import { ctx } from '../state.js';
import { openPopover, closePopover, reposition } from '../popover.js';
import { mediaUrl } from '../operations.js';
import { fileName, debounce } from '../util.js';

const ALLOWED = /\.(jpe?g|png|webp|avif|gif)$/i;
const UPLOAD_ACCEPT = '.jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp';

const media = () => ctx.boot.media || {};

export function imagePicker(value, change, setting = {}) {
  const wrap = h('div', { class: 'media' });
  const draw = (v) => {
    const thumb = v ? h('img', { class: 'media__thumb', src: mediaUrl(v), alt: '' }) : h('span', { class: 'media__empty' }, icon('image'));
    if (v) {
      thumb.addEventListener('error', () => thumb.replaceWith(h('span', { class: 'media__empty media__empty--missing', title: 'Photo missing' }, icon('alert'))), { once: true });
    }
    wrap.replaceChildren(
      thumb,
      h('span', { class: 'media__info' },
        h('span', { class: 'media__name', text: v ? fileName(v) : 'No image' }),
        h('span', { class: 'media__actions' },
          h('button', {
            type: 'button', class: 'link-btn', 'aria-haspopup': 'dialog',
            onclick: (e) => openLibrary(e.currentTarget, v, pick, setting),
          }, v ? 'Change' : 'Choose image'),
          v ? h('button', { type: 'button', class: 'link-btn', onclick: () => pick('') }, 'Remove') : null)));
  };
  const pick = (v) => {
    draw(v);
    change(v);
  };
  draw(value);
  return wrap;
}

function askForAccess(text) {
  return h('div', { class: 'pick-state' },
    h('span', { class: 'pick-state__icon' }, icon('shield-check')),
    h('p', { class: 'pick-state__title', text: 'Ask an admin for media access' }),
    h('p', { class: 'pick-state__text', text }));
}

function openLibrary(anchor, current, pick) {
  const m = media();
  const body = h('div', { class: 'library-pop' });
  openPopover(anchor, body, { wide: true, label: 'Choose an image' });

  if (m.can_browse === false) {
    body.replaceChildren(h('p', { class: 'pop-title', text: 'Choose an image' }),
      askForAccess('Your account cannot open the media library. An admin can give your user group the Media view permission.'));
    reposition();
    return;
  }

  const state = { path: m.path || '', q: '', page: 1, lastPage: 1, files: [], folders: [], crumbs: [], parent: null, loading: false };
  const search = h('input', { class: 'input pop-search', type: 'search', placeholder: 'Search photos', 'aria-label': 'Search photos' });
  const crumbs = h('nav', { class: 'library__crumbs', 'aria-label': 'Folders' });
  const grid = h('div', { class: 'library', role: 'radiogroup', 'aria-label': 'Photos', 'aria-busy': 'false' });
  const status = h('div', { class: 'library__status', 'aria-live': 'polite' });
  const more = h('button', { type: 'button', class: 'btn btn--secondary btn--sm btn--block', hidden: true, onclick: () => load({ append: true }) }, 'Show more photos');
  const fileInput = h('input', { type: 'file', accept: UPLOAD_ACCEPT, multiple: true, hidden: true, 'aria-label': 'Photos to upload' });
  const uploadBtn = h('button', {
    type: 'button', class: 'btn btn--secondary btn--sm',
    onclick: () => (m.can_upload === false ? showUploadDenied() : fileInput.click()),
  }, icon('plus', 'icon--sm'), 'Upload');
  const foot = h('div', { class: 'library__foot' },
    h('p', { class: 'pop-foot', text: 'JPG, PNG or WebP, up to 5 MB each.' }), uploadBtn, fileInput);

  body.replaceChildren(h('p', { class: 'pop-title', text: 'Choose an image' }), search, crumbs, grid, more, status, foot);

  function showUploadDenied() {
    status.replaceChildren(h('p', { class: 'library__note', text: 'Your account can choose photos but not upload them. Ask an admin for the Media add permission.' }));
  }

  function drawCrumbs() {
    if (state.q) {
      crumbs.replaceChildren(h('span', { text: `Results for "${state.q}"` }));
      return;
    }
    const parts = [h('button', { type: 'button', class: 'link-btn', onclick: () => go('') }, 'Media')];
    state.crumbs.forEach((c, i) => {
      parts.push(icon('chevron-right', 'icon--sm'));
      parts.push(i === state.crumbs.length - 1 ? h('span', { text: c.name }) : h('button', { type: 'button', class: 'link-btn', onclick: () => go(c.path) }, c.name));
    });
    crumbs.replaceChildren(...parts);
  }

  function drawGrid() {
    const folderItems = state.q ? [] : state.folders.map((f) => h('button', {
      type: 'button', class: 'library__folder', title: f.name, onclick: () => go(f.path),
    }, icon('layers'), h('span', { text: f.name })));
    const fileItems = state.files.filter((f) => ALLOWED.test(f.path || f.name)).map((f) => h('button', {
      type: 'button', role: 'radio', 'aria-checked': String(current === f.path), 'aria-label': f.name, title: f.name,
      onclick: () => {
        closePopover();
        pick(f.path);
      },
    }, h('img', { src: f.thumb || f.url, alt: '', loading: 'lazy' })));
    grid.replaceChildren(...folderItems, ...fileItems);
    if (!folderItems.length && !fileItems.length) {
      grid.append(h('p', { class: 'library__empty', text: state.q ? 'No photo matches that search.' : 'No photos in this folder yet. Upload one.' }));
    }
    more.hidden = state.page >= state.lastPage;
  }

  async function load({ append = false } = {}) {
    state.loading = true;
    grid.setAttribute('aria-busy', 'true');
    if (!append) grid.replaceChildren(...Array.from({ length: 6 }, () => h('span', { class: 'sk library__sk', 'aria-hidden': 'true' })));
    status.replaceChildren();
    try {
      const page = append ? state.page + 1 : 1;
      const res = await ctx.api.mediaBrowse({ path: state.path, q: state.q, page, root: m.root || '' });
      state.page = Number(res.page || page);
      state.lastPage = Number(res.lastPage || res.last_page || 1);
      state.path = res.path ?? state.path;
      state.crumbs = res.crumbs || [];
      state.folders = res.folders || [];
      state.files = append ? [...state.files, ...(res.files || [])] : res.files || [];
      drawCrumbs();
      drawGrid();
    } catch (error) {
      if (error.kind === 'forbidden') {
        body.replaceChildren(h('p', { class: 'pop-title', text: 'Choose an image' }),
          askForAccess('Your account cannot open the media library. An admin can give your user group the Media view permission.'));
      } else {
        grid.replaceChildren();
        status.replaceChildren(h('p', { class: 'library__note library__note--error', text: error.message }),
          h('button', { type: 'button', class: 'btn btn--secondary btn--sm', onclick: () => load({ append }) }, 'Try again'));
      }
    } finally {
      state.loading = false;
      grid.setAttribute('aria-busy', 'false');
      reposition();
    }
  }

  function go(path) {
    state.path = path;
    state.q = '';
    search.value = '';
    load();
  }

  search.addEventListener('input', debounce(() => {
    state.q = search.value.trim();
    load();
  }, 300));

  fileInput.addEventListener('change', async () => {
    const files = Array.from(fileInput.files || []);
    fileInput.value = '';
    if (!files.length) return;
    const tooBig = files.find((f) => f.size > 5 * 1024 * 1024);
    if (tooBig) {
      status.replaceChildren(h('p', { class: 'library__note library__note--error', text: `${tooBig.name} is larger than 5 MB. Make it smaller and upload it again.` }));
      return;
    }
    uploadBtn.disabled = true;
    status.replaceChildren(h('p', { class: 'library__note', text: `Uploading ${files.length === 1 ? files[0].name : `${files.length} photos`}...` }));
    try {
      const res = await ctx.api.mediaUpload(files, { path: state.path, root: m.root || '' });
      const added = res.files || [];
      status.replaceChildren(h('p', { class: 'library__note library__note--ok', text: added.length === 1 ? `${added[0].name} uploaded.` : `${added.length} photos uploaded.` }));
      if (added.length === 1) {
        closePopover();
        pick(added[0].path);
        toast(`${added[0].name} uploaded and chosen.`, { tone: 'success' });
        return;
      }
      state.files = [...added, ...state.files];
      drawGrid();
    } catch (error) {
      if (error.kind === 'forbidden') showUploadDenied();
      else {
        const messages = Object.values(error.fields || {}).flat();
        status.replaceChildren(...(messages.length ? messages : [error.message]).map((t) => h('p', { class: 'library__note library__note--error', text: t })));
      }
    } finally {
      uploadBtn.disabled = false;
      reposition();
    }
  });

  load();
}
