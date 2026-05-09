@extends('layouts.left-menu')

@section('title')
    Изменение главы
@endsection

@section('content')
    <div class="container-xl px-0">
        <div class="gc-card gc-page-header mb-3">
            <div>
                <a class="assessment-back-link" href="{{ url()->previous() }}"><i class="icon ion-chevron-left"></i> Назад</a>
                <h2 class="mb-1">Изменение главы</h2>
                <p class="mb-0 text-muted">Обновите название и описание раздела программы.</p>
            </div>
        </div>

        <div class="col-12 col-lg-8 col-xl-6">
            <div class="gc-card overflow-hidden">
                <form method="POST" enctype="multipart/form-data">
                    {{ csrf_field() }}
                    <div class="p-3 p-md-4">
                        <div class="mb-3">
                            <label for="name" class="form-label">Название</label>
                            <input id="name" type="text" class="form-control rounded-3" name="name"
                                   value="{{ old('name', $chapter->name) }}" required>
                            @error('name')
                                <span class="text-danger small d-block mt-1"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>

                        <div class="mb-0">
                            <label for="description" class="form-label">Описание</label>
                            <textarea id="description" class="form-control rounded-3" name="description" rows="5">{{ old('description', $chapter->description) }}</textarea>
                            @error('description')
                                <span class="text-danger small d-block mt-1"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                    </div>

                    <div class="gc-form-footer justify-content-end gap-2">
                        <button type="submit" class="btn btn-success rounded-3 fw-semibold px-4">Сохранить главу</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
