@extends('layouts.games', ['page' => 2])

@section('heading', 'Редактировать')

@section('content')

<form action="/insider/games/{{$game->id}}" method="POST" onsubmit="save()">
    @csrf
    
    <style scoped>
        h4
        {
            margin: 20px 0;
        }
    </style>
    @if ($errors->any())
        <div class="alert alert-danger">
            <div>
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        </div>
    @endif
    <div style="margin-bottom: 5px;">
        <h4><label for="title">Название:</label></h4>
        <input class="form-control col" type="text" name="title" id="title" value="{{$game->title}}">
    </div>
    <div>
        <h4><label for="title">Описание:</label></h4>
        <textarea class="form-control" rows="10" type="text" name="description" id="text">{{$game->description}}</textarea>
    </div>

    <div class="row" style="padding: 10px;">
        <input type="submit" name="submit" id="submit" value="Сохранить" class="col btn btn-primary">
    </div>
</form>

<div class="row">
    <div class="row-sm col">
        <h5 class="col-sm-1">engine.getMousePosition()</h5>
        <p class="col-sm-11 text-secondary">Возвращает положение мыши: x - положение по X, y - положение по Y</p>
        <p class="col-sm-11 text-secondary">Пример: <code>engine.getMousePosition().x</code> вернет положение по X относительно хоста </p>
    </div>
    <div class="row-sm col">
        <h5 class="col-sm-1">engine.mouseButtons</h5>
        <p class="col-sm-11 text-secondary">
            Возвращает текущий статус кнопок мыши (нажата, не нажата): [0] - левая кнопка, [1] - средняя кнопка (колесо), [2] - правая кнопка
        </p>
        <p class="col-sm-11 text-secondary">Пример: <code>engine.mouseButtons[0]</code> вернет нажата левая кнопка или нет</p>
    </div>
    <div class="row-sm col">
        <h5 class="col-sm-1">engine.keys</h5>
        <p class="col-sm-11 text-secondary">
            Возвращает текущий статус кнопок клавиатуры (нажата, не нажата), доступ через <code>engine.keys[key_code]</code> где <code>key_code</code> это код клавишы  
        </p>
        <p class="col-sm-11 text-secondary">Пример: <code>engine.keys['a']</code> вернет нажата ли клавиша <code>a</code></p>
    </div>
</div>
<div class="row-sm col" style="margin-bottom: 300px;">
    <h5 class="col-sm">Изучение JavaScript canvas</h5>
    <p class="col-sm text-secondary">
        Javascript canvas это технология которая используется для рисования в браузере 
    </p>
    <p class="col-sm text-secondary">Начните здесь: <a href="https://developer.mozilla.org/ru/docs/Web/API/Canvas_API">https://developer.mozilla.org/ru/docs/Web/API/Canvas_API</a> в этих примерах вместо <code>ctx</code> используется <code>context</code>, <br> для <code>canvas</code> используется <code>canvas</code></p>
</div>


<script>
        var simplemde_text = new EasyMDE({
            spellChecker: false,
            autosave: true,
            element: document.getElementById("text")
        });
        </script>
@endsection
