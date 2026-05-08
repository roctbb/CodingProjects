
    @if (\Request::is('insider/*'))
    <div class="modal fade" id="deadline-modal-{{$task->id}}" tabindex="-1" role="dialog"
         aria-labelledby="deadline-modal-{{$task->id}}Label" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deadline-modal-{{$task->id}}Label">Дедлайн для задачи</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                        
                    </button>
                </div>
                <div class="modal-body">
                  <form action="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/deadline')}}"
                          method="POST"
                         >
                        {{ csrf_field() }}
                        <div class="mb-3">
                            <label for="price" class="col-md-4">Дедлайн</label>

                            <div class="col-md-12">
                            <input type="date" name="deadline" class="form-control" id="deadline" value="{{$task->getDeadline($course->id) ? $task->getDeadline($course->id)->expiration->format('Y-m-d') : '0000-00-00'}}"/>
                                @if ($errors->has('deadline'))
                                    <span class="text-danger d-block">
                                    <strong>{{ $errors->first('deadline') }}</strong>
                                </span>
                                @endif
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="price" class="col-md-4">Штраф (баллы * штраф)</label>

                            <div class="col-md-12">
                                <input type="number" step="0.01" min=0 max=1 name="penalty" class="form-control" id="penalty" value="{{$task->getDeadline($course->id) ? $task->getDeadline($course->id)->penalty : 0}}"/>
                                @if ($errors->has('penalty'))
                                    <span class="text-danger d-block">
                                    <strong>{{ $errors->first('penalty') }}</strong>
                                </span>
                                @endif
                            </div>
                        </div>

                        <button class="btn btn-success">Сохранить</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endif