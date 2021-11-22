@extends('layouts.left-menu')

@section('title')
    GeekClass: Изменение идеи "{{$idea->name}}"
@endsection

@section('content')

    <h2>Изменение проекта "{{$idea->name}}"</h2>
    <div class="row" style="margin-top: 15px;">
        <div class="col">
            <div class="card">
                <div class="card-body">
            <form method="POST" class="form-horizontal" enctype="multipart/form-data">
                {{ csrf_field() }}
                <div class="form-group">
                    <label for="name">Название</label>

                    @if (old('name')!="")
                        <input id="name" type="text" class="form-control" name="name" value="{{old('name')}}" required>
                    @else
                        <input id="name" type="text" class="form-control" name="name" value="{{$idea->name}}"
                               required>
                    @endif
                    @if ($errors->has('name'))
                        <span class="help-block error-block">
                                        <strong>{{ $errors->first('name') }}</strong>
                                    </span>
                    @endif
                </div>

                <div class="form-group">
                    <label for="short_description">Краткое описание</label>
                    <textarea id="short_description" class="form-control" name="short_description"
                              required>@if (old('short_description')!=""){{old('short_description')}}@else{{$idea->short_description}}@endif</textarea>
                    @if ($errors->has('short_description'))
                        <span class="help-block error-block">
                                        <strong>{{ $errors->first('short_description') }}</strong>
                                    </span>
                    @endif
                </div>
                <div class="form-group">
                    <label for="description">Описание</label>
                    <textarea id="description" class="form-control" name="description"
                    >@if (old('description')!=""){{old('description')}}@else{{$idea->description}}@endif</textarea>
                    @if ($errors->has('description'))
                        <span class="help-block error-block">
                                        <strong>{{ $errors->first('description') }}</strong>
                                    </span>
                    @endif
                </div>

                @if ($user->role != 'student')
                    <hr />
                    <div class="form-group">
                        <label for="sdl_node_id">Связанная вершина из Core</label>
                        <select class="selectpicker form-control" data-live-search="true" id="sdl_node_id"
                                name="sdl_node_id" data-width="auto">
                            <option data-tokens="-1" value="">Нет</option>
                            @foreach (\App\CoreNode::where('is_root', false)->get() as $node)
                                <option data-tokens="{{ $node->id }}" value="{{ $node->id }}" data-subtext="Версия Core - {{$node->version}}">{{$node->title}}</option>
                            @endforeach
                        </select>
                        @if ($errors->has('sdl_node_id'))
                            <span class="help-block error-block">
                                        <strong>{{ $errors->first('sdl_node_id') }}</strong>
                                    </span>
                        @endif
                    </div>

                    <script>
                        @if ($idea->sdl_node_id != null)
                        $('.selectpicker').selectpicker('val', '{{$idea->sdl_node_id}}');
                        @else
                        $('.selectpicker').selectpicker();
                        @endif
                    </script>
                @endif


                <button type="submit" class="btn btn-success">Сохранить</button>
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

