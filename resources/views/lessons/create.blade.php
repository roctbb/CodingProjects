@extends('layouts.left-menu')

@section('title')
    Создание урока
@endsection

@section('content')
    <div class="form-page">
        <div class="form-page-header gc-card mb-3">
            <div>
                <h2 class="mb-1">Создание урока</h2>
                <p class="mb-0 text-muted">Добавьте новый урок в программу курса.</p>
            </div>
        </div>

        <div class="form-layout form-layout--single">
            <div class="gc-card form-card">
                <form method="POST" class="form-stack">
                    {{ csrf_field() }}
                    <div class="mb-3">
                        <label for="name" class="form-label">Название урока</label>
                        <input id="name" type="text" class="form-control" value="{{old('name')}}"
                               name="name" required>
                        @if ($errors->has('name'))
                            <span class="text-danger d-block"><strong>{{ $errors->first('name') }}</strong></span>
                        @endif
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Описание урока</label>
                        <textarea id="description" class="form-control" data-markdown-editor
                                  name="description">{{old('description')}}</textarea>
                        @if ($errors->has('description'))
                            <span class="text-danger d-block">
                                <strong>{{ $errors->first('description') }}</strong>
                            </span>
                        @endif
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-success">Создать</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
@push('editor')
<script type="text/javascript" src="{{ asset('build/js/vendor/easymde.min.js') }}"></script>
<script type="text/javascript" src="{{ asset('build/js/easymde-bridge.js') }}"></script>
@endpush
