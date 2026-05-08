@extends('layouts.empty-dark')

@section('title', 'Регистрация')

@section('auth-background-image', url('/images/bg/'.random_int(1,16).'.jpg'))

@section('head')
<style>
    .register-card {
        backdrop-filter: blur(12px);
        background: rgba(255,255,255,0.97);
        border: 1px solid rgba(255,255,255,0.2);
        border-radius: 0.85rem;
        box-shadow: 0 10px 15px -3px rgba(0,0,0,0.08);
        max-width: 800px;
        margin: 0 auto;
        padding: 1.5rem;
    }
    .register-grid {
        display: grid;
        gap: 0.6rem 1rem;
        grid-template-columns: repeat(2, 1fr);
    }
    .register-grid .full-span { grid-column: 1 / -1; }
    @media (max-width: 575px) {
        .register-grid { grid-template-columns: 1fr; }
        .register-grid .full-span { grid-column: auto; }
        .register-card { padding: 1rem; }
    }
</style>
@endsection

@section('content')
    <div class="text-center mb-3">
        <a href="{{ url('/') }}" class="d-inline-flex align-items-center gap-2 text-decoration-none text-white">
            <img src="{{ url('images/icons/icons8-idea-64.png') }}" width="28" height="28" alt="">
            <span class="fs-5 fw-medium">{{ config('app.name', 'Laravel') }}</span>
        </a>
        <h1 class="text-white fs-4 fw-normal mt-1">Регистрация</h1>
    </div>

    <form method="POST" enctype="multipart/form-data" class="register-card">
        @csrf

        @if (!$to_course)
            <h5 class="fw-bold text-primary mb-3 border-start border-3 border-success ps-2">Инвайт</h5>
            <div class="mb-3">
                <label for="invite" class="form-label">Инвайт</label>
                <input id="invite" type="text" class="form-control" name="invite" value="{{ old('invite') }}">
                <div class="form-text">Если вы получили инвайт преподавателя, укажите его.</div>
                @error('invite') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
        @endif

        <h5 class="fw-bold text-primary mb-3 border-start border-3 border-success ps-2">Аккаунт</h5>
        <div class="register-grid mb-3">
            <div class="full-span">
                <label for="email" class="form-label">E-Mail</label>
                <input id="email" type="email" class="form-control" name="email" value="{{ old('email') }}" required>
                <div class="form-text">Ваш действующий Email — он будет логином.</div>
                @error('email') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div>
                <label for="password" class="form-label">Пароль</label>
                <input id="password" type="password" class="form-control" name="password" required>
                @error('password') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div>
                <label for="password-confirm" class="form-label">Повторите пароль</label>
                <input id="password-confirm" type="password" class="form-control" name="password_confirmation" required>
            </div>
        </div>

        <h5 class="fw-bold text-primary mb-3 border-start border-3 border-success ps-2">Профиль</h5>
        <div class="register-grid mb-3">
            <div class="full-span">
                <label for="name" class="form-label">Имя и фамилия</label>
                <input id="name" type="text" class="form-control" name="name" value="{{ old('name') }}" required>
                @error('name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div>
                <label for="birthday" class="form-label">Дата рождения</label>
                <input id="birthday" type="text" class="form-control date" name="birthday" value="{{ old('birthday') }}" required>
                @error('birthday') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div>
                <label for="school" class="form-label">Место учебы</label>
                <input id="school" type="text" class="form-control" name="school" value="{{ old('school') }}" required>
                <div class="form-text">Например, «Гимназия 1576»</div>
                @error('school') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div>
                <label for="grade" class="form-label">Класс (число)</label>
                <input id="grade" type="number" class="form-control" name="grade" value="{{ old('grade') }}" required>
                @error('grade') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
        </div>

        <h5 class="fw-bold text-primary mb-3 border-start border-3 border-success ps-2">О себе</h5>
        <div class="register-grid mb-3">
            <div class="full-span">
                <label for="interests" class="form-label">Технические интересы</label>
                <textarea id="interests" class="form-control" name="interests" rows="2">{{ old('interests') }}</textarea>
                @error('interests') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="full-span">
                <label for="hobbies" class="form-label">Увлечения</label>
                <textarea id="hobbies" class="form-control" name="hobbies" rows="2">{{ old('hobbies') }}</textarea>
                @error('hobbies') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="full-span">
                <label for="image" class="form-label">Аватар</label>
                <input id="image" type="file" class="form-control" name="image">
                @error('image') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
        </div>

        <h5 class="fw-bold text-primary mb-3 border-start border-3 border-success ps-2">Контакты</h5>
        <p class="form-text mb-3">Эти данные увидят только другие студенты школы. Заполнять не обязательно.</p>
        <div class="register-grid mb-3">
            <div>
                <label for="telegram" class="form-label">Telegram</label>
                <input id="telegram" type="text" class="form-control" name="telegram" value="{{ old('telegram') }}">
                @error('telegram') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div>
                <label for="git" class="form-label">Git</label>
                <input id="git" type="text" class="form-control" name="git" value="{{ old('git') }}">
                @error('git') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="mb-3">
            @include('captcha')
        </div>

        <button type="submit" class="btn btn-success w-100">Регистрация</button>
    </form>
@endsection
