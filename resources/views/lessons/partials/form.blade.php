<x-gc-form-section title="Основное" description="Название, описание и стикер общие для курсов этой программы.">
<x-gc-field name="name" label="Название урока"><input id="name" name="name" type="text" class="form-control" value="{{ old('name', $lesson?->name ?? '') }}" required maxlength="255"></x-gc-field>
<x-gc-field name="chapter" label="Глава"><select id="chapter" name="chapter" class="form-select" required>@foreach($course->program->chapters as $chapter)<option value="{{ $chapter->id }}" @selected((int)old('chapter', $lesson?->chapter_id ?? $course->default_chapter_id ?? $course->program->chapters->first()?->id) === $chapter->id)>{{ $chapter->name }}</option>@endforeach</select></x-gc-field>
<x-gc-markdown-field name="description" label="Описание урока" :value="$lesson?->description ?? ''" :compact="true" :required="true" :improve="false" hint="Кратко опишите, чему посвящён урок. Основной материал размещается в ступенях." />
</x-gc-form-section>
<x-gc-form-section title="Доступ к уроку" description="Доступ по дате относится к этому курсу.">
@if($lesson)
<x-gc-field name="start_date" label="Дата открытия" hint="Без даты урок остаётся закрыт до ручного открытия."><input id="start_date" name="start_date" type="date" class="form-control" value="{{ old('start_date', $lesson->getStartDate($course)?->format('Y-m-d') ?? '') }}"  ></x-gc-field>
<x-gc-toggle name="open" label="Открытый урок" value="yes" :checked="$lesson->is_open" hint="Параметр общего доступа урока." />
@endif
<x-gc-toggle name="early_access_enabled" label="Разрешить ранний доступ" :checked="$lesson?->early_access_enabled ?? false" hint="Ученики смогут открыть урок раньше срока предусмотренными в системе способами." />
</x-gc-form-section>
<x-gc-form-section title="Награда за урок" description="Стикер выдаётся за выполнение урока с задачами.">
@include('lessons.partials.sticker', ['sticker' => $lesson?->sticker])
</x-gc-form-section>
@if($lesson)
<x-gc-form-section title="Импорт материалов">
<x-gc-field name="import" label="Файл урока" hint="JSON-файл, экспортированный из системы. Импорт будет выполнен при сохранении."><input id="import" name="import" type="file" class="form-control" accept=".json,application/json"></x-gc-field>
</x-gc-form-section>
@endif