@extends('layouts.left-menu')

@section('title')
    Изменение главы
@endsection

@section('content')
    <div class="form-page">
        <div class="form-page-header gc-card mb-3">
            <div>
                <a class="assessment-back-link" href="{{ url()->previous() }}"><i class="icon ion-chevron-left"></i> Назад</a>
                <h2 class="mb-1">Изменение главы</h2>
                <p class="mb-0 text-muted">Обновите название и описание раздела программы.</p>
            </div>
        </div>

        <div class="form-layout form-layout--single">
            <div class="gc-card form-card">
                <form method="POST" enctype="multipart/form-data" class="form-stack">
                    {{ csrf_field() }}
                    <div class="mb-3">
                        <label for="name" class="form-label">Название</label>
                        <input id="name" type="text" class="form-control" name="name"
                               value="{{old('name')==""?$chapter->name:old('name')}}" required>
                        @if ($errors->has('name'))
                            <span class="text-danger d-block">
                                <strong>{{ $errors->first('name') }}</strong>
                            </span>
                        @endif
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Описание</label>
                        <textarea id="description" class="form-control" name="description" rows="5">{{old('description')==""?$chapter->description:old('description')}}</textarea>
                        @if ($errors->has('description'))
                            <span class="text-danger d-block">
                                <strong>{{ $errors->first('description') }}</strong>
                            </span>
                        @endif
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-success">Сохранить главу</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
