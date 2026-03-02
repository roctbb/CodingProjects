@extends('layouts.left-menu')

@section('title')
    Профиль
@endsection

@section('content')


    <form method="POST" class="form-horizontal" enctype="multipart/form-data">
        {{ csrf_field() }}
        <div class="row">
            <div class="col-md-8">
                <h4>Профиль</h4>
            </div>
            <div class="col">
                <button type="submit" class="btn btn-success btn-sm float-right">Сохранить</button>
            </div>
        </div>

        <div class="row" style="margin-top: 15px;">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">

                        <div class="form-group{{ $errors->has('password') ? ' has-error' : '' }}">
                            <label for="password" class="control-label">Новый пароль</label>


                            <input id="password" type="password" class="form-control" name="password">

                            @if ($errors->has('password'))
                                <span class="help-block error-block"><strong>{{ $errors->first('password') }}</strong></span>
                            @endif

                        </div>

                        <div class="form-group">
                            <label for="password-confirm" class="control-label">Повторите
                                пароль</label>


                            <input id="password-confirm" type="password" class="form-control"
                                   name="password_confirmation">

                        </div>

                        <div class="form-group">
                            <label for='name'>Имя</label>

                            @if (old('name')!="")
                                <input id='name' type="text" class="form-control" name='name' value="{{old('name')}}"
                                       required>
                            @else
                                <input id='name' type="text" class="form-control" name='name' value="{{$user->name}}"
                                       required>
                            @endif
                            @if ($errors->has('name'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('name') }}</strong>
                                    </span>
                            @endif
                        </div>

                        @if (\Auth::User()->role == 'teacher' || \Auth::User()->role == 'admin')
                            <div class="form-group">
                                <label for='birthday'>Дата рождения</label>

                                @if (old('birthday')!="" || $user->birthday==null)
                                    <input id='birthday' type="text" class="form-control date" name='birthday'
                                           value="{{old('birthday')}}" required>
                                @else
                                    <input id='birthday' type="text" class="form-control date" name='birthday'
                                           value="{{$user->birthday->format('Y-m-d')}}"
                                           required>
                                @endif
                                @if ($errors->has('birthday'))
                                    <span class="help-block error-block">
                                        <strong>{{ $errors->first('birthday') }}</strong>
                                    </span>
                                @endif
                            </div>
                        @endif
                        <div class="form-group">
                            <label for='school'>Место учебы</label>

                            @if (old('school')!="")
                                <input id='school' type="text" class="form-control" name='school'
                                       value="{{old('school')}}"
                                       required>
                            @else
                                <input id='school' type="text" class="form-control" name='school'
                                       value="{{$user->school}}"
                                       required>
                            @endif
                            @if ($errors->has('school'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('school') }}</strong>
                                    </span>
                            @endif
                        </div>
                        <div class="form-group">
                            <label for='grade'>Класс</label>

                            @if (old('grade')!="" || $user->grade_year==null)
                                <input id='grade' type="text" class="form-control" name='grade' value="{{old('grade')}}"
                                       required>
                            @else
                                <input id='grade' type="text" class="form-control" name='grade'
                                       value="{{$user->grade()}}"
                                       required>
                            @endif
                            @if ($errors->has('grade'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('grade') }}</strong>
                                    </span>
                            @endif
                        </div>

                        @if (\Auth::User()->role == 'admin')
                            <div class="form-group">
                                <label for='role'>Роль пользователя</label>
                                <select id='role' class="form-control" name='role'>
                                    <option value="student" {{ $user->role == 'student' || !$user->role ? 'selected' : '' }}>Студент</option>
                                    <option value="teacher" {{ $user->role == 'teacher' ? 'selected' : '' }}>Преподаватель</option>
                                    <option value="theme_moderator" {{ $user->role == 'theme_moderator' ? 'selected' : '' }}>Модератор тем</option>
                                    <option value="admin" {{ $user->role == 'admin' ? 'selected' : '' }}>Администратор</option>
                                </select>
                                @if ($errors->has('role'))
                                    <span class="help-block error-block">
                                        <strong>{{ $errors->first('role') }}</strong>
                                    </span>
                                @endif
                            </div>
                        @endif

                        <h4>О себе</h4>
                        <div class="form-group">
                            <label for='interests'>Технические интересы</label>

                            @if (old('interests')!="")
                                <textarea id="interests" class="form-control"
                                          name="interests">{{old('interests')}}</textarea>
                            @else
                                <textarea id="interests" class="form-control"
                                          name="interests">{{$user->interests}}</textarea>
                            @endif
                            @if ($errors->has('interests'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('interests') }}</strong>
                                    </span>
                            @endif
                        </div>
                        <div class="form-group">
                            <label for='hobbies'>Увлечения</label>

                            @if (old('hobbies')!="")
                                <textarea id="hobbies" class="form-control" name="hobbies">{{old('hobbies')}}</textarea>
                            @else
                                <textarea id="hobbies" class="form-control" name="hobbies">{{$user->hobbies}}</textarea>
                            @endif
                            @if ($errors->has('hobbies'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('hobbies') }}</strong>
                                    </span>
                            @endif
                        </div>
                        @if ($guest->role=='teacher' || $guest->role=='admin')
                            <h4>Информация</h4>
                            <div class="form-group">
                                <label for='comments'>Комментарий</label>

                                @if (old('comments')!="")
                                    <textarea id="comments" class="form-control"
                                              name="comments">{{old('comments')}}</textarea>
                                @else
                                    <textarea id="comments" class="form-control"
                                              name="comments">{{$user->comments}}</textarea>
                                @endif
                                @if ($errors->has('comments'))
                                    <span class="help-block error-block">
                                        <strong>{{ $errors->first('comments') }}</strong>
                                    </span>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>


            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">


                        <div class="form-group{{ $errors->has('image') ? ' has-error' : '' }}">
                            <label for="image">Аватар</label>

                            <input id="image" type="file" class="form-control" name="image"/>

                            @if ($errors->has('image'))
                                <span class="help-block error-block"><strong>{{ $errors->first('image') }}</strong></span>
                            @endif
                        </div>
                        <h4>Контакты</h4>
                        <div class="form-group">
                            <label for='vk'>VK</label>

                            @if (old('vk')!="")
                                <input id='vk' type="text" class="form-control" name='vk' value="{{old('vk')}}">
                            @else
                                <input id='vk' type="text" class="form-control" name='vk' value="{{$user->vk}}">
                            @endif
                            @if ($errors->has('vk'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('vk') }}</strong>
                                    </span>
                            @endif
                        </div>
                        <div class="form-group">
                            <label for='telegram'>Telegram</label>

                            @if (old('telegram')!="")
                                <input id='telegram' type="text" class="form-control" name='telegram'
                                       value="{{old('telegram')}}">
                            @else
                                <input id='telegram' type="text" class="form-control" name='telegram'
                                       value="{{$user->telegram}}">
                            @endif
                            @if ($errors->has('telegram'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('telegram') }}</strong>
                                    </span>
                            @endif
                        </div>
                        <div class="form-group">
                            <label for='git'>Git</label>

                            @if (old('git')!="")
                                <input id='git' type="text" class="form-control" name='git' value="{{old('git')}}">
                            @else
                                <input id='git' type="text" class="form-control" name='git' value="{{$user->git}}">
                            @endif
                            @if ($errors->has('git'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('git') }}</strong>
                                    </span>
                            @endif
                        </div>
                        <div class="form-group">
                            <label for='facebook'>Facebook</label>

                            @if (old('facebook')!="")
                                <input id='facebook' type="text" class="form-control" name='facebook'
                                       value="{{old('facebook')}}">
                            @else
                                <input id='facebook' type="text" class="form-control" name='facebook'
                                       value="{{$user->facebook}}">
                            @endif
                            @if ($errors->has('facebook'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('facebook') }}</strong>
                                    </span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>


        </div>
    </form>
    <script>
        var simplemde_description = new EasyMDE({
            spellChecker: false,
            element: document.getElementById("description")
        });
        var simplemde_theory = new EasyMDE({spellChecker: false, element: document.getElementById("theory")});
    </script>
@endsection
