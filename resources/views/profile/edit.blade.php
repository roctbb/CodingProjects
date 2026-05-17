@extends('layouts.left-menu')

@section('title', 'Профиль')

@section('content')
    @php
        $canEditBirthday = $guest->role == 'teacher' || $guest->role == 'admin';
        $birthdayValue = old('birthday', optional($user->birthday)->format('Y-m-d'));
        $gradeValue = old('grade', $user->grade_year ? $user->grade() : '');
        $genderValue = old('gender', $user->learningAvatarGenderKey());
    @endphp

    <form method="POST" enctype="multipart/form-data" class="container-xl px-0">
        {{ csrf_field() }}

        <div class="gc-card gc-page-header mb-4">
            <div class="min-width-0">
                <a class="assessment-back-link" href="{{ url('/insider/profile/'.$user->id) }}"><i class="icon ion-chevron-left"></i> К профилю</a>
                <h2 class="fw-bold lh-sm mb-1">Редактирование профиля</h2>
                <p class="text-muted mb-0">{{ $user->name }}</p>
            </div>
            <button type="submit" class="btn btn-success rounded-3 fw-semibold px-4 py-2 flex-shrink-0">
                <i class="fas fa-check me-1"></i>Сохранить
            </button>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="gc-card overflow-hidden mb-4">
                    <div class="d-flex align-items-center gap-2 gc-section-header">
                        <span class="gc-icon-tile flex-shrink-0"><i class="fas fa-id-card"></i></span>
                        <div class="min-width-0">
                            <span class="gc-eyebrow">основное</span>
                            <h5 class="mb-0 text-truncate">Данные профиля</h5>
                        </div>
                    </div>

                    <div class="p-3 p-md-4">
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="name" class="form-label">Имя</label>
                                <input id="name" type="text" class="form-control rounded-3" name="name" value="{{ old('name', $user->name) }}" required>
                                @error('name')
                                    <span class="text-danger small d-block mt-1"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>

                            @if ($canEditBirthday)
                                <div class="col-12 col-md-6">
                                    <label for="birthday" class="form-label">Дата рождения</label>
                                    <input id="birthday" type="text" class="form-control rounded-3 date" name="birthday" value="{{ $birthdayValue }}" required>
                                    @error('birthday')
                                        <span class="text-danger small d-block mt-1"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            @endif

                            @if ($guest->role == 'admin')
                                <div class="col-12 col-md-6">
                                    <label for="gender" class="form-label">Пол персонажа</label>
                                    <select id="gender" class="form-select rounded-3" name="gender" required>
                                        @foreach(\App\User::learningAvatarGenders() as $genderKey => $genderLabel)
                                            <option value="{{ $genderKey }}" @selected($genderValue === $genderKey)>{{ $genderLabel }}</option>
                                        @endforeach
                                    </select>
                                    <div class="form-text">Определяет, какой персонаж будет выбран в комнате профиля.</div>
                                    @error('gender')
                                        <span class="text-danger small d-block mt-1"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            @else
                                <div class="col-12 col-md-6">
                                    <label class="form-label">Пол персонажа</label>
                                    <div class="form-control rounded-3 bg-body-tertiary">{{ $user->genderLabel() }}</div>
                                </div>
                            @endif

                            <div class="col-12 col-md-{{ $canEditBirthday ? '6' : '8' }}">
                                <label for="school" class="form-label">Место учебы</label>
                                <input id="school" type="text" class="form-control rounded-3" name="school" value="{{ old('school', $user->school) }}" required>
                                @error('school')
                                    <span class="text-danger small d-block mt-1"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>

                            <div class="col-12 col-md-4">
                                <label for="grade" class="form-label">Класс</label>
                                <select id="grade" class="form-select rounded-3" name="grade" required>
                                    @for($grade = 1; $grade <= 11; $grade++)
                                        <option value="{{ $grade }}" @selected((string) $gradeValue === (string) $grade)>{{ $grade }}</option>
                                    @endfor
                                    <option value="12" @selected((string) $gradeValue === '12')>Выпускник</option>
                                </select>
                                @error('grade')
                                    <span class="text-danger small d-block mt-1"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="gc-card overflow-hidden mb-4">
                    <div class="d-flex align-items-center gap-2 gc-section-header">
                        <span class="gc-icon-tile flex-shrink-0"><i class="fas fa-code"></i></span>
                        <div class="min-width-0">
                            <span class="gc-eyebrow">о себе</span>
                            <h5 class="mb-0 text-truncate">Интересы и увлечения</h5>
                        </div>
                    </div>

                    <div class="p-3 p-md-4">
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="interests" class="form-label">Технические интересы</label>
                                <textarea id="interests" class="form-control rounded-3" name="interests" rows="4" required>{{ old('interests', $user->interests) }}</textarea>
                                @error('interests')
                                    <span class="text-danger small d-block mt-1"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>

                            <div class="col-12">
                                <label for="hobbies" class="form-label">Увлечения</label>
                                <textarea id="hobbies" class="form-control rounded-3" name="hobbies" rows="4" required>{{ old('hobbies', $user->hobbies) }}</textarea>
                                @error('hobbies')
                                    <span class="text-danger small d-block mt-1"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>

                            @if ($guest->role == 'teacher' || $guest->role == 'admin')
                                <div class="col-12">
                                    <label for="comments" class="form-label">Комментарий</label>
                                    <textarea id="comments" class="form-control rounded-3" name="comments" rows="3">{{ old('comments', $user->comments) }}</textarea>
                                    @error('comments')
                                        <span class="text-danger small d-block mt-1"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="gc-card overflow-hidden sticky-lg-top">
                    <div class="p-3 p-md-4 text-center border-bottom">
                        <x-gc-avatar :user="$user" size="xl" img-class="profile-avatar" class="mb-3 mx-auto" alt="" />
                        <h6 class="fw-bold mb-1">{{ $user->name }}</h6>
                        <div class="mb-1">
                            @include('profile.partials.custom_title_badge', ['profileUser' => $user, 'compact' => true])
                        </div>
                        <small class="text-muted">{{ $user->rank()->name }}</small>
                    </div>

                    <div class="p-3">
                        <div class="mb-3">
                            <label for="image" class="form-label">Аватар</label>
                            <input id="image" type="file" class="form-control rounded-3" name="image">
                            @error('image')
                                <span class="text-danger small d-block mt-1"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>

                        <div class="border-top pt-3 mb-3">
                            <div class="gc-eyebrow mb-2">Контакты</div>
                            <div class="d-flex flex-column gap-3">
                                <div>
                                    <label for="telegram" class="form-label">Telegram</label>
                                    <input id="telegram" type="text" class="form-control rounded-3" name="telegram" value="{{ old('telegram', $user->telegram) }}">
                                    @error('telegram')
                                        <span class="text-danger small d-block mt-1"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>

                                <div>
                                    <label for="git" class="form-label">Git</label>
                                    <input id="git" type="text" class="form-control rounded-3" name="git" value="{{ old('git', $user->git) }}">
                                    @error('git')
                                        <span class="text-danger small d-block mt-1"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="border-top pt-3">
                            <div class="gc-eyebrow mb-2">Доступ</div>
                            <div class="d-flex flex-column gap-3">
                                <div>
                                    <label for="password" class="form-label">Новый пароль</label>
                                    <input id="password" type="password" class="form-control rounded-3" name="password" autocomplete="new-password">
                                    @error('password')
                                        <span class="text-danger small d-block mt-1"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>

                                <div>
                                    <label for="password-confirm" class="form-label">Повторите пароль</label>
                                    <input id="password-confirm" type="password" class="form-control rounded-3" name="password_confirmation" autocomplete="new-password">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

@endsection
