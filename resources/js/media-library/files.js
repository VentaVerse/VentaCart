/** Checks a chosen file before it is sent, so a refusal is instant. No DOM. */

const DEFAULT_ACCEPT = ['image/jpeg', 'image/png', 'image/webp'];
const EXTENSIONS = { jpg: 'image/jpeg', jpeg: 'image/jpeg', png: 'image/png', webp: 'image/webp' };

function typeOf(file) {
    if (file.type) return file.type;
    return EXTENSIONS[String(file.name || '').split('.').pop().toLowerCase()] || '';
}

function megabytes(bytes) {
    const mb = bytes / 1024 / 1024;
    return Number.isInteger(mb) ? String(mb) : mb.toFixed(1);
}

export function checkFiles(files, { maxBytes, accept = DEFAULT_ACCEPT } = {}) {
    const ok = [];
    const refused = [];

    Array.from(files).forEach((file) => {
        if (!accept.includes(typeOf(file))) {
            refused.push({ file, reason: 'Not a JPG, PNG or WebP file' });
        } else if (maxBytes && file.size > maxBytes) {
            refused.push({ file, reason: `Larger than ${megabytes(maxBytes)} MB` });
        } else {
            ok.push(file);
        }
    });

    return { ok, refused };
}

const SINGULAR = {
    products: 'product',
    categories: 'category',
    brands: 'brand',
    menus: 'menu',
    'blog posts': 'blog post',
    settings: 'setting',
    'theme pages': 'theme page',
    'theme drafts': 'theme draft',
};

/** "Used by 2 products", or "Used in 3 places" when the users are mixed. */
export function usedByText({ total, label } = {}) {
    if (!total) return '';
    if (!label) return total === 1 ? 'Used in 1 place' : `Used in ${total} places`;
    return `Used by ${total} ${total === 1 ? SINGULAR[label] || label : label}`;
}
