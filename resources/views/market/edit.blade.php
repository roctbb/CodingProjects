@extends('layouts.left-menu')

@section('title')
    GeekClass: Изменение товара "{{$good->name}}"
@endsection

@section('content')

    <h2>Изменение товара "{{$good->name}}"</h2>
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
                                <input id="name" type="text" class="form-control" name="name" value="{{$good->name}}"
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
                                <textarea id="description" class="form-control" name="description"
                                          required>{{old('description')}}</textarea>
                            @else
                                <textarea id="description" class="form-control" name="description"
                                          required>{{$good->description}}</textarea>
                            @endif
                            @if ($errors->has('description'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('description') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-group">
                            <label for="number">Количество</label>
                            @if (old('number')!='')
                                <input id="number" type="text" class="form-control" name="number"
                                       value="{{old('number')}}" required>
                            @else
                                <input id="number" type="text" class="form-control" name="number"
                                       value="{{$good->number}}" required>
                            @endif
                            @if ($errors->has('number'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('number') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-group">
                            <label for="price">Стоимость</label>
                            @if (old('price')!='')
                                <input id="price" type="text" class="form-control" name="price" value="{{old('price')}}"
                                       required>
                            @else
                                <input id="price" type="text" class="form-control" name="price" value="{{$good->price}}"
                                       required>
                            @endif
                            @if ($errors->has('price'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('price') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-group">
                            <label for="image">Фото</label>
                            @if (old('image')!='')
                                <input id="image" type="text" class="form-control" name="image" value="{{old('image')}}"
                                       required>
                            @else
                                <input id="image" type="text" class="form-control" name="image" value="{{$good->image}}"
                                       required>
                            @endif
                            @if ($errors->has('image'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('image') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-group">
                            <label for="in_stock">В продаже?</label>
                            <input id="in_stock" type="checkbox" name="in_stock" value="on"
                                   @if ($good->in_stock) checked @endif>
                            @if ($errors->has('in_stock'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('in_stock') }}</strong>
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

