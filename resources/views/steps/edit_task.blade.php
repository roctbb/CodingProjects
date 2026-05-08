@extends('layouts.left-menu')

@section('title')
    Изменение задачи "{{$task->name}}"
@endsection

@section('content')
    <div class="form-page">
        <div class="form-page-header gc-card mb-3">
            <div>
                <h2 class="mb-1">Изменение задачи</h2>
                <p class="mb-0 text-muted text-truncate">{{$task->name}}</p>
            </div>
        </div>

        <div class="form-layout">
            <div class="gc-card form-card form-card--wide">
                    <form method="POST" enctype="multipart/form-data" class="form-stack">
                        {{ csrf_field() }}
                        <div class="mb-3">
                            <label for="name" class="form-label">Название</label>

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

                        <div class="mb-3">
                            <label for="max_mark" class="form-label">Очков опыта</label>

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

                        <div class="mb-3">
                            <label for="text" class="form-label">Текст</label>
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

                        <div class="mb-3">
                            <label for="solution" class="form-label">Решение</label>
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

                        <div class="mb-3">
                            <label for="is_star" class="form-label">Дополнительное</label>
                            <input type="checkbox" id="is_star" name="is_star" value="on"
                                   @if ($task->is_star) checked @endif/>
                        </div>

                        <div class="mb-3">
                            <label for="is_hidden" class="form-label">Скрытая задача</label>
                            <input type="checkbox" id="is_hidden" name="is_hidden" value="on"
                                   @if ($task->is_hidden) checked @endif/>
                        </div>

                        <div class="mb-3">
                            <label for="is_code" class="form-label">Автопроверка</label>
                            <input type="checkbox" id="is_code" name="is_code" value="on"
                                   @if ($task->is_code) checked @endif/>
                        </div>


                        <div class="mb-3">
                            <label for="price" class="form-label">Премия</label>

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

                        <div class="mb-3">
                            <label for="answer" class="form-label">Ответ</label>

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


                        <div class="form-actions">
                            <button type="submit" class="btn btn-success">Сохранить</button>
                        </div>
                    </form>
            </div>
        </div>
    </div>
@endsection
@push('editor')
<script type="text/javascript" src="{{ asset('build/js/vendor/easymde.min.js') }}"></script>
<script type="text/javascript" src="{{ asset('build/js/easymde-bridge.js') }}"></script>
@endpush
