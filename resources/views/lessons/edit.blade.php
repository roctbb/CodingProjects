@extends('layouts.left-menu')

@section('title')
    Изменение урока
@endsection

@section('content')
    <div class="row">
        <div class="col s12">
            <h3>Изменение урока: "{{$lesson->name}}"</h3>
            <div class="card">
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        {{ csrf_field() }}
                        <div class="form-group">
                            <label for="name">Название</label>

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

                        <div class="form-group">
                            <label for="start_date">Дата начала</label>
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

                        <div class="form-group">
                            <label for="chapter" class="pb-2">Глава</label>
                            @if (old('chapter')!="")
                                <select class="form-control" name="chapter">
                                    @foreach($lesson->program->chapters as $chapter)
                                        <option value="{{$chapter->id}}"
                                                @if ($chapter->id==old('chapter')) selected @endif>{{$chapter->name}}</option>
                                    @endforeach
                                </select>
                            @else
                                <select class="form-control" name="chapter">
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

                        <div class="form-group">
                            <label for="description" class="pb-2">Описание</label>
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

                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input" name="open" value="yes"
                                       @if ($lesson->is_open) checked @endif>
                                Сделать занятие открытым
                            </label>
                        </div>

                        <div class="form-group">
                            <label for="import">Импорт</label>
                            <input id="import" type="file" class="form-control"
                                   name="import">

                            @if ($errors->has("import"))
                                <span class="text-danger d-block">
                                        <strong>{{ $errors->first("import") }}</strong>
                                    </span>
                            @endif
                        </div>


                        <button type="submit" class="btn btn-success">Сохранить</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
