<div class="col-12">
    <label for="sticker" class="form-label">Ссылка на стикер</label>
    <input id="sticker" type="text" inputmode="url" class="form-control rounded-3 @error('sticker') is-invalid @enderror"
           name="sticker" value="{{ old('sticker', $sticker ?? null) }}" maxlength="255"
           placeholder="https://…/sticker.png" aria-describedby="sticker-help sticker-error">
    <div id="sticker-help" class="form-text">
        Загрузите изображение в <a href="https://storage.geekclass.ru" target="_blank" rel="noopener">GeekStorage</a>
        и вставьте прямую ссылку. Оставьте поле пустым, чтобы не выдавать стикер.
        Стикер выдаётся только за уроки с задачами.
    </div>
    @error('sticker')
        <div id="sticker-error" class="invalid-feedback">Укажите прямую ссылку на изображение по HTTP или HTTPS (до 255 символов).</div>
    @enderror
    <div id="sticker-preview" class="mt-3" hidden>
        <img id="sticker-preview-image" class="gc-sticker" height="128" style="max-width: 100%; object-fit: contain;" alt="Предпросмотр стикера">
    </div>
    <div id="sticker-preview-error" class="form-text text-danger" role="status" hidden>Не удалось загрузить изображение. Проверьте прямую ссылку.</div>
</div>

@push('editor')
<script>
    (() => {
        const input = document.getElementById('sticker');
        const preview = document.getElementById('sticker-preview');
        const image = document.getElementById('sticker-preview-image');
        const error = document.getElementById('sticker-preview-error');
        const update = () => {
            const value = input.value.trim();
            preview.hidden = true;
            error.hidden = true;
            image.removeAttribute('src');
            if (/^https?:\/\//i.test(value) || /^\/stickers\/[1-9][0-9]*\.png$/.test(value)) {
                image.src = value;
            }
        };
        const hasCurrentImage = () => input.value.trim() !== '' && image.getAttribute('src') === input.value.trim();
        image.addEventListener('load', () => {
            if (hasCurrentImage()) {
                preview.hidden = false;
                error.hidden = true;
            }
        });
        image.addEventListener('error', () => {
            if (hasCurrentImage()) {
                preview.hidden = true;
                error.hidden = false;
            }
        });
        input.addEventListener('input', update);
        update();
    })();
</script>
@endpush
