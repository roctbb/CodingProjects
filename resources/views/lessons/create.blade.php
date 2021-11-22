@extends('layouts.left-menu')

@section('content')
    <h2>Создание урока</h2>

    <div class="row" style="margin-top: 15px;">
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <form method="POST" class="form-horizontal">
                        {{ csrf_field() }}
                        <div class="form-group{{ $errors->has('name') ? ' has-error' : '' }}">
                            <label for="name">Название урока</label>

                            <input id="name" type="text" class="form-control" value="{{old('name')}}"
                                   name="name"
                                   required>

                            @if ($errors->has('name'))
                                <span class="help-block error-block"><strong>{{ $errors->first('name') }}</strong></span>
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
                        </div>
                        <div class="form-group">
                            <label for="description" style="padding-bottom: 10px;">Описание урока</label>

                            <textarea id="description" class="materialize-textarea"
                                      name="description">{{old('description')}}</textarea>

                            @if ($errors->has('description'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('description') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-group">
                            <div class="col-md-8 col-md-offset-4">
                                <button type="submit" class="btn btn-success">Создать</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <script>
                var simplemde_description = new EasyMDE({
                    spellChecker: false,
                    element: document.getElementById("description")
                });
            </script>
        </div>
    </div>
@endsection
