@extends('layouts.left-menu')

@section('title')
    Изменение задачи "{{$task->name}}"
@endsection

@section('content')

    <h2>Изменение задачи "{{$task->name}}"</h2>
    <div class="row" style="margin-top: 15px;">
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <form method="POST" class="form-horizontal" enctype="multipart/form-data">
                        {{ csrf_field() }}
                        <div class="form-group">
                            <label for="name">Название</label>

                            @if (old('name')!="")
                                <input id="name" type="text" class="form-control" name="name" value="{{old('name')}}"
                                       required>
                            @else
                                <input id="name" type="text" class="form-control" name="name" value="{{$task->name}}"
                                       required>
                            @endif
                            @if ($errors->has('name'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('name') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-group">
                            <label for="consequences" style="padding-bottom: 10px;">Подтверждаемые результаты из <sup>
                                    <small>Core</small>
                                </sup>:</label><br>
                            <select class="selectpicker  form-control" data-live-search="true" id="consequences"
                                    name="consequences[]" multiple data-width="auto">
                                @foreach (\App\CoreNode::where('is_root', false)->where('version', 1)->get() as $node)
                                    <option data-tokens="{{ $node->id }}" value="{{ $node->id }}"
                                            data-subtext="{{$node->getParentLine()}}">{{$node->title}}</option>
                                @endforeach
                            </select>

                            <script>
                                $('.selectpicker').selectpicker('val', [{{implode(',', $task->consequences->pluck('id')->toArray())}}]);
                            </script>
                        </div>

                        <div class="form-group{{ $errors->has('max_mark') ? ' has-error' : '' }}">
                            <label for="max_mark">Очков опыта</label>

                            @if (old('max_mark')!="")
                                <input type="text" name="max_mark" class="form-control" id="max_mark"
                                       value="{{old('name')}}"
                                       required/>
                            @else
                                <input type="text" name="max_mark" class="form-control" id="max_mark"
                                       value="{{$task->max_mark}}" required/>
                            @endif

                            @if ($errors->has('max_mark'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('max_mark') }}</strong>
                                    </span>
                            @endif

                        </div>

                        <div class="form-group">
                            <label for="text">Текст</label>
                            <div class="mb-2">
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="improveText(this, 'text', 'fix_typos')">
                                    <i class="icon ion-android-checkbox-outline"></i> Исправить опечатки
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-info" onclick="improveText(this, 'text', 'improve_style')">
                                    <i class="icon ion-android-create"></i> Улучшить стиль
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-success" onclick="improveText(this, 'text', 'both')">
                                    <i class="icon ion-android-star"></i> Исправить и улучшить
                                </button>
                            </div>
                            <textarea id="text" class="form-control"
                                      name="text">@if (old('text')!=""){{old('text')}}@else{{$task->text}}@endif</textarea>
                            @if ($errors->has('text'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('text') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-group">
                            <label for="solution">Решение</label>
                            <div class="mb-2">
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="improveText(this, 'solution', 'fix_typos')">
                                    <i class="icon ion-android-checkbox-outline"></i> Исправить опечатки
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-info" onclick="improveText(this, 'solution', 'improve_style')">
                                    <i class="icon ion-android-create"></i> Улучшить стиль
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-success" onclick="improveText(this, 'solution', 'both')">
                                    <i class="icon ion-android-star"></i> Исправить и улучшить
                                </button>
                            </div>
                            <textarea id="solution" class="form-control"
                                      name="solution">@if (old('solution')!=""){{old('solution')}}@else{{$task->solution}}@endif</textarea>
                            @if ($errors->has('solution'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('solution') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-group">
                            <label for="is_star">Дополнительное</label>
                            <input type="checkbox" id="is_star" name="is_star" value="on"
                                   @if ($task->is_star) checked @endif/>
                        </div>

                        <div class="form-group">
                            <label for="is_code">Автопроверка</label>
                            <input type="checkbox" id="is_code" name="is_code" value="on"
                                   @if ($task->is_code) checked @endif/>
                        </div>


                        <div class="form-group{{ $errors->has('price') ? ' has-error' : '' }}">
                            <label for="price" class="col-md-4">Премия</label>

                            <div class="col-md-12">

                                @if (old('price')!="")
                                    <input type="text" name="price" class="form-control" id="price"
                                           value="{{old('price')}}"/>
                                @else
                                    <input type="text" name="price" class="form-control" id="price"
                                           value="{{$task->price}}"/>
                                @endif

                                @if ($errors->has('price'))
                                    <span class="help-block error-block">
                                        <strong>{{ $errors->first('price') }}</strong>
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="form-group{{ $errors->has('answer') ? ' has-error' : '' }}">
                            <label for="answer" class="col-md-4">Ответ</label>

                            <div class="col-md-12">

                                @if (old('answer')!="")
                                    <input type="text" name="answer" class="form-control" id="answer"
                                           value="{{old('answer')}}"/>
                                @else
                                    <input type="text" name="answer" class="form-control" id="answer"
                                           value="{{$task->answer}}"/>
                                @endif

                                @if ($errors->has('answer'))
                                    <span class="help-block error-block">
                                        <strong>{{ $errors->first('answer') }}</strong>
                                    </span>
                                @endif
                            </div>
                        </div>


                        <button type="submit" class="btn btn-success">Сохранить</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        var simplemde_task = new EasyMDE({
            spellChecker: false,
            element: document.getElementById("text")
        });
        var simplemde_solution = new EasyMDE({
            spellChecker: false,
            element: document.getElementById("solution")
        });

        // YandexGPT text improvement functionality
        function improveText(clickedButton, fieldId, action) {
            const editor = fieldId === 'text' ? simplemde_task : simplemde_solution;
            const currentText = editor.value();

            if (!currentText.trim()) {
                alert('Поле пустое. Введите текст для улучшения.');
                return;
            }

            // Store original button text
            const originalButtonText = clickedButton.innerHTML;

            // Show loading state only for clicked button
            clickedButton.disabled = true;
            clickedButton.innerHTML = clickedButton.innerHTML.replace(/Исправить|Улучшить/, 'Обработка...');

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
                // Restore only the clicked button state
                clickedButton.disabled = false;
                clickedButton.innerHTML = originalButtonText;
            });
        }
    </script>
@endsection
