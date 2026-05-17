@props(['src' => null, 'user' => null, 'size' => '', 'alt' => '', 'imgClass' => '', 'frame' => null])

@php
    $avatarSrc = $src ?: ($user ? $user->imageUrl() : '');
    $frameKey = $frame;

    if (!$frameKey && $user && method_exists($user, 'activeAvatarFrame')) {
        $frameKey = $user->activeAvatarFrame();
    }

    $frameConfig = null;
    if ($frameKey && $user && method_exists($user, 'activeAvatarFrameConfig')) {
        $frameConfig = $user->activeAvatarFrameConfig();
    }

    $frameStyle = null;
    if ($frameKey === 'custom' && $user && method_exists($user, 'avatarFrameStyle')) {
        $frameStyle = $user->avatarFrameStyle();
    }

    $imageClass = trim('avatar ' . $size . ' ' . $imgClass);
    $sizeModifier = trim($size) ?: 'md';
@endphp

<span {{ $attributes->class([
        'gc-avatar-frame',
        'gc-avatar-frame--' . ($frameKey ?: 'none'),
        'gc-avatar-frame--size-' . $sizeModifier,
    ])->merge($frameStyle ? ['style' => $frameStyle] : []) }}
    @if($frameConfig && !$attributes->has('title')) title="Рамка: {{ $frameConfig['name'] }}" @endif>
    <img src="{{ $avatarSrc }}" alt="{{ $alt }}" class="{{ $imageClass }}">
    @if($frameKey)
        <span class="gc-avatar-frame__avatar-effect" aria-hidden="true"></span>
        <span class="gc-avatar-frame__effect" aria-hidden="true"></span>
    @endif
</span>
