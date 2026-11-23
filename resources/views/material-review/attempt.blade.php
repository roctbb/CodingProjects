@extends('layouts.app')
@section('title','Условие на момент сдачи')
@section('head')@include('layouts.partials.mathjax')@endsection
@section('content')
<h1 class="h3">Условие на момент сдачи</h1>
<p>Работа №{{ $work->id }} · {{ $work->submitted }}</p>
@if(!$snapshot || !empty($snapshot['unknown']))
<p class="alert alert-info">Версия условия этой исторической работы неизвестна.</p>
@else
<h2 class="h4">{{ $snapshot['fields']['name'] ?? '' }}</h2>
<div class="markdown">{!! \App\MaterialReview\Rendering::versioned(parsedown_math($snapshot['fields']['text'] ?? ''),[$snapshot]) !!}</div>
@if($manager)<details><summary>Авторское решение</summary><div class="markdown">{!! \App\MaterialReview\Rendering::versioned(parsedown_math($snapshot['fields']['solution'] ?? ''),[$snapshot]) !!}</div></details>@endif
@endif
<h2 class="h4 mt-4">Отправленная работа</h2><div class="markdown">{!! parsedown_math($work->text ?? '') !!}</div>
<p>Баллы: {{ $work->mark ?? 'Ожидает проверки' }}</p>
@endsection
