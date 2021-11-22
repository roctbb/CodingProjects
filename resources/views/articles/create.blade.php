@extends('layouts.left-menu')

@section('title')
    GeekClass: Добавление статьи
@endsection

@section('content')
    <h2>Новая статья...</h2>
    <div class="row" style="margin-top: 15px;">
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <form method="POST">

                        <div class="form-group">
                            {{ csrf_field() }}
                            <label for="name">Название</label>
                            <input id="name" type="text" class="form-control" name="name" value="{{old('name')}}"
                                   required>
                            @if ($errors->has('name'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('name') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-group">

                            <label for="image">Картинка</label>
                            <input id="image" type="text" class="form-control" name="image"
                                   value="{{old('image')}}"
                                   required>
                            @if ($errors->has('image'))
                                <span class="help-block error-block">
                                    <strong>{{ $errors->first('image') }}</strong>
                                </span>
                            @endif
                        </div>


                        <div class="form-group">
                            <label for="anounce">Анонс</label>
                            <textarea id="anounce" class="form-control" name="anounce">{{old('anounce')}}</textarea>
                            @if ($errors->has('anounce'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('anounce') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-group">
                            <label for="text">Текст</label>
                            <p class="text-muted">Для разметки текста используется markdown, описание - <a target="_blank"
                                                                                                           href="https://simplemde.com/markdown-guide">тут</a>.
                            </p>

                            <textarea id="text" class="form-control" name="text">{{old('text')}}</textarea>
                            @if ($errors->has('text'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('text') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-group">
                            {{ csrf_field() }}
                            <label for="tags">Теги</label>
                            <p class="text-muted" style="margin-top: 5px;">Перечислите тэги вашей темы через точку с
                                запятой
                                <strong>без пробелов</strong>.</p>

                            <input id="tags" type="text" placeholder="python;типы данных;list;" class="form-control"
                                   name="tags"
                                   value="{{old('tags')}}">
                            @if ($errors->has('tags'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('tags') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <input type="submit" class="btn btn-success" value="Опубликовать"/>

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

            var simplemde_anounce = new EasyMDE({
                spellChecker: false,
                autosave: true,
                element: document.getElementById("anounce")
            });
        </script>
    </div>
@endsection
