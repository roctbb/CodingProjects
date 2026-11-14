<x-gc-panel title="Наклейки" icon="fas fa-note-sticky" id="profile-stickers" class="gc-reward-shelf">
    <x-slot:actions><span class="gc-help-text">{{ $stickers->count() }}</span></x-slot:actions>
    <div class="gc-sticker-collection">
        @foreach($stickers as $sticker)
            @php($stickerTitle = $sticker_description[$sticker] ?? 'Наклейка за урок')
            <button type="button" class="gc-sticker-collection__item" data-collection-viewer data-trophy-src="{{ url($sticker) }}" data-trophy-title="{{ $stickerTitle }}" data-trophy-alt="{{ $stickerTitle }}" aria-label="Рассмотреть наклейку: {{ $stickerTitle }}" title="{{ $stickerTitle }}">
                <img src="{{ url($sticker) }}" width="72" height="72" alt="" loading="lazy">
            </button>
        @endforeach
    </div>
</x-gc-panel>
