<div class="col-12" data-sticker-field>
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
