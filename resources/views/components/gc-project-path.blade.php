@props(['path', 'label' => 'Главы курса', 'currentChapterId' => false])
@if($path['total'] > 1)
<nav {{ $attributes->class(['gc-project-path']) }} aria-label="{{ $label }}">
    <ol>
        @foreach($path['nodes'] as $node)
            @php($chapterState = $node['state'].($node['id'] === $currentChapterId ? ' · Текущая глава' : ''))
            <li class="{{ $node['earned'] ? 'is-earned' : '' }} {{ $node['current'] ? 'is-current' : '' }} {{ !$node['url'] ? 'is-locked' : '' }} {{ $node['id'] === $currentChapterId ? 'is-course-current' : '' }} {{ $node['current'] && $currentChapterId !== false && $node['id'] !== $currentChapterId ? 'is-selected-other' : '' }}">
                @if($node['url'])<a href="{{ $node['url'] }}" @if($node['current']) aria-current="page" @endif aria-label="Глава {{ $node['number'] }}: {{ $node['name'] }}. {{ $chapterState }}" title="{{ $node['name'] }} · {{ $chapterState }}">
                @else<span class="gc-project-path__closed" aria-label="Глава {{ $node['number'] }}: {{ $node['name'] }}. {{ $chapterState }}" title="{{ $node['name'] }} · {{ $chapterState }}">
                @endif
                    <span class="gc-project-path__point" aria-hidden="true">@if($node['earned'])<i class="fas fa-check"></i>@elseif(!$node['url'])<i class="fas fa-lock"></i>@else{{ $node['number'] }}@endif</span>
                    <span class="gc-project-path__name">{{ $node['name'] }}</span>
                @if($node['url'])</a>@else</span>@endif
            </li>
        @endforeach
    </ol>
</nav>
@endif
