// Admin v2 behavior. Registered on alpine:init so this file can load
// before app.js (which calls Alpine.start()).
document.addEventListener('alpine:init', () => {});

// Any form marked data-v-form: on submit, flip its submit buttons into the
// loading state (spinner + no double-submit). Guard against re-entry.
document.addEventListener('submit', (e) => {
    const form = e.target.closest('form[data-v-form]');
    if (!form) return;
    if (form.dataset.vSubmitting === 'true') { e.preventDefault(); return; }
    form.dataset.vSubmitting = 'true';
    form.querySelectorAll('button[type="submit"]').forEach((b) => b.setAttribute('data-loading', 'true'));
});

// Rows with data-href navigate on click, unless the click hit an
// interactive element inside the row (link, button, input).
document.addEventListener('click', (e) => {
    const row = e.target.closest('tr[data-href]');
    if (!row || e.target.closest('a, button, input, select, label')) return;
    window.location.assign(row.dataset.href);
});
