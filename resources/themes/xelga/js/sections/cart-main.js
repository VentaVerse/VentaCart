/*
 * The cart page. Quantity changes, removals and coupons go through core's
 * cart and coupon routes; after each one the section is drawn again from
 * the real cart page, so every total, stock flag and checkout state on it
 * is core's. The shipping estimate asks core's estimate route with the same
 * address lookups core's checkout uses.
 */
import { $, $$, inEditorPreview } from '../util.js';
import { send, messageOf, money, cartChanged } from '../cart.js';

const el = (tag, className, text) => {
  const node = document.createElement(tag);
  if (className) node.className = className;
  if (text !== undefined) node.textContent = text;
  return node;
};

async function getJson(url) {
  const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
  if (!response.ok) throw new Error(String(response.status));
  return response.json();
}

function optionList(select, items, placeholder, valueKey = 'id') {
  select.replaceChildren(new Option(placeholder, ''));
  items.forEach((item) => select.append(new Option(item.name, String(item[valueKey]))));
}

/** The address part of the estimate: a country, then its own lookups (or a region and city). */
function bindEstimate(section, endpoints, signal) {
  const form = $('[data-estimate-form]', section);
  if (!form) return;
  const country = $('[data-estimate-country]', form);
  const fields = $('[data-estimate-fields]', form);
  const result = $('[data-estimate-result]', form);
  let providers = {};
  let provider = null;
  const lists = {};

  const loadCountries = async () => {
    if (country.options.length > 1) return;
    try {
      const [countries, known] = await Promise.all([
        getJson(endpoints.countries),
        endpoints.providers ? getJson(endpoints.providers).catch(() => ({})) : Promise.resolve({}),
      ]);
      providers = known || {};
      optionList(country, countries, 'Choose a country');
    } catch {
      result.textContent = 'Countries could not be loaded. Reload the page, then try again.';
    }
  };
  $('[data-estimate]', section)?.addEventListener('toggle', loadCountries, { signal });

  const field = (key, label, required) => {
    const wrap = el('label', 'field');
    wrap.append(el('span', 'field__label', label));
    const select = el('select', 'select');
    select.name = key;
    select.dataset.key = key;
    if (required) select.required = true;
    wrap.append(select);
    fields.append(wrap);
    return select;
  };

  const loadField = async (spec, parentId = null) => {
    const select = $(`select[data-key="${spec.key}"]`, fields);
    if (!select) return;
    const url = spec.paramFrom ? `${spec.endpoint}?${encodeURIComponent(spec.paramName)}=${encodeURIComponent(parentId)}` : spec.endpoint;
    try {
      lists[spec.key] = await getJson(url);
    } catch {
      lists[spec.key] = [];
    }
    optionList(select, lists[spec.key], `Choose ${spec.label.toLowerCase()}`, 'name');
    select.closest('.field').hidden = Boolean(spec.conditional) && lists[spec.key].length === 0;
  };

  country.addEventListener('change', async () => {
    fields.replaceChildren();
    result.replaceChildren();
    provider = providers[country.value] || null;
    if (!country.value) return;
    if (provider) {
      provider.fields.forEach((spec) => {
        // A lookup that depends on another waits, hidden, until that one is chosen.
        field(spec.key, spec.label, spec.required && !spec.conditional).closest('.field').hidden = Boolean(spec.paramFrom);
      });
      const rootField = provider.fields.find((f) => !f.paramFrom);
      if (rootField) await loadField(rootField);
      return;
    }
    let zones = [];
    try {
      zones = await getJson(`${endpoints.zones}?country_id=${encodeURIComponent(country.value)}`);
    } catch {
      zones = [];
    }
    if (zones.length) {
      optionList(field('state', 'State or region', false), zones, 'Choose one', 'name');
    } else {
      const wrap = el('label', 'field');
      wrap.append(el('span', 'field__label', 'State or region'));
      const input = el('input', 'input');
      input.name = 'state';
      wrap.append(input);
      fields.append(wrap);
    }
    const city = el('label', 'field');
    city.append(el('span', 'field__label', 'City'));
    const cityInput = el('input', 'input');
    cityInput.name = 'city';
    cityInput.required = true;
    city.append(cityInput);
    fields.append(city);
  }, { signal });

  fields.addEventListener('change', async (event) => {
    const select = event.target.closest('select[data-key]');
    if (!select || !provider) return;
    const index = provider.fields.findIndex((f) => f.key === select.dataset.key);
    for (const spec of provider.fields.slice(index + 1)) {
      const next = $(`select[data-key="${spec.key}"]`, fields);
      if (next) {
        optionList(next, [], `Choose ${spec.label.toLowerCase()}`);
        next.closest('.field').hidden = true;
      }
    }
    for (const spec of provider.fields) {
      if (!spec.paramFrom || !spec.paramFrom.split('|').includes(select.dataset.key)) continue;
      const chosen = (lists[select.dataset.key] || []).find((item) => item.name === select.value);
      if (chosen) await loadField(spec, chosen.id);
    }
  }, { signal });

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    result.replaceChildren();
    const data = Object.fromEntries(new FormData(form).entries());
    if (!data.country_id || !data.city) {
      result.append(el('p', '', 'Choose a country and a city first.'));
      result.firstChild.dataset.tone = 'error';
      return;
    }
    const button = $('button[type="submit"]', form);
    button.disabled = true;
    try {
      const answer = await send(endpoints.estimate, 'POST', {
        country_id: Number(data.country_id), state: data.state || '', city: data.city, barangay: data.barangay || '',
      });
      if (!answer.ok) {
        const p = el('p', '', messageOf(answer, 'No estimate for that address. Check it, then try again.'));
        p.dataset.tone = 'error';
        result.append(p);
        return;
      }
      const methods = Array.isArray(answer.data.methods) ? answer.data.methods : [];
      if (!methods.length) {
        result.append(el('p', '', 'No delivery option reaches that address yet.'));
        return;
      }
      const list = el('ul');
      methods.forEach((m) => {
        const li = el('li');
        const name = m.estimated_days ? `${m.name}, about ${m.estimated_days} days` : m.name;
        li.append(el('span', '', name), el('span', '', Number(m.cost) === 0 ? 'Free' : money(m.cost)));
        list.append(li);
      });
      result.append(list);
    } catch {
      result.append(el('p', '', 'The store could not be reached. Check your connection, then try again.'));
    } finally {
      button.disabled = false;
    }
  }, { signal });
}

export function initCartPage(section) {
  if (inEditorPreview()) return null;
  let endpoints = {};
  try {
    endpoints = JSON.parse(section.dataset.endpoints || '{}');
  } catch {
    endpoints = {};
  }
  const controller = new AbortController();
  const { signal } = controller;
  let busy = false;

  const warn = (text) => {
    const box = $('[data-cart-warning]', section);
    if (!box) return;
    box.textContent = text || '';
    box.hidden = !text;
  };

  // Draw the section again from the real cart page, so totals and flags are core's.
  const redraw = async (warning = '') => {
    try {
      const response = await fetch(window.location.href, { credentials: 'same-origin', headers: { Accept: 'text/html' } });
      const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
      const fresh = doc.querySelector(`[data-section-id="${CSS.escape(section.dataset.sectionId)}"] [data-cart-page-body]`);
      const body = $('[data-cart-page-body]', section);
      if (fresh && body) {
        body.replaceWith(document.importNode(fresh, true));
        bindEstimate(section, endpoints, signal);
      }
      const count = doc.querySelector('[data-cart-count]:not(html)')?.textContent;
      if (count !== undefined) $$('[data-cart-count]:not(html)').forEach((n) => { n.textContent = count; });
    } catch {
      window.location.reload();
      return;
    }
    warn(warning);
  };

  const change = async (li, quantity) => {
    if (busy) return;
    busy = true;
    section.classList.add('is-busy');
    const id = li.dataset.line;
    try {
      const result = quantity <= 0
        ? await send(endpoints.remove.replace('{id}', id), 'DELETE')
        : await send(endpoints.update.replace('{id}', id), 'PATCH', { quantity });
      if (!result.ok) {
        warn(messageOf(result, 'That change could not be saved. Try again.'));
        return;
      }
      cartChanged({ count: result.data.count, source: 'page' });
      await redraw(result.data.stock_warning || '');
    } catch {
      warn('The store could not be reached. Check your connection, then try again.');
    } finally {
      busy = false;
      section.classList.remove('is-busy');
    }
  };

  section.addEventListener('click', async (event) => {
    const step = event.target.closest('[data-line-step]');
    if (step) {
      const li = step.closest('[data-line]');
      const input = $('[data-line-qty]', li);
      const max = Number(input.max) || Infinity;
      const next = (Number(input.value) || 1) + Number(step.dataset.lineStep);
      if (next > max) {
        warn(`Only ${max} available.`);
        return;
      }
      change(li, Math.max(0, next));
      return;
    }
    const remove = event.target.closest('[data-line-remove]');
    if (remove) {
      change(remove.closest('[data-line]'), 0);
      return;
    }
    if (event.target.closest('[data-coupon-remove]') && endpoints.coupon_remove) {
      section.classList.add('is-busy');
      await send(endpoints.coupon_remove, 'DELETE');
      await redraw();
      section.classList.remove('is-busy');
    }
  }, { signal });

  section.addEventListener('change', (event) => {
    const input = event.target.closest('[data-line-qty]');
    if (input) change(input.closest('[data-line]'), Math.max(0, Math.floor(Number(input.value) || 0)));
  }, { signal });

  section.addEventListener('submit', async (event) => {
    const form = event.target.closest('[data-coupon-form]');
    if (!form || !endpoints.coupon_apply) return;
    event.preventDefault();
    const input = $('input', form);
    const msg = $('[data-coupon-msg]', form);
    const code = input.value.trim();
    if (!code) {
      msg.textContent = 'Enter a coupon code first.';
      msg.dataset.tone = 'error';
      input.setAttribute('aria-invalid', 'true');
      input.focus();
      return;
    }
    const result = await send(endpoints.coupon_apply, 'POST', { code });
    if (!result.ok || !result.data.success) {
      msg.textContent = messageOf(result, 'That code could not be applied.');
      msg.dataset.tone = 'error';
      input.setAttribute('aria-invalid', 'true');
      return;
    }
    await redraw();
  }, { signal });

  bindEstimate(section, endpoints, signal);

  return () => controller.abort();
}
