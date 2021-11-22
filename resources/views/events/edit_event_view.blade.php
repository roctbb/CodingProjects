@extends('layouts.left-menu')

@section('content')
    <div class="row">
        <div class="col">
            <h2>Изменить событие</h2>
            <div class="card">
                <div class="card-body">
                    <form method="POST">
                        @csrf
                        <div class="form-group">
                            <label>Тип события</label>
                            @if (old('type'))
                                <div class="form-check">
                                    <input type="radio" name="type" class="form-check-input" value="out" @if (old('type') == 'out') checked @endif>
                                    <label class="form-check-label">Внешнее</label>

                                </div>
                                <div class="form-check">
                                    <input type="radio" name="type" class="form-check-input" value="in" @if (old('type') == 'in') checked @endif>
                                    <label class="form-check-label">Внутреннее</label>
                                </div>
                            @else
                                <div class="form-check">
                                    <input type="radio" name="type" class="form-check-input" value="out" @if ($event->type == 'out') checked @endif>
                                    <label class="form-check-label">Внешнее</label>

                                </div>
                                <div class="form-check">
                                    <input type="radio" name="type" class="form-check-input" value="in" @if ($event->type == 'in') checked @endif>
                                    <label class="form-check-label">Внутреннее</label>
                                </div>
                            @endif

                            @if ($errors->has('type'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('type') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-group required">
                            <label>Название события</label>
                            <input type="text" name="name" class="form-control" required @if (!old('name')) value="{{$event->name}}" @else value="{{old('name')}}" @endif>
                            @if ($errors->has('name'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('name') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-group">
                            <label for="orgs" style="padding-bottom: 10px;">Организаторы:</label><br>
                            <select class="selectpicker1  form-control" data-live-search="true" id="orgs"
                                    name="orgs[]" multiple data-width="auto">
                                @foreach (\App\User::all() as $user)
                                    <option data-tokens="{{ $user->id }}"
                                            value="{{ $user->id }}">{{$user->name}}</option>
                                @endforeach
                            </select>

                            <script>
                                $('.selectpicker1').selectpicker('val', [{{implode(',', $event->userOrgs->pluck('id')->toArray())}}]);
                            </script>
                        </div>

                        <div class="form-group">
                            <label for="participants" style="padding-bottom: 10px;">Участники:</label><br>
                            <select class="selectpicker2  form-control" data-live-search="true" id="orgs"
                                    name="participants[]" multiple data-width="auto">
                                @foreach (\App\User::all() as $user)
                                    <option data-tokens="{{ $user->id }}"
                                            value="{{ $user->id }}">{{$user->name}}</option>
                                @endforeach
                            </select>

                            <script>
                                $('.selectpicker2').selectpicker('val', [{{implode(',', $event->participants->pluck('id')->toArray())}}]);
                            </script>
                        </div>

                        <div class="form-group required">
                            <label>Краткое описание события</label>
                            <input name="short_text" class="form-control" required @if (!old('short_text')) value="{{$event->short_text}}" @else value="{{old('short_text')}}" @endif>
                            @if ($errors->has('short_text'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('short_text') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-group required">
                            <label>Описание</label>
                            @if (!old('text'))
                                <textarea id="text" class="form-control"
                                          name="text">{{$event->text}}</textarea>
                            @else
                                <textarea id="text" class="form-control"
                                          name="text">{{old('text')}}</textarea>
                            @endif
                            @if ($errors->has('text'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('text') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-group required">
                            <label>Дата</label>
                            <input type="text" name="date" class="date form-control" required id="dp1570721390164" @if (!old('location')) value="{{ $event->date->format("Y-m-d")}}"
                                   @else value="{{old('location')}}" @endif>
                            @if ($errors->has('date'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('date') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <label>Время</label>
                        <input type="time" name="time" class="form-control" required="" @if (!old('location')) value="{{ $event->date->format("H:i")}}" @else value="{{old('location')}}" @endif>
                        @if ($errors->has('time'))
                            <span class="help-block error-block">
                                        <strong>{{ $errors->first('time') }}</strong>
                                    </span>
                        @endif

                        <div class="form-group required">
                            <label>Место проведения</label>
                            <input name="location" class="form-control" required @if (!old('location')) value="{{$event->location}}" @else value="{{old('location')}}" @endif>
                            @if ($errors->has('location'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('location') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-group">
                            <label>Максимальное число участников</label>
                            <input type="number" name="max_people" class="form-control"
                                   @if (!old('max_people')) value="{{$event->max_people}}" @else value="{{old('max_people')}}" @endif>
                            @if ($errors->has('max_people'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('max_people') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-group">
                            <label>Сайт мероприятия</label>
                            <input name="site" class="form-control" @if (!old('site')) value="{{$event->site}}" @else value="{{old('site')}}" @endif>
                            @if ($errors->has('site'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('site') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-group">
                            <label>Ограничения</label>
                            <input name="skills" class="form-control" @if (!old('skills')) value="{{$event->skills}}" @else value="{{old('skills')}}" @endif>
                            @if ($errors->has('skills'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('skills') }}</strong>
                                    </span>
                            @endif
                        </div>


                        <input type="submit" value="Сохранить" class="btn btn-success form-control">

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