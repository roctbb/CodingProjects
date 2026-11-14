@props(['target', 'label' => 'Колонки таблицы'])
<div class="gc-table-navigation" data-table-navigation="{{ $target }}" hidden>
    <span class="gc-help-text" data-table-range role="status" aria-label="{{ $label }}"></span>
    <div class="d-flex gap-1" role="group" aria-label="{{ $label }}">
        <button class="btn btn-outline-secondary btn-sm gc-icon-button" type="button" data-table-previous aria-controls="{{ $target }}" aria-label="Предыдущие колонки"><i class="fas fa-chevron-left" aria-hidden="true"></i></button>
        <button class="btn btn-outline-secondary btn-sm gc-icon-button" type="button" data-table-next aria-controls="{{ $target }}" aria-label="Следующие колонки"><i class="fas fa-chevron-right" aria-hidden="true"></i></button>
    </div>
</div>
