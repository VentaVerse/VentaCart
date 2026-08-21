@props(['title' => 'Admin'])
{{-- The framed page's half of the channel.

     The layout editor's panel needs three things from a module page it did
     not write: what to call it in the trail, whether there is anywhere to
     go back to inside the frame, and whether a save just landed so the
     editor can refresh the choices it is showing. All three are facts this
     page already has, so it states them and stops. No module writes any of
     this, and a page that is never framed never runs it. --}}
<script>
    (function () {
        if (window.parent === window) { return; }

        var say = function (type, extra) {
            var msg = Object.assign({
                v: 'venta-frame',
                type: type,
                title: @js($title),
                url: window.location.href,
                depth: window.history.length
            }, extra || {});
            // Same origin only: this channel carries nothing secret, but a
            // wildcard target would post it to whatever ends up embedding us.
            window.parent.postMessage(msg, window.location.origin);
        };

        // A module's own links know nothing about panels, so they would walk
        // the merchant out of the frame and rebuild the whole sidebar inside
        // it. Stamped here rather than asked of every module, and re-run
        // after Alpine has rendered whatever it renders.
        var FLAG = @js(\App\Support\AdminFrame::FLAG);

        var keep = function (url) {
            try {
                var u = new URL(url, window.location.href);
                if (u.origin !== window.location.origin) { return null; }
                if (u.searchParams.get(FLAG) === '1') { return null; }
                u.searchParams.set(FLAG, '1');
                return u.href;
            } catch (e) { return null; }
        };

        var stamp = function () {
            document.querySelectorAll('a[href]').forEach(function (a) {
                // A link that deliberately leaves is left alone: it opens a
                // real tab, where the admin should wear its own chrome.
                if (a.target === '_blank' || a.hasAttribute('download')) { return; }
                if (a.getAttribute('href').charAt(0) === '#') { return; }
                var next = keep(a.href);
                if (next) { a.href = next; }
            });

            document.querySelectorAll('form').forEach(function (f) {
                var next = keep(f.action || window.location.href);
                if (next) { f.action = next; }
            });
        };

        stamp();
        document.addEventListener('alpine:initialized', stamp);

        say('ready');

        @if (session('success'))
            say('saved', { message: @js(session('success')) });
        @endif

        // The host closes the panel from the outside; the page inside it is
        // told so its own leave guard can run first.
        window.addEventListener('message', function (e) {
            if (e.origin !== window.location.origin) { return; }
            if (!e.data || e.data.v !== 'venta-frame-host') { return; }
            if (e.data.type === 'back') { window.history.back(); }
        });
    })();
</script>
