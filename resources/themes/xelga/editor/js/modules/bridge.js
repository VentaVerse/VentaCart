/*
 * The editor half of the preview bridge, protocol version 1 (ARCHITECTURE
 * 7.4). Messages are plain objects {source, v, type, ...} sent with
 * postMessage to the store's own origin. Both directions are checked: a
 * message is only taken when it comes from the preview frame's window, from
 * the store's origin, says it is from the theme, speaks version 1 and has a
 * known type. Anything else is dropped without a word.
 */

export const PROTOCOL = 1;

/* What the preview may tell the editor. `page-data` carries the fresh list
   of sections the preview posts after every change of structure. */
const FROM_THEME = new Set(['ready', 'select', 'navigated', 'duplicated', 'error', 'page-data']);

/* What the editor may tell the preview. */
const TO_THEME = new Set([
  'highlight', 'select', 'field', 'setting', 'theme-setting',
  'move', 'remove', 'restore', 'duplicate', 'insert', 'replace',
  'block-insert', 'block-remove', 'block-restore', 'block-move', 'rename', 'empty',
]);

export function createBridge({ frame, origin }) {
  const handlers = new Map();
  let targetOrigin = origin;

  function onMessage(event) {
    if (event.origin !== targetOrigin) return;
    if (!frame.contentWindow || event.source !== frame.contentWindow) return;
    const msg = event.data;
    if (!msg || typeof msg !== 'object' || msg.source !== 'theme' || msg.v !== PROTOCOL) return;
    if (!FROM_THEME.has(msg.type)) return;
    (handlers.get(msg.type) || []).forEach((fn) => {
      try {
        fn(msg);
      } catch (error) {
        console.error(`The editor failed to handle "${msg.type}" from the preview.`, error);
      }
    });
  }

  window.addEventListener('message', onMessage);

  return {
    /** Sends one message; answers false when there is no frame to send to. */
    post(type, payload = {}) {
      if (!TO_THEME.has(type)) throw new Error(`Unknown bridge message "${type}".`);
      const target = frame.contentWindow;
      if (!target) return false;
      target.postMessage({ ...payload, source: 'editor', v: PROTOCOL, type }, targetOrigin);
      return true;
    },

    on(type, fn) {
      if (!FROM_THEME.has(type)) throw new Error(`Unknown bridge message "${type}".`);
      if (!handlers.has(type)) handlers.set(type, []);
      handlers.get(type).push(fn);
    },

    /** The store origin can only change when the editor is pointed at another store. */
    setOrigin(value) {
      targetOrigin = value;
    },

    get origin() {
      return targetOrigin;
    },

    destroy() {
      window.removeEventListener('message', onMessage);
      handlers.clear();
    },
  };
}
