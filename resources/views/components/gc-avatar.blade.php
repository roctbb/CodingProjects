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

    $imageClass = trim('avatar ' . $size . ' ' . $imgClass);
    $sizeModifier = trim($size) ?: 'md';
@endphp

<span {{ $attributes->class([
        'gc-avatar-frame',
        'gc-avatar-frame--' . ($frameKey ?: 'none'),
        'gc-avatar-frame--size-' . $sizeModifier,
    ]) }}
    @if($frameConfig && !$attributes->has('title')) title="Рамка: {{ $frameConfig['name'] }}" @endif>
    <img src="{{ $avatarSrc }}" alt="{{ $alt }}" class="{{ $imageClass }}">
    @if($frameKey)
        <span class="gc-avatar-frame__effect" aria-hidden="true"></span>
    @endif
</span>
