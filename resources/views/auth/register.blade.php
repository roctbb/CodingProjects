@extends('layouts.empty-dark')

@section('title')
    Регистрация
@endsection

@section('content')
    <div class="ge-auth-visual-shell ge-auth-register-shell">
        <div class="auth-panel-shell auth-panel-shell--register">
            <div class="text-center auth-brand">
                <a class="auth-brand-link" href="{{ url('/') }}">
                    <img class="auth-brand-icon" src="{{ url('images/icons/icons8-idea-64.png') }}" alt="logo">
                    {{ config('app.name', 'Laravel') }}
                </a>
                <h3 class="auth-title">Регистрация</h3>
            </div>
            <div class="card auth-card auth-card--register">
                <div class="card-body">
                    <form class="auth-form" method="POST" enctype="multipart/form-data">
                        {{ csrf_field() }}
                        @if (!$to_course)
                            <h4>Инвайт</h4>
                            <div class="mb-3">
                                <label for="invite" >Инвайт</label>
                                <input id="invite" type="text" class="form-control" name="invite" value="{{ old('invite') }}" autocomplete="off">
                                <span class="form-text text-muted">Если вы получили от инвайт преподавателя, укажите его.</span>
                                @if ($errors->has('invite'))
                                    <span class="invalid-feedback d-block"><strong>{{ $errors->first('invite') }}</strong></span>
                                @endif
                            </div>
                        @endif

                        <h4>Аккаунт</h4>
                        <div class="mb-3">
                            <label for="email" >E-Mail</label>
                            <input id="email" type="email" class="form-control" name="email" value="{{ old('email') }}" autocomplete="email" required>
                            <span class="form-text text-muted">Ваш действующий Email адрес, он будет вашим логином.</span>
                            @if ($errors->has('email'))
                                <span class="invalid-feedback d-block"><strong>{{ $errors->first('email') }}</strong></span>
                            @endif
                        </div>

                        <div class="mb-3">
                            <label for="password" >Пароль</label>
                            <div class="auth-password-wrap">
                                <input id="password" type="password" class="form-control" name="password" autocomplete="new-password" required>
                                <button type="button" class="auth-password-toggle" data-target="password" aria-label="Показать пароль">
                                    <i class="icon ion-eye"></i>
                                </button>
                            </div>
                            <div class="auth-password-strength" data-password-strength-target="password">
                                <div class="auth-password-strength__track"><span class="auth-password-strength__bar"></span></div>
                                <span class="auth-password-strength__label">Надежность пароля: не указано</span>
                            </div>
                            @if ($errors->has('password'))
                                <span class="invalid-feedback d-block"><strong>{{ $errors->first('password') }}</strong></span>
                            @endif
                        </div>

                        <div class="mb-3">
                            <label for="password-confirm" >Повторите пароль</label>
                            <div class="auth-password-wrap">
                                <input id="password-confirm" type="password" class="form-control" name="password_confirmation" autocomplete="new-password" required>
                                <button type="button" class="auth-password-toggle" data-target="password-confirm" aria-label="Показать пароль">
                                    <i class="icon ion-eye"></i>
                                </button>
                            </div>
                        </div>

                        <h4>Профиль</h4>
                        <div class="mb-3">
                            <label for='name'>Имя</label>
                            <input id='name' type="text" class="form-control" name='name' value="{{ old('name') }}" autocomplete="name" required>
                            <span class="form-text text-muted">Ваше имя и фамилия.</span>
                            @if ($errors->has('name'))
                                <span class="invalid-feedback d-block"><strong>{{ $errors->first('name') }}</strong></span>
                            @endif
                        </div>

                        <div class="mb-3">
                            <label for='birthday'>Дата рождения</label>
                            <input id='birthday' type="text" class="form-control date" name='birthday' value="{{old('birthday')}}" autocomplete="bday" required>
                            <span class="form-text text-muted"><strong>Это обязательное поле.</strong></span>
                            @if ($errors->has('birthday'))
                                <span class="invalid-feedback d-block">
                                    <strong>{{ $errors->first('birthday') }}</strong>
                                </span>
                            @endif
                        </div>

                        <div class="mb-3">
                            <label for='school'>Место учебы</label>
                            <input id='school' type="text" class="form-control" name='school' value="{{old('school')}}" autocomplete="organization" required>
                            <span class="form-text text-muted">Например, "Гимназия 1576". <strong>Это обязательное поле.</strong></span>
                            @if ($errors->has('school'))
                                <span class="invalid-feedback d-block">
                                    <strong>{{ $errors->first('school') }}</strong>
                                </span>
                            @endif
                        </div>

                        <div class="mb-3">
                            <label for='grade'>Класс (только число)</label>
                            <input id='grade' type="number" class="form-control" name='grade' value="{{old('grade')}}" autocomplete="off" required>
                            <span class="form-text text-muted">Ваш текущий класс, если сейчас лето, то класс в который вы переходите. <strong>Это обязательное поле.</strong></span>
                            @if ($errors->has('grade'))
                                <span class="invalid-feedback d-block">
                                    <strong>{{ $errors->first('grade') }}</strong>
                                </span>
                            @endif
                        </div>

                        <h4>О себе</h4>
                        <div class="mb-3">
                            <label for='interests'>Технические интересы</label>
                            <textarea id="interests" class="form-control" name="interests">{{old('interests')}}</textarea>
                            <span class="form-text text-muted">Все направления, предметы и технологии, которые вам могут быть интересны. Например, "Нейронные сети и блокчейн, разработка мобильных приложений на React Native".</span>
                            @if ($errors->has('interests'))
                                <span class="invalid-feedback d-block"><strong>{{ $errors->first('interests') }}</strong></span>
                            @endif
                        </div>

                        <div class="mb-3">
                            <label for='hobbies'>Увлечения</label>
                            <textarea id="hobbies" class="form-control" name="hobbies">{{old('hobbies')}}</textarea>
                            <span class="form-text text-muted">Все, чем вы интересуетесь помимо учебы и работы. Например, "катание на лошадях и игра на гитаре".</span>
                            @if ($errors->has('hobbies'))
                                <span class="invalid-feedback d-block">
                                    <strong>{{ $errors->first('hobbies') }}</strong>
                                </span>
                            @endif
                        </div>

                        <div class="mb-3">
                            <label for="image">Аватар</label>
                            <input id="image" type="file" class="form-control" name="image"/>
                            @if ($errors->has('image'))
                                <span class="invalid-feedback d-block"><strong>{{ $errors->first('image') }}</strong></span>
                            @endif
                        </div>

                        <h4>Контакты</h4>
                        <span class="form-text text-muted">Эти данные увидят только другие студенты школы, заполнять не обязательно. <strong>Они не видны из интернета.</strong></span>

                        <div class="mb-3">
                            <label for='telegram'>Telegram</label>
                            <input id='telegram' type="text" class="form-control" name='telegram' value="{{old('telegram')}}" autocomplete="username">
                            @if ($errors->has('telegram'))
                                <span class="invalid-feedback d-block">
                                    <strong>{{ $errors->first('telegram') }}</strong>
                                </span>
                            @endif
                        </div>

                        <div class="mb-3">
                            <label for='git'>Git</label>
                            <input id='git' type="text" class="form-control" name='git' value="{{old('git')}}" autocomplete="url">
                            @if ($errors->has('git'))
                                <span class="invalid-feedback d-block">
                                    <strong>{{ $errors->first('git') }}</strong>
                                </span>
                            @endif
                        </div>

                        <div class="mb-3">
                            <label>E-Mail для связи</label>
                            <input type="text" class="form-control" value="{{ old('email') }}" readonly>
                            <span class="form-text text-muted">Используется адрес из поля E-Mail выше.</span>
                        </div>

                        <div class="mb-3">
                            @include('captcha')
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-100">Регистрация!</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

@endsection
