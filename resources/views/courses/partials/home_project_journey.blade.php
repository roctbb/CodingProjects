@if($projectJourney ?? null)
    <section class="gc-card gc-project-journey mb-3" aria-label="Продолжить учёбу">
        <div class="gc-project-journey__heading">
            <div class="min-width-0">
                <a href="{{ url('/insider/courses/'.$projectJourney['course']->id.'?chapter='.$projectJourney['chapter']->id) }}" class="gc-quiet-link gc-project-journey__course">{{ $projectJourney['course']->name }} · {{ $projectJourney['chapter']->name }}</a>
                <h2>{{ $projectJourney['current']->name }}</h2>
            </div>
            <span class="gc-project-label"><i class="fas fa-location-dot" aria-hidden="true"></i>Сейчас проходим</span>
        </div>
        <div class="gc-project-journey__footer">
            <span class="gc-help-text">{{ $projectJourney['current']->steps->first()->name }}</span>
            <a class="btn btn-primary btn-sm" href="{{ $projectJourney['url'] }}">Открыть урок<i class="fas fa-arrow-right" aria-hidden="true"></i></a>
        </div>
    </section>
@endif
