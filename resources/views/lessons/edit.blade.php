@extends('layouts.left-menu')
@section('title', 'Редактирование урока')
@section('head') @include('layouts.partials.mathjax') @endsection
@section('content')
<x-gc-authoring-page title="Редактирование урока" :description="$course->name" :back="url('/insider/courses/'.$course->id)" back-label="К курсу" form="lesson-edit-form" submit="Сохранить урок">
    <form id="lesson-edit-form" method="POST" enctype="multipart/form-data" class="gc-card gc-form-sheet" data-authoring-form>
        @csrf
@include('lessons.partials.form')
    </form>
<x-slot:after>@include('lessons.partials.early-access-form')</x-slot:after>
</x-gc-authoring-page>
@endsection
@push('editor')
<script src="{{ asset('build/js/vendor/easymde.min.js') }}"></script>
<script src="{{ asset('build/js/easymde-bridge.js') }}"></script>
@endpush
