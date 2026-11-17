/*
 * The editor's own icons, one stroke set drawn for the editor. They
 * are put into the page once as a hidden sprite, so <use href="#i-name">
 * works without a separate file (the theme's build folder holds no SVG
 * files) and without depending on the storefront's smaller set.
 *
 * The whole set ships, so an icon a
 * schema names (a theme settings group, an icon choice) always draws; add
 * new ones here when the editor needs them.
 */

const SYMBOLS = [
  '<symbol id="i-alert" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <circle cx="12" cy="12" r="8.75"/> <path d="M12 7.5v5.25M12 16.25v.01"/> </symbol>',
  '<symbol id="i-align-center" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <path d="M4.25 6.25h15.5M7 10.25h10M4.25 14.25h15.5M7 18.25h10"/> </symbol>',
  '<symbol id="i-align-left" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <path d="M4.25 6.25h15.5M4.25 10.25h10M4.25 14.25h15.5M4.25 18.25h10"/> </symbol>',
  '<symbol id="i-arrow-left" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <path d="M19.75 12H4.25M10.25 6l-6 6 6 6"/> </symbol>',
  '<symbol id="i-arrow-right" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <path d="M4.25 12h15.5M13.75 6l6 6-6 6"/> </symbol>',
  '<symbol id="i-bag" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <path d="M5.25 8.25h13.5l-.96 11.4a1.5 1.5 0 0 1-1.5 1.35H7.71a1.5 1.5 0 0 1-1.5-1.35z"/> <path d="M8.75 10.75v-3.5a3.25 3.25 0 0 1 6.5 0v3.5"/> </symbol>',
  '<symbol id="i-bolt" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <path d="M13.25 2.75L5.25 13.5h6.25l-.75 7.75 8-10.75H12.5z"/> </symbol>',
  '<symbol id="i-button" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <rect x="3.25" y="7.25" width="17.5" height="9.5" rx="4.75"/> <path d="M8.75 12h6.5"/> </symbol>',
  '<symbol id="i-cart" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <path d="M2.75 4.25h2.1l2.3 10.4a1.5 1.5 0 0 0 1.47 1.18h8.46a1.5 1.5 0 0 0 1.45-1.12L20.5 7.75H5.62"/> <circle cx="9.25" cy="19.5" r="1.25"/> <circle cx="16.75" cy="19.5" r="1.25"/> </symbol>',
  '<symbol id="i-cash" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <rect x="2.75" y="6.25" width="18.5" height="11.5" rx="1.5"/> <circle cx="12" cy="12" r="2.5"/> <path d="M6.25 9.75v4.5M17.75 9.75v4.5"/> </symbol>',
  '<symbol id="i-chat" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <path d="M4.25 5.75a1.5 1.5 0 0 1 1.5-1.5h12.5a1.5 1.5 0 0 1 1.5 1.5v9a1.5 1.5 0 0 1-1.5 1.5H9.5l-4.25 3.5z"/> <path d="M8.25 9.25h7.5M8.25 12.25h4.5"/> </symbol>',
  '<symbol id="i-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <path d="M4.75 12.5l4.5 4.5 10-10"/> </symbol>',
  '<symbol id="i-chevron-down" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <path d="M5.5 9.25l6.5 6.5 6.5-6.5"/> </symbol>',
  '<symbol id="i-chevron-left" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <path d="M14.75 5.5L8.25 12l6.5 6.5"/> </symbol>',
  '<symbol id="i-chevron-right" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <path d="M9.25 5.5l6.5 6.5-6.5 6.5"/> </symbol>',
  '<symbol id="i-chevron-up" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <path d="M5.5 14.75l6.5-6.5 6.5 6.5"/> </symbol>',
  '<symbol id="i-clock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <circle cx="12" cy="12" r="8.75"/> <path d="M12 7.25V12l3.25 2"/> </symbol>',
  '<symbol id="i-close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <path d="M6 6l12 12M18 6L6 18"/> </symbol>',
  '<symbol id="i-columns" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <rect x="3.25" y="4.25" width="17.5" height="15.5" rx="1.5"/> <path d="M9.1 4.25v15.5M14.9 4.25v15.5"/> </symbol>',
  '<symbol id="i-copy" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <rect x="8.75" y="8.75" width="11.5" height="11.5" rx="1.5"/> <path d="M15.25 8.75v-3a1.5 1.5 0 0 0-1.5-1.5h-8a1.5 1.5 0 0 0-1.5 1.5v8a1.5 1.5 0 0 0 1.5 1.5h3"/> </symbol>',
  '<symbol id="i-external" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <path d="M13.75 4.25h6v6M19.75 4.25l-8.5 8.5"/> <path d="M17.75 13.75v4.5a1.5 1.5 0 0 1-1.5 1.5h-10.5a1.5 1.5 0 0 1-1.5-1.5V7.75a1.5 1.5 0 0 1 1.5-1.5h4.5"/> </symbol>',
  '<symbol id="i-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <path d="M2.75 12S6.25 5.75 12 5.75 21.25 12 21.25 12 17.75 18.25 12 18.25 2.75 12 2.75 12z"/> <circle cx="12" cy="12" r="3"/> </symbol>',
  '<symbol id="i-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <path d="M9.9 6.05A9.3 9.3 0 0 1 12 5.75c5.75 0 9.25 6.25 9.25 6.25a16.4 16.4 0 0 1-2.5 3.2M14.1 14.15A3 3 0 0 1 9.85 9.9"/> <path d="M6.6 7.6C4.2 9.2 2.75 12 2.75 12S6.25 18.25 12 18.25a8.9 8.9 0 0 0 4.3-1.1"/> <path d="M3.75 3.75l16.5 16.5"/> </symbol>',
  '<symbol id="i-facebook" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <path d="M14.75 3.75h-2a3.25 3.25 0 0 0-3.25 3.25v3H7.25v3H9.5v7.25h3.25V13h2.4l.6-3h-3V7.6a.85.85 0 0 1 .85-.85h1.15z"/> </symbol>',
  '<symbol id="i-file" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M13.25 3.75H7.25a1.5 1.5 0 0 0-1.5 1.5v13.5a1.5 1.5 0 0 0 1.5 1.5h9.5a1.5 1.5 0 0 0 1.5-1.5V8.75z"/><path d="M13.25 3.75v5h5"/></symbol>',
  '<symbol id="i-lock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="4.75" y="10.25" width="14.5" height="10" rx="1.5"/><path d="M8.25 10.25v-3a3.75 3.75 0 0 1 7.5 0v3"/></symbol>',
  '<symbol id="i-sparkle" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3.75l1.9 5.35 5.35 1.9-5.35 1.9L12 18.25l-1.9-5.35L4.75 11l5.35-1.9z"/><path d="M18.5 15.5l.75 2 2 .75-2 .75-.75 2-.75-2-2-.75 2-.75z"/></symbol>',
  '<symbol id="i-filter" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <path d="M3.75 5.75h16.5M6.75 12h10.5M10 18.25h4"/> </symbol>',
  '<symbol id="i-grip" viewBox="0 0 24 24" fill="currentColor" stroke="none"> <circle cx="9" cy="6" r="1.25"/><circle cx="15" cy="6" r="1.25"/> <circle cx="9" cy="12" r="1.25"/><circle cx="15" cy="12" r="1.25"/> <circle cx="9" cy="18" r="1.25"/><circle cx="15" cy="18" r="1.25"/> </symbol>',
  '<symbol id="i-heart" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <path d="M12 19.75s-7.75-4.55-7.75-10.1A4.15 4.15 0 0 1 12 7.4a4.15 4.15 0 0 1 7.75 2.25c0 5.55-7.75 10.1-7.75 10.1z"/> </symbol>',
  '<symbol id="i-image" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <rect x="3.25" y="4.25" width="17.5" height="15.5" rx="1.5"/> <circle cx="9" cy="9.75" r="1.5"/> <path d="M3.75 17.25l4.75-4.75 3.75 3.75 2.5-2.5 5.5 5.5"/> </symbol>',
  '<symbol id="i-info" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <circle cx="12" cy="12" r="8.75"/> <path d="M12 11v5.25M12 7.75v.01"/> </symbol>',
  '<symbol id="i-instagram" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <rect x="3.75" y="3.75" width="16.5" height="16.5" rx="4.75"/> <circle cx="12" cy="12" r="3.75"/> <circle cx="16.9" cy="7.1" r=".6" fill="currentColor"/> </symbol>',
  '<symbol id="i-layers" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <path d="M12 3.75l8.25 4.25L12 12.25 3.75 8z"/> <path d="M3.75 12L12 16.25 20.25 12M3.75 16L12 20.25 20.25 16"/> </symbol>',
  '<symbol id="i-layout" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <rect x="3.25" y="3.25" width="17.5" height="17.5" rx="1.5"/> <path d="M3.25 9.25h17.5M9.75 9.25v11.5"/> </symbol>',
  '<symbol id="i-mail" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <rect x="3.25" y="5.25" width="17.5" height="13.5" rx="1.5"/> <path d="M3.75 6.75L12 12.75l8.25-6"/> </symbol>',
  '<symbol id="i-map-pin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <path d="M12 20.75s-6.5-5.6-6.5-10.75a6.5 6.5 0 0 1 13 0c0 5.15-6.5 10.75-6.5 10.75z"/> <circle cx="12" cy="10" r="2.25"/> </symbol>',
  '<symbol id="i-menu" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <path d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5"/> </symbol>',
  '<symbol id="i-minus" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <path d="M5 12h14"/> </symbol>',
  '<symbol id="i-monitor" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <rect x="2.75" y="4.25" width="18.5" height="12.5" rx="1.5"/> <path d="M8.75 20.25h6.5M12 16.75v3.5"/> </symbol>',
  '<symbol id="i-package" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <path d="M12 3.25l8 4v9.5l-8 4-8-4v-9.5z"/> <path d="M4 7.25l8 4 8-4M12 11.25v9.5M8 5.25l8 4"/> </symbol>',
  '<symbol id="i-palette" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <path d="M12 3.25a8.75 8.75 0 1 0 0 17.5c1.15 0 1.8-.85 1.45-1.9-.4-1.2.4-2.35 1.65-2.35h1.9a3.75 3.75 0 0 0 3.75-3.75c0-5.3-3.9-9.5-8.75-9.5z"/> <circle cx="7.75" cy="11.25" r="1" fill="currentColor"/> <circle cx="10" cy="7.5" r="1" fill="currentColor"/> <circle cx="14.5" cy="7.5" r="1" fill="currentColor"/> </symbol>',
  '<symbol id="i-pause" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <path d="M9 5.75v12.5M15 5.75v12.5"/> </symbol>',
  '<symbol id="i-phone" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <path d="M6.55 3.75h2.6l1.35 4.05-1.85 1.4a11.25 11.25 0 0 0 6.15 6.15l1.4-1.85 4.05 1.35v2.6a2 2 0 0 1-2.15 2A15.75 15.75 0 0 1 4.55 5.9a2 2 0 0 1 2-2.15z"/> </symbol>',
  '<symbol id="i-play" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <path d="M7.75 5.25v13.5L18.75 12z"/> </symbol>',
  '<symbol id="i-plus" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <path d="M12 5v14M5 12h14"/> </symbol>',
  '<symbol id="i-plus-square" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <rect x="3.75" y="3.75" width="16.5" height="16.5" rx="2"/> <path d="M12 8.25v7.5M8.25 12h7.5"/> </symbol>',
  '<symbol id="i-redo" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <path d="M15.25 14.25l4.5-4.5-4.5-4.5"/> <path d="M19.75 9.75H9.5a5.25 5.25 0 0 0 0 10.5H13"/> </symbol>',
  '<symbol id="i-rotate-ccw" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <path d="M3.75 4.75v5h5"/> <path d="M5.1 15.25a7.75 7.75 0 1 0 1.45-8.3L3.75 9.75"/> </symbol>',
  '<symbol id="i-search" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <circle cx="10.75" cy="10.75" r="6.25"/> <path d="M15.25 15.25l4.5 4.5"/> </symbol>',
  '<symbol id="i-settings" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <path d="M3.75 7.25h8.5M17.25 7.25h3M3.75 16.75h3M11.75 16.75h8.5"/> <circle cx="14.75" cy="7.25" r="2.25"/> <circle cx="9.25" cy="16.75" r="2.25"/> </symbol>',
  '<symbol id="i-shield-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <path d="M12 3.25l7.25 2.9v5.35c0 4.45-3.05 7.95-7.25 9.25-4.2-1.3-7.25-4.8-7.25-9.25V6.15z"/> <path d="M8.75 12.1l2.25 2.25 4.25-4.6"/> </symbol>',
  '<symbol id="i-smartphone" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <rect x="7" y="2.75" width="10" height="18.5" rx="2"/> <path d="M11 18h2"/> </symbol>',
  '<symbol id="i-star" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <path d="M12 3.5l2.6 5.45 5.9.7-4.35 4.05 1.15 5.85L12 16.65 6.7 19.55l1.15-5.85L3.5 9.65l5.9-.7z"/> </symbol>',
  '<symbol id="i-star-fill" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <path d="M12 3.5l2.6 5.45 5.9.7-4.35 4.05 1.15 5.85L12 16.65 6.7 19.55l1.15-5.85L3.5 9.65l5.9-.7z"/> </symbol>',
  '<symbol id="i-star-half" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <path d="M12 16.65L6.7 19.55l1.15-5.85L3.5 9.65l5.9-.7L12 3.5z" fill="currentColor"/> <path d="M12 3.5l2.6 5.45 5.9.7-4.35 4.05 1.15 5.85L12 16.65 6.7 19.55l1.15-5.85L3.5 9.65l5.9-.7z"/> </symbol>',
  '<symbol id="i-tablet" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <rect x="5.25" y="2.75" width="13.5" height="18.5" rx="2"/> <path d="M11 17.75h2"/> </symbol>',
  '<symbol id="i-tag" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <path d="M3.75 4.75v6.1a1.5 1.5 0 0 0 .44 1.06l8.1 8.1a1.5 1.5 0 0 0 2.12 0l5.84-5.84a1.5 1.5 0 0 0 0-2.12l-8.1-8.1a1.5 1.5 0 0 0-1.06-.44h-6.1a1.25 1.25 0 0 0-1.24 1.24z"/> <circle cx="8.25" cy="8.75" r="1.25"/> </symbol>',
  '<symbol id="i-tiktok" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <path d="M13.75 3.75v11.1a3.65 3.65 0 1 1-3.65-3.65"/> <path d="M13.75 3.75c.35 2.55 2.15 4.3 4.75 4.5"/> </symbol>',
  '<symbol id="i-trash" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <path d="M4.25 6.75h15.5M9.25 6.75v-2h5.5v2"/> <path d="M6.25 6.75l.8 12.6a1.5 1.5 0 0 0 1.5 1.4h6.9a1.5 1.5 0 0 0 1.5-1.4l.8-12.6M10 10.75v6M14 10.75v6"/> </symbol>',
  '<symbol id="i-truck" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <path d="M14.25 16.75V6.25H2.75v10.5h2.1M9.6 16.75h4.65"/> <path d="M14.25 9.75h3.6l3.4 3.6v3.4h-1.85"/> <circle cx="7.25" cy="16.75" r="2"/> <circle cx="17" cy="16.75" r="2"/> </symbol>',
  '<symbol id="i-type" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <path d="M5 7.25v-2.5h14v2.5M12 4.75v14.5M9 19.25h6"/> </symbol>',
  '<symbol id="i-undo" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <path d="M8.75 14.25l-4.5-4.5 4.5-4.5"/> <path d="M4.25 9.75h10.25a5.25 5.25 0 0 1 0 10.5H11"/> </symbol>',
  '<symbol id="i-user" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <circle cx="12" cy="8.25" r="3.75"/> <path d="M4.75 20.25c.85-3.7 3.65-5.75 7.25-5.75s6.4 2.05 7.25 5.75"/> </symbol>',
  '<symbol id="i-wrench" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <path d="M14.6 6.1a4.25 4.25 0 0 0 5.3 5.3l-.02-.02a4.25 4.25 0 0 1-5.5.35L7.9 18.2a1.77 1.77 0 0 1-2.5-2.5l6.48-6.48a4.25 4.25 0 0 1 .35-5.5 4.25 4.25 0 0 1 2.37 2.38z"/> </symbol>',
  '<symbol id="i-youtube" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <rect x="2.75" y="5.75" width="18.5" height="12.5" rx="3.5"/> <path d="M10.25 9.25v5.5l4.5-2.75z"/> </symbol>',
];

const SVG_NS = 'http://www.w3.org/2000/svg';

/** Adds the sprite to the page unless it is already there. */
export function installIcons(doc = document) {
  if (doc.getElementById('xelga-editor-icons')) return;
  const parsed = new DOMParser().parseFromString(`<svg xmlns="${SVG_NS}">${SYMBOLS.join('')}</svg>`, 'image/svg+xml');
  const sprite = doc.createElementNS(SVG_NS, 'svg');
  sprite.id = 'xelga-editor-icons';
  sprite.setAttribute('aria-hidden', 'true');
  sprite.setAttribute('focusable', 'false');
  sprite.setAttribute('width', '0');
  sprite.setAttribute('height', '0');
  sprite.classList.add('icon-sprite');
  Array.from(parsed.documentElement.children).forEach((symbol) => sprite.append(doc.importNode(symbol, true)));
  doc.body.prepend(sprite);
}
