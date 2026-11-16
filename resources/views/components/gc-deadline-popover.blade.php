@php($popoverId = 'deadline-popover-'.\Illuminate\Support\Str::uuid())
<div {{ $attributes->class(['gc-deadline-popover']) }} data-deadline-popover>
    <button type="button" class="gc-deadline-popover__trigger" popovertarget="{{ $popoverId }}" aria-controls="{{ $popoverId }}" aria-expanded="false">
        {{ $summary }}
    </button>
    <div id="{{ $popoverId }}" class="gc-deadline-popover__panel" popover aria-label="Все сроки сдачи" role="region">
        <div class="gc-deadline-popover__heading">
            <strong>Сроки сдачи</strong>
            <button type="button" class="btn-options" popovertarget="{{ $popoverId }}" popovertargetaction="hide" aria-label="Закрыть сроки сдачи"><i class="fas fa-xmark" aria-hidden="true"></i></button>
        </div>
        {{ $slot }}
    </div>
</div>
