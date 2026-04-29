@extends('layouts.left-menu')

@section('title')
    Добавление главы
@endsection

@section('content')
    <h2>Добавление главы</h2>
    <div class="row mt-3">
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        {{ csrf_field() }}
                        <div class="form-group">
                            <label for="name">Название</label>
                            <input id="name" type="text" class="form-control" name="name" value="{{old('name')}}"
                                   required>
                            @if ($errors->has('name'))
                                <span class="text-danger d-block">
                                        <strong>{{ $errors->first('name') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-group">
                            <label for="description">Описание</label>
                            <textarea id="description" class="form-control"
                                      name="description">{{old('description')}}</textarea>
                            @if ($errors->has('description'))
                                <span class="text-danger d-block">
                                        <strong>{{ $errors->first('description') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <button type="submit" class="btn btn-success">Добавить</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
