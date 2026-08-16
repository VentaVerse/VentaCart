{{-- Applies the theme before first paint so there is no flash of the wrong
     one. Resolution order: the stored choice, then light. Light is the
     default rather than the OS preference, because the working scene is a
     lit office in daylight; system is still selectable in the switch, and
     when chosen it does follow the OS and react to OS changes.

     The storage key and the global are Venta's own (vTheme), deliberately
     unlike the ERP's (xenon-theme), so the two products can never collide
     on a shared origin. --}}
{{-- The html element carries the canvas color so no navigation gap or
     cold load can ever paint white: whatever frame the browser shows
     before first render is already the page's own paper. --}}
<style>
    html { background-color: #EDF1F7; }
    html[data-theme="dark"] { background-color: #0B1017; }
    /* Desktop: the background is the app's own silhouette (azure nav
       band on paper), so a between-pages frame looks like the app's
       frame, never a white flash. Shells with a different band width
       override --boot-band in their own head. */
    @media (min-width: 1024px) {
        html { background: linear-gradient(to right, #1E4368 0 var(--boot-band, 216px), #EDF1F7 var(--boot-band, 216px)); }
        html[data-theme="dark"] { background: linear-gradient(to right, #10273C 0 var(--boot-band, 216px), #0B1017 var(--boot-band, 216px)); }
    }
</style>
<script>
    (function () {
        var KEY = 'vTheme';

        function resolve(mode) {
            if (mode === 'dark' || mode === 'light') return mode;
            return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }

        function apply(mode) {
            document.documentElement.setAttribute('data-theme', resolve(mode));
            document.documentElement.setAttribute('data-theme-mode', mode);
        }

        var stored = null;
        try { stored = localStorage.getItem(KEY); } catch (e) {}
        apply(stored || 'light');

        window.vTheme = {
            get: function () {
                try { return localStorage.getItem(KEY) || 'light'; } catch (e) { return 'light'; }
            },
            set: function (mode) {
                try { localStorage.setItem(KEY, mode); } catch (e) {}
                apply(mode);
            }
        };

        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
            if (window.vTheme.get() === 'system') apply('system');
        });
    })();
</script>
