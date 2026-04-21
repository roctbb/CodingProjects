<script>
    (function () {
        function addHiddenInput(form, name, value) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            form.appendChild(input);
        }

        document.addEventListener('click', function (event) {
            var trigger = event.target.closest('[data-action-url][data-action-method]');
            if (!trigger) {
                return;
            }

            event.preventDefault();

            var confirmText = trigger.getAttribute('data-action-confirm');
            if (confirmText && !window.confirm(confirmText)) {
                return;
            }

            var csrfMeta = document.querySelector('meta[name="csrf-token"]');
            if (!csrfMeta) {
                return;
            }

            var form = document.createElement('form');
            var method = (trigger.getAttribute('data-action-method') || 'POST').toUpperCase();

            form.method = 'POST';
            form.action = trigger.getAttribute('data-action-url');
            form.style.display = 'none';

            addHiddenInput(form, '_token', csrfMeta.getAttribute('content'));
            if (method !== 'POST') {
                addHiddenInput(form, '_method', method);
            }

            document.body.appendChild(form);
            form.submit();
        });
    })();
</script>
