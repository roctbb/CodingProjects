@php
    $editor = app(\App\Services\CourseDeadlineService::class)->editor($course, $targetType, $targetId);
    $prefix = 'deadline-'.$targetType.'-'.$targetId;
@endphp
<div data-deadline-editor>
    <input type="hidden" name="revision" value="{{ $editor['revision'] }}">
    <input type="hidden" name="timezone" value="{{ $editor['timezone'] }}">
    <label class="form-label" for="{{ $prefix }}-mode">Правила сдачи</label>
    <select class="form-select mb-3" id="{{ $prefix }}-mode" name="mode" data-deadline-mode>
        @if($targetType === 'task')<option value="inherit" @selected($editor['mode'] === 'inherit')>Сроки урока{{ $task->is_star ? ' (звёздная задача — без сроков)' : '' }}</option>@endif
        <option value="custom" @selected($editor['mode'] === 'custom')>Свои сроки</option>
        <option value="none" @selected($editor['mode'] === 'none')>Без дедлайна</option>
    </select>
    <div data-deadline-stages @if($editor['mode'] !== 'custom') hidden @endif>
        <p class="gc-help-text mb-3">До первого срока — 100% баллов. Каждый следующий срок меняет процент для новых сдач. Время: {{ $editor['timezone'] }}.</p>
        <div class="d-grid gap-3" data-deadline-rows>
            @foreach($editor['stages'] as $i => $stage)
                <div class="row g-2 align-items-end" data-deadline-row>
                    <div class="col-12 col-sm-7"><label class="form-label w-100 mb-0">Начиная с даты и времени<input class="form-control" type="datetime-local" name="stages[{{ $i }}][cutoff_at]" value="{{ $stage['cutoff_at'] }}" required></label></div>
                    <div class="col-9 col-sm-4"><label class="form-label w-100 mb-0">Остаётся баллов<span class="input-group"><input class="form-control" type="number" min="0" max="100" step="any" name="stages[{{ $i }}][retained_percent]" value="{{ $stage['retained_percent'] }}" required><span class="input-group-text">%</span></span></label></div>
                    <div class="col-3 col-sm-1 pb-1"><button class="btn btn-outline-secondary gc-icon-button" type="button" data-deadline-remove aria-label="Убрать срок"><i class="fas fa-xmark" aria-hidden="true"></i></button></div>
                </div>
            @endforeach
        </div>
        <button class="btn btn-outline-secondary btn-sm mt-3" type="button" data-deadline-add><i class="fas fa-plus" aria-hidden="true"></i> Ещё срок</button>
    </div>
    @if($targetType === 'lesson')
        <label class="form-check mt-3"><input class="form-check-input" type="checkbox" name="apply_to_tasks" value="1"><span class="form-check-label">Заменить индивидуальные сроки обычных задач сроками урока</span></label>
        <p class="gc-help-text mt-2 mb-0">Новые обычные задачи наследуют сроки урока. Звёздные задачи сохраняют свои правила.</p>
    @endif
    <p class="gc-help-text mt-3 mb-0">Последний процент действует и дальше. Сдача остаётся открытой. За отправленное решение сохраняется минимум 1 балл; уже заработанные баллы не снижаются.</p>
    @if($errors->any())<div class="alert alert-danger mt-3 mb-0">{{ $errors->first() }}</div>@endif
</div>
