<x-gc-panel title="Обучение" icon="fas fa-graduation-cap" id="profile-learning">
    @if($guest->role == 'admin')
        <x-slot:actions><button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#exampleModal" aria-label="Добавить завершённый курс"><i class="fas fa-plus" aria-hidden="true"></i> Добавить</button></x-slot:actions>
    @endif
    <div class="gc-stack">
        @foreach(['Преподаёт' => $managedCourses, 'Текущие курсы' => $startedCourses] as $label => $courseList)
            @if($courseList->isNotEmpty())
                <section>
                    <h3 class="gc-section-label mb-3">{{ $label }} <span class="gc-help-text">{{ $courseList->count() }}</span></h3>
                    <div class="gc-profile-course-grid">
                        @foreach($courseList as $course)
                            @include('profile.partials.course-row', ['completedEntry' => null])
                        @endforeach
                    </div>
                </section>
            @endif
        @endforeach
        @if($completedCourses->isNotEmpty())
            <section id="profile-completed-courses">
                <h3 class="gc-section-label mb-3">Завершённые курсы <span class="gc-help-text">{{ $completedCourses->count() }}</span></h3>
                <div class="gc-profile-course-grid">
                    @foreach($completedCourses as $completedCourse)
                        @include('profile.partials.course-row', ['course' => $completedCourse->course, 'completedEntry' => $completedCourse, 'label' => 'Завершённые курсы'])
                    @endforeach
                </div>
            </section>
        @endif
        @if($managedCourses->isEmpty() && $startedCourses->isEmpty() && $completedCourses->isEmpty())<p class="text-muted mb-0">Курсов пока нет.</p>@endif
    </div>
</x-gc-panel>
