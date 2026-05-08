@extends('layouts.left-menu')

@section('title')
    Добавление курса
@endsection

@section('content')
    <div class="form-page">
        <div class="form-page-header gc-card mb-3">
            <div>
                <a class="assessment-back-link" href="{{ url('/insider/courses') }}"><i class="icon ion-chevron-left"></i> К курсам</a>
                <h2 class="mb-1">Создание курса</h2>
                <p class="mb-0 text-muted">Задайте основу курса. Программу, учеников и материалы можно настроить после создания.</p>
            </div>
        </div>

        <div class="form-layout form-layout--single">
            <div class="gc-card form-card">
                <form method="POST" enctype="multipart/form-data" class="form-stack">
                    {{ csrf_field() }}
                    <div class="mb-3">
                        <label for="name" class="form-label">Название</label>
                        <input id="name" type="text" class="form-control" name="name" value="{{old('name')}}" required>
                        @if ($errors->has('name'))
                            <span class="text-danger d-block">
                                <strong>{{ $errors->first('name') }}</strong>
                            </span>
                        @endif
                    </div>

                    <div class="mb-3">
                        <label for="program" class="form-label">Программа</label>
                        <select id="program" class="form-select" name="program" required>
                            <option value="-1">Новая программа на основе курса</option>
                            @foreach($programs as $program)
                                <option value="{{$program->id}}">{{$program->name}}</option>
                            @endforeach
                        </select>
                        @if ($errors->has('program'))
                            <span class="text-danger d-block">
                                <strong>{{ $errors->first('program') }}</strong>
                            </span>
                        @endif
                        <div class="form-text">Можно создать новую программу или взять структуру существующей.</div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Описание</label>
                        <textarea id="description" class="form-control" name="description" rows="5" required>{{old('description')}}</textarea>
                        @if ($errors->has('description'))
                            <span class="text-danger d-block">
                                <strong>{{ $errors->first('description') }}</strong>
                            </span>
                        @endif
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-success">Создать курс</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
