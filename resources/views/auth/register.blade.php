@extends('layouts.empty-dark')

@section('title', 'Регистрация')

@section('auth-background-image', url('/images/bg/'.random_int(1,16).'.jpg'))

@section('content')
    <div class="text-center text-white mb-3">
        <a href="{{ url('/') }}" class="auth-brand d-inline-flex align-items-center gap-2 text-decoration-none text-white">
            <img src="{{ url('images/icons/icons8-idea-64.png') }}" width="28" height="28" alt="">
            <span class="fs-5 fw-semibold">{{ config('app.name', 'Laravel') }}</span>
        </a>
    </div>

    <form method="POST" enctype="multipart/form-data" class="auth-card auth-card--wide p-0 overflow-hidden">
        @csrf

        <div class="gc-section-header gc-section-header--responsive">
            <div class="d-flex align-items-center gap-3 min-width-0">
                <span class="gc-icon-tile flex-shrink-0"><i class="fas fa-user-plus"></i></span>
                <div class="min-width-0">
                    <span class="gc-eyebrow">аккаунт</span>
                    <h1 class="h4 fw-bold mb-0">Регистрация</h1>
                </div>
            </div>
            <a href="{{ url('/login') }}" class="btn btn-outline-secondary rounded-3 fw-semibold flex-shrink-0">Уже есть аккаунт</a>
        </div>

        <div class="p-3 p-md-4">
            @if (!$to_course)
                <h5 class="auth-section-title">Инвайт</h5>
                <div class="mb-3">
                    <label for="invite" class="form-label">Инвайт</label>
                    <input id="invite" type="text" class="form-control rounded-3" name="invite" value="{{ old('invite') }}">
                    <div class="form-text">Если вы получили инвайт преподавателя, укажите его.</div>
                    @error('invite') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>
            @endif

            <h5 class="auth-section-title">Аккаунт</h5>
            <div class="row g-3 mb-3">
                <div class="col-12">
                    <label for="email" class="form-label">E-Mail</label>
                    <input id="email" type="email" class="form-control rounded-3" name="email" value="{{ old('email') }}" required>
                    <div class="form-text">Ваш действующий Email — он будет логином.</div>
                    @error('email') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>
                <div class="col-12 col-sm-6">
                    <label for="password" class="form-label">Пароль</label>
                    <input id="password" type="password" class="form-control rounded-3" name="password" required>
                    @error('password') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>
                <div class="col-12 col-sm-6">
                    <label for="password-confirm" class="form-label">Повторите пароль</label>
                    <input id="password-confirm" type="password" class="form-control rounded-3" name="password_confirmation" required>
                </div>
            </div>

            <h5 class="auth-section-title">Профиль</h5>
            <div class="row g-3 mb-3">
                <div class="col-12">
                    <label for="name" class="form-label">Имя и фамилия</label>
                    <input id="name" type="text" class="form-control rounded-3" name="name" value="{{ old('name') }}" required>
                    @error('name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>
                <div class="col-12 col-sm-6">
                    <label for="birthday" class="form-label">Дата рождения</label>
                    <input id="birthday" type="text" class="form-control rounded-3 date" name="birthday" value="{{ old('birthday') }}" required>
                    @error('birthday') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>
                <div class="col-12 col-sm-6">
                    <label for="gender" class="form-label">Пол персонажа</label>
                    <select id="gender" class="form-select rounded-3" name="gender" required>
                        <option value="" @selected(!old('gender'))>Выберите</option>
                        @foreach(\App\User::learningAvatarGenders() as $genderKey => $genderLabel)
                            <option value="{{ $genderKey }}" @selected(old('gender') === $genderKey)>{{ $genderLabel }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">По нему будет выбран персонаж в комнате профиля.</div>
                    @error('gender') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>
                <div class="col-12 col-sm-6">
                    <label for="school" class="form-label">Место учебы</label>
                    <input id="school" type="text" class="form-control rounded-3" name="school" value="{{ old('school') }}" required>
                    <div class="form-text">Например, «Гимназия 1576»</div>
                    @error('school') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>
                <div class="col-12 col-sm-6">
                    <label for="grade" class="form-label">Класс</label>
                    <select id="grade" class="form-select rounded-3" name="grade" required>
                        <option value="" @selected(old('grade') === null)>Выберите класс</option>
                        @for($grade = 1; $grade <= 11; $grade++)
                            <option value="{{ $grade }}" @selected((string) old('grade') === (string) $grade)>{{ $grade }}</option>
                        @endfor
                        <option value="12" @selected((string) old('grade') === '12')>Выпускник</option>
                    </select>
                    @error('grade') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>
            </div>

            <h5 class="auth-section-title">О себе</h5>
            <div class="row g-3 mb-3">
                <div class="col-12">
                    <label for="interests" class="form-label">Технические интересы</label>
                    <textarea id="interests" class="form-control rounded-3" name="interests" rows="2">{{ old('interests') }}</textarea>
                    @error('interests') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>
                <div class="col-12">
                    <label for="hobbies" class="form-label">Увлечения</label>
                    <textarea id="hobbies" class="form-control rounded-3" name="hobbies" rows="2">{{ old('hobbies') }}</textarea>
                    @error('hobbies') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>
                <div class="col-12">
                    <label for="image" class="form-label">Аватар</label>
                    <input id="image" type="file" class="form-control rounded-3" name="image">
                    @error('image') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>
            </div>

            <h5 class="auth-section-title">Контакты</h5>
            <p class="form-text mb-3">Эти данные увидят только другие студенты школы. Заполнять не обязательно.</p>
            <div class="row g-3">
                <div class="col-12 col-sm-6">
                    <label for="telegram" class="form-label">Telegram</label>
                    <input id="telegram" type="text" class="form-control rounded-3" name="telegram" value="{{ old('telegram') }}">
                    @error('telegram') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>
                <div class="col-12 col-sm-6">
                    <label for="git" class="form-label">Git</label>
                    <input id="git" type="text" class="form-control rounded-3" name="git" value="{{ old('git') }}">
                    @error('git') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>

        <div class="px-3 px-md-4 pb-3 pb-md-4">
            @include('captcha')
        </div>

        <div class="bg-body-tertiary border-top p-3 p-md-4">
            <button type="submit" class="btn btn-success rounded-3 fw-semibold w-100 py-2">Зарегистрироваться</button>
        </div>
    </form>
@endsection
