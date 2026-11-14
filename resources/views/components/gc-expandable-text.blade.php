@props(['text', 'limit' => 150])
@if(mb_strlen($text ?? '') > $limit)
    <details {{ $attributes->class(['gc-expandable-text']) }}>
        <summary><span class="gc-expandable-text__preview">{{ \Illuminate\Support\Str::limit($text, $limit) }}</span><span class="gc-expandable-text__toggle"><span class="gc-expandable-text__more">Подробнее</span><span class="gc-expandable-text__less">Свернуть</span></span></summary>
        <div class="gc-preserve-lines">{{ $text }}</div>
    </details>
@else
    <p {{ $attributes->class(['gc-preserve-lines', 'mb-0']) }}>{{ $text }}</p>
@endif
