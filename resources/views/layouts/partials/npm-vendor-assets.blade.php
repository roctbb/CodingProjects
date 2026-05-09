<script>
    (function () {
        try {
            var theme = localStorage.getItem('gc-theme');
            if (theme) document.documentElement.setAttribute('data-bs-theme', theme);
        } catch (e) {}
    })();
</script>
<link rel="stylesheet" href="{{ asset('build/css/app.css') }}">
