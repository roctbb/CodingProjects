@if($characters->isNotEmpty())
    <section class="home-characters" aria-labelledby="recent-characters-title">
        <h3 id="recent-characters-title" class="small mb-0">Недавно создали персонажей</h3>
        <div class="home-characters__scroll">
            <div class="home-characters__list">
                @foreach($characters as $character)
                    <a class="home-characters__link" href="{{ url('/insider/profile/'.$character->user_id) }}"
                       title="{{ $character->user->name }}" aria-label="Профиль: {{ $character->user->name }}">
                        <img class="home-characters__image" src="{{ route('room-character.preview', $character->id) }}" width="128" height="192"
                             alt="Персонаж {{ $character->user->name }}" loading="lazy" decoding="async">
                    </a>
                @endforeach
            </div>
        </div>
    </section>
@endif
