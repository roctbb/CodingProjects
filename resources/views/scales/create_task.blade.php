@extends('layouts.left-menu')

@section('title')
    GeekClass: Добавление задачи
@endsection

@section('content')

    <h2>Добавление задачи</h2>
    <div class="row" style="margin-top: 15px;">
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <form method="POST" class="form-horizontal" enctype="multipart/form-data">
                        {{ csrf_field() }}
                        <div class="form-group">
                            <label for="name">Название</label>

                            <input id="name" type="text" class="form-control" name="name" value="{{old('name')}}"
                                   required>

                            @if ($errors->has('name'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('name') }}</strong>
                                    </span>
                            @endif
                        </div>


                        <div class="form-group">
                            <label for="text">Текст</label>
                            <textarea id="text" class="form-control" name="text">{{old('text')}}</textarea>
                            @if ($errors->has('text'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('text') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-group">
                            <label for="solution">Решение</label>
                            <textarea id="solution" class="form-control" name="solution">{{old('solution')}}</textarea>
                            @if ($errors->has('solution'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('solution') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-group">
                            <label for="is_demo">Демонстрационное задание</label>
                            <input type="checkbox" id="is_demo" name="is_demo" value="yes"/>
                        </div>

                        <div class="form-group">
                            <label for="is_training">Тренировочное задание</label>
                            <input type="checkbox" id="is_training" name="is_training" value="yes"/>
                        </div>


                        <button type="submit" class="btn btn-success">Создать</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        var simplemde_task = new EasyMDE({
            spellChecker: false,
            element: document.getElementById("text")
        });
        var simplemde_solution = new EasyMDE({
            spellChecker: false,
            element: document.getElementById("solution")
        });
    </script>
@endsection

