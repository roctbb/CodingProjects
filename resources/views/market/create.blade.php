@extends('layouts.left-menu')

@section('title')
    Добавление курса
@endsection

@section('content')
    <div class="form-page">
        <div class="form-page-header gc-card mb-3">
            <div>
                <a class="assessment-back-link" href="{{ url('/insider/market') }}"><i class="icon ion-chevron-left"></i> В магазин</a>
                <h2 class="mb-1">Создание товара</h2>
                <p class="mb-0 text-muted">Заполните информацию о новом товаре для магазина.</p>
            </div>
        </div>

        <div class="form-layout form-layout--single">
            <div class="gc-card form-card">
                <form method="POST" enctype="multipart/form-data" class="form-stack">
                    {{ csrf_field() }}
                    <div class="mb-3">
                        <label for="name" class="form-label">Название</label>
                        <input id="name" type="text" class="form-control" name="name" value="{{old('name')}}"
                               required>
                        @if ($errors->has('name'))
                            <span class="text-danger d-block">
                                    <strong>{{ $errors->first('name') }}</strong>
                                </span>
                        @endif
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Описание</label>
                        <textarea id="description" class="form-control" name="description" rows="4"
                                  required>{{old('description')}}</textarea>
                        @if ($errors->has('description'))
                            <span class="text-danger d-block">
                                    <strong>{{ $errors->first('description') }}</strong>
                                </span>
                        @endif
                    </div>

                    <div class="mb-3">
                        <label for="number" class="form-label">Количество</label>
                        <input id="number" type="text" class="form-control" name="number" value="{{old('number')}}"
                               required>
                        @if ($errors->has('number'))
                            <span class="text-danger d-block">
                                    <strong>{{ $errors->first('number') }}</strong>
                                </span>
                        @endif
                    </div>

                    <div class="mb-3">
                        <label for="price" class="form-label">Стоимость</label>
                        <input id="price" type="text" class="form-control" name="price" value="{{old('price')}}"
                               required>
                        @if ($errors->has('price'))
                            <span class="text-danger d-block">
                                    <strong>{{ $errors->first('price') }}</strong>
                                </span>
                        @endif
                    </div>

                    <div class="mb-3">
                        <label for="image" class="form-label">Фото</label>
                        <input id="image" type="text" class="form-control" name="image" value="{{old('image')}}"
                               required>
                        @if ($errors->has('image'))
                            <span class="text-danger d-block">
                                    <strong>{{ $errors->first('image') }}</strong>
                                </span>
                        @endif
                    </div>

                    <div class="mb-3 form-check">
                        <input id="in_stock" type="checkbox" class="form-check-input" name="in_stock" value="on">
                        <label for="in_stock" class="form-check-label">В продаже?</label>
                        @if ($errors->has('in_stock'))
                            <span class="text-danger d-block">
                                    <strong>{{ $errors->first('in_stock') }}</strong>
                                </span>
                        @endif
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-success">Создать товар</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
