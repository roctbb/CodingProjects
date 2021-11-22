@extends('layouts.left-menu')

@section('title')
    GeekClass: Добавление идеи
@endsection

@section('content')
    <h2>Создание идеи</h2>
    <div class="row" style="margin-top: 15px;">
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <form method="POST" class="form-horizontal" enctype="multipart/form-data">

                        {{ csrf_field() }}
                        <div class="form-group">
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
                            <label for="short_description">Краткое описание</label>
                            <input id="short_description" class="form-control" name="short_description"
                                   required>{{old('short_description')}}</input>
                            @if ($errors->has('short_description'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('short_description') }}</strong>
                                    </span>
                            @endif
                        </div>
                        <div class="form-group">
                            <label for="description">Описание</label>
                            <p class="text-muted">Опишите идею максимально подробно, по возможности, приведите картинки
                                или собственные зарисовки. Обязательно укажите, что нужно <strong>знать</strong>, чтобы
                                реализовать проект (например, <i>списки в питоне</i> или <i>реверс-инжиниринг</i>),
                                добавьте ссылки на учебные материалы.<br> Для разметки текста используется
                                <b>markdown</b>, описание - <a target="_blank"
                                                               href="https://simplemde.com/markdown-guide">тут</a>,
                                кроме этого можно использовать некоторые html тэги, например, таблицы.
                            </p>
                            <textarea id="description" class="form-control"
                                      name="description">{{old('description')}}</textarea>
                            @if ($errors->has('description'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('description') }}</strong>
                                    </span>
                            @endif
                        </div>
                        <div class="form-group">
                            @include('captcha')
                        </div>


                        <input type="submit" class="btn btn-success" value="Создать"/>
                    </form>
                </div>
            </div>
        </div>
        <script>
            var simplemde_description = new EasyMDE({
                spellChecker: false,
                autosave: true,
                element: document.getElementById("description")
            });
        </script>
    </div>
@endsection
