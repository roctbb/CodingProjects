@extends('layouts.left-menu')

@section('title')
    GeekClass: Добавление курса
@endsection

@section('content')
    <h2>Создание товара</h2>
    <div class="row" style="margin-top: 15px;">
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <form method="POST" class="form-horizontal" enctype="multipart/form-data">
                        {{ csrf_field() }}
                        <div class="form-group">
                            <label for="name">Название</label>
                            <input id="name" type="text" class="form-control" name="name" value="{{old('name')}}"
                                   required>
                            @if ($errors->has('name'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('name') }}</strong>
                                    </span>
                            @endif
                        </div>


                        <div class="form-group">
                            <label for="description">Описание</label>
                            <textarea id="description" class="form-control" name="description"
                                      required>{{old('description')}}</textarea>
                            @if ($errors->has('description'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('description') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-group">
                            <label for="number">Количество</label>
                            <input id="number" type="text" class="form-control" name="number" value="{{old('number')}}"
                                   required>
                            @if ($errors->has('number'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('number') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-group">
                            <label for="price">Стоимость</label>
                            <input id="price" type="text" class="form-control" name="price" value="{{old('price')}}"
                                   required>
                            @if ($errors->has('price'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('price') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-group">
                            <label for="image">Фото</label>
                            <input id="image" type="text" class="form-control" name="image" value="{{old('image')}}"
                                   required>
                            @if ($errors->has('image'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('image') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-group">
                            <label for="in_stock">В продаже?</label>
                            <input id="in_stock" type="checkbox" name="in_stock" value="on">
                            @if ($errors->has('in_stock'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('in_stock') }}</strong>
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
