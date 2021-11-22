@extends('layouts.left-menu')

@section('content')
    <div class="row">
        <div class="col">
            <h2>Создать событие</h2>
            <div class="card">
                <div class="card-body">
                    <form method="POST">
                        @csrf
                        <div class="form-group">
                            <label>Тип события</label>
                            <div class="form-check">
                                <input type="radio" name="type" class="form-check-input" value="out" @if (old('type') == 'out' or !old('type')) checked @endif>
                                <label class="form-check-label">Внешнее</label>

                            </div>
                            <div class="form-check">
                                <input type="radio" name="type" class="form-check-input" value="in" @if (old('type') == 'in') checked @endif>
                                <label class="form-check-label">Внутреннее</label>
                            </div>

                            @if ($errors->has('type'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('type') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-group required">
                            <label>Название события</label>
                            <input type="text" name="name" class="form-control" value="{{old("name")}}" required>
                            @if ($errors->has('name'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('name') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-group required">
                            <label>Краткое описание события</label>
                            <input name="short_text" class="form-control" value="{{old("short_text")}}" required>
                            @if ($errors->has('short_text'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('short_text') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-group required">
                            <label>Описание</label>
                            <textarea id="text" class="form-control" name="text">{{old('text')}}</textarea>
                            @if ($errors->has('text'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('text') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-group required">
                            <label>Дата проведения мероприятия</label>
                            <input type="text" name="date" class="date form-control" id="dp1570721390164" value="{{old("date")}}" required>
                            @if ($errors->has('date'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('date') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <label>Время</label>
                        <input type="time" name="time" class="form-control" id="dp1570721390164"
                               value="{{ old('time')}}" required>
                        @if ($errors->has('time'))
                            <span class="help-block error-block">
                                        <strong>{{ $errors->first('time') }}</strong>
                                    </span>
                        @endif

                        <div class="form-group required">
                            <label>Место проведения мероприятия</label>
                            <input name="location" class="form-control" value="{{old("location")}}" required>
                            @if ($errors->has('location'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('location') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-group">
                            <label>Максимальное количество участников</label>
                            <input type="number" name="max_people" class="form-control" value="{{old("max_people")}}">
                            @if ($errors->has('max_people'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('max_people') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-group">
                            <label>Сайт мероприятия</label>
                            <input name="site" class="form-control" value="{{old("site")}}">
                            @if ($errors->has('site'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('site') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-group">
                            <label>Ограничения</label>
                            <input name="skills" class="form-control" value="{{old("skills")}}">
                            @if ($errors->has('skills'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('skills') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <input type="submit" value="Добавить" class="form-control btn btn-success">
                    </form>
                </div>
            </div>
        </div>
        <script>
            var simplemde_description = new EasyMDE({
                spellChecker: false,
                autosave: true,
                element: document.getElementById("text")
            });
        </script>
    </div>
@endsection