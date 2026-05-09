
    @if (\Request::is('insider/*'))
    <div class="modal fade" id="deadline-modal-{{$task->id}}" tabindex="-1" role="dialog"
         aria-labelledby="deadline-modal-{{$task->id}}Label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-3 shadow-sm overflow-hidden">
                <div class="modal-header border-bottom p-3">
                    <div class="d-flex align-items-center gap-2">
                        <span class="gc-icon-tile flex-shrink-0"><i class="icon ion-ios-calendar"></i></span>
                        <h5 class="modal-title" id="deadline-modal-{{$task->id}}Label">Дедлайн для задачи</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <form action="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/deadline')}}" method="POST">
                    <div class="modal-body p-3 p-md-4">
                        {{ csrf_field() }}
                        <div class="row g-3">
                            <div class="col-sm-7">
                                <label for="deadline-{{$task->id}}" class="form-label">Дедлайн</label>
                                <input type="date" name="deadline" class="form-control rounded-3" id="deadline-{{$task->id}}" value="{{$task->getDeadline($course->id) ? $task->getDeadline($course->id)->expiration->format('Y-m-d') : ''}}"/>
                                @error('deadline')
                                    <span class="text-danger small d-block mt-1"><strong>{{ $message }}</strong></span>
                                @enderror
	                            </div>
	                            <div class="col-sm-5">
	                                <label for="penalty-{{$task->id}}" class="form-label">После срока</label>
	                                <div class="input-group">
	                                    <input type="number" step="0.01" min="0" max="1" name="penalty" class="form-control rounded-start-3" id="penalty-{{$task->id}}" aria-describedby="penalty-help-{{$task->id}}" value="{{$task->getDeadline($course->id) ? $task->getDeadline($course->id)->penalty : 0}}"/>
	                                    <span class="input-group-text rounded-end-3">× баллы</span>
	                                </div>
	                                @error('penalty')
	                                    <span class="text-danger small d-block mt-1"><strong>{{ $message }}</strong></span>
	                                @enderror
	                            </div>
	                        </div>
	                        <div class="gc-help-note mt-3" id="penalty-help-{{$task->id}}">
	                            Коэффициент применяется только к поздним сдачам: 1 — без снижения, 0.5 — половина баллов, 0 — поздняя сдача даёт 0 XP.
	                            В день дедлайна штраф ещё не применяется.
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
