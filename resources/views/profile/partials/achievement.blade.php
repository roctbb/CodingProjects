@php
    $canEditAchievement = $guest->role == 'admin'
        || ($achievement->course && $achievement->course->teachers->contains('id', $guest->id));
    $canDeleteAchievement = $guest->role == 'admin';
    $achievementTrophyUrl = $achievement->trophyImageUrl();
@endphp
<article class="gc-entry gc-entry--award" id="achievement-{{ $achievement->id }}">
    @if($canEditAchievement || $canDeleteAchievement)
        <span class="profile-achievement__actions">
            @if($canEditAchievement)
                <button type="button"
                        class="btn btn-sm gc-icon-button"
                        data-bs-toggle="modal"
                        data-bs-target="#achievement-edit-{{ $achievement->id }}"
                        title="Редактировать достижение"
                        aria-label="Редактировать достижение">
                    <i class="fas fa-pen"></i>
                </button>
            @endif
            @if($canDeleteAchievement)
                <form method="POST"
                      action="{{ url('/insider/profile/'.$user->id.'/achievement/'.$achievement->id.'/delete') }}"
                      class="d-inline-flex">
                    @csrf
                    <button type="submit"
                            class="btn btn-sm gc-icon-button text-danger"
                            title="Удалить достижение"
                            aria-label="Удалить достижение"
                            data-confirm="Удалить достижение «{{ $achievement->title }}» из профиля и пульса?">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </form>
            @endif
        </span>
    @endif
    <span class="gc-entry__icon gc-reward-mark">
        @if($achievementTrophyUrl)
            <button type="button"
                    class="profile-achievement__trophy-button"
                    data-achievement-trophy-viewer
                    data-trophy-src="{{ $achievementTrophyUrl }}"
                    data-trophy-title="{{ $achievement->title }}"
                    data-trophy-description="{{ $achievement->description }}"
                    aria-label="Увеличить кубок «{{ $achievement->title }}»">
                <img src="{{ $achievementTrophyUrl }}" alt="">
            </button>
        @else
            <i class="fas fa-trophy"></i>
        @endif
    </span>
    <div class="gc-entry__body min-width-0">
        <h6 class="gc-entry__title">{{ $achievement->title }}</h6>
        <p class="gc-entry__description">{{ $achievement->description }}</p>
        <div class="gc-entry__meta">
            @if($achievement->task)
                @if($canEditAchievement && $achievement->course && $achievement->solution_id)
                    <a href="{{ url('/insider/courses/'.$achievement->course_id.'/tasks/'.$achievement->task_id.'/student/'.$achievement->user_id.'#solution-'.$achievement->solution_id) }}"
                       class="link-underline-opacity-25 link-underline-opacity-100-hover">{{ $achievement->task->name }}</a>
                @else
                    <span>{{ $achievement->task->name }}</span>
                @endif
            @endif
            @if($achievement->course)
                <span>{{ $achievement->course->name }}</span>
            @endif
            @if($achievement->published_at)
                <span>{{ $achievement->published_at->format('d.m.Y') }}</span>
            @endif
        </div>
    </div>
</article>
@if($canEditAchievement)
    <div class="modal fade" id="achievement-edit-{{ $achievement->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content overflow-hidden">
                <div class="modal-header">
                    <div class="d-flex align-items-center gap-2 min-width-0">
                        <span class="gc-icon-tile flex-shrink-0">
                            @if($achievementTrophyUrl)
                                <img src="{{ $achievementTrophyUrl }}" alt="">
                            @else
                                <i class="fas fa-trophy"></i>
                            @endif
                        </span>
                        <h5 class="modal-title text-truncate">Редактировать достижение</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <form method="POST"
                      action="{{ url('/insider/profile/'.$user->id.'/achievement/'.$achievement->id) }}"
                      data-fullscreen-loading
                      data-loading-message="Сохраняю достижение">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="achievement-title-{{ $achievement->id }}" class="form-label">Название</label>
                            <input id="achievement-title-{{ $achievement->id }}"
                                   type="text"
                                   name="title"
                                   class="form-control"
                                   maxlength="120"
                                   value="{{ old('title', $achievement->title) }}"
                                   required>
                        </div>
                        <div class="mb-3">
                            <label for="achievement-description-{{ $achievement->id }}" class="form-label">Описание</label>
                            <textarea id="achievement-description-{{ $achievement->id }}"
                                      name="description"
                                      class="form-control"
                                      rows="4"
                                      required>{{ old('description', $achievement->description) }}</textarea>
                        </div>
                    </div>
                    <div class="modal-footer gc-form-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
                        @if($guest->role == 'admin')
                            <button type="submit" name="regenerate_trophy" value="1" class="btn btn-outline-primary">Сохранить и перегенерировать кубок</button>
                        @endif
                        <button type="submit" class="btn btn-primary">Сохранить</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
