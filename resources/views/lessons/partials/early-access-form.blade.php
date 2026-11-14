<section class="gc-card gc-form-sheet mt-4" aria-label="Ручной ранний доступ">
    @php
                        $earlyAccessByUser = $lesson->earlyAccesses->keyBy('user_id');
                        $lessonStarted = $lesson->isStarted($course);
                    @endphp
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <span class="gc-icon-tile flex-shrink-0" aria-hidden="true"><i class="fas fa-user-lock"></i></span>
                        <div class="min-width-0">
                            <h5 class="mb-1">Ручной ранний доступ</h5>
                            <p class="mb-0 text-muted small">Откройте урок выбранным ученикам без списания GC. Доступы сохраняются отдельно от настроек урока.</p>
                        </div>
                    </div>

                    @if($lessonStarted)
                        <div class="alert alert-info rounded-3 mb-0">
                            Урок уже доступен по дате начала, ручное открытие не требуется.
                        </div>
                    @elseif($course->students->isEmpty())
                        <div class="alert alert-info rounded-3 mb-0">
                            В курсе пока нет учеников.
                        </div>
                    @else
                        <form method="POST" action="{{ url('/insider/courses/'.$course->id.'/lessons/'.$lesson->id.'/early-access/grant') }}">
                            @csrf
                            <div class="lesson-early-access-grants">
                                @foreach($course->students as $student)
                                    @php
                                        $access = $earlyAccessByUser->get($student->id);
                                        $isChecked = $access !== null;
                                        $isTeacherGrant = $access && ($access->source ?? 'purchase') === \App\LessonEarlyAccess::SOURCE_TEACHER;
                                        $isProtectedGrant = $access && !$isTeacherGrant;
                                        $sourceLabel = $isProtectedGrant
                                            ? (($access->source ?? 'purchase') === \App\LessonEarlyAccess::SOURCE_PET ? 'открыто питомцем' : 'уже куплено')
                                            : null;
                                    @endphp
                                    <label class="lesson-early-access-grant form-check">
                                        <input type="checkbox"
                                               class="form-check-input"
                                               name="student_ids[]"
                                               value="{{ $student->id }}"
                                               @if($isChecked) checked @endif
                                               @if($isProtectedGrant) disabled @endif>
                                        <span class="form-check-label">
                                            <span class="lesson-early-access-grant__name">{{ $student->name }}</span>
                                            @if($sourceLabel)
                                                <small>{{ $sourceLabel }}</small>
                                            @elseif($isTeacherGrant)
                                                <small>открыто преподавателем</small>
                                            @endif
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            <button type="submit" class="btn btn-outline-secondary btn-sm mt-3">
                                Сохранить доступы
                            </button>
                        </form>
                    @endif
</section>
