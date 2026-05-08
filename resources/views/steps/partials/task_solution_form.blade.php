@php $blocked = $task->isBlocked(Auth::User()->id, $course->id); @endphp

<div class="row my-3">
    <div class="col">
        <div class="card step-submit-card">
            <div class="card-header">
                <i class="icon ion-android-send" style="margin-right:0.35rem;"></i>Добавить решение
            </div>
            <div class="card-body p-0">
                @if (!$blocked)
                    <form action="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/solution')}}" method="POST">
                        {{ csrf_field() }}
                        <div class="mb-3">
                            <div class="col-md-12">
                                <textarea id="text{{$task->id}}" class="form-control mt-3" name="text" rows="4">{{old('text')}}</textarea>
                                <small class="text-muted">Пожалуйста, не используйте это поле для отправки исходного кода. Выложите код на <a target="_blank" href="https://paste.geekclass.ru">GeekPaste</a>, <a target="_blank" href="https://pastebin.com">pastebin</a>, <a target="_blank" href="https://gist.github.com">gist</a> или <a target="_blank" href="https://paste.ofcode.org/">paste.ofcode</a>, а затем скопируйте ссылку сюда.<br>Для загрузки картинок и небольших файлов можно использовать <a href="https://storage.geekclass.ru/" target="_blank">storage.geekclass.ru</a>.</small>

                                @if ($errors->has('text'))
                                    <br><span class="text-danger d-block"><strong>{{ $errors->first('text') }}</strong></span>
                                @endif
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="col-md-12">
                                <button type="submit" class="btn btn-success" id="sbtn">Отправить</button>
                            </div>
                        </div>
                    </form>
                @else
                    <div class="alert alert-danger m-3" role="alert">
                        Задача заблокирована для вас. Новые сдачи запрещены.
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
