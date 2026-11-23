@extends('layouts.app')
@section('title','Архив ступени')
@section('content')
<h1 class="h3">{{ $archivedStep->name }}</h1><p class="alert alert-info">Ступень исключена из текущего маршрута. Прежние работы сохранены.</p>
@if($manager)<div class="markdown">{!! parsedown_math($archivedStep->theory??'') !!}</div>@endif
<h2 class="h4">История работ</h2><ul>@foreach($works as $work)<li><a href="/insider/material-history/{{ $work->id }}">Работа №{{ $work->id }} · {{ $work->submitted }}</a></li>@endforeach</ul>
@endsection
