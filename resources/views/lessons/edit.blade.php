@extends('layouts.left-menu')

@section('title')
    Изменение урока
@endsection

@section('content')
    <div class="cp-lesson-form-page">
    <div class="row cp-row-gap-top">
        <div class="col-12 col-xl-10">
            <h2 class="cp-heading-lite">Изменение урока: "{{$lesson->name}}"</h2>
            <div class="card cp-form-card">
                <div class="card-body">
                    <form method="POST" class="vstack gap-3" enctype="multipart/form-data">
                        {{ csrf_field() }}
                        <div class="mb-3">
                            <label for="name">Название</label>

                            @if (old('name')!="")
                                <input id="name" type="text" class="form-control" value="{{old('name')}}"
                                       name="name" required>
                            @else
                                <input id="name" type="text" class="form-control" value="{{$lesson->name}}"
                                       name="name" required>
                            @endif
                            @if ($errors->has('name'))
                                <span class="invalid-feedback d-block">
                                        <strong>{{ $errors->first('name') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="mb-3">
                            <label for="prerequisites" class="cp-label-spaced">Необходимые знания из <sup>
                                    <small>Core</small>
                                </sup>:</label>
                            @php
                                $selectedPrerequisiteIds = array_map(
                                    'strval',
                                    (array) old('prerequisites', $lesson->prerequisites->pluck('id')->toArray())
                                );
                            @endphp
                            <select class="form-select" id="prerequisites" name="prerequisites[]" multiple>
                                @foreach (\App\CoreNode::where('is_root', false)->where('version', 1)->get() as $node)
                                    <option data-tokens="{{ $node->id }}" value="{{ $node->id }}"
                                            @if (in_array((string) $node->id, $selectedPrerequisiteIds, true)) selected @endif
                                            data-subtext="{{$node->getParentLine()}}">{{$node->title}}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
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
                                <span class="invalid-feedback d-block">
                                        <strong>{{ $errors->first("start_date") }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="mb-3">
                            <label for="chapter" class="cp-label-spaced">Глава</label>
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
                                <span class="invalid-feedback d-block">
                                        <strong>{{ $errors->first('chapter') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="mb-3">
                            <label for="description" class="cp-label-spaced">Описание</label>
                            @if (old('description')!="")
                                <textarea id="description" class="form-control"
                                          name="description">{{old('description')}}</textarea>
                            @else
                                <textarea id="description" class="form-control"
                                          name="description">{{$lesson->description}}</textarea>
                            @endif

                            @if ($errors->has('description'))
                                <span class="invalid-feedback d-block">
                                        <strong>{{ $errors->first('description') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="open" name="open" value="yes"
                                   @if ($lesson->is_open) checked @endif>
                            <label class="form-check-label" for="open">Сделать занятие открытым</label>
                        </div>

                        <div class="mb-3">
                            <label for="import">Импорт</label>
                            <input id="import" type="file" class="form-control"
                                   name="import">

                            @if ($errors->has("import"))
                                <span class="invalid-feedback d-block">
                                        <strong>{{ $errors->first("import") }}</strong>
                                    </span>
                            @endif
                        </div>


                        <button type="submit" class="btn btn-primary">Сохранить</button>
                    </form>
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
    </div>
    </div>
@endsection
