export default function initAiSettings() {
    const form = document.querySelector('[data-ai-settings]');
    if (!form) return;
    const provider = form.querySelector('[data-ai-provider-select]');
    const panels = [...form.querySelectorAll('[data-ai-provider]')];
    const syncSecret = select => {
        const input = document.getElementById(select.dataset.secretAction);
        const replace = select.value === 'replace' && !select.closest('fieldset').disabled;
        input.disabled = !replace;
        input.hidden = !replace;
        input.required = replace;
        if (!replace) input.value = '';
    };
    const sync = () => {
        panels.forEach(panel => {
            const active = panel.dataset.aiProvider === provider.value;
            panel.hidden = !active;
            panel.disabled = !active;
            panel.querySelectorAll('[data-secret-action]').forEach(syncSecret);
        });
        const active = panels.find(panel => !panel.hidden);
        const images = active.querySelector('[data-ai-image-api]')?.value === 'images';
        form.querySelector('[data-ai-model-hint="text"]').textContent = 'ID модели из каталога вашего API. Используется во всех текстовых операциях.';
        form.querySelector('[data-ai-model-hint="image"]').textContent = images
            ? 'ID модели для Images из каталога вашего API.'
            : 'Модель Responses с доступом к инструменту image_generation.';
    };
    form.addEventListener('change', sync);
    sync();
}
