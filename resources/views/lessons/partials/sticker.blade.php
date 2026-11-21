<div class="col-12" data-sticker-field>
    <label for="sticker" class="form-label">Наклейка за урок</label>
    <select id="sticker" class="form-select @error('sticker') is-invalid @enderror" name="sticker" aria-describedby="sticker-help">
        <option value="">Без наклейки</option>
        @foreach($stickerGroups as $collection => $items)
            <optgroup label="{{ $collection }}">
                @foreach($items as $item)
                    <option value="{{ $item->image }}" @selected(old('sticker', $sticker ?? '') === $item->image)>{{ $item->name }}</option>
                @endforeach
            </optgroup>
        @endforeach
    </select>
    <div id="sticker-help" class="form-text">Выдаётся за результат выше 90% в уроке с задачами.
        @if(auth()->user()?->role === 'admin')<a href="{{ route('settings.stickers.index') }}" target="_blank" rel="noopener">Каталог наклеек</a>@endif
    </div>
    @error('sticker')<div class="invalid-feedback">Выберите наклейку из каталога.</div>@enderror
    <div id="sticker-preview" class="mt-2" hidden><img id="sticker-preview-image" width="72" height="72" class="object-fit-contain" alt="Выбранная наклейка"></div>
    <div id="sticker-preview-error" class="form-text" role="status" hidden>Изображение недоступно.</div>
</div>
