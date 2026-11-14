<div class="offcanvas offcanvas-end gc-drawer" tabindex="-1" id="room-settings" aria-labelledby="room-settings-title"
     @if($errors->hasAny(['manifest', 'equipped.*', 'room_character', 'request_id', 'expected_cost'])) data-room-settings-open @endif>
    <div class="offcanvas-header">
        <h2 class="offcanvas-title h5" id="room-settings-title">Настройки комнаты</h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Закрыть"></button>
    </div>
    <div class="offcanvas-body gc-stack">
        <x-gc-learning-avatar :user="$user" :data="$learningAvatarData" id="room-editor-preview-{{ $user->id }}" data-learning-avatar-preview data-preview-copy-of="learning-avatar-preview-{{ $user->id }}" data-custom-character-url="{{ $user->roomCharacterImageUrl() }}" />
        <div><p class="text-muted mb-3">Выберите доступные предметы. Новые предметы и питомцы есть в <a href="{{ url('/insider/market#market-digital') }}">магазине</a>.</p>
<form method="POST"
      action="{{ url('/insider/profile/'.$user->id.'/learning-avatar') }}"
      class="gc-form-stack"
      data-learning-avatar-form
      data-learning-avatar-balance="{{ $coinBalance }}"
      data-learning-avatar-preview-target="room-editor-preview-{{ $user->id }}">
    @csrf
    <input type="hidden" name="manifest" value="{{ $currentLearningAvatarManifest }}">
    @foreach($learningAvatarSlotLabels as $slotKey => $slotLabel)
        <label class="gc-form-field">
            <span>{{ $slotLabel }}</span>
            <select name="equipped[{{ $slotKey }}]" class="form-select form-select-sm rounded-3">
                <option value="">Пусто</option>
                @foreach(($learningAvatarOptionsBySlot[$slotKey] ?? []) as $itemKey => $item)
                    @php
                        $isOwned = in_array($itemKey, $learningAvatarOwned, true);
                    @endphp
                    @continue(!$isOwned)
                    @php
                        $previewLayer = $learningAvatarPreviewItemsBySlot[$slotKey][$itemKey] ?? null;
                    @endphp
                    <option value="{{ $itemKey }}"
                            @selected(($learningAvatarConfig['equipped'][$slotKey] ?? '') === $itemKey)
                            data-item-cost="0"
                            data-item-owned="1"
                            @if($previewLayer)
                                data-preview-src="{{ $previewLayer['src'] }}"
                                data-preview-slot="{{ $previewLayer['slot'] }}"
                                data-preview-equipped-slot="{{ $previewLayer['equippedSlot'] }}"
                                data-preview-order="{{ $learningAvatarData['renderOrder'][$slotKey] ?? 99 }}"
                                data-preview-style="{{ $previewLayer['style'] }}"
                                data-preview-fit="{{ $previewLayer['fit'] === 'cover' ? 'cover' : 'contain' }}"
                                data-preview-object-position="{{ $previewLayer['objectPosition'] }}"
                            @endif>
                        {{ $item['name'] }}
                    </option>
                @endforeach
            </select>
        </label>
    @endforeach

    <button type="submit" class="btn btn-primary justify-content-center" data-learning-avatar-submit>
        <i class="fas fa-save"></i>
        Сохранить комнату
    </button>
    <a href="{{ url('/insider/market#market-digital') }}" class="btn btn-sm btn-outline-secondary rounded-3 justify-content-center">
        Открыть цифровой магазин
    </a>
</form>
@if($roomCharacterAvailable ?? false)
    @include('profile.partials.room-character')
@endif
        </div>
    </div>
</div>
