export default function initMarketPreviews() {
    document.querySelectorAll('[data-market-preview]').forEach(preview => {
        const form = document.getElementById(preview.dataset.marketPreview);
        if (!form) return;
        const field = name => form.querySelector(`[data-market-preview-field="${name}"]`);
        const text = (name, value) => { const node = preview.querySelector(`[data-market-preview-${name}]`); if (node) node.textContent = value; };
        const update = () => {
            const name = field('name')?.value.trim() || 'Новый товар';
            const number = Number(field('number')?.value || 0);
            const auction = field('sale_type')?.value === 'auction';
            text('name', name);
            text('description', field('description')?.value.trim() || 'Описание товара появится здесь.');
            text('price', field('price')?.value.trim() || '0');
            text('sale-type', auction ? 'Аукцион' : 'Покупка');
            text('action', auction ? 'Ставка' : 'Купить');
            text('stock', number > 0 ? `В наличии: ${number}` : 'Закончился');
            const stock = preview.querySelector('[data-market-preview-stock]');
            if (stock) {
                stock.classList.toggle('bg-body-tertiary', number > 0);
                ['bg-warning-subtle','text-warning-emphasis','border','border-warning-subtle'].forEach(cls => stock.classList.toggle(cls, number <= 0));
            }
            const image = preview.querySelector('[data-market-preview-image]');
            const empty = preview.querySelector('[data-market-preview-empty]');
            const source = field('image')?.value.trim();
            if (image && empty) {
                image.alt = name;
                if (source && image.getAttribute('src') !== source) image.src = source;
                if (!source) image.removeAttribute('src');
                image.classList.toggle('d-none', !source);
                empty.classList.toggle('d-none', Boolean(source));
            }
        };
        form.addEventListener('input', update);
        form.addEventListener('change', update);
        update();
    });
}
