
    @if (\Request::is('insider/*'))
    <div class="modal fade" id="deadline-modal-{{$task->id}}" tabindex="-1" role="dialog"
         aria-labelledby="deadline-modal-{{$task->id}}Label" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content overflow-hidden">
                <div class="modal-header">
                    <div class="d-flex align-items-center gap-2">
                        <span class="gc-icon-tile flex-shrink-0"><i class="fas fa-calendar-days"></i></span>
                        <h5 class="modal-title" id="deadline-modal-{{$task->id}}Label">Дедлайн для задачи</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <form class="gc-dialog-form" action="{{url('/insider/courses/'.$course->id.'/deadline-policies/task/'.$task->id)}}" method="POST">
                    <div class="modal-body">
                        {{ csrf_field() }}
                        @include('components.deadline-editor', ['targetType' => 'task', 'targetId' => $task->id])
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
