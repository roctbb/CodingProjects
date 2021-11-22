@extends('layouts.left-menu')

@section('title')
    GeekClass: Редактирование образовательного направления
@endsection

@section('content')
    <h2>Изменение образовательного направления</h2>
    <div class="row" style="margin-top: 15px;">
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <form method="POST">
                        {{ csrf_field() }}


                        <div class="form-group">

                            <label for="title">Название</label>
                            <input id="title" type="text" class="form-control" name="title"
                                   value="@if (old('title')!=''){{old('title')}}@else{{$category->title}}@endif"
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
                                   value="@if (old('small_image_url')!=''){{old('small_image_url')}}@else{{$category->small_image_url}}@endif"
                                   required>
                            @if ($errors->has('small_image_url'))
                                <span class="help-block error-block">
                                    <strong>{{ $errors->first('small_image_url') }}</strong>
                                </span>
                            @endif
                        </div>

                        <div class="form-group">

                            <label for="card_image_url">Картинка на карточке</label>
                            <input id="card_image_url" type="text" class="form-control" name="card_image_url"
                                   value="@if (old('card_image_url')!=''){{old('card_image_url')}}@else{{$category->card_image_url}}@endif"
                                   required>
                            @if ($errors->has('card_image_url'))
                                <span class="help-block error-block">
                                    <strong>{{ $errors->first('card_image_url') }}</strong>
                                </span>
                            @endif
                        </div>

                        <div class="form-group">

                            <label for="head_image_url">Картинка в заголовке</label>
                            <input id="head_image_url" type="text" class="form-control" name="head_image_url"
                                   value="@if (old('head_image_url')!=''){{old('head_image_url')}}@else{{$category->head_image_url}}@endif"
                                   required>
                            @if ($errors->has('head_image_url'))
                                <span class="help-block error-block">
                                    <strong>{{ $errors->first('head_image_url') }}</strong>
                                </span>
                            @endif
                        </div>

                        <div class="form-group">

                            <label for="video_url">Картинка</label>
                            <input id="video_url" type="text" class="form-control" name="video_url"
                                   value="@if (old('video_url')!=''){{old('video_url')}}@else{{$category->video_url}}@endif">
                            @if ($errors->has('video_url'))
                                <span class="help-block error-block">
                                    <strong>{{ $errors->first('video_url') }}</strong>
                                </span>
                            @endif
                        </div>


                        <div class="form-group">
                            <label for="short_description">Описание на карточке</label>
                            <textarea id="short_description" class="form-control"
                                      name="short_description">@if (old('short_description')!=''){{old('short_description')}}@else{{$category->short_description}}@endif</textarea>
                            @if ($errors->has('short_description'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('short_description') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-group">
                            <label for="description">Текст</label>
                            <textarea id="description" class="form-control"
                                      name="description">@if (old("description")!=''){{old("description")}}@else{{$category->description}}@endif</textarea>
                            @if ($errors->has("description"))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first("description") }}</strong>
                                    </span>
                            @endif
                        </div>

                        <input type="submit" class="btn btn-success" value="Сохранить"/>
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
