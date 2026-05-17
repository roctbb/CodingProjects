@props(['user' => null, 'data' => null])

@php
    $avatarData = $data ?: ($user ? $user->learningAvatarRenderData() : null);
@endphp

@if($avatarData)
    <div {{ $attributes->class(['gc-learning-avatar']) }}>
        @foreach($avatarData['layers'] as $layer)
            @if($layer['fullCanvas'])
                <img src="{{ $layer['src'] }}"
                     class="gc-learning-avatar__layer gc-learning-avatar__layer--full"
                     data-learning-avatar-layer-slot="{{ $layer['equippedSlot'] }}"
                     data-learning-avatar-layer-order="{{ $layer['order'] ?? $loop->index }}"
                     style="{{ $layer['style'] ?? '' }}"
                     alt=""
                     loading="lazy">
            @else
                <span class="gc-learning-avatar__slot gc-learning-avatar__slot--{{ $layer['slot'] }}"
                      data-learning-avatar-layer-slot="{{ $layer['equippedSlot'] }}"
                      data-learning-avatar-layer-order="{{ $layer['order'] ?? $loop->index }}"
                      style="{{ $layer['style'] }}">
                    <img src="{{ $layer['src'] }}"
                         class="gc-learning-avatar__layer gc-learning-avatar__layer--item"
                         style="object-fit: {{ in_array($layer['fit'], ['cover', 'contain', 'fill'], true) ? $layer['fit'] : 'contain' }}; object-position: {{ $layer['objectPosition'] }}; {{ $layer['innerStyle'] ?? '' }}"
                         alt=""
                         loading="lazy">
                </span>
            @endif
        @endforeach
    </div>
@endif
