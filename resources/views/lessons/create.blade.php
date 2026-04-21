@extends('layouts.left-menu')

@section('title')
    Создание урока
@endsection

@section('content')
    <div class="cp-lesson-form-page">
    <h2 class="cp-heading-lite">Создание урока</h2>

    <div class="row cp-row-gap-top">
        <div class="col-12 col-xl-10">
            <div class="card cp-form-card">
                <div class="card-body">
                    <form method="POST" class="vstack gap-3">
                        {{ csrf_field() }}
                        <div class="mb-3">
                            <label for="name">Название урока</label>

                            <input id="name" type="text" class="form-control" value="{{old('name')}}"
                                   name="name"
                                   required>

                            @if ($errors->has('name'))
                                <span class="invalid-feedback d-block"><strong>{{ $errors->first('name') }}</strong></span>
                            @endif
                        </div>
                        <div class="mb-3">
                            <label for="prerequisites" class="cp-label-spaced">Необходимые знания из <sup>
                                    <small>Core</small>
                                </sup>:</label>
                            <select class="form-select" id="prerequisites" name="prerequisites[]" multiple>
                                @foreach (\App\CoreNode::where('is_root', false)->where('version', 1)->get() as $node)
                                    <option data-tokens="{{ $node->id }}" value="{{ $node->id }}"
                                            data-subtext="{{$node->getParentLine()}}">{{$node->title}}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="cp-label-spaced">Описание урока</label>

                            <textarea id="description" class="form-control"
                                      name="description">{{old('description')}}</textarea>

                            @if ($errors->has('description'))
                                <span class="invalid-feedback d-block">
                                        <strong>{{ $errors->first('description') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="mb-3">
                            <button type="submit" class="btn btn-primary">Создать</button>
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
    </div>
@endsection
