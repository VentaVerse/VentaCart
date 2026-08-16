import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

// Paint first, hydrate after: Alpine walking the whole DOM before
// first paint held every page white past the browser's frame-hold
// window (measured: first paint 624ms vs DOM ready 269ms). Server
// HTML is complete on its own, so booting one frame after paint is
// invisible, and the page shows at parse speed.
const startAlpine = () => requestAnimationFrame(() => requestAnimationFrame(() => Alpine.start()));
startAlpine();
