@if($studyOutcome)
    <section class="gc-card gc-course-outcome" aria-label="Результат обучения">
        <div><h2>{{ $studyOutcome['title'] }}</h2><p>{{ $studyOutcome['text'] }}</p></div>
        @if($studyOutcome['url'])<a class="btn btn-primary btn-sm" href="{{ $studyOutcome['url'] }}" data-course-next-action>{{ $studyOutcome['action'] }} <i class="fas fa-arrow-right" aria-hidden="true"></i></a>@endif
    </section>
@endif
