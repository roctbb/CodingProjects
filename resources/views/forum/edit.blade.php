@extends('layouts.left-menu')

@section('title')
    GeekClass: Редактирование поста
@endsection

@section('content')
    <h2>Редактирование поста...</h2>
    <div class="row" style="margin-top: 15px;">
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <form method="POST" class="form-horizontal" enctype="multipart/form-data">
                        {{ csrf_field() }}
                        @if ($post->is_question)

                            <div class="form-group">

                                <label for="name">Название</label>
                                <input id="name" type="text" class="form-control" name="name"
                                       value="@if (old('name')!=''){{old('name')}}@else{{$thread->name}}@endif"
                                       required>
                                @if ($errors->has('name'))
                                    <span class="help-block error-block">
                                        <strong>{{ $errors->first('name') }}</strong>
                                    </span>
                                @endif
                            </div>
                        @endif

                        <div class="form-group">
                            <label for="text">Текст</label>
                            <textarea id="text" class="form-control"
                                      name="text">@if (old('text')!=''){{old('text')}}@else{{$post->text}}@endif</textarea>
                            @if ($errors->has('text'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('text') }}</strong>
                                    </span>
                            @endif
                        </div>

                        @if ($post->is_question)
                            <div class="form-group">
                                <label for="tags">Теги</label>
                                <p class="text-muted" style="margin-top: 5px;">Перечислите тэги вашей темы через точку с
                                    запятой <strong>без пробелов</strong>.</p>

                                <input id="tags" type="text" placeholder="python;типы данных;list;" class="form-control"
                                       name="tags"
                                       value="@if (old('name')!=''){{old('tags')}}@else{{ $thread->tags->pluck('name')->implode(';') }}@endif"
                                       required>
                                @if ($errors->has('tags'))
                                    <span class="help-block error-block">
                                        <strong>{{ $errors->first('tags') }}</strong>
                                    </span>
                                @endif
                            </div>
                        @endif

                        <input type="submit" class="btn btn-success" value="Сохранить"/>
                    </form>
                </div>
            </div>
        </div>
        <script>
            var simplemde_text = new EasyMDE({
                spellChecker: false,
                autosave: true,
                element: document.getElementById("text")
            });
        </script>
    </div>
@endsection
