@extends('layouts.left-menu')

@section('title')
    GeekClass: Добавление шкалы
@endsection

@section('content')
    <h2>Создание результата</h2>
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
                    <label for="level">Уровень</label>
                    <select class="form-control" id="level" name="level" required>
                        <option value="8" @if (old('level')==8) selected @endif>4.0 - продвинутая цель</option>
                        <option value="6" @if (old('level')==6) selected @endif>3.0 - основная цель</option>
                        <option value="4" @if (old('level')==4) selected @endif>2.0 - вспомогательная цель</option>
                    </select>
                    @if ($errors->has('level'))
                        <span class="help-block error-block">
                                        <strong>{{ $errors->first('level') }}</strong>
                                    </span>
                    @endif
                </div>


                <div class="form-group">
                    <label for="description">Описание</label>
                    <textarea id="description" class="form-control" name="description">{{old('description')}}</textarea>
                    @if ($errors->has('description'))
                        <span class="help-block error-block">
                                        <strong>{{ $errors->first('description') }}</strong>
                                    </span>
                    @endif
                </div>

                <button type="submit" class="btn btn-success">Создать</button>
            </form>
                </div>
            </div>
        </div>
    </div>
@endsection
