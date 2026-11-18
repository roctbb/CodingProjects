@extends('layouts.left-menu')
@section('title', 'Создание задания')
@section('head') @include('layouts.partials.mathjax') @endsection
@section('content')
@php
    $task = null;
@endphp
<x-gc-authoring-page title="Создание задания" :description="$course->name.' · '.$step->name" :back="url('/insider/courses/'.$course->id.'/steps/'.$step->id)" back-label="К ступени" form="task-create-form" submit="Создать задание">
    <form id="task-create-form" method="POST" action="{{ url('/insider/courses/'.$course->id.'/steps/'.$step->id.'/task') }}" class="gc-task-editor-form" data-authoring-form>
        @csrf
        @include('steps.partials.task-form')
    </form>
</x-gc-authoring-page>
@endsection
@push('editor')
<script src="{{ asset('build/js/vendor/easymde.min.js') }}"></script>
<script src="{{ asset('build/js/easymde-bridge.js') }}"></script>
@endpush
