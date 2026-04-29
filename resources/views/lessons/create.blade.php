@extends('layouts.left-menu')

@section('title')
    Создание урока
@endsection

@section('content')
    <h2>Создание урока</h2>

    <div class="row mt-3">
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <form method="POST">
                        {{ csrf_field() }}
                        <div class="form-group">
                            <label for="name">Название урока</label>

                            <input id="name" type="text" class="form-control" value="{{old('name')}}"
                                   name="name"
                                   required>

                            @if ($errors->has('name'))
                                <span class="text-danger d-block"><strong>{{ $errors->first('name') }}</strong></span>
                            @endif
                        </div>
                        <div class="form-group">
                            <label for="description" class="pb-2">Описание урока</label>

                            <textarea id="description" class="form-control" data-markdown-editor
                                      name="description">{{old('description')}}</textarea>

                            @if ($errors->has('description'))
                                <span class="text-danger d-block">
                                        <strong>{{ $errors->first('description') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-group">
                            <div class="col-md-8 offset-md-4">
                                <button type="submit" class="btn btn-success">Создать</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
