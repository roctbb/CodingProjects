<div {{ $attributes->merge(['class' => 'card gc-card']) }}>
    @if(isset($header))
        <div class="card-header bg-transparent">{{ $header }}</div>
    @endif
    <div class="card-body">
        {{ $slot }}
    </div>
</div>
