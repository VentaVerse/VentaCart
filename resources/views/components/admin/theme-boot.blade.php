<script>
    (function () {
        var pref = 'light';
        try {
            pref = localStorage.getItem('vTheme') || 'light';
        } catch (e) {
            pref = 'light';
        }
        var dark = pref === 'dark' || (pref === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
        document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
    })();
</script>
