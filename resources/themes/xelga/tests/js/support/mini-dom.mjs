/*
 * A small stand-in for the browser DOM, enough for the editor's markup
 * cleaner to run under Node: elements with attributes and children,
 * template elements with their own content, a parser for plain test
 * markup (tags, quoted attributes, text, comments, void elements) and
 * `document.createElement`, `importNode` and `baseURI`.
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

  remove() {
    this.parentNode?.removeChild(this);
  }

  /** Every element under this one, in document order. Only `*` is supported. */
  querySelectorAll(selector) {
    if (selector !== '*') return this.descendants().filter((el) => el.matches(selector));
    return this.descendants();
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

  /** `tag`, `[attr]`, `[attr="value"]` or a tag followed by attribute tests. */
  matches(selector) {
    const m = /^([a-z0-9-]*)((?:\[[^\]]+\])*)$/i.exec(selector.trim());
    if (!m) throw new Error(`mini-dom cannot match ${selector}`);
    if (m[1] && m[1].toLowerCase() !== this.localName) return false;
    const tests = [...m[2].matchAll(/\[([^\]=]+)(?:="([^"]*)")?\]/g)];
    return tests.every(([, name, value]) => (value === undefined ? this.hasAttribute(name) : this.getAttribute(name) === value));
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
  globalThis.location = { href: url.href, origin: url.origin };
  globalThis.document = {
    baseURI: url.href,
    createElement: (tag) => new Element(tag),
    importNode: (node, deep) => node.clone(deep),
  };
}
