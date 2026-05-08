@extends('layouts.left-menu')

@section('title')
    {{$student->name}}: {{$task->name}}
@endsection

@section('content')
    <div class="solution-review-page">
        <div class="solution-review-header gc-card mb-3">
            <div class="min-width-0">
                <a class="assessment-back-link" href="{{ url('/insider/courses/'.$course->id) }}"><i class="icon ion-chevron-left"></i> К курсу</a>
                <h2 class="mb-1 text-truncate">Проверка решения</h2>
                <p class="mb-0 text-muted text-truncate">{{ $student->name }} · {{ $task->name }}</p>
            </div>
            <div class="solution-review-actions">
                <a class="btn btn-outline-primary btn-sm" href="{{ url('/insider/courses/'.$course->id.'/assessments') }}">Журнал</a>
                <a class="btn btn-success btn-sm" href="{{ url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/edit') }}">Редактировать</a>
            @if ($course->teachers->contains(Auth::user()) || Auth::user()->role=='admin')
                @php $isBlocked = $task->isBlocked($student->id, $course->id); @endphp
                @if ($isBlocked)
                    <a class="btn btn-warning btn-sm"
                       href="{{ url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/unblock/'.$student->id) }}"
                       data-confirm="Разблокировать задачу для этого ученика?">Разблокировать</a>
                @else
                    <a class="btn btn-outline-danger btn-sm"
                       href="{{ url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/block/'.$student->id) }}"
                       data-confirm="Заблокировать задачу для этого ученика? Все предыдущие баллы будут обнулены.">Заблокировать</a>
                @endif
            @endif
            </div>
        </div>

        <div class="gc-card solution-task-card mb-3">
            <div class="card-header d-flex align-items-center justify-content-between gap-2">
                <strong class="text-truncate">{{ $task->name }}</strong>
                <span class="badge bg-secondary text-nowrap">{{ $task->max_mark }} XP</span>
            </div>
            <div class="card-body markdown">
                {!! parsedown_math($task->text) !!}
            </div>
        </div>

        @forelse ($solutions as $key => $solution)
            <div class="gc-card solution-review-card mb-3" id="solution-{{ $solution->id }}">
                <div class="card-header solution-review-card__header">
                    <div>
                        <strong>Решение #{{ $solutions->count() - $key }}</strong>
                        <span class="text-muted small d-block">{{ $solution->submitted->format('d.m.Y H:i') }}</span>
                    </div>
                    @if ($solution->mark!=null)
                        <span class="badge bg-primary">{{ $solution->mark }} / {{ $task->max_mark }} XP</span>
                    @else
                        <span class="badge bg-warning text-dark">На проверке</span>
                    @endif
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-lg-8">
                            <div class="solution-answer" data-linkify>
                                {!! nl2br(e(str_replace("\t", '&nbsp;&nbsp;&nbsp;&nbsp;', str_replace(' ', '&nbsp;', $solution->text)), false))!!}
                            </div>

                            @if ($solution->mark!=null)
                                <div class="solution-feedback mt-3">
                                    <div class="small text-muted mb-1">Проверено: {{ $solution->checked }}@if($solution->teacher), {{ $solution->teacher->name }}@endif</div>
                                    <div class="small" data-linkify>{!! nl2br(e(str_replace("\t", '&nbsp;&nbsp;&nbsp;&nbsp;', str_replace(' ', '&nbsp;', $solution->comment)), false))!!}</div>
                                </div>
                            @endif
                        </div>
                        <div class="col-lg-4">
                            <form class="solution-grade-form" method="post" action="{{ url('insider/courses/'.$solution->course_id.'/solution/'.$solution->id) }}">
                                {{ csrf_field() }}
                                <div class="mb-3">
                                    <label for="mark-{{ $solution->id }}" class="form-label">Очков опыта</label>
                                    <input type="number" class="form-control form-control-sm" id="mark-{{ $solution->id }}" name="mark" min="0" max="{{ $task->max_mark }}" placeholder="0-{{ $task->max_mark }}">
                                    @if ($errors->has('mark'))
                                        <span class="text-danger d-block"><strong>{{ $errors->first('mark') }}</strong></span>
                                    @endif
                                </div>
                                <div class="mb-3">
                                    <label for="comment-{{ $solution->id }}" class="form-label">Комментарий</label>
                                    <textarea class="form-control" id="comment-{{ $solution->id }}" name="comment" rows="5" placeholder="Что получилось, что поправить"></textarea>
                                </div>
                                <button type="submit" class="btn btn-success btn-sm w-100">Оценить</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="courses-empty-state gc-card">
                <div class="courses-empty-state__icon"><i class="fas fa-inbox"></i></div>
                <h5>Решений пока нет</h5>
                <p>Когда ученик отправит работу, она появится здесь.</p>
            </div>
        @endforelse
    </div>
@endsection
