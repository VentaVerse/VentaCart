<script>
    (function () {
        var pref = 'system';
        try {
            pref = localStorage.getItem('vTheme') || 'system';
        } catch (e) {
            pref = 'system';
        }
        var dark = pref === 'dark' || (pref === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
        document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
    })();
</script>
