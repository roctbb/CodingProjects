@php
    $nativeAvailable = \Illuminate\Support\Facades\Schema::hasTable('code_task_checkers');
    $nativeChecker = $nativeAvailable && $task ? \App\CodeTaskChecker::find($task->id) : null;
    $answerMode = old('answer_mode', $nativeChecker ? 'native' : ($task?->is_code ? 'code' : ($task?->is_quiz ? 'short' : 'manual')));
@endphp
<x-gc-form-section title="Условие задания" description="Материалы задания общие для курсов этой программы.">
    <x-gc-field name="name" label="Название задания"><input id="name" name="name" class="form-control" value="{{ old('name', $task?->name) }}" maxlength="255" required></x-gc-field>
    <x-gc-markdown-field name="text" label="Условие" :value="$task?->text ?? ''" :required="true" />
</x-gc-form-section>
<x-gc-form-section title="Проверка и ответ">
    <fieldset id="answer_mode" class="gc-field" data-answer-mode @if($nativeChecker) hidden @endif>
        <legend class="form-label">Способ проверки</legend>
        <div class="gc-choice-grid">
            @foreach(($nativeChecker ? ['native'=>['Сдача в Coding Projects', 'Текст, код и файлы. Ручная проверка, тесты или ИИ.']] : (['manual'=>['Преподавателем', 'Работу проверяет преподаватель.'], 'short'=>['Короткий ответ', 'Ответ ученика сравнивается с указанным ниже.']] + ($nativeAvailable ? ['native'=>['Сдача в Coding Projects', 'Текст, код и файлы. Ручная проверка, тесты или ИИ.']] : []) + ($task?->is_code ? ['code'=>['Проверка не настроена', 'Настройте способ сдачи и проверки, чтобы открыть новые отправки.']] : []))) as $key=>[$label,$hint])
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
    <x-gc-field name="answer" label="Короткий ответ" :hidden="$answerMode !== 'short'"><input id="answer" name="answer" class="form-control" value="{{ old('answer', $task?->answer) }}" maxlength="255" data-short-answer></x-gc-field>
    <details class="gc-disclosure gc-disclosure--inline" data-authoring-optional @if(filled(old('solution', $task?->solution))) open @endif>
        <summary>Авторское решение (необязательно)</summary>
        <x-gc-markdown-field name="solution" label="Авторское решение" :value="$task?->solution ?? ''" :compact="true" hint="Разбор решения и пояснения преподавателя." />
    </details>
</x-gc-form-section>
<x-gc-form-section title="Баллы и награды">
    <div class="gc-form-pair">
        <x-gc-field name="max_mark" label="Максимум XP"><input id="max_mark" name="max_mark" type="number" class="form-control" value="{{ old('max_mark', $task?->max_mark ?? 10) }}" min="0" max="1000" step="1" required></x-gc-field>
        <x-gc-field name="price" label="Премия, GC" hint="0 — без премии."><input id="price" name="price" type="number" class="form-control" value="{{ old('price', $task?->price ?? 0) }}" min="0"></x-gc-field>
    </div>
    <x-gc-toggle name="xp_booster_enabled" label="Разрешить бустер +5 XP" :checked="$task?->xp_booster_enabled ?? false" />
    <x-gc-toggle name="generates_ai_achievement" label="Достижение за максимум" :checked="$task?->generates_ai_achievement ?? false" data-authoring-achievement />
    <x-gc-field name="ai_achievement_instruction" label="Фокус достижения" :hidden="old('generates_ai_achievement', $task?->generates_ai_achievement ? 'on' : '0') !== 'on'"><textarea id="ai_achievement_instruction" name="ai_achievement_instruction" class="form-control" rows="2" maxlength="1000" data-authoring-achievement-instruction placeholder="Например: необычная идея или аккуратность кода">{{ old('ai_achievement_instruction', $task?->ai_achievement_instruction) }}</textarea></x-gc-field>
</x-gc-form-section>
<x-gc-form-section title="Видимость">
    <x-gc-toggle name="is_star" label="Дополнительное задание" :checked="$task?->is_star ?? false" />
    <x-gc-toggle name="is_hidden" label="Скрытое задание" :checked="$task?->is_hidden ?? false" hint="Не показывать задание в обычной программе ученика." />
</x-gc-form-section>
