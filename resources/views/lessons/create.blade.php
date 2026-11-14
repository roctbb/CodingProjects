@extends('layouts.left-menu')
@section('title', 'Создание урока')
@section('head') @include('layouts.partials.mathjax') @endsection
@section('content')
@php
    $lesson = null;
@endphp
<x-gc-authoring-page title="Создание урока" :description="$course->name" :back="url('/insider/courses/'.$course->id)" back-label="К курсу" form="lesson-create-form" submit="Создать урок">
    <form id="lesson-create-form" method="POST" enctype="multipart/form-data" class="gc-card gc-form-sheet" data-authoring-form>
        @csrf
@include('lessons.partials.form')
    </form>
</x-gc-authoring-page>
@endsection
@push('editor')
<script src="{{ asset('build/js/vendor/easymde.min.js') }}"></script>
<script src="{{ asset('build/js/easymde-bridge.js') }}"></script>
@endpush
