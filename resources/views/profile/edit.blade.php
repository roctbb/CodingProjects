@extends('layouts.left-menu')

@section('title')
    Профиль
@endsection

@section('content')


    <form method="POST" enctype="multipart/form-data" class="profile-edit-page form-page">
        {{ csrf_field() }}
        <div class="form-page-header gc-card mb-3">
            <div class="min-width-0">
                <a class="assessment-back-link" href="{{ url('/insider/profile/'.$user->id) }}"><i class="icon ion-chevron-left"></i> К профилю</a>
                <h2 class="mb-1 text-truncate">Редактирование профиля</h2>
                <p class="mb-0 text-muted text-truncate">{{ $user->name }}</p>
            </div>
            <div>
                <button type="submit" class="btn btn-success">Сохранить</button>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-8">
                <div class="gc-card profile-edit-card">
                    <div class="card-body">

                        <div class="form-section-title">Доступ</div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Новый пароль</label>


                            <input id="password" type="password" class="form-control" name="password">

                            @if ($errors->has('password'))
                                <span class="text-danger d-block"><strong>{{ $errors->first('password') }}</strong></span>
                            @endif

                        </div>

                        <div class="mb-3">
                            <label for="password-confirm" class="form-label">Повторите
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
                                <span class="text-danger d-block">
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
                                    <span class="text-danger d-block">
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
                                <span class="text-danger d-block">
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
                                <span class="text-danger d-block">
                                        <strong>{{ $errors->first('grade') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-section-title">О себе</div>
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
                                <span class="text-danger d-block">
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
                                <span class="text-danger d-block">
                                        <strong>{{ $errors->first('hobbies') }}</strong>
                                    </span>
                            @endif
                        </div>
                        @if ($guest->role=='teacher' || $guest->role=='admin')
                            <div class="form-section-title">Информация</div>
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
                                    <span class="text-danger d-block">
                                        <strong>{{ $errors->first('comments') }}</strong>
                                    </span>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>


            </div>
            <div class="col-md-4">
                <div class="gc-card profile-edit-card profile-edit-side-card">
                    <div class="card-body">
                        <div class="text-center mb-3">
                            <img src="{{ $user->imageUrl() }}" class="avatar lg rounded-circle" alt="">
                            <h6 class="mt-2 mb-0">{{ $user->name }}</h6>
                            <small class="text-muted">{{ $user->rank()->name }}</small>
                        </div>

                        <div class="mb-3">
                            <label for="image">Аватар</label>

                            <input id="image" type="file" class="form-control" name="image"/>

                            @if ($errors->has('image'))
                                <span class="text-danger d-block"><strong>{{ $errors->first('image') }}</strong></span>
                            @endif
                        </div>
                        <div class="form-section-title">Контакты</div>
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
                                <span class="text-danger d-block">
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
                                <span class="text-danger d-block">
                                        <strong>{{ $errors->first('git') }}</strong>
                                    </span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>


        </div>
    </form>
@endsection
