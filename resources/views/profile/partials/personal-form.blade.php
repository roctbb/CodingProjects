<form method="POST" enctype="multipart/form-data" action="{{ url('/insider/profile/'.$user->id.'/edit') }}" id="profile-edit-form" class="gc-card gc-form-sheet" data-profile-form>
    @csrf
    <x-gc-form-section title="Имя и аватар" description="Эти данные видны другим участникам в сообществе.">
        <div class="gc-form-photo" data-profile-photo>
            <div data-profile-image-preview><x-gc-avatar :user="$user" size="lg" alt="Ваш аватар" /></div>
            <div class="gc-profile-photo-field">
                <x-gc-field name="image" label="Аватар" hint="JPG, PNG, GIF или WebP, до 10 МБ."><input id="image" type="file" name="image" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp,image/bmp" data-profile-image></x-gc-field>
                <button type="button" class="btn btn-link btn-sm px-0" data-profile-image-clear hidden>Отменить выбор</button>
            </div>
        </div>
        <x-gc-field name="name" label="Имя и фамилия"><input id="name" name="name" class="form-control" value="{{ old('name', $user->name) }}" maxlength="255" autocomplete="name" required></x-gc-field>
    </x-gc-form-section>
    <x-gc-form-section title="Учёба">
        <x-gc-field name="school" label="Место учёбы"><input id="school" name="school" class="form-control" value="{{ old('school', $user->school) }}" maxlength="255" required></x-gc-field>
        <x-gc-field name="grade" label="Класс"><select id="grade" name="grade" class="form-select" required><option value="" @selected($gradeValue==='')>Выберите класс</option>@for($grade=1;$grade<=11;$grade++)<option value="{{ $grade }}" @selected((string)$gradeValue===(string)$grade)>{{ $grade }}</option>@endfor<option value="12" @selected((string)$gradeValue==='12')>Выпускник</option></select></x-gc-field>
    </x-gc-form-section>
    <x-gc-form-section title="Контакты" description="Контакты будут доступны другим участникам платформы.">
        <x-gc-field name="telegram" label="Telegram" hint="Контакт в профиле. Подключение бота — в разделе «Аккаунт»."><input id="telegram" name="telegram" class="form-control" value="{{ old('telegram', $user->telegram) }}" maxlength="255" placeholder="@username" autocapitalize="none" spellcheck="false"></x-gc-field>
        <x-gc-field name="git" label="Git-профиль"><input id="git" name="git" class="form-control" value="{{ old('git', $user->git) }}" maxlength="255" placeholder="github.com/username" autocapitalize="none" spellcheck="false"></x-gc-field>
    </x-gc-form-section>
    <x-gc-form-section title="О себе" description="Можно заполнить позже или оставить пустым.">
        <x-gc-field name="interests" label="Технические интересы"><textarea id="interests" name="interests" class="form-control" rows="3" maxlength="10000">{{ old('interests', $user->interests) }}</textarea></x-gc-field>
        <x-gc-field name="hobbies" label="Увлечения"><textarea id="hobbies" name="hobbies" class="form-control" rows="3" maxlength="10000">{{ old('hobbies', $user->hobbies) }}</textarea></x-gc-field>
    </x-gc-form-section>
    <x-gc-form-section title="День рождения">
        @if($canEditBirthday)
            <x-gc-field name="birthday" label="Дата рождения"><input id="birthday" type="date" name="birthday" class="form-control" value="{{ $birthdayValue }}" autocomplete="bday" required></x-gc-field>
        @else
            <div><p class="gc-meta-label mb-1">Дата рождения</p><p class="mb-1">{{ $user->birthday?->format('d.m.Y') ?: 'Не указана' }}</p><p class="gc-help-text mb-0">Для исправления даты обратитесь к преподавателю.</p></div>
        @endif
        <x-gc-toggle name="birthday_hidden" label="Скрыть дату рождения" value="1" :checked="$user->birthday_hidden" hint="Дата не будет видна в профиле и списке ближайших дней рождения." />
    </x-gc-form-section>
    <x-gc-form-section title="Персонаж">
        @if($guest->role==='admin')
            <x-gc-field name="gender" label="Пол персонажа"><select id="gender" name="gender" class="form-select" required>@foreach(\App\User::learningAvatarGenders() as $key=>$label)<option value="{{ $key }}" @selected($genderValue===$key)>{{ $label }}</option>@endforeach</select></x-gc-field>
        @else
            <p class="gc-help-text mb-0">Пол персонажа: <span class="text-body">{{ $user->genderLabel() }}</span></p>
        @endif
        @if($isOwner)<p class="gc-help-text mb-0">Внешность и предметы комнаты настраиваются <a href="{{ url('/insider/profile/'.$user->id.'#room-settings') }}">в профиле</a>.</p>@endif
    </x-gc-form-section>
    @if(in_array($guest->role,['teacher','admin'],true))
        <x-gc-form-section title="Для преподавателя" description="Комментарий не виден ученикам.">
            <x-gc-field name="comments" label="Комментарий"><textarea id="comments" name="comments" class="form-control" rows="3" maxlength="10000">{{ old('comments', $user->comments) }}</textarea></x-gc-field>
            @if($guest->role==='admin')
                <x-gc-field name="role" label="Роль"><select id="role" name="role" class="form-select" required>@foreach(\App\User::roleLabels() as $key=>$label)<option value="{{ $key }}" @selected(old('role',$user->role)===$key)>{{ $label }}</option>@endforeach</select></x-gc-field>
                @if($user->oidc_subject)<p class="gc-help-text mb-0">При следующем входе через ЛК роль синхронизируется; роль admin из ЛК станет teacher.</p>@endif
            @endif
        </x-gc-form-section>
    @endif
</form>
