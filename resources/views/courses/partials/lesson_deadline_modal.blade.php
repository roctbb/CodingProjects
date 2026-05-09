@if (\Request::is('insider/*'))
    @php
        $lessonTasks = $lesson->steps->flatMap(function ($step) {
            return $step->tasks;
        })->values();
        $lessonDeadlines = $lessonTasks->map(function ($task) use ($course) {
            return $task->getDeadline($course->id);
        })->filter()->values();
        $firstLessonDeadline = $lessonDeadlines->first();
        $hasSharedDeadline = $firstLessonDeadline
            && $lessonDeadlines->count() === $lessonTasks->count()
            && $lessonDeadlines->every(function ($deadline) use ($firstLessonDeadline) {
                return $deadline->expiration->format('Y-m-d') === $firstLessonDeadline->expiration->format('Y-m-d')
                    && (float) $deadline->penalty === (float) $firstLessonDeadline->penalty;
            });
    @endphp

    <div class="modal fade" id="lesson-deadline-modal-{{$lesson->id}}" tabindex="-1" role="dialog"
         aria-labelledby="lesson-deadline-modal-{{$lesson->id}}Label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-3 shadow-sm overflow-hidden">
                <div class="modal-header border-bottom p-3">
                    <div class="d-flex align-items-center gap-2 min-width-0">
                        <span class="gc-icon-tile flex-shrink-0"><i class="icon ion-ios-calendar"></i></span>
                        <div class="min-width-0">
                            <h5 class="modal-title text-truncate" id="lesson-deadline-modal-{{$lesson->id}}Label">Дедлайн для урока</h5>
                            <small class="text-muted text-truncate d-block">{{$lesson->name}}</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <form action="{{url('/insider/courses/'.$course->id.'/lessons/'.$lesson->id.'/deadline')}}" method="POST">
                    <div class="modal-body p-3 p-md-4">
                        {{ csrf_field() }}
                        <div class="row g-3">
                            <div class="col-sm-7">
                                <label for="lesson-deadline-{{$lesson->id}}" class="form-label">Дедлайн</label>
                                <input type="date" name="deadline" class="form-control rounded-3" id="lesson-deadline-{{$lesson->id}}" value="{{$hasSharedDeadline ? $firstLessonDeadline->expiration->format('Y-m-d') : ''}}"/>
                                @error('deadline')
                                    <span class="text-danger small d-block mt-1"><strong>{{ $message }}</strong></span>
                                @enderror
	                            </div>
	                            <div class="col-sm-5">
	                                <label for="lesson-penalty-{{$lesson->id}}" class="form-label">После срока</label>
	                                <div class="input-group">
	                                    <input type="number" step="0.01" min="0" max="1" name="penalty" class="form-control rounded-start-3" id="lesson-penalty-{{$lesson->id}}" aria-describedby="lesson-penalty-help-{{$lesson->id}}" value="{{$hasSharedDeadline ? $firstLessonDeadline->penalty : 0}}"/>
	                                    <span class="input-group-text rounded-end-3">× баллы</span>
	                                </div>
	                                @error('penalty')
	                                    <span class="text-danger small d-block mt-1"><strong>{{ $message }}</strong></span>
	                                @enderror
	                            </div>
	                        </div>
	                        <div class="gc-help-note mt-3" id="lesson-penalty-help-{{$lesson->id}}">
	                            Применится ко всем задачам урока: {{$lessonTasks->count()}}. Коэффициент работает только для поздних сдач:
	                            1 — без снижения, 0.5 — половина баллов, 0 — поздняя сдача даёт 0 XP.
	                            Пустая дата снимет дедлайн со всех задач урока.
	                        </div>
                    </div>
                    <div class="modal-footer gc-form-footer step-modal-footer">
                        <button type="button" class="btn btn-outline-secondary rounded-3" data-bs-dismiss="modal">Отмена</button>
                        <button class="btn btn-success rounded-3">Сохранить</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
