@extends('layouts.left-menu')

@section('title')
    GeekClass: Добавление образовательного направления
@endsection

@section('content')
    <h2>Новое образовательное направление</h2>
    <div class="row" style="margin-top: 15px;">
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <form method="POST">

                        <div class="form-group">
                            {{ csrf_field() }}
                            <label for="name">Название</label>
                            <input id="title" type="text" class="form-control" name="title" value="{{old('title')}}"
                                   required>
                            @if ($errors->has('title'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('title') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-group">

                            <label for="small_image_url">Маленькая картинка</label>
                            <input id="small_image_url" type="text" class="form-control" name="small_image_url"
                                   value="{{old('small_image_url')}}"
                                   required>
                            @if ($errors->has('small_image_url'))
                                <span class="help-block error-block">
                                    <strong>{{ $errors->first('small_image_url') }}</strong>
                                </span>
                            @endif
                        </div>

                        <div class="form-group">

                            <label for="card_image_url">Картинка для карточки</label>
                            <input id="card_image_url" type="text" class="form-control" name="card_image_url"
                                   value="{{old('card_image_url')}}"
                                   required>
                            @if ($errors->has('card_image_url'))
                                <span class="help-block error-block">
                                    <strong>{{ $errors->first('card_image_url') }}</strong>
                                </span>
                            @endif
                        </div>

                        <div class="form-group">

                            <label for="head_image_url">Картинка для заголовка</label>
                            <input id="head_image_url" type="text" class="form-control" name="head_image_url"
                                   value="{{old('head_image_url')}}"
                                   required>
                            @if ($errors->has('head_image_url'))
                                <span class="help-block error-block">
                                    <strong>{{ $errors->first('head_image_url') }}</strong>
                                </span>
                            @endif
                        </div>

                        <div class="form-group">

                            <label for="video_url">Видео</label>
                            <input id="video_url" type="text" class="form-control" name="video_url"
                                   value="{{old('video_url')}}">
                            @if ($errors->has('video_url'))
                                <span class="help-block error-block">
                                    <strong>{{ $errors->first('video_url') }}</strong>
                                </span>
                            @endif
                        </div>


                        <div class="form-group">
                            <label for="short_description">Описание для карточки</label>
                            <textarea id="short_description" class="form-control"
                                      name="short_description">{{old('short_description')}}</textarea>
                            @if ($errors->has('short_description'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('short_description') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-group">
                            <label for="description">Описание</label>
                            <p class="text-muted">Для разметки текста используется markdown, описание - <a
                                        target="_blank"
                                        href="https://simplemde.com/markdown-guide">тут</a>.
                            </p>

                            <textarea id="description" class="form-control"
                                      name="description">{{old("description")}}</textarea>
                            @if ($errors->has("description"))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first("description") }}</strong>
                                    </span>
                            @endif
                        </div>

                        <input type="submit" class="btn btn-success" value="Добавить"/>

                    </form>
                </div>
            </div>
        </div>
        <script>
            var simplemde_text = new EasyMDE({
                spellChecker: false,
                autosave: true,
                element: document.getElementById("description")
            });
        </script>
    </div>
@endsection
