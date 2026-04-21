@extends('layouts.left-menu')

@section('title')
    Изменение программы
@endsection

@section('content')
    <div class="cp-program-form-page">
    <h2 class="cp-heading-lite">Изменение программы "{{$program->name}}"</h2>
    <div class="row cp-row-gap-top">
        <div class="col-12 col-xl-10">
            <div class="card cp-form-card">
                <div class="card-body">
            <form method="POST" class="vstack gap-3" enctype="multipart/form-data">
                {{ csrf_field() }}
                <div class="mb-3">
                    <label for="name">Название</label>

                    @if (old('name')!="")
                        <input id="name" type="text" class="form-control" name="name" value="{{old('name')}}" required>
                    @else
                        <input id="name" type="text" class="form-control" name="name" value="{{$program->name}}"
                               required>
                    @endif
                    @if ($errors->has('name'))
                        <span class="invalid-feedback d-block">
                                        <strong>{{ $errors->first('name') }}</strong>
                                    </span>
                    @endif
                </div>

                <div class="mb-3">
                    <label for="description">Описание</label>
                    <textarea id="description" class="form-control" name="description"
                              required>@if (old('description')!=""){{old('description')}}@else{{$program->description}}@endif</textarea>
                    @if ($errors->has('description'))
                        <span class="invalid-feedback d-block">
                                        <strong>{{ $errors->first('description') }}</strong>
                                    </span>
                    @endif
                </div>

                <button type="submit" class="btn btn-primary">Сохранить</button>
            </form>
                </div>
            </div>
        </div>
    </div>
    </div>
@endsection
