@extends('layouts.left-menu')

@section('title')
    GeekClass: Добавление курса
@endsection

@section('content')
    <h2>Создание курса</h2>
    <div class="row" style="margin-top: 15px;">
        <div class="col">
            <div class="card">
                <div class="card-body">

            <form method="POST" class="form-horizontal" enctype="multipart/form-data">
                {{ csrf_field() }}
                <div class="form-group">
                    <label for="name">Название</label>
                    <input id="name" type="text" class="form-control" name="name" value="{{old('name')}}" required>
                    @if ($errors->has('name'))
                        <span class="help-block error-block">
                                        <strong>{{ $errors->first('name') }}</strong>
                                    </span>
                    @endif
                </div>

                <div class="form-group">
                    <label for="program">Программа</label>
                    <select id="program"  class="form-control"  name="program" required>
                        <option value="-1">Новая программа на основе курса</option>
                        @foreach($programs as $program)
                            <option value="{{$program->id}}">{{$program->name}}</option>
                        @endforeach
                        <option value="-2">Курс SDL</option>
                    </select>
                    @if ($errors->has('program'))
                        <span class="help-block error-block">
                                        <strong>{{ $errors->first('program') }}</strong>
                                    </span>
                    @endif
                </div>

                <div class="form-group">
                    <label for="sdl_version">Версия графа SDL (только для SDL курсов)</label>
                    <input id="sdl_version" type="text" class="form-control" name="sdl_version" value="{{old('sdl_version')}}">
                    @if ($errors->has('sdl_version'))
                        <span class="help-block error-block">
                                        <strong>{{ $errors->first('sdl_version') }}</strong>
                                    </span>
                    @endif
                </div>

                <div class="form-group">
                    <label for="description">Описание</label>
                    <textarea id="description"  class="form-control"  name="description" required>{{old('description')}}</textarea>
                    @if ($errors->has('description'))
                        <span class="help-block error-block">
                                        <strong>{{ $errors->first('description') }}</strong>
                                    </span>
                    @endif
                </div>

            <!--
                        <div class="form-group{{ $errors->has('image') ? ' has-error' : '' }}">
                            <label for="image" class="col-md-4 control-label">Аватар</label>

                            <div class="col-md-8">
                                <input id="image" type="file" class="form-control" name="image"/>

                                @if ($errors->has('image'))
                <span class="help-block error-block">
                    <strong>{{ $errors->first('image') }}</strong>
                                    </span>
                                @endif
                    </div>
                </div>
-->

                <button type="submit" class="btn btn-success">Создать</button>
            </form>
                </div>
            </div>
        </div>
    </div>
@endsection
