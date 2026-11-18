<div class="gc-quiz-option-field" data-quiz-option>
    <label class="gc-quiz-option-field__key">
        <input class="form-check-input m-0" type="{{ $optionType }}" name="correct_options[]"
               value="{{ $optionIndex }}" aria-label="Правильный вариант" @checked($optionCorrect ?? false)
               @if($firstOption ?? false) id="correct_options" @endif data-quiz-option-correct>
    </label>
    <input class="form-control" type="text" name="answer_options[{{ $optionIndex }}]" value="{{ is_string($optionLabel ?? null) ? $optionLabel : '' }}"
           aria-label="Текст варианта" maxlength="1000" required data-quiz-option-label>
    <button type="button" class="btn btn-sm btn-outline-secondary gc-quiz-option-field__remove @if(!$canRemoveOption) d-none @endif"
            aria-label="Удалить вариант" data-quiz-option-remove><i class="fas fa-xmark" aria-hidden="true"></i></button>
</div>
