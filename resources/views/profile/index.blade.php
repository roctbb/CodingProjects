@extends('layouts.left-menu')
@section('title', 'Сообщество')
@section('content')
    <div class="gc-page-masthead gc-directory-header mb-4">
        <x-gc-page-header title="Сообщество" mark="users" :description="$users->total() ? $users->firstItem().'–'.$users->lastItem().' из '.$users->total() : '0 участников'">
            <x-gc-page-navigation :paginator="$users" label="Страницы сообщества" />
        </x-gc-page-header>
        <form method="GET" action="{{ url('/insider/community') }}" class="gc-directory-search" role="search" aria-label="Поиск участников">
            <div class="gc-directory-search__query">
                <div class="input-group gc-search-box">
                    <span class="input-group-text"><i class="fas fa-search" aria-hidden="true"></i></span>
                    <input type="search" name="q" value="{{ $search }}" maxlength="120" class="form-control" placeholder="Имя, ранг или звание" aria-label="Найти участника">
                </div>
                <button type="submit" name="role" value="{{ $role }}" class="btn btn-primary">Найти</button>
            </div>
            <div class="gc-directory-search__roles" role="group" aria-label="Роль участника">
                @foreach(['all' => 'Все', 'student' => 'Ученики', 'teacher' => 'Преподаватели'] as $value => $label)
                    <button type="submit" name="role" value="{{ $value }}" class="gc-filter-toggle" aria-pressed="{{ $role === $value ? 'true' : 'false' }}">{{ $label }}</button>
                @endforeach
            </div>
            @if($search !== '' || $role !== 'all')<a href="{{ url('/insider/community') }}" class="gc-quiet-link gc-directory-search__reset">Сбросить</a>@endif
        </form>
    </div>
    @if($users->isNotEmpty())
        <div class="gc-person-grid" id="community-grid" data-community-grid>
            @foreach($users as $user)
                @php $leaderPosition = $topUserIds->search($user->id); @endphp
                <x-gc-person-card :user="$user" :position="$leaderPosition === false ? null : $leaderPosition + 1" :profile-query="$profileQuery" />
            @endforeach
        </div>
    @else
        <div class="gc-card gc-empty-state">
            <h2 class="h5">{{ $search !== '' || $role !== 'all' ? 'Ничего не найдено' : 'Участников пока нет' }}</h2>
            @if($search !== '' || $role !== 'all')
                <p>Попробуйте изменить имя, ранг или роль участника.</p>
                <a href="{{ url('/insider/community') }}" class="btn btn-outline-secondary btn-sm">Показать всех участников</a>
            @endif
        </div>
    @endif
    @if($users->hasPages())
        <div class="mt-4">{{ $users->onEachSide(1)->links('pagination::bootstrap-5') }}</div>
    @endif
@endsection
