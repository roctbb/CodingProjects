export default function initRoomCharacters() {
    document.querySelectorAll('[data-room-character-form]').forEach(function (form) {
        const button = form.querySelector('[data-room-character-submit]');
        const label = form.querySelector('[data-room-character-button-text]');
        const status = form.querySelector('[data-room-character-status]');
        const balanceLabel = form.querySelector('[data-room-character-balance]');
        const token = form.querySelector('input[name="request_id"]');
        const cost = Number(form.dataset.cost);
        let balance = Number(form.dataset.balance);
        let busy = form.dataset.busy === '1';
        let hasCharacter = form.dataset.hasCharacter === '1';
        let statusUrl = form.dataset.statusUrl;
        let timer;

        function renderButton() {
            button.disabled = busy || form.dataset.hasAvatar !== '1' || balance < cost;
            label.textContent = busy ? 'Создаём персонажа…' : (hasCharacter ? 'Перегенерировать — ' : 'Создать по аватарке — ') + cost + ' GC';
        }

        function showMessage(text, failed = false) {
            status.textContent = text;
            status.classList.toggle('text-danger', failed);
            status.classList.toggle('text-muted', !failed);
        }

        function applyState(data) {
            busy = data.status === 'pending' || data.status === 'processing';
            balance = data.balance;
            balanceLabel.textContent = balance;
            statusUrl = data.status_url;
            if (!busy && data.next_request_id) token.value = data.next_request_id;
            showMessage(data.message, data.status === 'failed');
            const preview = document.getElementById(form.dataset.previewTarget);
            if (data.image_url && preview) {
                hasCharacter = true;
                const layer = preview.querySelector('[data-learning-avatar-layer-slot="character"]');
                const image = layer?.tagName === 'IMG' ? layer : layer?.querySelector('img');
                if (image) image.src = data.image_url;
                preview.dataset.customCharacterUrl = data.image_url;
            }
            renderButton();
            clearTimeout(timer);
            if (busy) timer = setTimeout(poll, 3000);
        }

        async function poll() {
            try {
                const response = await fetch(statusUrl, { headers: { Accept: 'application/json' }, cache: 'no-store' });
                if (response.status === 401 || response.status === 403) {
                    showMessage('Сессия завершилась. Войдите снова, чтобы увидеть результат генерации.');
                    return;
                }
                if (!response.ok) throw new Error('status_unavailable');
                applyState(await response.json());
            } catch (error) {
                showMessage('Не удалось обновить статус. Повторим проверку автоматически.');
                timer = setTimeout(poll, 15000);
            }
        }

        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            if (busy || button.disabled) return;
            busy = true;
            renderButton();
            showMessage('Запускаем генерацию…');
            let userError = null;
            try {
                const response = await fetch(form.action, {
                    method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' },
                });
                const data = await response.json();
                if (!response.ok) {
                    userError = data.errors ? Object.values(data.errors).flat()[0] : 'Не удалось запустить генерацию. Попробуйте ещё раз.';
                    throw new Error('request_failed');
                }
                applyState(data);
            } catch (error) {
                busy = false;
                renderButton();
                // Retain request_id: resending after a lost response cannot charge twice.
                showMessage(userError || 'Не удалось получить ответ. Повторите запрос — повторного списания не будет.', true);
            }
        });
        renderButton();
        if (busy && statusUrl) timer = setTimeout(poll, 1000);
        window.addEventListener('pagehide', () => clearTimeout(timer), { once: true });
    });
}
