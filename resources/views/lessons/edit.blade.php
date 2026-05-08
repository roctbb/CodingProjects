@extends('layouts.left-menu')

@section('title')
    Изменение урока
@endsection

@section('content')
    <div class="form-page">
        <div class="form-page-header gc-card mb-3">
            <div>
                <a class="assessment-back-link" href="{{ url('/insider/courses/'.$course->id) }}"><i class="icon ion-chevron-left"></i> К курсу</a>
                <h2 class="mb-1">Изменение урока</h2>
                <p class="mb-0 text-muted text-truncate">{{$lesson->name}}</p>
            </div>
        </div>

        <div class="form-layout form-layout--single">
            <div class="gc-card form-card">
                <form method="POST" enctype="multipart/form-data" class="form-stack">
                    {{ csrf_field() }}
                    <div class="mb-3">
                        <label for="name" class="form-label">Название</label>
                        @if (old('name')!="")
                            <input id="name" type="text" class="form-control" value="{{old('name')}}"
                                   name="name" required>
                        @else
                            <input id="name" type="text" class="form-control" value="{{$lesson->name}}"
                                   name="name" required>
                        @endif
                        @if ($errors->has('name'))
                            <span class="text-danger d-block">
                                    <strong>{{ $errors->first('name') }}</strong>
                                </span>
                        @endif
                    </div>

                    <div class="mb-3">
                        <label for="start_date" class="form-label">Дата начала</label>
                        @if (old('start_date')!="" || $lesson->getStartDate($course)==null)
                            <input id="start_date" type="text" class="form-control date"
                                   value="{{old("start_date")}}"
                                   name="start_date">
                        @else
                            <input id="start_date" type="text" class="form-control date"
                                   value="{{$lesson->getStartDate($course)->format('Y-m-d')}}"
                                   name="start_date">
                        @endif
                        @if ($errors->has("start_date"))
                            <span class="text-danger d-block">
                                    <strong>{{ $errors->first("start_date") }}</strong>
                                </span>
                        @endif
                    </div>

                    <div class="mb-3">
                        <label for="chapter" class="form-label">Глава</label>
                        @if (old('chapter')!="")
                            <select class="form-select" name="chapter">
                                @foreach($lesson->program->chapters as $chapter)
                                    <option value="{{$chapter->id}}"
                                            @if ($chapter->id==old('chapter')) selected @endif>{{$chapter->name}}</option>
                                @endforeach
                            </select>
                        @else
                            <select class="form-select" name="chapter">
                                @foreach($lesson->program->chapters as $chapter)
                                    <option value="{{$chapter->id}}"
                                            @if ($chapter->id==$lesson->chapter->id) selected @endif>{{$chapter->name}}</option>
                                @endforeach
                            </select>
                        @endif
                        @if ($errors->has('chapter'))
                            <span class="text-danger d-block">
                                    <strong>{{ $errors->first('chapter') }}</strong>
                                </span>
                        @endif
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Описание</label>
                        @if (old('description')!="")
                            <textarea id="description" class="form-control" data-markdown-editor data-markdown-autosave="true"
                                      name="description">{{old('description')}}</textarea>
                        @else
                            <textarea id="description" class="form-control" data-markdown-editor data-markdown-autosave="true"
                                      name="description">{{$lesson->description}}</textarea>
                        @endif
                        @if ($errors->has('description'))
                            <span class="text-danger d-block">
                                    <strong>{{ $errors->first('description') }}</strong>
                                </span>
                        @endif
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="open" name="open" value="yes"
                               @if ($lesson->is_open) checked @endif>
                        <label class="form-check-label" for="open">Сделать занятие открытым</label>
                    </div>

                    <div class="mb-3">
                        <label for="import" class="form-label">Импорт</label>
                        <input id="import" type="file" class="form-control"
                               name="import">
                        @if ($errors->has("import"))
                            <span class="text-danger d-block">
                                    <strong>{{ $errors->first("import") }}</strong>
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
