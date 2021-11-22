@extends('layouts.left-menu')

@section('title')
    Изменение программы
@endsection

@section('content')

    <h2>Изменение программы "{{$program->name}}"</h2>
    <div class="row" style="margin-top: 15px;">
        <div class="col">
            <form method="POST" class="form-horizontal" enctype="multipart/form-data">
                {{ csrf_field() }}
                <div class="form-group">
                    <label for="name">Название</label>

                    @if (old('name')!="")
                        <input id="name" type="text" class="form-control" name="name" value="{{old('name')}}" required>
                    @else
                        <input id="name" type="text" class="form-control" name="name" value="{{$program->name}}"
                               required>
                    @endif
                    @if ($errors->has('name'))
                        <span class="help-block error-block">
                                        <strong>{{ $errors->first('name') }}</strong>
                                    </span>
                    @endif
                </div>

                <div class="form-group">
                    <label for="description">Описание</label>
                    <textarea id="description" class="form-control" name="description"
                              required>@if (old('description')!=""){{old('description')}}@else{{$program->description}}@endif</textarea>
                    @if ($errors->has('description'))
                        <span class="help-block error-block">
                                        <strong>{{ $errors->first('description') }}</strong>
                                    </span>
                    @endif
                </div>

                <button type="submit" class="btn btn-success">Сохранить</button>
            </form>
        </div>
    </div>
@endsection

