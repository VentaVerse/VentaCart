/*
 * Country and place fields of an address, filled from core's address
 * lookup exactly as core's own address form does:
 *
 *  - a country with an address lookup provider (provinces, cities,
 *    districts, barangays) gets lists, each filled from the one before it
 *    through the provider's endpoints; a list shows once it has entries,
 *    and a shown district or barangay is required;
 *  - any other country gets its regions as a list when core has them
 *    (storefront.address.zones), else a text field, and the city as text;
 *  - changing the country clears the places and the typed street,
 *    postcode and mobile number, which belonged to the old country.
 *
 * The field names stay core's (country_id, state, city, district,
 * barangay). Of a place's list and text field only the one in use is
 * enabled, so a plain form post sends exactly one value per name.
 */
import { $, $$ } from '../util.js';

const PLACES = ['state', 'city', 'district', 'barangay'];
const GENERIC_LABELS = { state: 'State or region', city: 'City', district: 'District', barangay: 'Barangay' };

let shared = null;

/** The lookup data the page printed once: providers, phone codes and the zones address. */
export function addressData() {
  if (shared) return shared;
  try {
    shared = JSON.parse($('#xelga-address-data')?.textContent || '{}');
  } catch {
    shared = {};
  }
  shared.providers = shared.providers || {};
  shared.phoneCodes = shared.phoneCodes || {};
  return shared;
}

async function getJson(url) {
  const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
  if (!response.ok) throw new Error(`Lookup failed (${response.status})`);
  const data = await response.json();
  return Array.isArray(data) ? data : [];
}

function fillSelect(select, items, placeholder, value) {
  select.replaceChildren();
  const first = document.createElement('option');
  first.value = '';
  first.textContent = placeholder;
  select.append(first);
  items.forEach((item) => {
    const option = document.createElement('option');
    option.value = item.name;
    option.textContent = item.name;
    select.append(option);
  });
  // Keep a saved value the list no longer offers, so editing never drops it silently.
  if (value && !items.some((item) => item.name === value)) {
    const kept = document.createElement('option');
    kept.value = value;
    kept.textContent = value;
    select.append(kept);
  }
  select.value = value || '';
}

/**
 * Bind the fields inside `root` (an element carrying data-address-fields).
 * `form` is the element whose typed street, postcode and phone are cleared
 * when the country changes. Returns an object to read and set the values.
 */
export function bindAddressFields(root, { form = root.closest('form') || root.parentElement, onChange = () => {} } = {}) {
  const data = addressData();
  const country = $('[data-addr="country_id"]', root);
  const fields = {};
  PLACES.forEach((key) => {
    const wrap = $(`[data-addr-field="${key}"]`, root);
    if (!wrap) return;
    fields[key] = {
      wrap,
      label: $('[data-addr-label]', wrap),
      select: $(`[data-addr-select="${key}"]`, wrap),
      input: $(`[data-addr-input="${key}"]`, wrap),
      items: [],
      mode: 'text',
    };
  });

  let token = 0;
  const phoneCode = form ? $('[data-phone-code]', form) : null;

  const provider = () => data.providers[country.value] || null;

  function use(key, mode, { show = true, required = false, label = null } = {}) {
    const f = fields[key];
    if (!f) return;
    f.mode = mode;
    f.wrap.hidden = !show;
    const list = mode === 'list';
    f.select.hidden = !list;
    f.select.disabled = !list || !show;
    f.input.hidden = list;
    f.input.disabled = list || !show;
    f.select.required = list && show && required;
    f.input.required = !list && show && required;
    if (label) f.label.textContent = label;
    f.label.htmlFor = list ? f.select.id : f.input.id;
  }

  function value(key) {
    const f = fields[key];
    if (!f || f.wrap.hidden) return '';
    return (f.mode === 'list' ? f.select.value : f.input.value).trim();
  }

  function setValue(key, v) {
    const f = fields[key];
    if (!f) return;
    f.input.value = v || '';
    if (f.mode === 'list') f.select.value = v || '';
  }

  function updatePhoneCode() {
    if (!phoneCode) return;
    const code = data.phoneCodes[country.value];
    phoneCode.textContent = code ? `+${code}` : '';
  }

  function clearTyped() {
    if (!form) return;
    $$('input, textarea', form).forEach((el) => {
      const name = el.name || el.dataset.addrValue || el.dataset.billingValue || '';
      if (['address_1', 'address_2', 'postcode', 'phone'].includes(name) && el.value !== '') {
        el.value = '';
        el.dispatchEvent(new Event('input', { bubbles: true }));
      }
    });
  }

  /** The id of the chosen entry a dependent list is filled from (the last of its sources with a value). */
  function parentId(field) {
    const sources = String(field.paramFrom || '').split('|').reverse();
    for (const src of sources) {
      const chosen = value(src);
      if (!chosen || !fields[src]) continue;
      const match = fields[src].items.find((item) => item.name === chosen);
      if (match) return match.id;
    }
    return null;
  }

  async function loadProviderField(spec, restore, run) {
    const f = fields[spec.key];
    if (!f) return;
    let url = spec.endpoint;
    if (spec.paramFrom) {
      const id = parentId(spec);
      if (!id) return;
      url = `${spec.endpoint}?${encodeURIComponent(spec.paramName)}=${encodeURIComponent(id)}`;
    }
    const keep = restore ? f.input.value : '';
    f.select.classList.add('is-loading');
    try {
      const items = await getJson(url);
      if (run !== token) return;
      f.items = items;
      fillSelect(f.select, items, `Choose a ${spec.label.toLowerCase()}`, keep);
      // A dependent list shows once it has entries (a city without districts skips District).
      use(spec.key, 'list', { show: spec.paramFrom ? items.length > 0 : true, required: Boolean(spec.required), label: spec.label });
      if (restore && keep) await placeChanged(spec.key, false, run);
    } catch {
      // A failed lookup leaves the text field, so the address can still be typed.
      use(spec.key, 'text', { show: true, required: Boolean(spec.required), label: spec.label });
    } finally {
      f.select.classList.remove('is-loading');
    }
  }

  async function placeChanged(key, clear, run = token) {
    const p = provider();
    if (!p) return;
    const index = p.fields.findIndex((spec) => spec.key === key);
    if (clear) {
      p.fields.slice(index + 1).forEach((spec) => {
        const f = fields[spec.key];
        if (!f) return;
        f.items = [];
        f.input.value = '';
        fillSelect(f.select, [], `Choose a ${spec.label.toLowerCase()}`, '');
        use(spec.key, 'list', { show: false });
      });
    }
    if (!value(key)) return;
    for (const spec of p.fields) {
      if (!spec.paramFrom || !spec.paramFrom.split('|').includes(key)) continue;
      // eslint-disable-next-line no-await-in-loop
      await loadProviderField(spec, !clear, run);
    }
  }

  async function countryChanged(clear) {
    token += 1;
    const run = token;
    if (clear) {
      PLACES.forEach((key) => setValue(key, ''));
      clearTyped();
    }
    updatePhoneCode();
    PLACES.forEach((key) => { if (fields[key]) fields[key].items = []; });

    if (!country.value) {
      PLACES.forEach((key) => use(key, 'text', { show: key === 'state' || key === 'city', required: key === 'city', label: GENERIC_LABELS[key] }));
      return;
    }

    const p = provider();
    if (p) {
      // Lists replace every place; a dependent list shows once it has entries.
      p.fields.forEach((spec) => use(spec.key, 'list', { show: !spec.paramFrom, required: Boolean(spec.required), label: spec.label }));
      PLACES.filter((key) => !p.fields.some((spec) => spec.key === key)).forEach((key) => use(key, 'text', { show: false }));
      const rootSpec = p.fields.find((spec) => !spec.paramFrom);
      if (rootSpec) await loadProviderField(rootSpec, !clear, run);
      return;
    }

    use('district', 'text', { show: false });
    use('barangay', 'text', { show: false });
    use('city', 'text', { show: true, required: true, label: GENERIC_LABELS.city });
    use('state', 'text', { show: true, label: GENERIC_LABELS.state });
    if (!data.zonesUrl) return;
    const keep = clear ? '' : fields.state?.input.value;
    try {
      const zones = await getJson(`${data.zonesUrl}?country_id=${encodeURIComponent(country.value)}`);
      if (run !== token || !fields.state) return;
      if (zones.length > 0) {
        fields.state.items = zones;
        fillSelect(fields.state.select, zones, 'Choose a state or region', keep);
        use('state', 'list', { show: true, label: GENERIC_LABELS.state });
      }
    } catch {
      // Keep the text field.
    }
  }

  country.addEventListener('change', () => { countryChanged(true).then(onChange); });
  PLACES.forEach((key) => {
    const f = fields[key];
    if (!f) return;
    f.select.addEventListener('change', () => {
      f.input.value = f.select.value;
      placeChanged(key, true).then(onChange);
    });
    f.input.addEventListener('input', onChange);
  });

  const ready = countryChanged(false);

  return {
    ready,
    /** The values core's address fields take. */
    values() {
      return {
        country_id: country.value,
        state: value('state'),
        city: value('city'),
        district: value('district'),
        barangay: value('barangay'),
      };
    },
    /** Put an address in (editing a saved one), then fill the lists from it. */
    async set(address = {}) {
      country.value = address.country_id ? String(address.country_id) : '';
      PLACES.forEach((key) => setValue(key, address[key] || ''));
      await countryChanged(false);
    },
    focus() {
      country.focus();
    },
  };
}
