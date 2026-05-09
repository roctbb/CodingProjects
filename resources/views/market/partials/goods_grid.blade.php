@if($items->count())
    <div class="row row-cols-1 row-cols-sm-2 row-cols-xl-3 row-cols-xxl-4 g-3" @isset($gridId) id="{{ $gridId }}" @endisset data-market-grid>
        @foreach($items as $good)
            @php
                $isAuction = $good->isAuction();
                $auctionActive = $good->isAuctionActive();
                $auctionWinnerDeals = $isAuction && $isArchive ? $good->auctionWinnerDeals() : collect();
                $userBid = $isAuction && !$isArchive ? $good->userAuctionBid($user) : null;
                $minBid = $isAuction && !$isArchive ? $good->minAuctionBid($user) : null;
                $currentBid = $isAuction && !$isArchive ? $good->currentAuctionPrice() : $good->price;
                $topBids = $isAuction && !$isArchive ? $good->topAuctionBids($good->number) : collect();
                $availableForBid = $user->balance() + ($userBid ? $userBid->amount : 0);
            @endphp
            <div class="col" data-market-good data-market-good-text="{{ $good->name }} {{ $good->description }} {{ $good->price }} {{ $good->number }} {{ $isAuction ? 'аукцион ставка '.$auctionWinnerDeals->pluck('user.name')->filter()->join(' ') : 'покупка' }}">
                <article class="gc-card market-good-card h-100 overflow-hidden d-flex flex-column @if($isAuction) market-good-card--auction @endif @if($isArchive) is-archived @endif">
                    @if (!$isArchive)
                        <div class="ratio ratio-4x3 gc-media-frame">
                            @if ($good->image)
                                <img class="w-100 h-100 object-fit-cover" src="{{ $good->image }}" alt="{{ $good->name }}">
                            @else
                                <div class="d-flex align-items-center justify-content-center text-muted fs-2">
                                    <i class="fas fa-gift"></i>
                                </div>
                            @endif
                        </div>
                    @endif

                    <div class="p-3 d-flex flex-column flex-grow-1 market-good-card__body">
                        <div class="market-good-card__header">
                            <div class="market-good-card__identity min-width-0">
                                <div class="market-good-card__meta">
                                    @if ($isArchive)
                                        <span class="badge rounded-pill market-good-status bg-secondary-subtle text-secondary border border-secondary-subtle fw-semibold">
                                            {{ $isAuction && $good->auction_finished_at ? 'Аукцион завершён' : 'Снят с продажи' }}
                                        </span>
                                    @elseif ($isAuction)
                                        <span class="badge rounded-pill market-good-status bg-info-subtle text-info-emphasis border border-info-subtle fw-semibold">Аукцион · мест: {{ $good->number }}</span>
                                    @elseif ($good->number > 0)
                                        <span class="badge rounded-pill market-good-status bg-body-tertiary">В наличии: {{ $good->number }}</span>
                                    @else
                                        <span class="badge rounded-pill market-good-status bg-warning-subtle text-warning-emphasis border border-warning-subtle fw-semibold">Закончился</span>
                                    @endif
                                </div>

                                <h6 class="market-good-title fw-bold lh-sm mb-0">{{ $good->name }}</h6>
                                <p class="market-good-description text-muted small lh-sm mb-0">{{ \Illuminate\Support\Str::limit($good->description, 150) }}</p>
                            </div>

                                @if ($user->role == 'admin')
                                    <div class="dropdown flex-shrink-0">
                                        <button class="btn btn-sm gc-icon-button market-good-menu" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Действия с товаром">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>

                                        <div class="dropdown-menu dropdown-menu-end rounded-3 p-2">
                                            <a href="{{ url('/insider/market/'.$good->id.'/edit') }}" class="dropdown-item rounded-2 d-flex align-items-center gap-2">
                                                <i class="fas fa-pen text-muted"></i> Редактировать
                                            </a>
                                            @if ($isArchive)
                                                <a href="{{ url('/insider/market/'.$good->id.'/restore') }}" class="dropdown-item rounded-2 d-flex align-items-center gap-2" data-confirm="Вернуть товар в магазин?">
                                                    <i class="fas fa-undo"></i> Вернуть в магазин
                                                </a>
                                            @else
                                                @if ($auctionActive)
                                                    <a href="{{ url('/insider/market/'.$good->id.'/finish-auction') }}" class="dropdown-item rounded-2 d-flex align-items-center gap-2" data-confirm="Завершить аукцион и создать заказы для победителей?">
                                                        <i class="fas fa-gavel"></i> Завершить аукцион
                                                    </a>
                                                @endif
                                                <a href="{{ url('/insider/market/'.$good->id.'/archive') }}" class="dropdown-item rounded-2 d-flex align-items-center gap-2 text-danger" data-confirm="Снять товар с продажи и перенести в архив?">
                                                    <i class="fas fa-archive"></i> Снять с продажи
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                        </div>

                        @if ($isAuction && $isArchive)
                            <div class="market-auction-panel market-auction-panel--archive mb-3">
                                <div class="market-auction-winners">
                                    <div class="market-auction-leaders__title">
                                        <span>Победители</span>
                                        <span>{{ $auctionWinnerDeals->count() }}</span>
                                    </div>
                                    @if ($auctionWinnerDeals->count())
                                        <ol class="list-unstyled mb-0">
                                            @foreach($auctionWinnerDeals as $deal)
                                                <li>
                                                    <span class="market-auction-leader-name text-truncate">{{ optional($deal->user)->name ?? 'Ученик удалён' }}</span>
                                                    <strong>{{ $deal->displayPrice() }} <i class="fas fa-coins text-warning"></i></strong>
                                                </li>
                                            @endforeach
                                        </ol>
                                    @else
                                        <div class="market-auction-empty small text-muted">Победителей нет.</div>
                                    @endif
                                </div>
                            </div>
                        @elseif ($isAuction)
                            <div class="market-auction-panel mb-3">
                                <div class="market-auction-panel__summary">
                                    <div class="market-auction-panel__price">
                                        <span class="text-muted small">Сейчас</span>
                                        <strong>{{ $currentBid }} <i class="fas fa-coins text-warning"></i></strong>
                                    </div>
                                    <div class="market-auction-panel__facts">
                                        <div>
                                            <span>Минимум</span>
                                            <strong>{{ $minBid }}</strong>
                                        </div>
                                        @if ($userBid)
                                            <div>
                                                <span>Ваша</span>
                                                <strong>{{ $userBid->amount }}</strong>
                                            </div>
                                        @else
                                            <div>
                                                <span>Ваша</span>
                                                <strong class="text-muted">-</strong>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                @if ($topBids->count())
                                    <div class="market-auction-leaders" aria-label="Лидеры аукциона">
                                        <div class="market-auction-leaders__title">
                                            <span>Лидеры</span>
                                            <span>{{ $topBids->count() }} / {{ $good->number }}</span>
                                        </div>
                                        <ol class="list-unstyled mb-0">
                                            @foreach($topBids as $bid)
                                                <li>
                                                    <span class="market-auction-leader-name text-truncate">{{ $bid->user->name }}</span>
                                                    <strong>{{ $bid->amount }}</strong>
                                                </li>
                                            @endforeach
                                        </ol>
                                    </div>
                                @else
                                    <div class="market-auction-empty small text-muted">Пока без ставок</div>
                                @endif
                            </div>
                        @endif

                        <div class="flex-grow-1"></div>

                        @if (!$isArchive)
                            @if ($isAuction)
                                @if ($auctionActive)
                                    @if ($canManageMarket ?? false)
                                        <div class="market-auction-note small text-muted">Завершение доступно в меню товара.</div>
                                    @else
                                        <form method="POST" action="{{ url('/insider/market/'.$good->id.'/bid') }}" class="market-auction-bid-form">
                                            {{ csrf_field() }}
                                            <div class="input-group input-group-sm">
                                                <input type="number" min="{{ $minBid }}" name="amount" value="{{ $minBid }}" class="form-control rounded-start-3" aria-label="Ставка" @if($availableForBid < $minBid) disabled @endif>
                                                <button class="btn btn-success fw-semibold" type="submit" @if($availableForBid < $minBid) disabled @endif>
                                                    <i class="fas fa-gavel"></i> Ставка
                                                </button>
                                            </div>
                                            <div class="market-auction-note small text-muted">GC резервируются до завершения.</div>
                                        </form>
                                        @if($availableForBid < $minBid)
                                            <div class="small text-danger mt-2">Недостаточно GC для минимальной ставки.</div>
                                        @endif
                                    @endif
                                @else
                                    <button class="btn gc-action-button gc-action-button--block market-good-action-disabled" disabled>
                                        Аукцион завершён
                                    </button>
                                @endif
                            @elseif ($good->number > 0 && $good->price <= $user->balance())
                                <a href="{{ url('/insider/market/'.$good->id.'/buy') }}" class="btn btn-success gc-action-button gc-action-button--block" data-confirm="Вы уверены?">
                                    Купить за {{ $good->price }} <i class="fas fa-coins"></i>
                                </a>
                            @else
                                <button class="btn gc-action-button gc-action-button--block market-good-action-disabled" disabled>
                                    Недоступно · {{ $good->price }} <i class="fas fa-coins"></i>
                                </button>
                            @endif
                        @endif
                    </div>
                </article>
            </div>
        @endforeach
    </div>
@else
    <div class="gc-empty-state">
        <div class="gc-empty-icon"><i class="fas fa-store"></i></div>
        <h5>{{ $emptyTitle ?? ($isArchive ? 'Архив пуст' : 'Нет доступных товаров') }}</h5>
        <p class="mx-auto mb-0">{{ $emptyText ?? ($isArchive ? 'Снятые с продажи товары появятся здесь.' : 'Новые товары появятся в магазине позже.') }}</p>
    </div>
@endif
