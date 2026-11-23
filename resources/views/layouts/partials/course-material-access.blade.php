@if(request()->attributes->get('watch_course_access'))
<script>
(() => {
    let checking = false;
    async function checkAccess() {
        if (checking || document.hidden) return;
        checking = true;
        try {
            const response = await fetch('{{ route('course-material-access', [], false) }}', {
                headers: {Accept: 'application/json'}, cache: 'no-store'
            });
            if (response.status === 403 && (await response.json()).error === 'strict_exam_active') {
                window.location.reload();
            }
        } catch {
            // Retry on the next check when the connection returns.
        } finally {
            checking = false;
        }
    }
    document.addEventListener('visibilitychange', checkAccess);
    window.addEventListener('pageshow', event => { if (event.persisted) checkAccess(); });
    window.setInterval(checkAccess, 15000);
})();
</script>
@endif
