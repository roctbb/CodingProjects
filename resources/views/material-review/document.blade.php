@foreach($documents as $document)
<article class="gc-card mr-lesson" id="{{ $prefix??'' }}lesson-{{ $document['id'] }}">
    @if($showLessonTitle??true)<h2 class="h4">{{ $document['fields']['name'] }}</h2>@endif
    @include('material-review.field',['entity'=>'lesson','entityId'=>$document['id'],'field'=>'description','label'=>'Описание урока','value'=>$document['fields']['description']??'','nodeFields'=>$document['fields']])
    @foreach($document['steps'] as $step)
    <section class="mr-step {{ $step['archived']?'mr-archived':'' }}" id="{{ $prefix??'' }}step-{{ $step['id'] }}">
        <h2 class="h4">{{ $loop->iteration }}. {{ $step['fields']['name'] }} @if($step['archived'])<span class="badge text-bg-secondary">Исключена из маршрута</span>@endif</h2>
        @include('material-review.field',['entity'=>'step','entityId'=>$step['id'],'field'=>'theory','label'=>'Материал','value'=>$step['fields']['theory']??'','nodeFields'=>$step['fields']])
        @if(!empty($step['fields']['notes']) || $draft)
        <details class="mr-teacher-notes"><summary>Заметки преподавателя</summary>@include('material-review.field',['entity'=>'step','entityId'=>$step['id'],'field'=>'notes','label'=>'Заметки','value'=>$step['fields']['notes']??'','nodeFields'=>$step['fields']])</details>
        @endif
        <div class="mr-tasks" id="{{ $prefix??'' }}tasks-{{ $step['id'] }}"><h3 class="h5">Задачи · {{ count($step['tasks']) }}</h3>
        @forelse($step['tasks'] as $task)
            <article class="mr-task" id="{{ $prefix??'' }}task-{{ $task['id'] }}">
                <h3 class="h5">{{ $task['fields']['name'] }} <small class="text-muted">{{ $task['fields']['max_mark']??0 }} баллов @if(!empty($task['fields']['is_hidden'])) · скрыта @endif</small></h3>
                @include('material-review.field',['entity'=>'task','entityId'=>$task['id'],'field'=>'text','label'=>'Условие','value'=>$task['fields']['text']??'','nodeFields'=>$task['fields']])
                <details><summary>Авторское решение</summary>@include('material-review.field',['entity'=>'task','entityId'=>$task['id'],'field'=>'solution','label'=>'Решение','value'=>$task['fields']['solution']??'','nodeFields'=>$task['fields']])</details>
            </article>
        @empty<p class="text-muted">В этой ступени нет отдельных задач.</p>@endforelse
        </div>
    </section>
    @endforeach
</article>
@endforeach
