@extends('layouts.left-menu')

@section('title')
    Профиль
@endsection

@section('content')
    <div class="cp-profile-edit-page">
    <form method="POST" enctype="multipart/form-data">
        {{ csrf_field() }}
        <div class="row">
            <div class="col-md-8">
                <h4 class="cp-profile-edit-title">Профиль</h4>
            </div>
            <div class="col text-md-end">
                <button type="submit" class="btn btn-primary btn-sm">Сохранить</button>
            </div>
        </div>

        <div class="row cp-row-gap-top">
            <div class="col-md-8">
                <div class="card cp-form-card">
                    <div class="card-body">

                        <div class="mb-3">
                            <label for="password">Новый пароль</label>


                            <input id="password" type="password" class="form-control" name="password">

                            @if ($errors->has('password'))
                                <span class="invalid-feedback d-block"><strong>{{ $errors->first('password') }}</strong></span>
                            @endif

                        </div>

                        <div class="mb-3">
                            <label for="password-confirm">Повторите
                                пароль</label>


                            <input id="password-confirm" type="password" class="form-control"
                                   name="password_confirmation">

                        </div>

                        <div class="mb-3">
                            <label for='name'>Имя</label>

                            @if (old('name')!="")
                                <input id='name' type="text" class="form-control" name='name' value="{{old('name')}}"
                                       required>
                            @else
                                <input id='name' type="text" class="form-control" name='name' value="{{$user->name}}"
                                       required>
                            @endif
                            @if ($errors->has('name'))
                                <span class="invalid-feedback d-block">
                                        <strong>{{ $errors->first('name') }}</strong>
                                    </span>
                            @endif
                        </div>

                        @if (\Auth::User()->role == 'teacher' || \Auth::User()->role == 'admin')
                            <div class="mb-3">
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
                                    <span class="invalid-feedback d-block">
                                        <strong>{{ $errors->first('birthday') }}</strong>
                                    </span>
                                @endif
                            </div>
                        @endif
                        <div class="mb-3">
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
                                <span class="invalid-feedback d-block">
                                        <strong>{{ $errors->first('school') }}</strong>
                                    </span>
                            @endif
                        </div>
                        <div class="mb-3">
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
                                <span class="invalid-feedback d-block">
                                        <strong>{{ $errors->first('grade') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <h4 class="cp-profile-edit-section-title">О себе</h4>
                        <div class="mb-3">
                            <label for='interests'>Технические интересы</label>

                            @if (old('interests')!="")
                                <textarea id="interests" class="form-control"
                                          name="interests">{{old('interests')}}</textarea>
                            @else
                                <textarea id="interests" class="form-control"
                                          name="interests">{{$user->interests}}</textarea>
                            @endif
                            @if ($errors->has('interests'))
                                <span class="invalid-feedback d-block">
                                        <strong>{{ $errors->first('interests') }}</strong>
                                    </span>
                            @endif
                        </div>
                        <div class="mb-3">
                            <label for='hobbies'>Увлечения</label>

                            @if (old('hobbies')!="")
                                <textarea id="hobbies" class="form-control" name="hobbies">{{old('hobbies')}}</textarea>
                            @else
                                <textarea id="hobbies" class="form-control" name="hobbies">{{$user->hobbies}}</textarea>
                            @endif
                            @if ($errors->has('hobbies'))
                                <span class="invalid-feedback d-block">
                                        <strong>{{ $errors->first('hobbies') }}</strong>
                                    </span>
                            @endif
                        </div>
                        @if ($guest->role=='teacher' || $guest->role=='admin')
                            <h4 class="cp-profile-edit-section-title">Информация</h4>
                            <div class="mb-3">
                                <label for='comments'>Комментарий</label>

                                @if (old('comments')!="")
                                    <textarea id="comments" class="form-control"
                                              name="comments">{{old('comments')}}</textarea>
                                @else
                                    <textarea id="comments" class="form-control"
                                              name="comments">{{$user->comments}}</textarea>
                                @endif
                                @if ($errors->has('comments'))
                                    <span class="invalid-feedback d-block">
                                        <strong>{{ $errors->first('comments') }}</strong>
                                    </span>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>


            </div>
            <div class="col-md-4">
                <div class="card cp-form-card">
                    <div class="card-body">


                        <div class="mb-3">
                            <label for="image">Аватар</label>

                            <input id="image" type="file" class="form-control" name="image"/>

                            @if ($errors->has('image'))
                                <span class="invalid-feedback d-block"><strong>{{ $errors->first('image') }}</strong></span>
                            @endif
                        </div>
                        <h4 class="cp-profile-edit-section-title">Контакты</h4>
                        <div class="mb-3">
                            <label for='telegram'>Telegram</label>

                            @if (old('telegram')!="")
                                <input id='telegram' type="text" class="form-control" name='telegram'
                                       value="{{old('telegram')}}">
                            @else
                                <input id='telegram' type="text" class="form-control" name='telegram'
                                       value="{{$user->telegram}}">
                            @endif
                            @if ($errors->has('telegram'))
                                <span class="invalid-feedback d-block">
                                        <strong>{{ $errors->first('telegram') }}</strong>
                                    </span>
                            @endif
                        </div>
                        <div class="mb-3">
                            <label for='git'>Git</label>

                            @if (old('git')!="")
                                <input id='git' type="text" class="form-control" name='git' value="{{old('git')}}">
                            @else
                                <input id='git' type="text" class="form-control" name='git' value="{{$user->git}}">
                            @endif
                            @if ($errors->has('git'))
                                <span class="invalid-feedback d-block">
                                        <strong>{{ $errors->first('git') }}</strong>
                                    </span>
                            @endif
                        </div>
                        <div class="mb-3">
                            <label for='email'>E-Mail</label>
                            <input id='email' type="text" class="form-control" value="{{$user->email}}" readonly>
                            <span class="form-text text-muted">Почта задается при регистрации.</span>
                        </div>
                    </div>
                </div>
            </div>


        </div>
    </form>
    </div>
@endsection
