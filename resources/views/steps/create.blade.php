@extends('layouts.left-menu')
@section('title', 'Создание ступени')
@section('head') @include('layouts.partials.mathjax') @endsection
@section('content')
@php
    $step = null;
@endphp
<x-gc-authoring-page title="Создание ступени" :description="$lesson->name" :back="url('/insider/courses/'.$course->id.'?chapter='.$lesson->chapter_id)" back-label="К курсу" form="step-create-form" submit="Создать ступень">
    <form id="step-create-form" method="POST" enctype="multipart/form-data" class="gc-card gc-form-sheet" data-authoring-form>
        @csrf
@include('steps.partials.form')
    </form>
</x-gc-authoring-page>
@endsection
@push('editor')
<script src="{{ asset('build/js/vendor/easymde.min.js') }}"></script>
<script src="{{ asset('build/js/easymde-bridge.js') }}"></script>
@endpush
