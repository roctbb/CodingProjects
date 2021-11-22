@extends('layouts.left-menu')

@section('title')
    GeekClass: Добавление проекта
@endsection

@section('content')
    <h2>Новый вопрос...</h2>
    <div class="row" style="margin-top: 15px;">
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <form method="POST" class="form-horizontal" enctype="multipart/form-data">

                        <div class="form-group">
                            {{ csrf_field() }}
                            <label for="name">Название</label>
                            <p class="text-muted">Постарайтесь сформулировать название максимально емко и понятно, чтобы
                                его
                                несложно было отыскать в поиске.</p>
                            <input id="name" type="text" class="form-control" name="name" value="{{old('name')}}"
                                   required>
                            @if ($errors->has('name'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('name') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-group">
                            <label for="text">Текст вопроса</label>
                            <p class="text-muted">Опишите ваш вопрос максимально подробно, по возможности, приведите
                                примеры
                                кода. Для разметки текста используется markdown, описание - <a target="_blank"
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
                                   value="{{old('tags')}}" required>
                            @if ($errors->has('tags'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('tags') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-group">
                            {!! \NoCaptcha::display() !!}
                            @if ($errors->has('g-recaptcha-response'))
                                <span class="help-block">
                                            <strong>{{ $errors->first('g-recaptcha-response') }}</strong>
                                        </span>
                            @endif
                        </div>

                        <p style="margin-top: 5px;">Перед отправкой еще раз проверьте правильность, точность и
                            грамотность
                            вашего вопроса. Кстати, если участники сочтут вопрос полезным и проголосуют за него более
                            двух раз,
                            вы получите несколько GC.</p>


                        <input type="submit" class="btn btn-success" value="Задать вопрос"/>

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
