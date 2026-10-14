@php
    $characterBusy = $roomCharacterGeneration?->isActive() ?? false;
    $characterHasAvatar = $user->image && \Illuminate\Support\Facades\Storage::exists($user->image);
    $characterCost = \App\RoomCharacterGeneration::COST;
    $characterIsCustom = (bool) $user->roomCharacterImageUrl();
@endphp
<form method="POST" action="{{ route('room-character.store', $user->id) }}"
      class="border-top mt-4 pt-3" data-room-character-form
      data-preview-target="learning-avatar-preview-{{ $user->id }}"
      data-balance="{{ $coinBalance }}" data-cost="{{ $characterCost }}"
      data-has-avatar="{{ $characterHasAvatar ? '1' : '0' }}"
      data-has-character="{{ $characterIsCustom ? '1' : '0' }}"
      data-busy="{{ $characterBusy ? '1' : '0' }}"
      data-status-url="{{ $characterBusy ? route('room-character.show', [$user->id, $roomCharacterGeneration->id]) : '' }}">
    @csrf
    <input type="hidden" name="request_id" value="{{ \Illuminate\Support\Str::uuid() }}">
    <div class="fw-medium mb-2">Свой персонаж</div>
    <div class="d-flex align-items-center gap-3 mb-3">
        <img src="{{ $user->imageUrl() }}" alt="Аватарка для создания персонажа" width="64" height="64"
             class="rounded-3 flex-shrink-0 object-fit-cover">
        <div class="text-muted small">Создадим персонажа в стиле комнаты по вашей текущей аватарке.
            Каждая генерация стоит {{ $characterCost }} GC. При ошибке монеты вернутся.</div>
    </div>
    @if(!$characterHasAvatar)
        <a href="{{ url('/insider/profile/'.$user->id.'/edit') }}" class="small d-block mb-2">Сначала загрузите аватарку в профиле</a>
    @endif
    <div class="small text-muted mb-2">Баланс: <span data-room-character-balance>{{ $coinBalance }}</span> GC</div>
    <button type="submit" class="btn btn-sm solution-special-action w-100 justify-content-center"
            data-room-character-submit @disabled($characterBusy || !$characterHasAvatar || $coinBalance < $characterCost)>
        <i class="fas fa-wand-magic-sparkles" aria-hidden="true"></i>
        <span data-room-character-button-text>{{ $characterBusy ? 'Создаём персонажа…' : ($characterIsCustom ? 'Перегенерировать — ' : 'Создать по аватарке — ').$characterCost.' GC' }}</span>
    </button>
    <p class="small mt-2 mb-0 {{ $roomCharacterGeneration?->status === 'failed' ? 'text-danger' : 'text-muted' }}"
       data-room-character-status role="status" aria-live="polite">{{ $errors->first('room_character') ?: ($errors->first('request_id') ?: ($roomCharacterGeneration?->statusMessage() ?? ($coinBalance < $characterCost ? 'Для генерации нужно '.$characterCost.' GC.' : 'Новый персонаж появится здесь после завершения генерации.'))) }}</p>
</form>
