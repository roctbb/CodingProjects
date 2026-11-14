@extends('layouts.left-menu')
@section('title', 'Создание главы')
@section('content')
@php
    $chapter = null;
@endphp
<x-gc-authoring-page title="Создание главы" :description="$course->name" :back="url('/insider/courses/'.$course->id)" back-label="К курсу" form="chapter-create-form" submit="Создать главу">
    <form id="chapter-create-form" method="POST" enctype="multipart/form-data" class="gc-card gc-form-sheet" data-authoring-form>
        @csrf
@include('courses.partials.chapter-form')
    </form>
</x-gc-authoring-page>
@endsection
