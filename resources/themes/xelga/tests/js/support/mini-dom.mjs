/*
 * A small stand-in for the browser DOM, enough for the editor's markup
 * cleaner and its board to run under Node: elements with attributes,
 * classes, styles, listeners and children, template elements with their
 * own content, a parser for plain test markup (tags, quoted attributes,
 * text, comments, void elements), simple selectors (tag, #id, .class,
 * attributes, :not of an attribute, and a space between them for a
 * descendant) and `document.createElement`, `createElementNS`,
 * `createTextNode`, `importNode` and `baseURI`.
 *
 * Only for tests; it is not a full HTML parser.
 */

const VOID = new Set(['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'source', 'track', 'wbr']);

class Node {
  constructor() {
    this.parentNode = null;
    this.childNodes = [];
  }

  get children() {
    return this.childNodes.filter((n) => n instanceof Element);
  }

  append(...nodes) {
    nodes.forEach((n) => {
      if (typeof n === 'string') n = new Text(n);
      n.parentNode?.removeChild(n);
      n.parentNode = this;
      this.childNodes.push(n);
    });
  }

  removeChild(n) {
    this.childNodes = this.childNodes.filter((c) => c !== n);
    n.parentNode = null;
  }

  replaceChildren(...nodes) {
    this.childNodes.forEach((c) => { c.parentNode = null; });
    this.childNodes = [];
    this.append(...nodes);
  }

  get firstElementChild() {
    return this.children[0] ?? null;
  }

  /** The text of the node and everything under it. */
  get textContent() {
    return this.childNodes.map((n) => n.textContent).join('');
  }

  set textContent(value) {
    this.replaceChildren(String(value ?? ''));
  }

  remove() {
    this.parentNode?.removeChild(this);
  }

  /** Every element under this one, in document order, that the selector matches. */
  querySelectorAll(selector) {
    if (selector === '*') return this.descendants();
    return this.descendants().filter((el) => selector.split(',').some((one) => matchesChain(el, one.trim().split(/\s+/))));
  }

  descendants() {
    const out = [];
    const walk = (node) => node.children.forEach((el) => { out.push(el); walk(el); });
    walk(this);
    return out;
  }

  get outerHTMLs() {
    return this.childNodes.map((n) => n.html()).join('');
  }
}

class Text extends Node {
  constructor(text) {
    super();
    this.text = text;
  }

  get textContent() {
    return this.text;
  }

  html() {
    return this.text;
  }

  clone() {
    return new Text(this.text);
  }
}

class Comment extends Node {
  constructor(text) {
    super();
    this.text = text;
  }

  get textContent() {
    return '';
  }

  html() {
    return `<!--${this.text}-->`;
  }

  clone() {
    return new Comment(this.text);
  }
}

export class Fragment extends Node {
  html() {
    return this.outerHTMLs;
  }

  get innerHTML() {
    return this.outerHTMLs;
  }
}

export class Element extends Node {
  constructor(tag) {
    super();
    this.localName = tag.toLowerCase();
    this.tagName = tag.toUpperCase();
    this.attrs = [];
    if (this.localName === 'template') this.content = new Fragment();
  }

  get attributes() {
    return this.attrs.map((a) => ({ ...a }));
  }

  getAttribute(name) {
    return this.attrs.find((a) => a.name === name.toLowerCase())?.value ?? null;
  }

  hasAttribute(name) {
    return this.getAttribute(name) !== null;
  }

  setAttribute(name, value) {
    const key = name.toLowerCase();
    const found = this.attrs.find((a) => a.name === key);
    if (found) found.value = String(value);
    else this.attrs.push({ name: key, value: String(value) });
  }

  removeAttribute(name) {
    this.attrs = this.attrs.filter((a) => a.name !== name.toLowerCase());
  }

  /**
   * `tag`, `#id`, `.class`, `[attr]`, `[attr="value"]`, these one after
   * the other, `:not([attr])` after them, and a comma-separated list of
   * these. A space between them asks for a descendant.
   */
  matches(selector) {
    return selector.split(',').some((one) => matchesChain(this, one.trim().split(/\s+/)));
  }

  matchesOne(selector) {
    const m = /^([a-z0-9-]*)((?:#[\w-]+)?)((?:\.[\w-]+)*)((?:\[[^\]]+\])*)((?::not\(\[[^\]]+\]\))*)$/i.exec(selector.trim());
    if (!m) throw new Error(`mini-dom cannot match ${selector}`);
    if (m[1] && m[1].toLowerCase() !== this.localName) return false;
    if (m[2] && this.id !== m[2].slice(1)) return false;
    if (m[3] && !m[3].split('.').filter(Boolean).every((c) => this.classList.contains(c))) return false;
    const test = ([, name, value]) => (value === undefined ? this.hasAttribute(name) : this.getAttribute(name) === value);
    const tests = [...m[4].matchAll(/\[([^\]=]+)(?:="([^"]*)")?\]/g)];
    const nots = [...m[5].matchAll(/:not\(\[([^\]=]+)(?:="([^"]*)")?\]\)/g)];
    return tests.every(test) && !nots.some(test);
  }

  get className() {
    return this.getAttribute('class') ?? '';
  }

  set className(value) {
    this.setAttribute('class', value);
  }

  /** The element's classes: add, remove, toggle and contains. */
  get classList() {
    const list = () => this.className.split(/\s+/).filter(Boolean);
    const write = (names) => this.setAttribute('class', names.join(' '));
    return {
      contains: (name) => list().includes(name),
      add: (...names) => write([...new Set([...list(), ...names])]),
      remove: (...names) => write(list().filter((n) => !names.includes(n))),
      toggle: (name, force) => {
        const on = force ?? !list().includes(name);
        if (on) write([...new Set([...list(), name])]);
        else write(list().filter((n) => n !== name));
        return on;
      },
    };
  }

  /** Inline styles, kept as plain properties. */
  get style() {
    this.styles ??= {};
    return this.styles;
  }

  /** The element's `data-*` attributes as camel-case properties, readable, writable and deletable. */
  get dataset() {
    const attr = (key) => `data-${key.replace(/[A-Z]/g, (c) => `-${c.toLowerCase()}`)}`;
    return new Proxy({}, {
      get: (_, key) => this.getAttribute(attr(key)) ?? undefined,
      set: (_, key, value) => { this.setAttribute(attr(key), value); return true; },
      deleteProperty: (_, key) => { this.removeAttribute(attr(key)); return true; },
    });
  }

  get id() {
    return this.getAttribute('id') ?? '';
  }

  contains(node) {
    for (let n = node; n; n = n.parentNode) if (n === this) return true;
    return false;
  }

  closest(selector) {
    for (let n = this; n instanceof Element; n = n.parentNode) if (n.matches(selector)) return n;
    return null;
  }

  querySelector(selector) {
    return this.querySelectorAll(selector)[0] ?? null;
  }

  addEventListener(type, fn) {
    (this.listeners ??= []).push({ type, fn });
  }

  removeEventListener(type, fn) {
    this.listeners = (this.listeners ?? []).filter((l) => l.type !== type || l.fn !== fn);
  }

  /** Runs listeners on this element, then each parent, then the document (when attached under `body`). */
  dispatchEvent(event) {
    event.target ??= this;
    event.preventDefault ??= () => { event.defaultPrevented = true; };
    for (let n = this; n; n = n.parentNode) {
      (n.listeners ?? []).filter((l) => l.type === event.type).forEach((l) => l.fn(event));
    }
    if (this.contains(globalThis.document?.body) || globalThis.document?.body?.contains(this)) {
      (globalThis.document.listeners ?? []).filter((l) => l.type === event.type).forEach((l) => l.fn(event));
    }
    return !event.defaultPrevented;
  }

  showModal() {
    this.open = true;
    this.querySelector('[autofocus]')?.focus();
  }

  close() {
    this.open = false;
  }

  focus() {
    if (globalThis.document) globalThis.document.activeElement = this;
  }

  set innerHTML(html) {
    const target = this.content || this;
    target.childNodes = [];
    parseInto(target, html);
  }

  get innerHTML() {
    return (this.content || this).outerHTMLs;
  }

  html() {
    const attrs = this.attrs.map((a) => ` ${a.name}="${a.value.replace(/"/g, '&quot;')}"`).join('');
    if (VOID.has(this.localName)) return `<${this.localName}${attrs}>`;
    const inner = this.content ? this.content.outerHTMLs : this.outerHTMLs;
    return `<${this.localName}${attrs}>${inner}</${this.localName}>`;
  }

  get outerHTML() {
    return this.html();
  }

  clone(deep = true) {
    const copy = new Element(this.localName);
    copy.attrs = this.attrs.map((a) => ({ ...a }));
    if (deep) {
      this.childNodes.forEach((c) => copy.append(c.clone(true)));
      if (this.content) this.content.childNodes.forEach((c) => copy.content.append(c.clone(true)));
    }
    return copy;
  }
}

/** Whether an element matches the last selector of a chain and has ancestors matching the ones before, in order. */
function matchesChain(el, parts) {
  if (!el.matchesOne(parts[parts.length - 1])) return false;
  let rest = parts.slice(0, -1);
  for (let n = el.parentNode; n instanceof Element && rest.length; n = n.parentNode) {
    if (n.matchesOne(rest[rest.length - 1])) rest = rest.slice(0, -1);
  }
  return rest.length === 0;
}

const decode = (text) => text.replace(/&quot;/g, '"').replace(/&#39;/g, "'").replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&#(\d+);/g, (_, n) => String.fromCharCode(Number(n))).replace(/&amp;/g, '&');

function parseInto(root, html) {
  const stack = [root];
  const top = () => stack[stack.length - 1];
  const into = (node) => (node.content || node);
  const re = /<!--([\s\S]*?)-->|<\/([a-zA-Z][\w:-]*)\s*>|<([a-zA-Z][\w:-]*)((?:\s+[^\s=>\/]+(?:\s*=\s*(?:"[^"]*"|'[^']*'|[^\s>]+))?)*)\s*(\/?)>|([^<]+)/g;
  let m;
  while ((m = re.exec(html)) !== null) {
    if (m[1] !== undefined) into(top()).append(new Comment(m[1]));
    else if (m[2] !== undefined) {
      const tag = m[2].toLowerCase();
      for (let i = stack.length - 1; i > 0; i -= 1) {
        if (stack[i].localName === tag) {
          stack.length = i;
          break;
        }
      }
    } else if (m[3] !== undefined) {
      const el = new Element(m[3]);
      for (const a of m[4].matchAll(/([^\s=>\/]+)(?:\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s>]+)))?/g)) {
        el.setAttribute(a[1], decode(a[2] ?? a[3] ?? a[4] ?? ''));
      }
      into(top()).append(el);
      if (!m[5] && !VOID.has(el.localName)) stack.push(el);
    } else if (m[6] !== undefined) {
      into(top()).append(new Text(decode(m[6])));
    }
  }
}

/** Puts `document` and `location` on the global object for code that reads them. */
export function installDom({ href = 'https://shop.example.com/' } = {}) {
  const url = new URL(href);
  // The editor's element builder checks its children against the browser's Node.
  globalThis.Node = Node;
  globalThis.location = { href: url.href, origin: url.origin };
  const body = new Element('body');
  const documentElement = new Element('html');
  const listeners = [];
  globalThis.document = {
    baseURI: url.href,
    body,
    documentElement,
    activeElement: body,
    listeners,
    createElement: (tag) => new Element(tag),
    createElementNS: (_, tag) => new Element(tag),
    createTextNode: (text) => new Text(String(text)),
    importNode: (node, deep) => node.clone(deep),
    querySelector: (selector) => body.querySelector(selector),
    querySelectorAll: (selector) => body.querySelectorAll(selector),
    addEventListener: (type, fn) => listeners.push({ type, fn }),
    removeEventListener: (type, fn) => {
      const at = listeners.findIndex((l) => l.type === type && l.fn === fn);
      if (at >= 0) listeners.splice(at, 1);
    },
  };
}
