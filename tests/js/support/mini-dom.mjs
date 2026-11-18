/**
 * A small stand-in for the browser DOM, enough for node tests of code that
 * builds elements, listens for events and opens a <dialog>. It parses no
 * HTML: insertAdjacentHTML and innerHTML only keep the text they are given.
 *
 * Selectors cover what the tests need: tag, #id, .class, [attr] and
 * [attr="value"], joined into compound selectors, separated by spaces
 * (descendant) or commas.
 */

class Event {
    constructor(type, init = {}) {
        this.type = type;
        this.bubbles = Boolean(init.bubbles);
        this.cancelable = Boolean(init.cancelable);
        this.defaultPrevented = false;
        this.target = null;
        this.currentTarget = null;
        this.stopped = false;
    }

    preventDefault() {
        if (this.cancelable) this.defaultPrevented = true;
    }

    stopPropagation() {
        this.stopped = true;
    }
}

class CustomEvent extends Event {
    constructor(type, init = {}) {
        super(type, init);
        this.detail = init.detail ?? null;
    }
}

class Target {
    constructor() {
        this.listeners = new Map();
    }

    addEventListener(type, fn) {
        if (!this.listeners.has(type)) this.listeners.set(type, []);
        this.listeners.get(type).push(fn);
    }

    removeEventListener(type, fn) {
        const list = this.listeners.get(type) || [];
        this.listeners.set(type, list.filter((f) => f !== fn));
    }

    dispatchEvent(event) {
        if (!event.target) event.target = this;
        let node = this;
        while (node) {
            event.currentTarget = node;
            (node.listeners?.get(event.type) || []).slice().forEach((fn) => fn.call(node, event));
            if (!event.bubbles || event.stopped) break;
            node = node.parentNode;
        }
        return !event.defaultPrevented;
    }
}

class Node extends Target {
    constructor(doc) {
        super();
        this.ownerDocument = doc;
        this.parentNode = null;
        this.childNodes = [];
    }

    get isConnected() {
        let node = this;
        while (node.parentNode) node = node.parentNode;
        return node === this.ownerDocument;
    }

    get textContent() {
        return this.childNodes.map((c) => c.textContent).join('');
    }

    set textContent(value) {
        this.replaceChildren();
        if (value !== '' && value !== null && value !== undefined) this.append(String(value));
    }

    adopt(child) {
        const node = typeof child === 'string' ? this.ownerDocument.createTextNode(child) : child;
        node.remove?.();
        node.parentNode = this;
        return node;
    }

    append(...kids) {
        kids.forEach((k) => this.childNodes.push(this.adopt(k)));
    }

    prepend(...kids) {
        this.childNodes.unshift(...kids.map((k) => this.adopt(k)));
    }

    replaceChildren(...kids) {
        this.childNodes.forEach((c) => { c.parentNode = null; });
        this.childNodes = [];
        this.append(...kids);
    }

    before(...kids) {
        const parent = this.parentNode;
        if (!parent) return;
        const nodes = kids.map((k) => parent.adopt(k));
        parent.childNodes.splice(parent.childNodes.indexOf(this), 0, ...nodes);
    }

    remove() {
        if (!this.parentNode) return;
        const list = this.parentNode.childNodes;
        list.splice(list.indexOf(this), 1);
        this.parentNode = null;
    }

    contains(node) {
        for (let n = node; n; n = n.parentNode) if (n === this) return true;
        return false;
    }

    get children() {
        return this.childNodes.filter((c) => c instanceof Element);
    }

    get firstElementChild() {
        return this.children[0] || null;
    }

    descendants() {
        const out = [];
        const walk = (n) => n.children.forEach((c) => { out.push(c); walk(c); });
        walk(this);
        return out;
    }

    querySelectorAll(selector) {
        return this.descendants().filter((el) => matches(el, selector, this));
    }

    querySelector(selector) {
        return this.querySelectorAll(selector)[0] || null;
    }
}

class Text extends Node {
    constructor(doc, text) {
        super(doc);
        this.data = String(text);
    }

    get textContent() {
        return this.data;
    }

    set textContent(value) {
        this.data = String(value);
    }
}

class ClassList {
    constructor(el) {
        this.el = el;
    }

    items() {
        return (this.el.getAttribute('class') || '').split(/\s+/).filter(Boolean);
    }

    contains(name) {
        return this.items().includes(name);
    }

    add(...names) {
        this.el.setAttribute('class', [...new Set([...this.items(), ...names])].join(' '));
    }

    remove(...names) {
        this.el.setAttribute('class', this.items().filter((n) => !names.includes(n)).join(' '));
    }

    toggle(name, force) {
        const on = force ?? !this.contains(name);
        if (on) this.add(name);
        else this.remove(name);
        return on;
    }
}

class Element extends Node {
    constructor(doc, tag) {
        super(doc);
        this.tagName = tag.toUpperCase();
        this.attributes = new Map();
        this.classList = new ClassList(this);
        this.style = {};
        this.html = '';
        this.value = '';
        this.disabled = false;
        const element = this;
        this.dataset = new Proxy({}, {
            get: (_, key) => element.getAttribute(`data-${String(key).replace(/[A-Z]/g, (c) => `-${c.toLowerCase()}`)}`) ?? undefined,
            set: (_, key, value) => {
                element.setAttribute(`data-${String(key).replace(/[A-Z]/g, (c) => `-${c.toLowerCase()}`)}`, value);
                return true;
            },
        });
    }

    setAttribute(name, value) {
        this.attributes.set(name, String(value));
    }

    getAttribute(name) {
        return this.attributes.has(name) ? this.attributes.get(name) : null;
    }

    hasAttribute(name) {
        return this.attributes.has(name);
    }

    removeAttribute(name) {
        this.attributes.delete(name);
    }

    get id() {
        return this.getAttribute('id') || '';
    }

    set id(value) {
        this.setAttribute('id', value);
    }

    get className() {
        return this.getAttribute('class') || '';
    }

    set className(value) {
        this.setAttribute('class', value);
    }

    get hidden() {
        return this.hasAttribute('hidden');
    }

    set hidden(on) {
        if (on) this.setAttribute('hidden', '');
        else this.removeAttribute('hidden');
    }

    get tabIndex() {
        return Number(this.getAttribute('tabindex') ?? -1);
    }

    set tabIndex(value) {
        this.setAttribute('tabindex', value);
    }

    get innerHTML() {
        return this.html;
    }

    set innerHTML(value) {
        this.replaceChildren();
        this.html = String(value);
    }

    insertAdjacentHTML(_, html) {
        this.html += String(html);
    }

    closest(selector) {
        for (let n = this; n instanceof Element; n = n.parentNode) if (matches(n, selector)) return n;
        return null;
    }

    matches(selector) {
        return matches(this, selector);
    }

    focus() {
        this.ownerDocument.activeElement = this;
    }

    blur() {
        if (this.ownerDocument.activeElement === this) this.ownerDocument.activeElement = this.ownerDocument.body;
    }

    click() {
        this.dispatchEvent(new Event('click', { bubbles: true, cancelable: true }));
    }

    scrollIntoView() {}

    getBoundingClientRect() {
        return { top: 0, left: 0, right: 0, bottom: 0, width: 0, height: 0 };
    }

    // <dialog>
    get open() {
        return this.hasAttribute('open');
    }

    showModal() {
        this.setAttribute('open', '');
    }

    show() {
        this.setAttribute('open', '');
    }

    close() {
        if (!this.open) return;
        this.removeAttribute('open');
        this.dispatchEvent(new Event('close'));
    }
}

function matchesCompound(el, compound) {
    const parts = compound.match(/(^[a-z0-9-]+)|#[\w-]+|\.[\w-]+|\[[^\]]+\]/gi) || [];
    return parts.every((part) => {
        if (part[0] === '#') return el.id === part.slice(1);
        if (part[0] === '.') return el.classList.contains(part.slice(1));
        if (part[0] === '[') {
            const [, name, value] = part.match(/^\[([\w-]+)(?:="?([^"]*)"?)?\]$/);
            return value === undefined ? el.hasAttribute(name) : el.getAttribute(name) === value;
        }
        return el.tagName === part.toUpperCase();
    });
}

function matches(el, selector, scope = null) {
    return selector.split(',').some((one) => {
        const chain = one.trim().split(/\s+/);
        if (!matchesCompound(el, chain[chain.length - 1])) return false;
        let node = el.parentNode;
        for (let i = chain.length - 2; i >= 0; i -= 1) {
            while (node instanceof Element && node !== scope && !matchesCompound(node, chain[i])) node = node.parentNode;
            if (!(node instanceof Element) || node === scope) return false;
            node = node.parentNode;
        }
        return true;
    });
}

class Document extends Node {
    constructor() {
        super(null);
        this.ownerDocument = this;
        this.documentElement = this.createElement('html');
        this.head = this.createElement('head');
        this.body = this.createElement('body');
        this.documentElement.append(this.head, this.body);
        this.append(this.documentElement);
        this.activeElement = this.body;
    }

    createElement(tag) {
        return new Element(this, tag);
    }

    createElementNS(_, tag) {
        return new Element(this, tag);
    }

    createTextNode(text) {
        return new Text(this, text);
    }

    getElementById(id) {
        return this.querySelector(`#${id}`);
    }
}

/** Puts a fresh document, window and the event classes on globalThis. */
export function installDom() {
    const document = new Document();
    const store = new Map();
    const window = globalThis;

    Object.assign(globalThis, {
        document,
        window,
        Event,
        CustomEvent,
        Node,
        Element,
        HTMLElement: Element,
        localStorage: {
            getItem: (k) => (store.has(k) ? store.get(k) : null),
            setItem: (k, v) => store.set(k, String(v)),
            removeItem: (k) => store.delete(k),
        },
        matchMedia: () => ({ matches: false, addEventListener() {}, removeEventListener() {} }),
        requestAnimationFrame: (fn) => setTimeout(fn, 0),
    });

    return { document, window };
}
