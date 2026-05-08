@extends('layouts.left-menu')

@section('title')
    Изменение товара "{{$good->name}}"
@endsection

@section('content')
    <div class="form-page">
        <div class="form-page-header gc-card mb-3">
            <div class="min-width-0">
                <a class="assessment-back-link" href="{{ url('/insider/market') }}"><i class="icon ion-chevron-left"></i> В магазин</a>
                <h2 class="mb-1 text-truncate">Изменение товара</h2>
                <p class="mb-0 text-muted text-truncate">{{ $good->name }}</p>
            </div>
        </div>

        <div class="form-layout form-layout--single">
            <div class="gc-card form-card">
                <form method="POST" enctype="multipart/form-data" class="form-stack">
                    {{ csrf_field() }}
                    <div class="mb-3">
                        <label for="name" class="form-label">Название</label>
                        @if (old('name')!='')
                            <input id="name" type="text" class="form-control" name="name" value="{{old('name')}}"
                                   required>
                        @else
                            <input id="name" type="text" class="form-control" name="name" value="{{$good->name}}"
                                   required>
                        @endif
                        @if ($errors->has('name'))
                            <span class="text-danger d-block">
                                    <strong>{{ $errors->first('name') }}</strong>
                                </span>
                        @endif
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Описание</label>
                        @if (old('description')!='')
                            <textarea id="description" class="form-control" name="description" rows="4"
                                      required>{{old('description')}}</textarea>
                        @else
                            <textarea id="description" class="form-control" name="description" rows="4"
                                      required>{{$good->description}}</textarea>
                        @endif
                        @if ($errors->has('description'))
                            <span class="text-danger d-block">
                                    <strong>{{ $errors->first('description') }}</strong>
                                </span>
                        @endif
                    </div>

                    <div class="mb-3">
                        <label for="number" class="form-label">Количество</label>
                        @if (old('number')!='')
                            <input id="number" type="text" class="form-control" name="number"
                                   value="{{old('number')}}" required>
                        @else
                            <input id="number" type="text" class="form-control" name="number"
                                   value="{{$good->number}}" required>
                        @endif
                        @if ($errors->has('number'))
                            <span class="text-danger d-block">
                                    <strong>{{ $errors->first('number') }}</strong>
                                </span>
                        @endif
                    </div>

                    <div class="mb-3">
                        <label for="price" class="form-label">Стоимость</label>
                        @if (old('price')!='')
                            <input id="price" type="text" class="form-control" name="price" value="{{old('price')}}"
                                   required>
                        @else
                            <input id="price" type="text" class="form-control" name="price" value="{{$good->price}}"
                                   required>
                        @endif
                        @if ($errors->has('price'))
                            <span class="text-danger d-block">
                                    <strong>{{ $errors->first('price') }}</strong>
                                </span>
                        @endif
                    </div>

                    <div class="mb-3">
                        <label for="image" class="form-label">Фото</label>
                        @if (old('image')!='')
                            <input id="image" type="text" class="form-control" name="image" value="{{old('image')}}"
                                   required>
                        @else
                            <input id="image" type="text" class="form-control" name="image" value="{{$good->image}}"
                                   required>
                        @endif
                        @if ($errors->has('image'))
                            <span class="text-danger d-block">
                                    <strong>{{ $errors->first('image') }}</strong>
                                </span>
                        @endif
                    </div>

                    <div class="mb-3 form-check">
                        <input id="in_stock" type="checkbox" class="form-check-input" name="in_stock" value="on"
                               @if ($good->in_stock) checked @endif>
                        <label for="in_stock" class="form-check-label">В продаже?</label>
                        @if ($errors->has('in_stock'))
                            <span class="text-danger d-block">
                                    <strong>{{ $errors->first('in_stock') }}</strong>
                                </span>
                        @endif
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-success">Сохранить товар</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
