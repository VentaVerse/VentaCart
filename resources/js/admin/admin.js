// Admin v2 behavior. Everything registers on alpine:init so this file can
// load before app.js (which calls Alpine.start()).
document.addEventListener('alpine:init', () => {});
