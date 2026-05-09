<script>
    document.addEventListener('DOMContentLoaded', function () {
        const fields = document.querySelectorAll('[data-market-preview-field]');
        const previewName = document.querySelector('[data-market-preview-name]');
        const previewDescription = document.querySelector('[data-market-preview-description]');
        const previewStock = document.querySelector('[data-market-preview-stock]');
        const previewSaleType = document.querySelector('[data-market-preview-sale-type]');
        const previewAction = document.querySelector('[data-market-preview-action]');
        const previewPrice = document.querySelector('[data-market-preview-price]');
        const previewImage = document.querySelector('[data-market-preview-image]');
        const previewEmpty = document.querySelector('[data-market-preview-empty]');

        if (!fields.length) return;

        const getField = (name) => document.querySelector(`[data-market-preview-field="${name}"]`);

        function updatePreview() {
            const name = getField('name')?.value.trim() || 'Новый товар';
            const description = getField('description')?.value.trim() || 'Описание товара появится здесь.';
            const number = Number(getField('number')?.value || 0);
            const price = getField('price')?.value.trim() || '0';
            const saleType = getField('sale_type')?.value || 'regular';
            const image = getField('image')?.value.trim();

            if (previewName) previewName.textContent = name;
            if (previewDescription) previewDescription.textContent = description;
            if (previewPrice) previewPrice.textContent = price;
            if (previewSaleType) previewSaleType.textContent = saleType === 'auction' ? 'Аукцион' : 'Покупка';
            if (previewAction) previewAction.textContent = saleType === 'auction' ? 'Ставка от' : 'Купить за';

            if (previewStock) {
                const hasStock = number > 0;
                previewStock.textContent = hasStock ? `В наличии: ${number}` : 'Закончился';
                previewStock.classList.toggle('bg-body-tertiary', hasStock);
                previewStock.classList.toggle('text-muted', hasStock);
                previewStock.classList.toggle('border', hasStock);
                previewStock.classList.toggle('bg-warning-subtle', !hasStock);
                previewStock.classList.toggle('text-warning-emphasis', !hasStock);
                previewStock.classList.toggle('border-warning-subtle', !hasStock);
            }

            if (previewImage && previewEmpty) {
                if (image) {
                    previewImage.src = image;
                    previewImage.alt = name;
                    previewImage.classList.remove('d-none');
                    previewEmpty.classList.add('d-none');
                } else {
                    previewImage.removeAttribute('src');
                    previewImage.classList.add('d-none');
                    previewEmpty.classList.remove('d-none');
                }
            }
        }

        fields.forEach((field) => field.addEventListener('input', updatePreview));
        updatePreview();
    });
</script>
