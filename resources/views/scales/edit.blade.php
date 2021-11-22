@extends('layouts.left-menu')

@section('title')
    GeekClass: Изменение шкалы "{{$scale->name}}"
@endsection

@section('content')

    <h2>Изменение шкалы "{{$scale->name}}"</h2>
    <div class="row" style="margin-top: 15px;">
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <form method="POST" class="form-horizontal" enctype="multipart/form-data">
                        {{ csrf_field() }}
                        <div class="form-group">
                            <label for="name">Название</label>
                            @if (old('name')!='')
                                <input id="name" type="text" class="form-control" name="name" value="{{old('name')}}"
                                       required>
                            @else
                                <input id="name" type="text" class="form-control" name="name" value="{{$scale->name}}"
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
                            @if (old('description')!='')
                                <textarea id="description" class="form-control"
                                          name="description">{{old('description')}}</textarea>
                            @else
                                <textarea id="description" class="form-control"
                                          name="description">{{$scale->description}}</textarea>
                            @endif
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
        </div>
    </div>
@endsection

