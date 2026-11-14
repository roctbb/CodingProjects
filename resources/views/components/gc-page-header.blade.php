@props(['title', 'description' => null, 'embedded' => false, 'mark' => null])

<header {{ $attributes->class(['gc-page-header', 'gc-page-header--with-middle' => isset($middle), 'gc-page-header--embedded' => $embedded]) }}>
    <div class="gc-page-header__title min-width-0 {{ isset($portrait) ? 'gc-page-heading-identity' : '' }}">
        @isset($portrait) {{ $portrait }} @endisset
        <div class="min-width-0">
        @isset($breadcrumb)
            <div class="gc-breadcrumb">{{ $breadcrumb }}</div>
        @endisset
        @isset($icon)
            <div class="gc-heading-identity">{{ $icon }}<h1>{{ $title }}</h1></div>
        @else
            @if($mark)<div class="gc-heading-identity"><span class="gc-page-mark" aria-hidden="true"><i class="fas fa-{{ $mark }}"></i></span><h1>{{ $title }}</h1></div>
            @else<h1>{{ $title }}</h1>@endif
        @endisset
        @if($description)
            <p class="gc-page-description">{{ $description }}</p>
        @endif
        @isset($summary)<div class="gc-page-heading-summary">{{ $summary }}</div>@endisset
        </div>
    </div>
    @isset($middle)<div class="gc-page-header__middle">{{ $middle }}</div>@endisset
    @if($slot->isNotEmpty())
        <div class="gc-actions">{{ $slot }}</div>
    @endif
</header>
