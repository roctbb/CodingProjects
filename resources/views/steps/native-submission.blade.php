@extends('layouts.left-menu')
@section('title', 'Решение: '.$task->name)
@section('content')
<x-gc-page-header :title="$task->name" :description="$course->name">
    <x-slot:breadcrumb><a href="{{ url('/insider/courses/'.$course->id.'/steps/'.$task->step_id.'#task'.$task->id) }}">← К задаче</a></x-slot:breadcrumb>
</x-gc-page-header>
@include('steps.partials.native-workspace', ['nativeTeacher' => app(\App\Services\CodeChecking\Access::class)->teacher(Auth::user(), $course->id), 'nativeUser' => $submission->user_id, 'nativeSubmission' => $submission->id])
@endsection
