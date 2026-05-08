@extends('layouts.left-menu')

@section('title')
    {{$course->name}}
@endsection

@section('content')
    <div class="management-page">
        <div class="management-header gc-card mb-3">
            <div class="min-width-0">
                <a class="assessment-back-link" href="{{ url('/insider/courses/'.$course->id) }}"><i class="icon ion-chevron-left"></i> К курсу</a>
                <h2 class="mb-1">Заблокированные задачи</h2>
                <p class="mb-0 text-muted text-truncate">{{ $course->name }}</p>
            </div>
            <span class="management-count">{{ $blocked->flatten(1)->unique('task_id')->count() }}</span>
        </div>

            @if ($blocked->isEmpty())
                <div class="courses-empty-state gc-card">
                    <div class="courses-empty-state__icon"><i class="fas fa-shield-alt"></i></div>
                    <h5>Блокировок нет</h5>
                    <p>Если задача будет заблокирована из-за плагиата или другой причины, она появится здесь.</p>
                </div>
            @else
                <div class="gc-card management-table-card">
                    <div class="table-responsive">
                    <table class="table table-hover mb-0 management-table">
                        <thead>
                        <tr>
                            <th>Студент</th>
                            <th>Задача</th>
                            <th>Причина</th>
                            <th>Дата</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($blocked as $user_id => $records)
                            @php
                                $student = $records->first()->user;
                                $tasks = $records->unique('task_id');
                            @endphp
                            @foreach ($tasks as $bt)
                                <tr>
                                    <td>
                                        <a href="{{ url('/insider/profile/'.$student->id) }}" target="_blank">{{ $student->name }}</a>
                                    </td>
                                    <td>
                                        <a target="_blank"
                                           href="{{ url('/insider/courses/'.$course->id.'/tasks/'.$bt->task_id.'/student/'.$student->id) }}">
                                            {{ optional($bt->task)->name ?? ('Задача #'.$bt->task_id) }}
                                        </a>
                                    </td>
                                    <td><span class="badge bg-danger">{{ $bt->reason ?? 'заблокировано' }}</span></td>
                                    <td>
                                        @if($bt->blocked_at)
                                            {{ $bt->blocked_at->format('d.m.Y H:i') }}
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        @endforeach
                        </tbody>
                    </table>
                    </div>
                </div>
            @endif
    </div>
@endsection
