@extends('layouts.left-menu')
@section('title', 'Редактирование ступени')
@section('head') @include('layouts.partials.mathjax') @endsection
@section('content')
<x-gc-authoring-page title="Редактирование ступени" :description="$step->lesson->name" :back="url('/insider/courses/'.$course->id.'/steps/'.$step->id)" back-label="К ступени" form="step-edit-form" submit="Сохранить ступень">
    <form id="step-edit-form" method="POST" enctype="multipart/form-data" class="gc-card gc-form-sheet" data-authoring-form>
        @csrf
@include('steps.partials.form')
    </form>
</x-gc-authoring-page>
@endsection
@push('editor')
<script src="{{ asset('build/js/vendor/easymde.min.js') }}"></script>
<script src="{{ asset('build/js/easymde-bridge.js') }}"></script>
@endpush
