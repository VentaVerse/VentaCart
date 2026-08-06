<script>
    (function () {
        var pref = localStorage.getItem('vTheme') || 'system';
        var dark = pref === 'dark' || (pref === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
        document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
    })();
</script>
