@if ($empty || !$zero_theory)
    <div class="tab-pane fade show active markdown" id="theory" role="tabpanel" aria-labelledby="v-theory-tab">

        @if ($step->video_url)
            <div class="ratio ratio-16x9 mb-4">
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

            <div>
                <h3>Комментарий для преподавателя</h3>
                @parsedown($step->notes)
            </div>

        @endif
    </div>
@endif
