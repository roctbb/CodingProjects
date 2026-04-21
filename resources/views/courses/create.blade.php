@extends('layouts.left-menu')

@section('title')
    Добавление курса
@endsection

@section('content')
    <div class="cp-course-form-page">
    <h2 class="cp-heading-lite">Создание курса</h2>
    <div class="row cp-row-gap-top">
        <div class="col-12 col-xl-10">
            <div class="card cp-form-card">
                <div class="card-body">

            <form method="POST" class="vstack gap-3" enctype="multipart/form-data">
                {{ csrf_field() }}
                <div class="mb-3">
                    <label for="name">Название</label>
                    <input id="name" type="text" class="form-control" name="name" value="{{old('name')}}" required>
                    @if ($errors->has('name'))
                        <span class="invalid-feedback d-block">
                                        <strong>{{ $errors->first('name') }}</strong>
                                    </span>
                    @endif
                </div>

                <div class="mb-3">
                    <label for="program">Программа</label>
                    <select id="program" class="form-select" name="program" required>
                        <option value="-1">Новая программа на основе курса</option>
                        @foreach($programs as $program)
                            <option value="{{$program->id}}">{{$program->name}}</option>
                        @endforeach
                    </select>
                    @if ($errors->has('program'))
                        <span class="invalid-feedback d-block">
                                        <strong>{{ $errors->first('program') }}</strong>
                                    </span>
                    @endif
                </div>

                <div class="mb-3">
                    <label for="description">Описание</label>
                    <textarea id="description"  class="form-control"  name="description" required>{{old('description')}}</textarea>
                    @if ($errors->has('description'))
                        <span class="invalid-feedback d-block">
                                        <strong>{{ $errors->first('description') }}</strong>
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
