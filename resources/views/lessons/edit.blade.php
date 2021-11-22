@extends('layouts.left-menu')

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
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('name') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-group">
                            <label for="prerequisites" style="padding-bottom: 10px;">Необходимые знания из <sup>
                                    <small>Core</small>
                                </sup>:</label><br>
                            <select class="selectpicker  form-control" data-live-search="true" id="prerequisites"
                                    name="prerequisites[]" multiple data-width="auto">
                                @foreach (\App\CoreNode::where('is_root', false)->where('version', 1)->get() as $node)
                                    <option data-tokens="{{ $node->id }}" value="{{ $node->id }}"
                                            data-subtext="{{$node->getParentLine()}}">{{$node->title}}</option>
                                @endforeach
                            </select>

                            <script>
                                $('.selectpicker').selectpicker('val', [{{implode(',', $lesson->prerequisites->pluck('id')->toArray())}}]);
                            </script>
                        </div>

                        <div class="form-group{{ $errors->has("start_date") ? ' has-error' : '' }}">
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
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first("start_date") }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-group">
                            <label for="chapter" style="padding-bottom: 10px;">Глава</label>
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
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('chapter') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-group">
                            <label for="description" style="padding-bottom: 10px;">Описание</label>
                            @if (old('description')!="")
                                <textarea id="description" class="form-control"
                                          name="description">{{old('description')}}</textarea>
                            @else
                                <textarea id="description" class="form-control"
                                          name="description">{{$lesson->description}}</textarea>
                            @endif

                            @if ($errors->has('description'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('description') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <hr>

                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input" name="is_sdl" value="yes"
                                       @if ($lesson->is_sdl) checked @endif>
                                Доступно в SDL
                            </label>
                        </div>


                        <div class="form-group">
                            <label for="sdl_node_id">Связанная вершина из Core для SDL</label>
                            <select class="selectpicker form-control" data-live-search="true" id="sdl_node_id"
                                    name="sdl_node_id" data-width="auto">
                                <option data-tokens="-1" value="-1">Недоступно в SDL курсах</option>
                                @foreach (\App\CoreNode::where('is_root', false)->where('version', 2)->get() as $node)
                                    <option data-tokens="{{ $node->id }}"
                                            value="{{ $node->id }}">{{$node->title}}</option>
                                @endforeach
                            </select>
                            @if ($errors->has('sdl_node_id'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('sdl_node_id') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <script>
                            @if ($lesson->sdl_node_id != null)
                            $('.selectpicker').selectpicker('val', '{{$lesson->sdl_node_id}}');
                            @else
                            $('.selectpicker').selectpicker();
                            @endif
                        </script>

                        <div class="form-group">
                            <label for="scale_id">Шкала образовательных результатов</label>
                            <select class="selectpicker2 form-control" data-live-search="true" id="scale_id"
                                    name="scale_id" data-width="auto">
                                <option data-tokens="-1" value="-1">Нет</option>
                                @foreach (\App\ResultScale::all() as $scale)
                                    <option data-tokens="{{ $scale->id }}"
                                            value="{{ $scale->id }}">{{$scale->name}}</option>
                                @endforeach
                            </select>
                            @if ($errors->has('scale_id'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('scale_id') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <script>
                            @if ($lesson->scale_id != null)
                            $('.selectpicker2').selectpicker('val', '{{$lesson->scale_id}}');
                            @else
                            $('.selectpicker2').selectpicker();
                            @endif
                        </script>


                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input" name="open" value="yes"
                                       @if ($lesson->is_open) checked @endif>
                                Сделать занятие открытым
                            </label>
                        </div>

                        <div class="form-group{{ $errors->has("import") ? ' has-error' : '' }}">
                            <label for="import">Импорт</label>
                            <input id="import" type="file" class="form-control"
                                   name="import">

                            @if ($errors->has("import"))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first("import") }}</strong>
                                    </span>
                            @endif
                        </div>


                        <button type="submit" class="btn btn-success">Сохранить</button>
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
@endsection
