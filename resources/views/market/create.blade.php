@extends('layouts.left-menu')

@section('title')
    Создание товара
@endsection

@section('content')
    <div class="cp-market-form-page">
    <h2 class="cp-heading-lite">Создание товара</h2>
    <div class="row cp-row-gap-top">
        <div class="col-12 col-xl-10">
            <div class="card cp-form-card">
                <div class="card-body">
                    <form method="POST" class="vstack gap-3" enctype="multipart/form-data">
                        {{ csrf_field() }}
                        <div class="mb-3">
                            <label for="name">Название</label>
                            <input id="name" type="text" class="form-control" name="name" value="{{old('name')}}"
                                   required>
                            @if ($errors->has('name'))
                                <span class="invalid-feedback d-block">
                                        <strong>{{ $errors->first('name') }}</strong>
                                    </span>
                            @endif
                        </div>


                        <div class="mb-3">
                            <label for="description">Описание</label>
                            <textarea id="description" class="form-control" name="description"
                                      required>{{old('description')}}</textarea>
                            @if ($errors->has('description'))
                                <span class="invalid-feedback d-block">
                                        <strong>{{ $errors->first('description') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="mb-3">
                            <label for="number">Количество</label>
                            <input id="number" type="number" min="0" step="1" class="form-control" name="number" value="{{old('number')}}"
                                   required>
                            @if ($errors->has('number'))
                                <span class="invalid-feedback d-block">
                                        <strong>{{ $errors->first('number') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="mb-3">
                            <label for="price">Стоимость</label>
                            <input id="price" type="number" min="0" step="1" class="form-control" name="price" value="{{old('price')}}"
                                   required>
                            @if ($errors->has('price'))
                                <span class="invalid-feedback d-block">
                                        <strong>{{ $errors->first('price') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="mb-3">
                            <label for="image">Фото</label>
                            <input id="image" type="text" class="form-control" name="image" value="{{old('image')}}"
                                   required>
                            @if ($errors->has('image'))
                                <span class="invalid-feedback d-block">
                                        <strong>{{ $errors->first('image') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-check mb-3">
                            <input id="in_stock" class="form-check-input" type="checkbox" name="in_stock" value="on">
                            <label class="form-check-label" for="in_stock">В продаже?</label>
                            @if ($errors->has('in_stock'))
                                <span class="invalid-feedback d-block">
                                        <strong>{{ $errors->first('in_stock') }}</strong>
                                    </span>
                            @endif
                        </div>


                        <button type="submit" class="btn btn-primary">Создать</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    </div>
@endsection
