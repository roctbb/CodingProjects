@props(['paginator', 'label' => 'Страницы списка'])

@if($paginator->hasPages())
<nav {{ $attributes->class(['gc-page-navigation']) }} aria-label="{{ $label }}">
    <span class="visually-hidden">Страница {{ $paginator->currentPage() }} из {{ $paginator->lastPage() }}</span>
    @foreach([['Предыдущая страница', 'left', $paginator->previousPageUrl()], ['Следующая страница', 'right', $paginator->nextPageUrl()]] as [$title, $direction, $href])
        @if($href)
            <a class="btn btn-outline-secondary btn-sm gc-icon-button" href="{{ $href }}" aria-label="{{ $title }}" title="{{ $title }}"><i class="fas fa-chevron-{{ $direction }}" aria-hidden="true"></i></a>
        @else
            <button class="btn btn-outline-secondary btn-sm gc-icon-button" type="button" disabled aria-label="{{ $title }}"><i class="fas fa-chevron-{{ $direction }}" aria-hidden="true"></i></button>
        @endif
    @endforeach
</nav>
@endif
