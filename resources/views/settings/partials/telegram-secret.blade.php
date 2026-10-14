<div class="mb-3">
    <label for="telegram-{{ $field }}-action" class="form-label">{{ $label }}</label>
    <div class="small text-muted mb-2">{{ $configured[$field] ? 'Задан; значение скрыто.' : 'Не задан.' }}</div>
    <select id="telegram-{{ $field }}-action" name="secrets[{{ $field }}][action]" class="form-select mb-2" data-secret-action="telegram-{{ $field }}-value">
        <option value="keep">Оставить как есть</option>
        <option value="replace">Заменить</option>
        <option value="clear">{{ $field === 'proxy' ? 'Без прокси' : 'Очистить' }}</option>
        <option value="inherit">Использовать настройку сервера</option>
    </select>
    <input id="telegram-{{ $field }}-value" name="secrets[{{ $field }}][value]" type="password" class="form-control"
           autocomplete="new-password" maxlength="4096" spellcheck="false" placeholder="Новое значение" aria-label="{{ $label }}: новое значение" disabled>
    <div class="form-text">{{ $hint }}</div>
</div>
