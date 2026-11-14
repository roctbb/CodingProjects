@props(['value', 'label' => 'Скопировать'])

<span class="gc-copy-value">
    <code>{{ filled($value) ? $value : '—' }}</code>
    <button type="button" class="btn btn-outline-secondary btn-sm gc-icon-button" data-copy-text="{{ $value }}" aria-label="{{ $label }}" @disabled(blank($value))>
        <i class="fas fa-copy" aria-hidden="true"></i>
    </button>
    <span class="visually-hidden" role="status" aria-live="polite" data-copy-status></span>
</span>
