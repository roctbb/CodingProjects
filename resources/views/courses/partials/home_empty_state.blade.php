<div class="gc-empty-state">
    <div class="gc-empty-icon">
        <i class="{{ $icon }}"></i>
    </div>
    <h5>{{ $title }}</h5>
    <p class="mx-auto mb-0">{{ $text }}</p>
    @isset($actionUrl)
        <a class="btn btn-success rounded-3 mt-3" href="{{ $actionUrl }}">{{ $actionText }}</a>
    @endisset
</div>
