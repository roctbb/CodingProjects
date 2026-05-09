@php $blocked = $task->isBlocked(Auth::User()->id, $course->id); @endphp

<div class="row my-3">
    <div class="col">
        <div class="gc-card step-submit-card overflow-hidden">
            <div class="gc-section-header gc-section-header--inline">
                <span class="gc-icon-tile flex-shrink-0"><i class="icon ion-android-send"></i></span>
                <div class="min-width-0">
                    <h6 class="mb-0">Добавить решение</h6>
                    <p class="text-muted small mb-0 text-truncate">Ссылка, текст ответа или пояснение к работе.</p>
                </div>
            </div>
            <div class="step-submit-card__body">
                @if (!$blocked)
                    <form action="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/solution')}}" method="POST"
                          class="step-submit-form">
                        {{ csrf_field() }}
                        <div class="mb-3">
                            <textarea id="text{{$task->id}}" class="form-control rounded-3" name="text" rows="3">{{old('text')}}</textarea>
                            <small class="solution-submit-hint gc-help-note d-block mt-2">Для кода лучше отправить ссылку на <a target="_blank" href="https://paste.geekclass.ru">GeekPaste</a>, <a target="_blank" href="https://pastebin.com">pastebin</a>, <a target="_blank" href="https://gist.github.com">gist</a> или <a target="_blank" href="https://paste.ofcode.org/">paste.ofcode</a>. Файлы и картинки: <a href="https://storage.geekclass.ru/" target="_blank">storage.geekclass.ru</a>.</small>

                            @if ($errors->has('text'))
                                <span class="text-danger small d-block mt-2"><strong>{{ $errors->first('text') }}</strong></span>
                            @endif
                        </div>
                        <div class="step-submit-actions">
                            <button type="submit" class="btn btn-success rounded-3 fw-semibold" id="sbtn">Отправить</button>
                        </div>
                    </form>
                @else
                    <div class="step-task-note step-task-note--danger bg-danger-subtle text-danger-emphasis mb-0" role="note">
                        <span class="flex-shrink-0"><i class="fas fa-lock"></i></span>
                        <div class="min-width-0">
                            <strong class="d-block">Задача заблокирована</strong>
                            <span class="small">Новые сдачи запрещены.</span>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
