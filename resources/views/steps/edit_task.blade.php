@extends('layouts.left-menu')
@section('title', 'Редактирование задания')
@section('head') @include('layouts.partials.mathjax') @endsection
@section('content')
<x-gc-authoring-page title="Редактирование задания" :description="$course->name.' · '.$task->step->name" :back="url('/insider/courses/'.$course->id.'/steps/'.$task->step_id.'#task'.$task->id)" back-label="К заданию" form="task-edit-form" submit="Сохранить задание">
    <form id="task-edit-form" method="POST" class="gc-card gc-form-sheet" data-authoring-form>
        @csrf
        @include('steps.partials.task-form')
    </form>
</x-gc-authoring-page>
@endsection
@push('editor')
<script src="{{ asset('build/js/vendor/easymde.min.js') }}"></script>
<script src="{{ asset('build/js/easymde-bridge.js') }}"></script>
@endpush
