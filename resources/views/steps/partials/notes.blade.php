@if ($empty || !$zero_theory)
    @php
        $hasTheoryContent = filled($step->video_url) || trim((string) $step->theory) !== '';
        $hasStepTasks = isset($tasks) ? $tasks->count() > 0 : $step->tasks->count() > 0;
        $isManager = $course->teachers->contains($user) || $user->role == 'admin';
    @endphp

    <div class="tab-pane fade show active markdown step-reading-card" id="theory" role="tabpanel" aria-labelledby="v-theory-tab">

        @if ($step->video_url)
            <div class="ratio ratio-16x9 step-reading-video">
                <iframe src="{{ video_embed_url($step->video_url) }}"
                        allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture"
                        allowfullscreen></iframe>
            </div>
        @endif

        @if ($step->is_notebook)
            <div class="notebook-container" id="notebook" data-notebook-content="{{ e($step->theory) }}">

            </div>
        @else
            @parsedown($step->theory)
        @endif
        @if (!$step->lesson->is_open && ($course->teachers->contains($user) || $user->role=='admin') && $step->notes!='')
            <div class="step-teacher-note mt-4">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="text-muted"><i class="fas fa-user-shield"></i></span>
                    <h3 class="h6 mb-0">Комментарий для преподавателя</h3>
                </div>
                <div class="markdown mb-0">
                    @parsedown($step->notes)
                </div>
            </div>

	        @endif

	        @if (!$hasTheoryContent && !$hasStepTasks)
	            <div class="gc-empty-state step-empty-state">
	                <div class="gc-empty-icon">
	                    <i class="{{ $isManager ? 'fas fa-file-alt' : 'fas fa-hourglass-half' }}"></i>
	                </div>
	                <h5>{{ $isManager ? 'Урок пока пуст' : 'Материалы урока ещё готовятся' }}</h5>
	                <p class="mx-auto mb-0">
	                    {{ $isManager ? 'Добавьте теорию или задачу, чтобы урок выглядел завершённым для учеников.' : 'Когда преподаватель откроет материалы, они появятся на этой странице.' }}
	                </p>
	            </div>
	        @endif
	    </div>
@endif
