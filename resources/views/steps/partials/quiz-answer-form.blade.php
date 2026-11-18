@php
    $mode = $task->quizAnswerMode();
    $attemptsRemaining = $task->quizAttemptsRemaining(Auth::id(), $course->id);
    $quizErrors = $errors->getBag('quiz_'.$task->id);
    $oldText = $quizErrors->any() ? old('text') : null;
    $oldSelection = $quizErrors->any() ? (array) old('selected_options') : [];
@endphp
<form action="{{ url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/solution') }}" method="POST"
      class="step-quiz-answer-form" data-check-task data-task-id="{{ $task->id }}" aria-describedby="quiz-attempts-{{ $task->id }}">
    @csrf
    <input type="hidden" name="quiz_revision" value="{{ $task->quizRevision() }}">
    <fieldset class="step-quiz-answer-fields" data-quiz-fields @disabled($attemptsRemaining === 0)>
        @if($mode === 'short')
            <label for="text{{ $task->id }}" class="form-label mb-0">Ответ</label>
            <input type="text" name="text" id="text{{ $task->id }}" class="form-control"
                   value="{{ is_string($oldText) ? $oldText : '' }}" required>
        @else
            <legend class="form-label">{{ $mode === 'single' ? 'Выберите один вариант' : 'Отметьте все правильные варианты' }}</legend>
            <div class="step-quiz-options">
                @foreach($task->quiz_choices['options'] as $index => $option)
                    <label class="step-quiz-choice">
                        <input class="form-check-input" type="{{ $mode === 'single' ? 'radio' : 'checkbox' }}" name="selected_options[]"
                               value="{{ $index }}" @required($mode === 'single') @checked(in_array($index, $oldSelection))>
                        <span>{{ $option['label'] }}</span>
                    </label>
                @endforeach
            </div>
        @endif
        <div class="step-quiz-answer-actions">
            <button type="submit" class="btn btn-primary btn-sm">Отправить</button>
            <span class="gc-help-text" id="quiz-attempts-{{ $task->id }}" role="status" data-quiz-attempts>
                {{ $attemptsRemaining > 0 ? 'Осталось попыток: '.$attemptsRemaining.' из '.$task->quizAttemptLimit().'.' : 'Попытки исчерпаны.' }}
            </span>
        </div>
    </fieldset>
    <p class="gc-field__error mt-2 mb-0" role="alert" data-quiz-error @if(!$quizErrors->any()) hidden @endif>{{ $quizErrors->first() }}</p>
</form>
