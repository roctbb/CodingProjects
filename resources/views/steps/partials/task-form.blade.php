@php
    $nativeAvailable = \Illuminate\Support\Facades\Schema::hasTable('code_task_checkers');
    $nativeChecker = $nativeAvailable && $task ? \App\CodeTaskChecker::find($task->id) : null;
    $answerMode = old('answer_mode', $nativeChecker ? 'native' : ($task?->is_code ? 'code' : ($task?->is_quiz ? $task->quizAnswerMode() : 'manual')));
    $choiceMode = in_array($answerMode, \App\Task::CHOICE_MODES, true);
    $quizMode = $choiceMode || $answerMode === 'short';
    $quizOptions = $task?->quiz_choices['options'] ?? [];
    $optionLabels = (array) old('answer_options', array_column($quizOptions, 'label'));
    $correctOptions = (array) old('correct_options',
        old('answer_options') === null ? array_keys(array_filter($quizOptions, fn ($option) => $option['correct'])) : []);
    $optionLabels = array_filter($optionLabels, fn ($index) => is_int($index) && $index >= 0, ARRAY_FILTER_USE_KEY);
    while (count($optionLabels) < 2) $optionLabels[] = '';
    $optionType = $answerMode === 'single' ? 'radio' : 'checkbox';
    $canRemoveOption = count($optionLabels) > 2;
    $choiceError = collect($errors->get('answer_options*'))->merge($errors->get('correct_options*'))->flatten()->first();
    $attemptLimit = old('max_attempts', $task?->quizAttemptLimit() ?? \App\Task::DEFAULT_QUIZ_ATTEMPTS);
@endphp
<x-gc-form-section title="Условие задания" description="Условие и файлы общие для курсов этой программы.">
    <x-gc-field name="name" label="Название задания"><input id="name" name="name" class="form-control" value="{{ old('name', $task?->name) }}" maxlength="255" required></x-gc-field>
    <x-gc-markdown-field name="text" label="Условие" :value="$task?->text ?? ''" :required="true" />
</x-gc-form-section>
@if($nativeAvailable)
<x-gc-form-section title="Материалы задания" description="Файлы к условию, стартовый код и данные для программы.">
    <div class="gc-material-editor" data-checker-materials></div>
</x-gc-form-section>
@endif
<x-gc-form-section title="Проверка и ответ">
    <fieldset id="answer_mode" class="gc-field" data-answer-mode @if($nativeChecker) hidden @endif>
        <legend class="form-label">Способ проверки</legend>
        <div class="gc-choice-grid gc-task-answer-modes">
            @foreach(($nativeChecker ? ['native'=>['Сдача в Coding Projects', 'Текст, код и файлы. Ручная проверка, тесты или ИИ.']] : ([
                'manual'=>['Преподавателем', 'Работу проверяет преподаватель.'],
                'short'=>['Короткий ответ', 'Ученик вводит ответ одной строкой.'],
                'single'=>['Один вариант ответа', 'Ученик выбирает один вариант из списка.'],
                'multiple'=>['Несколько вариантов ответа', 'Ученик отмечает все правильные варианты галочками.'],
            ] + ($nativeAvailable ? ['native'=>['Сдача в Coding Projects', 'Текст, код и файлы. Ручная проверка, тесты или ИИ.']] : []) + ($task?->is_code ? ['code'=>['Проверка не настроена', 'Настройте способ сдачи и проверки, чтобы открыть новые отправки.']] : []))) as $key=>[$label,$hint])
                <label class="gc-choice"><input class="form-check-input" type="radio" name="answer_mode" value="{{ $key }}" @checked($answerMode === $key)><span><strong>{{ $label }}</strong><span class="gc-help-text d-block">{{ $hint }}</span></span></label>
            @endforeach
        </div>
        @error('answer_mode')<p class="gc-field__error">{{ $message }}</p>@enderror
    </fieldset>
    @if($nativeAvailable)
        <input type="hidden" name="checker_config" value="{{ old('checker_config', json_encode(($nativeChecker?->config ?? ['language'=>'python','check_type'=>'manual','submission_modes'=>['files','code','project'],'allowed_languages'=>['python'],'test_mode'=>'io']) + ['allows_test_reveals' => $nativeChecker?->allows_test_reveals ?? true], JSON_UNESCAPED_UNICODE)) }}" data-checker-value>
        <div data-checker-editor data-endpoint="{{ $task ? url('/insider/courses/'.$course->id.'/tasks/'.$task->id) : '' }}" @if($answerMode !== 'native') hidden @endif></div>
        @error('checker_config')<p class="gc-field__error" role="alert">{{ $message }}</p>@enderror
    @endif
    <x-gc-field name="answer" label="Короткий ответ" :hidden="$answerMode !== 'short'"><input id="answer" name="answer" class="form-control" value="{{ old('answer', $task?->answer) }}" maxlength="255" @disabled($answerMode !== 'short') @required($answerMode === 'short') data-short-answer></x-gc-field>
    <fieldset id="answer_options" class="gc-field gc-quiz-options-editor" data-quiz-options-editor aria-describedby="quiz-options-hint" @if(!$choiceMode) hidden disabled @endif>
        <legend class="form-label">Варианты ответа</legend>
        <p class="gc-help-text mb-0" id="quiz-options-hint" data-quiz-options-hint>{{ $answerMode === 'single' ? 'Отметьте один правильный вариант.' : 'Отметьте не менее двух правильных вариантов.' }}</p>
        <div class="d-grid gap-2" data-quiz-option-list>
            @foreach($optionLabels as $optionIndex => $optionLabel)
                @include('steps.partials.quiz-option-field', ['optionCorrect' => in_array($optionIndex, $correctOptions), 'firstOption' => $loop->first])
            @endforeach
        </div>
        <template data-quiz-option-template>
            @include('steps.partials.quiz-option-field', ['optionIndex' => '', 'optionLabel' => '', 'optionCorrect' => false])
        </template>
        <button type="button" class="btn btn-sm btn-outline-secondary gc-quiz-options-editor__add" data-quiz-option-add>Добавить вариант</button>
        @if($choiceError)<p class="gc-field__error mb-0" role="alert">{{ $choiceError }}</p>@endif
    </fieldset>
    <x-gc-field name="max_attempts" label="Максимум попыток" class="gc-quiz-attempt-limit" :hidden="!$quizMode">
        <input id="max_attempts" name="max_attempts" type="number" class="form-control" value="{{ is_scalar($attemptLimit) ? $attemptLimit : '' }}"
               min="1" max="2147483647" step="1" @disabled(!$quizMode) @required($quizMode) data-quiz-attempt-limit>
    </x-gc-field>
    <details class="gc-disclosure gc-disclosure--inline" data-authoring-optional @error('solution') open @enderror>
        <summary>Авторское решение (необязательно)</summary>
        <x-gc-markdown-field name="solution" label="Авторское решение" :value="$task?->solution ?? ''" :compact="true" hint="Разбор решения и пояснения преподавателя." />
    </details>
</x-gc-form-section>
<x-gc-form-section title="Параметры задания">
    <div class="gc-form-pair">
        <x-gc-field name="max_mark" label="Максимум XP"><input id="max_mark" name="max_mark" type="number" class="form-control" value="{{ old('max_mark', $task?->max_mark ?? 10) }}" min="0" max="1000" step="1" required></x-gc-field>
        <x-gc-field name="price" label="Премия, GC" hint="0 — без премии."><input id="price" name="price" type="number" class="form-control" value="{{ old('price', $task?->price ?? 0) }}" min="0"></x-gc-field>
    </div>
    <details class="gc-disclosure gc-disclosure--inline" data-authoring-optional><summary>Награды и усиления</summary>
    <x-gc-toggle name="xp_booster_enabled" label="Разрешить бустер +5 XP" :checked="$task?->xp_booster_enabled ?? false" />
    <x-gc-toggle name="generates_ai_achievement" label="Достижение за максимум" :checked="$task?->generates_ai_achievement ?? false" data-authoring-achievement />
    <x-gc-field name="ai_achievement_instruction" label="Фокус достижения" :hidden="old('generates_ai_achievement', $task?->generates_ai_achievement ? 'on' : '0') !== 'on'"><textarea id="ai_achievement_instruction" name="ai_achievement_instruction" class="form-control" rows="2" maxlength="1000" data-authoring-achievement-instruction placeholder="Например: необычная идея или аккуратность кода">{{ old('ai_achievement_instruction', $task?->ai_achievement_instruction) }}</textarea></x-gc-field>
    </details>
    <div class="gc-task-visibility">
    <x-gc-toggle name="is_star" label="Дополнительное задание" :checked="$task?->is_star ?? false" />
    <x-gc-toggle name="is_hidden" label="Скрытое задание" :checked="$task?->is_hidden ?? false" hint="Не показывать задание в обычной программе ученика." />
    </div>
</x-gc-form-section>
