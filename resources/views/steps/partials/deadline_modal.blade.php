
    @if (\Request::is('insider/*'))
    <div class="modal fade" id="deadline-modal-{{$task->id}}" tabindex="-1"
         aria-labelledby="deadline-modal-{{$task->id}}Label" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deadline-modal-{{$task->id}}Label">Дедлайн для задачи</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть">
                    </button>
                </div>
                <div class="modal-body">
                  <form action="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/deadline')}}"
                          method="POST"
                          class="vstack gap-3">
                        {{ csrf_field() }}
                        <div class="mb-3{{ $errors->has('deadline') ? ' is-invalid' : '' }}">
                            <label for="deadline_{{$task->id}}" class="form-label">Дедлайн</label>
                            <input type="date" name="deadline" class="form-control" id="deadline_{{$task->id}}" value="{{$task->getDeadline($course->id) ? $task->getDeadline($course->id)->expiration->format('Y-m-d') : ''}}"/>
                                @if ($errors->has('deadline'))
                                    <span class="invalid-feedback d-block">
                                    <strong>{{ $errors->first('deadline') }}</strong>
                                </span>
                                @endif
                        </div>
                        <div class="mb-3{{ $errors->has('penalty') ? ' is-invalid' : '' }}">
                            <label for="penalty_{{$task->id}}" class="form-label">Штраф (баллы * штраф)</label>
                                <input type="number" step="0.01" min=0 max=1 name="penalty" class="form-control" id="penalty_{{$task->id}}" value="{{$task->getDeadline($course->id) ? $task->getDeadline($course->id)->penalty : 0}}"/>
                                @if ($errors->has('penalty'))
                                    <span class="invalid-feedback d-block">
                                    <strong>{{ $errors->first('penalty') }}</strong>
                                </span>
                                @endif
                        </div>

                        <button class="btn btn-primary">Сохранить</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endif
