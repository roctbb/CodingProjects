@extends('layouts.left-menu')
@section('title', 'Редактирование главы')
@section('content')
<x-gc-authoring-page title="Редактирование главы" :description="$course->name" :back="url('/insider/courses/'.$course->id)" back-label="К курсу" form="chapter-edit-form" submit="Сохранить главу">
    <form id="chapter-edit-form" method="POST" enctype="multipart/form-data" class="gc-card gc-form-sheet" data-authoring-form>
        @csrf
@include('courses.partials.chapter-form')
    </form>
</x-gc-authoring-page>
@endsection
