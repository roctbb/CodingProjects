@props(['course', 'chapter', 'viewer', 'embedded' => false, 'projectPath' => null, 'currentChapterId' => null])

@php
    $navigation = \App\Support\CourseChapterNavigation::forCourse($course, $chapter, $viewer);
    $items = $navigation['items'];
    $selected = $navigation['selected'];
    $count = count($items);
    $position = $count > 1 ? $selected * 100 / ($count - 1) : 0;
    $statusId = 'chapter-timeline-status-'.$course->id;
    $pickerId = 'chapter-picker-'.$course->id;
@endphp
<section class="{{ $embedded ? 'gc-chapter-timeline--embedded' : 'gc-card' }} gc-chapter-timeline {{ $projectPath ? 'gc-chapter-timeline--journey' : '' }} {{ $count === 1 ? 'gc-chapter-timeline--single' : '' }} {{ $chapter->id === $currentChapterId ? 'is-current-chapter' : '' }} mb-3" data-chapter-timeline aria-label="Выбор главы" style="--gc-timeline-position: {{ $position }}%; --gc-timeline-count: {{ $count }};">
    <div class="dropdown gc-chapter-timeline__picker" data-chapter-picker>
        <button type="button" class="gc-chapter-timeline__choose" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" aria-controls="{{ $pickerId }}" title="Глава {{ $selected + 1 }} из {{ $count }}: {{ $chapter->name }}">
            <span class="gc-chapter-timeline__text">
                <span class="gc-help-text gc-chapter-timeline__counter" data-chapter-counter>Глава {{ $selected + 1 }} из {{ $count }}</span>
                <span class="gc-chapter-timeline__title" data-chapter-title>{{ $chapter->name }}</span>
            </span>
            <i class="fas fa-chevron-down" aria-hidden="true"></i>
        </button>
        <div id="{{ $pickerId }}" class="dropdown-menu gc-chapter-picker" aria-label="Главы курса">
            <div class="gc-chapter-picker__heading"><h3>Главы курса</h3><span class="gc-help-text">{{ $count }}</span></div>
            @if($count > 8)
                <input type="search" class="form-control gc-chapter-picker__search" placeholder="Найти главу" aria-label="Найти главу" autocomplete="off" data-chapter-search>
            @endif
            <nav aria-label="Список глав" class="gc-chapter-picker__list" data-chapter-list>
                @foreach($items as $index => $item)
                    <div data-chapter-choice data-chapter-name="{{ $item['name'] }}">
                        @if($item['url'])
                            <a class="dropdown-item gc-chapter-picker__item" href="{{ $item['url'] }}" @if($index === $selected) aria-current="page" @endif>
                                <span class="gc-chapter-picker__number" aria-hidden="true">{{ $index + 1 }}</span><span>{{ $item['name'] }}@if($item['id'] === $currentChapterId)<span class="gc-chapter-picker__current">Текущая</span>@endif</span>
                                @if($index === $selected)<span class="gc-chapter-picker__state"><i class="fas fa-check" aria-hidden="true"></i><span class="visually-hidden">Выбранная глава</span></span>@endif
                            </a>
                        @else
                            <span class="gc-chapter-picker__item is-locked" aria-disabled="true" @if($index === $selected) aria-current="page" @endif>
                                <span class="gc-chapter-picker__number" aria-hidden="true">{{ $index + 1 }}</span><span>{{ $item['name'] }}</span><span class="gc-chapter-picker__state"><i class="fas fa-lock" aria-hidden="true"></i><span class="visually-hidden">Пока закрыта</span></span>
                            </span>
                        @endif
                    </div>
                @endforeach
            </nav>
            <p class="gc-help-text gc-chapter-picker__empty" data-chapter-empty role="status" hidden>Главы не найдены.</p>
        </div>
    </div>
    @if($projectPath)
        <x-gc-project-path :path="$projectPath" :current-chapter-id="$currentChapterId" />
    @elseif($count > 1)
        <div class="gc-chapter-timeline__rail">
            <div class="gc-chapter-timeline__track" aria-hidden="true">
                <span class="gc-chapter-timeline__fill"></span>
                @foreach($items as $index => $item)
                    <span class="gc-chapter-timeline__dot {{ $item['url'] ? '' : 'is-locked' }}" style="left: {{ $index * 100 / ($count - 1) }}%;"></span>
                @endforeach
                <span class="gc-chapter-timeline__marker"></span>
            </div>
            <input type="range" class="gc-chapter-timeline__range" min="0" max="{{ $count - 1 }}" step="1" value="{{ $selected }}" aria-label="Выбрать главу на таймлайне" aria-valuetext="Глава {{ $selected + 1 }} из {{ $count }}: {{ $chapter->name }}" aria-describedby="{{ $statusId }}" data-chapter-range hidden @disabled(!$navigation['hasAlternatives'])>
        </div>
        <p class="gc-help-text mb-0" id="{{ $statusId }}" data-chapter-status role="status" @if($navigation['hasAlternatives']) hidden @endif>@if(!$navigation['hasAlternatives']){{ $navigation['availableCount'] ? 'Другие главы пока закрыты.' : 'Главы пока не открыты.' }}@endif</p>
        <noscript>
            <form method="GET" action="{{ url('/insider/courses/'.$course->id) }}" class="gc-toolbar mt-2">
                <select name="chapter" class="form-select form-select-sm" aria-label="Глава курса">
                    @foreach($items as $index => $item)
                        <option value="{{ $item['id'] }}" @selected($index === $selected) @disabled(!$item['url'])>{{ $index + 1 }}. {{ $item['name'] }}</option>
                    @endforeach
                </select>
                <button class="btn btn-primary btn-sm" @disabled(!$navigation['availableCount'])>Перейти</button>
            </form>
        </noscript>
    @endif
    @isset($actions) {{ $actions }} @endisset
    <script type="application/json" data-chapter-options>@json(['items' => $items, 'selected' => $selected, 'currentChapterId' => $currentChapterId], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)</script>
</section>
