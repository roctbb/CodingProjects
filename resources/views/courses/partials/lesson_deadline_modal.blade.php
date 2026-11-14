@if (\Request::is('insider/*'))
    @php
        $lessonTasks = $lesson->tasks()->where('is_star', false);
        $firstLessonDeadline = $lesson->sharedDeadline($course->id);
        $hasSharedDeadline = $firstLessonDeadline !== null;
    @endphp

    <div class="modal fade" id="lesson-deadline-modal-{{$lesson->id}}" tabindex="-1" role="dialog"
         aria-labelledby="lesson-deadline-modal-{{$lesson->id}}Label" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content overflow-hidden">
                <div class="modal-header">
                    <div class="d-flex align-items-center gap-2 min-width-0">
                        <span class="gc-icon-tile flex-shrink-0"><i class="fas fa-calendar-days"></i></span>
                        <div class="min-width-0">
                            <h5 class="modal-title text-truncate" id="lesson-deadline-modal-{{$lesson->id}}Label">Дедлайн для урока</h5>
                            <small class="text-muted text-truncate d-block">{{$lesson->name}}</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <form class="gc-dialog-form" action="{{url('/insider/courses/'.$course->id.'/deadline-policies/lesson/'.$lesson->id)}}" method="POST">
                    <div class="modal-body">
                        {{ csrf_field() }}
                        @include('components.deadline-editor', ['targetType' => 'lesson', 'targetId' => $lesson->id])
                    </div>
                    <div class="modal-footer gc-form-footer step-modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
                        <button class="btn btn-success">Сохранить</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
