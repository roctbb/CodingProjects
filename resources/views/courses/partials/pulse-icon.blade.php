@php($achievementImageUrl = $activity->achievementImageUrl())
<span class="pulse-feed__icon {{ $achievementImageUrl ? 'pulse-feed__icon--image' : '' }}" aria-hidden="true">
    @if($achievementImageUrl)
        <img src="{{ $achievementImageUrl }}" alt="" width="48" height="48" loading="lazy" decoding="async">
    @else
        <i class="{{ $activity->iconClass() }}"></i>
    @endif
</span>
