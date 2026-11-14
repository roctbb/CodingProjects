<div {{ $attributes->class(['gc-tab-strip']) }} data-tab-strip>
    <button class="gc-tab-strip__scroll" type="button" data-tab-scroll="previous" aria-label="Прокрутить разделы влево" hidden>
        <i class="fas fa-chevron-left" aria-hidden="true"></i>
    </button>
    {{ $slot }}
    <button class="gc-tab-strip__scroll" type="button" data-tab-scroll="next" aria-label="Прокрутить разделы вправо" hidden>
        <i class="fas fa-chevron-right" aria-hidden="true"></i>
    </button>
</div>
