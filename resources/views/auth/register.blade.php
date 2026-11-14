@extends('layouts.empty-dark')
@section('title', 'Регистрация')
@section('auth-shell-class', 'auth-shell--registration')
@section('auth-background-image', url('/images/bg/'.random_int(1,16).'.jpg'))
@section('content')
<form id="registration-form" method="POST" enctype="multipart/form-data" class="auth-card gc-auth-card gc-auth-card--wide" data-registration-flow data-auth-form data-form-page>
    @csrf
    <x-gc-auth-heading title="Создать аккаунт" description="Приготовьте код приглашения от преподавателя." />
    <x-gc-form-errors />
    <ol class="gc-form-steps" aria-label="Шаги регистрации" data-registration-progress hidden>
        <li><button type="button" data-registration-go="0" aria-current="step"><span>1</span>Аккаунт</button></li>
        <li><button type="button" data-registration-go="1"><span>2</span>Профиль</button></li>
    </ol>
    <fieldset data-registration-step aria-labelledby="registration-account-title">
        <legend id="registration-account-title" class="gc-section-title">Данные для входа</legend>
        <div class="gc-form-stack">
            <x-gc-field name="invite" label="Код приглашения" hint="Код, который выдал преподаватель."><input id="invite" name="invite" class="form-control" value="{{ old('invite', request('invite')) }}" maxlength="255" autocapitalize="none" spellcheck="false" required></x-gc-field>
            <x-gc-field name="name" label="Имя и фамилия"><input id="name" name="name" class="form-control" value="{{ old('name') }}" autocomplete="name" maxlength="255" required></x-gc-field>
            <x-gc-field name="email" label="Email" hint="Этот адрес будет вашим логином."><input id="email" type="email" name="email" class="form-control" value="{{ old('email') }}" autocomplete="username" inputmode="email" autocapitalize="none" spellcheck="false" maxlength="255" required></x-gc-field>
            <x-gc-password-field label="Пароль" :hint="$errors->any() ? 'Введите пароль ещё раз: после отправки формы он не сохраняется.' : 'От 6 до 72 символов.'" />
            <x-gc-password-field name="password_confirmation" id="password-confirm" label="Повторите пароль" confirm="password" />
        </div>
    </fieldset>
    <fieldset data-registration-step aria-labelledby="registration-profile-title">
        <legend id="registration-profile-title" class="gc-section-title">Профиль участника</legend>
        <div class="gc-form-stack">
            <x-gc-field name="birthday" label="Дата рождения"><input id="birthday" type="date" name="birthday" class="form-control" value="{{ old('birthday') }}" autocomplete="bday" required></x-gc-field>
            <x-gc-field name="gender" label="Пол персонажа" hint="Определяет персонажа в комнате профиля."><select id="gender" name="gender" class="form-select" required><option value="">Выберите</option>@foreach(\App\User::learningAvatarGenders() as $key=>$label)<option value="{{ $key }}" @selected(old('gender')===$key)>{{ $label }}</option>@endforeach</select></x-gc-field>
            <x-gc-field name="school" label="Место учёбы"><input id="school" name="school" class="form-control" value="{{ old('school') }}" maxlength="255" placeholder="Например, школа № 1576" required></x-gc-field>
            <x-gc-field name="grade" label="Класс"><select id="grade" name="grade" class="form-select" required><option value="">Выберите класс</option>@for($grade=1;$grade<=11;$grade++)<option value="{{ $grade }}" @selected((string)old('grade')===(string)$grade)>{{ $grade }}</option>@endfor<option value="12" @selected((string)old('grade')==='12')>Выпускник</option></select></x-gc-field>
        </div>
        <div class="gc-auth-optional">
            <h2 class="gc-section-title">О себе и контакты</h2>
            <p class="gc-help-text">Необязательно. Можно заполнить позже в личных настройках.</p>
            <div class="gc-form-stack">
                <x-gc-field name="interests" label="Технические интересы"><textarea id="interests" name="interests" class="form-control" rows="2" maxlength="10000">{{ old('interests') }}</textarea></x-gc-field>
                <x-gc-field name="hobbies" label="Увлечения"><textarea id="hobbies" name="hobbies" class="form-control" rows="2" maxlength="10000">{{ old('hobbies') }}</textarea></x-gc-field>
                <x-gc-field name="image" label="Аватар" hint="Изображение до 10 МБ."><input id="image" name="image" type="file" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp,image/bmp"></x-gc-field>
                <x-gc-field name="telegram" label="Telegram" hint="Контакт для других участников. Уведомления подключаются отдельно в настройках."><input id="telegram" name="telegram" class="form-control" value="{{ old('telegram') }}" maxlength="255" placeholder="@username" autocapitalize="none" spellcheck="false"></x-gc-field>
                <x-gc-field name="git" label="Git-профиль"><input id="git" name="git" class="form-control" value="{{ old('git') }}" maxlength="255" placeholder="github.com/username" autocapitalize="none" spellcheck="false"></x-gc-field>
            </div>
        </div>
        @include('captcha')
    </fieldset>
    <div class="gc-auth-actions">
        <button type="button" class="btn btn-outline-secondary" data-registration-back hidden>Назад</button>
        <button type="button" class="btn btn-primary ms-auto" data-registration-next hidden>Далее</button>
        <button type="submit" class="btn btn-primary ms-auto">Создать аккаунт</button>
    </div>
    <p class="gc-auth-footer gc-help-text">Уже есть аккаунт? <a href="{{ url('/login') }}">Войти</a></p>
</form>
@endsection
