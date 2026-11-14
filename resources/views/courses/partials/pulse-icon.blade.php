@php($achievementImageUrl = $activity->achievementImageUrl())
@if(!$achievementImageUrl && $activity->relationLoaded('user') && $activity->user && $activity->hasActor())
    <span class="gc-activity-avatar" aria-hidden="true">
        <x-gc-avatar :user="$activity->user" size="md" alt="" />
        <span class="gc-activity-avatar__kind"><i class="{{ $activity->iconClass() }}"></i></span>
    </span>
@else
<span class="pulse-feed__icon {{ $achievementImageUrl ? 'pulse-feed__icon--image' : '' }}" aria-hidden="true">
    @if($achievementImageUrl)
        <img src="{{ $achievementImageUrl }}" alt="" width="48" height="48" loading="lazy" decoding="async">
    @else
        <i class="{{ $activity->iconClass() }}"></i>
    @endif
</span>
@endif
