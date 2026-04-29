@extends('layouts.left-menu')

@section('title')
    Изменение задачи "{{$task->name}}"
@endsection

@section('content')

    <h2>Изменение задачи "{{$task->name}}"</h2>
    <div class="row mt-3">
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        {{ csrf_field() }}
                        <div class="form-group">
                            <label for="name">Название</label>

                            @if (old('name')!="")
                                <input id="name" type="text" class="form-control" name="name" value="{{old('name')}}"
                                       required>
                            @else
                                <input id="name" type="text" class="form-control" name="name" value="{{$task->name}}"
                                       required>
                            @endif
                            @if ($errors->has('name'))
                                <span class="text-danger d-block">
                                        <strong>{{ $errors->first('name') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-group">
                            <label for="max_mark">Очков опыта</label>

                            @if (old('max_mark')!="")
                                <input type="text" name="max_mark" class="form-control" id="max_mark"
                                       value="{{old('name')}}"
                                       required/>
                            @else
                                <input type="text" name="max_mark" class="form-control" id="max_mark"
                                       value="{{$task->max_mark}}" required/>
                            @endif

                            @if ($errors->has('max_mark'))
                                <span class="text-danger d-block">
                                        <strong>{{ $errors->first('max_mark') }}</strong>
                                    </span>
                            @endif

                        </div>

                        <div class="form-group">
                            <label for="text">Текст</label>
                            <div class="mb-2">
                                <button type="button" class="btn btn-sm btn-outline-primary" data-improve-text="fix_typos" data-field-id="text">
                                    <i class="icon ion-android-checkbox-outline"></i> Исправить опечатки
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-info" data-improve-text="improve_style" data-field-id="text">
                                    <i class="icon ion-android-create"></i> Улучшить стиль
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-success" data-improve-text="both" data-field-id="text">
                                    <i class="icon ion-android-star"></i> Исправить и улучшить
                                </button>
                            </div>
                            <textarea id="text" class="form-control" data-markdown-editor
                                      name="text">@if (old('text')!=""){{old('text')}}@else{{$task->text}}@endif</textarea>
                            @if ($errors->has('text'))
                                <span class="text-danger d-block">
                                        <strong>{{ $errors->first('text') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-group">
                            <label for="solution">Решение</label>
                            <div class="mb-2">
                                <button type="button" class="btn btn-sm btn-outline-primary" data-improve-text="fix_typos" data-field-id="solution">
                                    <i class="icon ion-android-checkbox-outline"></i> Исправить опечатки
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-info" data-improve-text="improve_style" data-field-id="solution">
                                    <i class="icon ion-android-create"></i> Улучшить стиль
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-success" data-improve-text="both" data-field-id="solution">
                                    <i class="icon ion-android-star"></i> Исправить и улучшить
                                </button>
                            </div>
                            <textarea id="solution" class="form-control" data-markdown-editor
                                      name="solution">@if (old('solution')!=""){{old('solution')}}@else{{$task->solution}}@endif</textarea>
                            @if ($errors->has('solution'))
                                <span class="text-danger d-block">
                                        <strong>{{ $errors->first('solution') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-group">
                            <label for="is_star">Дополнительное</label>
                            <input type="checkbox" id="is_star" name="is_star" value="on"
                                   @if ($task->is_star) checked @endif/>
                        </div>

                        <div class="form-group">
                            <label for="is_hidden">Скрытая задача</label>
                            <input type="checkbox" id="is_hidden" name="is_hidden" value="on"
                                   @if ($task->is_hidden) checked @endif/>
                        </div>

                        <div class="form-group">
                            <label for="is_code">Автопроверка</label>
                            <input type="checkbox" id="is_code" name="is_code" value="on"
                                   @if ($task->is_code) checked @endif/>
                        </div>


                        <div class="form-group">
                            <label for="price" class="col-md-4">Премия</label>

                            <div class="col-md-12">

                                @if (old('price')!="")
                                    <input type="text" name="price" class="form-control" id="price"
                                           value="{{old('price')}}"/>
                                @else
                                    <input type="text" name="price" class="form-control" id="price"
                                           value="{{$task->price}}"/>
                                @endif

                                @if ($errors->has('price'))
                                    <span class="text-danger d-block">
                                        <strong>{{ $errors->first('price') }}</strong>
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="answer" class="col-md-4">Ответ</label>

                            <div class="col-md-12">

                                @if (old('answer')!="")
                                    <input type="text" name="answer" class="form-control" id="answer"
                                           value="{{old('answer')}}"/>
                                @else
                                    <input type="text" name="answer" class="form-control" id="answer"
                                           value="{{$task->answer}}"/>
                                @endif

                                @if ($errors->has('answer'))
                                    <span class="text-danger d-block">
                                        <strong>{{ $errors->first('answer') }}</strong>
                                    </span>
                                @endif
                            </div>
                        </div>


                        <button type="submit" class="btn btn-success">Сохранить</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
