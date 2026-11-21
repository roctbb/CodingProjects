<x-gc-panel title="Альбом наклеек" icon="fas fa-note-sticky" id="profile-stickers" class="gc-reward-shelf">
    <div class="gc-sticker-album">
        <x-gc-tab-strip>
            <div class="nav gc-page-tabs gc-album-tabs" role="tablist" aria-label="Коллекции наклеек">
                @foreach($album as $collection => $items)
                    <button type="button" class="nav-link @if($loop->first) active @endif"
                            id="album-tab-{{ $loop->index }}" role="tab" data-bs-toggle="tab" data-no-tab-history
                            data-bs-target="#album-page-{{ $loop->index }}" aria-controls="album-page-{{ $loop->index }}"
                            aria-selected="{{ $loop->first ? 'true' : 'false' }}" tabindex="{{ $loop->first ? 0 : -1 }}">{{ $collection }}</button>
                @endforeach
            </div>
        </x-gc-tab-strip>
        <div class="gc-album-pages">
            @foreach($album as $collection => $items)
                @php
                    $count = $items->count();
                    $single = $count % 10 === 1 && $count % 100 !== 11;
                @endphp
                <section class="gc-album-page tab-pane @if($loop->first) active @endif" id="album-page-{{ $loop->index }}"
                         role="tabpanel" aria-labelledby="album-tab-{{ $loop->index }}" tabindex="0">
                    <p class="gc-album-count">@if($isOwner)Собрано {{ $items->where('state', 'earned')->count() }} из {{ $count }} {{ $single ? 'наклейки' : 'наклеек' }}@else{{ trans_choice('В коллекции :count наклейка|В коллекции :count наклейки|В коллекции :count наклеек', $count, [], 'ru') }}@endif</p>
                    <ul class="gc-album-slots list-unstyled mb-0">
                        @foreach($items as $item)
                            <li class="gc-album-slot is-{{ $item['state'] }}">
                                @if($item['state'] === 'earned')
                                    <button type="button" data-collection-viewer data-trophy-src="{{ url($item['image']) }}"
                                            data-trophy-title="{{ $item['name'] }} · {{ $collection }}" data-trophy-description="{{ $item['lesson'] }}"
                                            data-trophy-alt="{{ $item['name'] }}" aria-label="{{ $item['name'] }}. Получена. Посмотреть наклейку">
                                        <span class="gc-sticker-art"><img src="{{ url($item['image']) }}" alt="" loading="lazy" width="96" height="96"></span>
                                        <span>{{ $item['name'] }}</span>
                                    </button>
                                @elseif($item['state'] === 'available')
                                    <a href="{{ $item['href'] }}" aria-label="{{ $item['name'] }}. Открыть урок «{{ $item['lesson'] }}»" title="{{ $item['lesson'] }}">
                                        <span class="gc-sticker-art"><img src="{{ url($item['image']) }}" alt="" loading="lazy" width="96" height="96"></span>
                                        <span>{{ $item['name'] }}</span><small>К уроку <i class="fas fa-arrow-right" aria-hidden="true"></i></small>
                                    </a>
                                @else
                                    <div>
                                        <span class="gc-sticker-art"><img src="{{ url($item['image']) }}" alt="" loading="lazy" width="96" height="96"><i class="fas fa-lock" aria-hidden="true"></i></span>
                                        <span>{{ $item['name'] }}</span><small>Урок пока закрыт</small>
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        </div>
    </div>
</x-gc-panel>
