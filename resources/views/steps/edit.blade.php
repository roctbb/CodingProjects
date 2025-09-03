@extends('layouts.left-menu')

@section('content')
    <div class="row">
        <div class="col s12">
            <h3>Изменение темы: "{{$step->name}}"</h3>
            <div class="card">
                <div class="card-body">
                    <form method="POST">
                        {{ csrf_field() }}
                        <div class="form-group">
                            <label for="name">Название</label>

                            @if (old('name')!="")
                                <input id="name" type="text" class="form-control" value="{{old('name')}}"
                                       name="name" required>
                            @else
                                <input id="name" type="text" class="form-control" value="{{$step->name}}"
                                       name="name" required>
                            @endif
                            @if ($errors->has('name'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('name') }}</strong>
                                    </span>
                            @endif
                        </div>
                        <div class="form-group">
                            <label for="theory" style="padding-bottom: 10px;">Теоретический материал</label>
                            <div class="mb-2">
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="improveText('theory', 'fix_typos')">
                                    <i class="icon ion-android-checkbox-outline"></i> Исправить опечатки
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-info" onclick="improveText('theory', 'improve_style')">
                                    <i class="icon ion-android-create"></i> Улучшить стиль
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-success" onclick="improveText('theory', 'both')">
                                    <i class="icon ion-android-star"></i> Исправить и улучшить
                                </button>
                            </div>
                            @if (old('theory')!="")
                                <textarea id="theory" class="form-control"
                                          name="theory">{{old('theory')}}</textarea>
                            @else
                                <textarea id="theory" class="form-control"
                                          name="theory">{{$step->theory}}</textarea>
                            @endif

                            @if ($errors->has('theory'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('theory') }}</strong>
                                    </span>
                            @endif
                        </div>
                        <div class="form-group">
                            <label for="notes" style="padding-bottom: 10px;">Комментарий для преподавателя</label>
                            <div class="mb-2">
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="improveText('notes', 'fix_typos')">
                                    <i class="icon ion-android-checkbox-outline"></i> Исправить опечатки
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-info" onclick="improveText('notes', 'improve_style')">
                                    <i class="icon ion-android-create"></i> Улучшить стиль
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-success" onclick="improveText('notes', 'both')">
                                    <i class="icon ion-android-star"></i> Исправить и улучшить
                                </button>
                            </div>
                            @if (old('notes')!="")
                                <textarea id="notes" class="form-control"
                                          name="notes">{{old('notes')}}</textarea>
                            @else
                                <textarea id="notes" class="form-control"
                                          name="notes">{{$step->notes}}</textarea>
                            @endif

                            @if ($errors->has('notes'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('notes') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input" name="notebook" value="yes"
                                       @if ($step->is_notebook) checked @endif>
                                Это тетрадка
                            </label>
                        </div>

                        <div class="form-group">
                            <label for="video_url">Видео</label>

                            @if (old('video_url')!="")
                                <input id="video_url" type="text" class="form-control" value="{{old('video_url')}}"
                                       name="video_url">
                            @else
                                <input id="video_url" type="text" class="form-control" value="{{$step->video_url}}"
                                       name="video_url">
                            @endif
                            @if ($errors->has('video_url'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('video_url') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <button type="submit" class="btn btn-success">Сохранить</button>
                    </form>
                </div>
            </div>
            <script>
                var simplemde_description = new EasyMDE({
                    spellChecker: false,
                    element: document.getElementById("description")
                });
                var simplemde_theory = new EasyMDE({
                    spellChecker: false,
                    element: document.getElementById("theory")
                });
                var simplemde_notes = new EasyMDE({
                    spellChecker: false,
                    element: document.getElementById("notes")
                });

                // YandexGPT text improvement functionality
                function improveText(fieldId, action) {
                    let editor;
                    if (fieldId === 'theory') {
                        editor = simplemde_theory;
                    } else if (fieldId === 'notes') {
                        editor = simplemde_notes;
                    } else if (fieldId === 'description') {
                        editor = simplemde_description;
                    }

                    const currentText = editor.value();

                    if (!currentText.trim()) {
                        alert('Поле пустое. Введите текст для улучшения.');
                        return;
                    }

                    // Show loading state
                    const buttons = document.querySelectorAll(`button[onclick*="${fieldId}"]`);
                    buttons.forEach(btn => {
                        btn.disabled = true;
                        btn.innerHTML = btn.innerHTML.replace(/Исправить|Улучшить/, 'Обработка...');
                    });

                    // Make API request
                    fetch('/insider/yandexgpt/improve-text', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            text: currentText,
                            action: action
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Show comparison modal or replace text directly
                            if (confirm('Текст был улучшен. Заменить оригинальный текст на улучшенную версию?')) {
                                editor.value(data.improved_text);
                            }
                        } else {
                            alert('Ошибка: ' + (data.error || 'Не удалось улучшить текст'));
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Произошла ошибка при обращении к сервису улучшения текста');
                    })
                    .finally(() => {
                        // Restore button states
                        buttons.forEach(btn => {
                            btn.disabled = false;
                            if (btn.innerHTML.includes('Обработка...')) {
                                if (action === 'fix_typos') {
                                    btn.innerHTML = '<i class="icon ion-android-checkbox-outline"></i> Исправить опечатки';
                                } else if (action === 'improve_style') {
                                    btn.innerHTML = '<i class="icon ion-android-create"></i> Улучшить стиль';
                                } else if (action === 'both') {
                                    btn.innerHTML = '<i class="icon ion-android-star"></i> Исправить и улучшить';
                                }
                            }
                        });
                    });
                }
            </script>
        </div>
    </div>
    </div>
    </div>
@endsection
