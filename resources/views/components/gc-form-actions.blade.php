@props(['form', 'label' => 'Сохранить', 'cancel' => null])
<div class="gc-form-actions">
    <span class="gc-form-actions__hint" data-form-state="{{ $form }}" aria-live="polite">Изменения сохраняются после нажатия кнопки.</span>
    @if($cancel)<a class="btn btn-outline-secondary" href="{{ $cancel }}">Отмена</a>@endif
    <button type="submit" form="{{ $form }}" class="btn btn-primary">{{ $label }}</button>
</div>
