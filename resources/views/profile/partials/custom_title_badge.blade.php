@php
    $profileUser = $profileUser ?? null;
    $compact = $compact ?? false;
    $customTitle = $profileUser ? $profileUser->activeCustomTitle() : null;
@endphp

@if ($customTitle)
    <span class="profile-custom-title @if($compact) profile-custom-title--compact @endif" title="{{ $customTitle }}">
        <i class="fas fa-certificate"></i>
        <span>{{ $customTitle }}</span>
    </span>
@endif
