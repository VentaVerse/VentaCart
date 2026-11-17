/*
 * The catalog: section schemas, theme setting groups and font pairs, as the
 * boot payload delivers them. Forms, the add section panel and live preview
 * rules are all read from here, so a new section type needs no editor code.
 */

let catalog = { sections: {}, theme_settings: [], font_pairs: [] };
const optionSources = {};

export function setCatalog(value, sources = {}) {
  catalog = {
    sections: value?.sections || {},
    theme_settings: value?.theme_settings || [],
    font_pairs: value?.font_pairs || [],
  };
  Object.assign(optionSources, sources);
}

export const sectionTypes = () => catalog.sections;
export const sectionDef = (type) => catalog.sections[type] || null;
export const blockTypes = (type) => sectionDef(type)?.blocks?.types || [];
export const blockDef = (type, blockType) => blockTypes(type).find((t) => t.type === blockType) || null;
export const themeGroups = () => catalog.theme_settings;

/*
 * A theme settings group may be edited in a section's panel instead of under
 * Theme settings (`edited_in`: the cart icon belongs with the header). Its
 * values are still stored, saved and published with the theme settings.
 */
export const ownThemeGroups = () => themeGroups().filter((g) => !g.edited_in);
export const themeGroupsIn = (sectionType) => themeGroups().filter((g) => g.edited_in?.section === sectionType);
export const fontPairs = () => catalog.font_pairs;

export function themeSetting(key) {
  for (const group of themeGroups()) {
    const found = (group.settings || []).find((s) => s.key === key);
    if (found) return found;
  }
  return null;
}

/** Options of a select, including lists filled at boot (menus, blog categories, font pairs). */
export function optionsOf(setting) {
  if (setting.options_source) {
    if (setting.options_source === 'font_pairs') return fontPairs().length ? fontPairs() : setting.options || [];
    return optionSources[setting.options_source] || setting.options || [];
  }
  if (setting.control === 'font-pairs' && !setting.options?.length) return fontPairs();
  return setting.options || [];
}

/* Hidden is stored as the section's `disabled` flag, never as a setting. */
export const isFormSetting = (setting) => setting.key !== 'visible';

const PICKER_TYPES = new Set(['product-picker', 'category-picker', 'brand-picker', 'menu-picker', 'page-picker', 'blog-post-picker']);
const TEXT_TYPES = new Set(['text', 'textarea', 'richtext']);

/**
 * How the preview applies a change to this setting: text, attr, css or
 * render (a server re-render of the section). A schema may say so with
 * `live`; otherwise picker types, links on data-driven sections and keys in
 * the section's `render_keys` re-render, text types write text, and the rest
 * set attributes.
 */
export function liveMode(setting, def) {
  if (setting.live) return setting.live;
  if (PICKER_TYPES.has(setting.type)) return 'render';
  if (def?.render_keys?.includes(setting.key)) return 'render';
  if (setting.type === 'url' && def?.data) return 'render';
  if (TEXT_TYPES.has(setting.type)) return 'text';
  return 'attr';
}

/**
 * True when the schema allows this section type in the template being
 * edited. `*extension` opens it to every page type an extension added.
 */
export function allowedIn(def, pageType, isCustom, isExtensionPage = false) {
  if (!def || def.group !== 'template') return false;
  const list = def.templates;
  if (!list || list.includes('*')) return true;
  if (isExtensionPage && list.includes('*extension')) return true;
  if (!list.includes(pageType)) return false;
  // `*custom` opens the section to custom templates of the listed page types.
  return !isCustom || list.includes('*custom');
}

export const SECTION_ICONS = {
  'announcement-bar': 'info', header: 'menu', hero: 'image', 'usp-bar': 'shield-check',
  'category-tiles': 'columns', 'featured-products': 'tag', 'product-spotlight': 'bolt',
  'image-with-text': 'layout', 'promo-banners': 'layers', 'shop-the-look': 'map-pin',
  'brand-logos': 'plus-square', testimonials: 'chat', 'rich-text': 'type',
  'blog-posts': 'align-left', footer: 'columns',
};

export const BLOCK_ICONS = {
  slide: 'image', message: 'info', promise: 'check', category: 'package', step: 'check',
  banner: 'image', hotspot: 'map-pin', brand: 'plus-square', review: 'chat', menu: 'menu',
  contact: 'phone', text: 'type', 'mega-promo': 'image',
};

/*
 * A section or block of an extension that is switched off or removed: its
 * type is `{extension}.{name}` and the catalog has no such type now.
 */
export const extensionOf = (type) => {
  const m = /^([a-z][a-z0-9-]{0,39})\.[a-z0-9]+(?:-[a-z0-9]+)*$/.exec(String(type || ''));
  return m ? m[1] : null;
};

/** "Marketplace is switched off." for a section or block whose extension is away. */
export function absentNote(type, extensions = {}) {
  const name = extensionOf(type);
  if (!name) return null;
  const ext = extensions[name] || { title: name, state: 'missing' };
  return `${ext.title || name} is ${ext.state === 'off' ? 'switched off' : 'not installed'}.`;
}

/** A section or block the catalog lacks, by its own name: its label, else its type made readable. */
export function absentName(item) {
  if (item?.label) return String(item.label);
  const type = String(item?.type || '');
  const name = type.includes('.') ? type.slice(type.indexOf('.') + 1) : type;
  const words = name.replace(/-/g, ' ').trim();
  return words ? words[0].toUpperCase() + words.slice(1) : 'Section';
}

/** "Seller card: Marketplace is switched off." for a row whose extension is away. */
export function absentLabel(item, extensions = {}) {
  const note = absentNote(item?.type, extensions);
  return note ? `${absentName(item)}: ${note}` : null;
}

export const sectionIcon = (type) => sectionDef(type)?.icon || SECTION_ICONS[type] || 'layout';
export const blockIcon = (type, blockType) => blockDef(type, blockType)?.icon || BLOCK_ICONS[blockType] || 'layout';

/* Which part of the store a theme settings group changes, for hover outlines. */
export const THEME_GROUP_TARGETS = {
  'product-cards': 'featured-products',
  'social-contact': 'footer',
  'cart-checkout': 'footer',
};

/*
 * Layout drawings for variant cards (see schematic() in dom.js). A schema may
 * carry its own `schematic` per variant; these cover the shipped sections.
 */
export const SCHEMATICS = {
  'announcement-bar': {
    static: 'm0,15,64,10 t6,19,14,2 t25,19,14,2 t44,19,14,2',
    rotate: 'm0,15,64,10 t20,19,24,2 t4,19,4,2 t56,19,4,2',
    marquee: 'm0,15,64,10 t0,19,14,2 t18,19,16,2 t38,19,16,2 t58,19,6,2',
  },
  header: {
    classic: 'h4,8,12,4 t21,9,6,2 t29,9,6,2 t37,9,6,2 t50,8,3,3 t55,8,3,3 t60,8,2,3 m0,18,64,22',
    centered: 't4,6,3,3 h24,5,16,4 t50,6,3,3 t55,6,3,3 t60,6,2,3 t14,13,7,2 t23,13,7,2 t32,13,7,2 t41,13,7,2 m0,18,64,22',
    'search-first': 'h3,5,10,4 o16,4,32,6 t51,5,3,3 t56,5,3,3 t60,5,2,3 t3,13,6,2 t11,13,6,2 t19,13,6,2 t27,13,6,2 m0,18,64,22',
  },
  hero: {
    'full-image': 'i0,0,64,40 v6,15,30,4 v6,22,22,2 b6,27,12,5',
    split: 'i32,0,32,40 h4,14,24,4 t4,21,20,2 b4,27,12,5',
    slideshow: 'i0,0,64,40 v6,13,30,4 v6,20,22,2 b6,25,12,5 v27,34,3,2 b31,34,3,2 v35,34,3,2',
    collage: 'h4,13,22,4 t4,20,18,2 b4,26,11,5 i30,3,16,34 i48,3,13,16 i48,21,13,16',
  },
  'usp-bar': {
    strip: 'm0,14,64,12 d4,18,4,4 t10,19,10,2 d25,18,4,4 t31,19,10,2 d46,18,4,4 t52,19,9,2',
    columns: 'd4,9,6,6 h4,18,14,2 t4,22,14,2 t4,25,10,2 d25,9,6,6 h25,18,14,2 t25,22,14,2 t25,25,10,2 d46,9,6,6 h46,18,14,2 t46,22,14,2 t46,25,10,2',
    centered: 'd9,10,6,6 h5,19,14,2 t6,23,12,2 d29,10,6,6 h25,19,14,2 t26,23,12,2 d49,10,6,6 h45,19,14,2 t46,23,12,2',
  },
  'category-tiles': {
    'image-cards': 'h4,4,18,3 i4,10,12,14 t4,26,9,2 i19,10,12,14 t19,26,9,2 i34,10,12,14 t34,26,9,2 i49,10,12,14 t49,26,9,2',
    circles: 'h23,4,18,3 c5,11,11,11 t6,25,9,2 c19,11,11,11 t20,25,9,2 c34,11,11,11 t35,25,9,2 c48,11,11,11 t49,25,9,2',
    'text-over-image': 'h4,4,18,3 i4,10,18,24 v7,28,10,2 i24,10,18,24 v27,28,10,2 i44,10,17,24 v47,28,10,2',
    chips: 'h4,8,18,3 p4,16,14,6 p20,16,12,6 p34,16,16,6 p52,16,9,6 p4,25,12,6 p18,25,16,6',
  },
  'featured-products': {
    grid: 'h4,3,20,3 i4,9,12,10 t4,20,9,2 i19,9,12,10 t19,20,9,2 i34,9,12,10 t34,20,9,2 i49,9,12,10 t49,20,9,2 i4,25,12,10 i19,25,12,10 i34,25,12,10 i49,25,12,10',
    carousel: 'h4,5,20,3 i4,12,14,16 t4,30,10,2 i21,12,14,16 t21,30,10,2 i38,12,14,16 t38,30,10,2 i55,12,9,16 t55,30,9,2',
    list: 'h4,4,20,3 i4,11,8,8 h14,12,14,2 t14,16,9,2 i34,11,8,8 h44,12,14,2 t44,16,9,2 i4,24,8,8 h14,25,14,2 t14,29,9,2 i34,24,8,8 h44,25,14,2 t44,29,9,2',
  },
  'product-spotlight': {
    split: 'i4,5,26,30 h34,8,22,3 t34,14,24,2 t34,18,18,2 o34,23,5,5 o41,23,5,5 o48,23,5,5 b34,31,14,5',
    banner: 'i0,0,64,40 w34,7,26,26 h37,11,18,3 t37,16,16,2 b37,26,12,4',
    compact: 'm2,12,60,16 i4,14,12,12 h19,16,20,3 t19,21,14,2 b48,17,12,6',
  },
  'image-with-text': {
    split: 'i4,6,28,28 h37,12,20,3 t37,18,22,2 t37,22,18,2 b37,27,12,5',
    overlap: 'i4,4,36,32 w30,11,30,20 h33,15,18,3 t33,20,22,2 b33,24,11,4',
    background: 'i0,0,64,40 w16,10,32,20 h20,14,24,3 t21,19,22,2 b26,24,12,4',
  },
  'promo-banners': {
    overlay: 'i3,6,28,28 v6,24,14,3 v6,29,10,2 i33,6,28,28 v36,24,14,3 v36,29,10,2',
    stacked: 'i3,4,28,20 h3,27,16,3 t3,32,20,2 i33,4,28,20 h33,27,16,3 t33,32,20,2',
    mosaic: 'i3,4,34,32 v6,27,14,3 i39,4,22,15 i39,21,22,15',
  },
  'shop-the-look': {
    side: 'i4,4,32,32 d12,14,3,3 d24,24,3,3 d16,28,3,3 h40,8,18,3 i40,14,6,6 t48,15,10,2 i40,22,6,6 t48,23,10,2',
    full: 'i0,0,64,40 d14,12,3,3 d34,22,3,3 d48,14,3,3 d22,30,3,3',
  },
  'brand-logos': {
    row: 'h22,10,20,3 o4,18,9,6 o14,18,9,6 o24,18,9,6 o34,18,9,6 o44,18,9,6 o54,18,7,6',
    grid: 'h22,5,20,3 o4,12,13,9 o19,12,13,9 o34,12,13,9 o49,12,12,9 o4,24,13,9 o19,24,13,9 o34,24,13,9 o49,24,12,9',
  },
  testimonials: {
    grid: 'h22,4,20,3 w4,11,17,22 t6,14,13,2 t6,18,11,2 h6,27,8,2 w23,11,17,22 t25,14,13,2 t25,18,11,2 h25,27,8,2 w42,11,18,22 t44,14,13,2 t44,18,11,2 h44,27,8,2',
    carousel: 'h4,4,20,3 w4,11,22,22 t6,14,17,2 t6,18,14,2 h6,27,8,2 w28,11,22,22 t30,14,17,2 t30,18,14,2 h30,27,8,2 w52,11,12,22 t54,14,9,2',
    large: 'h14,12,36,4 h18,18,28,4 t26,27,12,2 d30,31,4,2',
  },
  'rich-text': {
    center: 'h16,10,32,4 t12,18,40,2 t14,22,36,2 t18,26,28,2 b26,31,12,4',
    start: 'h4,10,32,4 t4,18,40,2 t4,22,36,2 t4,26,28,2 b4,31,12,4',
    split: 'h4,12,22,4 h4,18,16,4 t34,12,26,2 t34,16,24,2 t34,20,26,2 t34,24,18,2 b34,29,12,4',
  },
  'blog-posts': {
    grid: 'h4,4,18,3 i4,10,17,12 h4,24,15,2 t4,28,12,2 i23,10,17,12 h23,24,15,2 t23,28,12,2 i42,10,18,12 h42,24,15,2 t42,28,12,2',
    featured: 'h4,4,18,3 i4,10,30,18 h4,30,24,2 t4,34,18,2 i38,10,8,7 h48,11,12,2 t48,15,9,2 i38,20,8,7 h48,21,12,2 t48,25,9,2 i38,30,8,7 h48,31,12,2',
    list: 'h4,4,18,3 i4,10,10,7 h17,11,26,2 t17,15,18,2 i4,20,10,7 h17,21,26,2 t17,25,18,2 i4,30,10,7 h17,31,26,2 t17,35,18,2',
  },
  footer: {
    columns: 'm0,0,64,40 h4,6,12,3 t4,12,16,2 t4,16,14,2 h26,6,8,2 t26,11,8,2 t26,15,7,2 t26,19,8,2 h40,6,8,2 t40,11,8,2 t40,15,7,2 h52,6,8,2 t52,11,8,2 t52,15,7,2 t4,33,20,2 t44,32,4,3 t49,32,4,3 t54,32,4,3',
    centered: 'm0,0,64,40 h24,8,16,3 t14,16,7,2 t23,16,7,2 t32,16,7,2 t41,16,7,2 t25,22,4,3 t30,22,4,3 t35,22,4,3 t22,32,20,2',
    compact: 'm0,0,64,40 h4,18,10,3 t20,19,6,2 t28,19,6,2 t36,19,6,2 t48,18,4,3 t54,18,4,3 t58,18,4,3',
  },
  // A section setting's cards: `{section type}:{setting key}`. Each shape is
  // a frame with the photo inside it: whole with a margin, or filling the
  // frame's width with its sides cropped away.
  'product-main:photo_shape': {
    fit: 'i16,4,32,32 h18,13,28,14',
    square: 'i16,4,32,32 h16,11,32,18',
    portrait: 'i19,4,26,32 h19,12,26,16',
    landscape: 'i12,5,40,30 h12,11,40,18',
  },
  'theme:card_style': {
    plain: 'i14,4,36,24 h14,31,22,2 t14,35,12,2',
    bordered: 'o12,2,40,37 i14,4,36,24 h14,30,22,2 t14,34,12,2',
    panel: 'm12,2,40,37 i14,4,36,24 h14,30,22,2 t14,34,12,2',
  },
};

export function variantDrawing(type, value) {
  const fromSchema = sectionDef(type)?.variants?.find((v) => v.value === value)?.schematic;
  return fromSchema || SCHEMATICS[type]?.[value] || null;
}
