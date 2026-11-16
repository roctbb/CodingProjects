@if($items->count())
    <div class="gc-catalog-grid" @isset($gridId) id="{{ $gridId }}" @endisset data-market-grid>
        @foreach($items as $good)
            @php
                $isAuction = $good->isAuction();
                $priceQuote = app(\App\Services\SystemStoreSettings::class)->quoteGood($good);
                $marketPrice = $priceQuote['price'];
                $auctionActive = $good->isAuctionActive();
                $auctionWinnerDeals = $isAuction && $isArchive ? $good->auctionWinnerDeals() : collect();
                $userBid = $isAuction && !$isArchive ? $good->userAuctionBid($user) : null;
                $minBid = $isAuction && !$isArchive ? $good->minAuctionBid($user) : null;
                $currentBid = $isAuction && !$isArchive ? $good->currentAuctionPrice() : $good->price;
                $topBids = $isAuction && !$isArchive ? $good->topAuctionBids($good->number) : collect();
                $availableForBid = $user->balance() + ($userBid ? $userBid->amount : 0);
            @endphp
            <div class="col" data-market-good data-market-discounted="{{ $priceQuote['discount_percent'] > 0 ? 'true' : 'false' }}" data-market-available="{{ ($isAuction ? ($auctionActive && $good->number > 0 && $availableForBid >= $minBid) : ($good->number > 0 && $marketPrice <= $user->balance())) ? 'true' : 'false' }}" data-market-good-text="{{ $good->name }} {{ $good->description }} {{ $good->price }} {{ $good->number }} {{ $isAuction ? 'аукцион ставка '.$auctionWinnerDeals->pluck('user.name')->filter()->join(' ') : 'покупка' }}">
                <article class="gc-card gc-catalog-card {{ $priceQuote['discount_percent'] > 0 ? 'gc-catalog-card--promotion' : '' }} h-100 d-flex flex-column @if($isAuction) gc-catalog-card--auction @endif @if($isArchive) is-archived @endif">
                    @if (!$isArchive)
                        <div class="ratio ratio-4x3 gc-catalog-media {{ $good->image ? '' : 'gc-catalog-media--placeholder' }}">
                            @if ($good->image)
                                <img class="w-100 h-100 object-fit-cover" src="{{ $good->image }}" alt="{{ $good->name }}">
                            @else
                                <div class="d-flex align-items-center justify-content-center text-muted fs-2">
                                    <i class="fas fa-gift" aria-hidden="true"></i>
                                </div>
                            @endif
                        </div>
                    @endif

                    <div class="p-3 d-flex flex-column flex-grow-1 gc-catalog-card__body">
                        <div class="gc-catalog-card__header">
                            <div class="gc-catalog-card__identity min-width-0">
                                <div class="gc-catalog-card__meta">
                                    <x-gc-promotion-badge :percent="$priceQuote['discount_percent']" :name="$priceQuote['promotion_name']" />
                                    @if ($isArchive)
                                        <span class="gc-soft-badge">
                                            {{ $isAuction && $good->auction_finished_at ? 'Аукцион завершён' : 'Снят с продажи' }}
                                        </span>
                                    @elseif ($isAuction)
                                        <span class="gc-soft-badge">Аукцион · мест: {{ $good->number }}</span>
                                    @elseif ($good->number > 0)
                                        <span class="gc-soft-badge">В наличии: {{ $good->number }}</span>
                                    @else
                                        <span class="badge rounded-pill  bg-warning-subtle text-warning-emphasis border border-warning-subtle fw-medium">Закончился</span>
                                    @endif
                                </div>

                                <h6 class="gc-catalog-title lh-sm mb-0">{{ $good->name }}</h6>
                                <x-gc-expandable-text :text="$good->description" class="gc-catalog-description text-muted small" />
                            </div>

                                @if ($user->role == 'admin')
                                    <div class="dropdown flex-shrink-0">
                                        <button class="btn btn-sm gc-icon-button " type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Действия с товаром">
                                            <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
                                        </button>

                                        <div class="dropdown-menu dropdown-menu-end rounded-3 p-2">
                                            <a href="{{ url('/insider/market/'.$good->id.'/edit') }}" class="dropdown-item rounded-2 d-flex align-items-center gap-2">
                                                <i class="fas fa-pen text-muted" aria-hidden="true"></i> Редактировать
                                            </a>
                                            @if ($isArchive)
                                                <form method="POST" action="{{ url('/insider/market/'.$good->id.'/restore') }}">@csrf<button type="submit" class="dropdown-item rounded-2 d-flex align-items-center gap-2" data-confirm="Вернуть товар в магазин?">
                                                    <i class="fas fa-undo" aria-hidden="true"></i> Вернуть в магазин
                                                </button></form>
                                            @else
                                                @if ($auctionActive)
                                                    <form method="POST" action="{{ url('/insider/market/'.$good->id.'/finish-auction') }}">@csrf<button type="submit" class="dropdown-item rounded-2 d-flex align-items-center gap-2" data-confirm="Завершить аукцион и создать заказы для победителей?">
                                                        <i class="fas fa-gavel" aria-hidden="true"></i> Завершить аукцион
                                                    </button></form>
                                                @endif
                                                <form method="POST" action="{{ url('/insider/market/'.$good->id.'/archive') }}">@csrf<button type="submit" class="dropdown-item rounded-2 d-flex align-items-center gap-2 text-danger" data-confirm="Снять товар с продажи и перенести в архив?">
                                                    <i class="fas fa-archive" aria-hidden="true"></i> Снять с продажи
                                                </button></form>
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
                                                    <strong>{{ $deal->displayPrice() }} <i class="fas fa-coins gc-currency-icon" aria-hidden="true"></i></strong>
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
                                        <strong>{{ $currentBid }} <i class="fas fa-coins gc-currency-icon" aria-hidden="true"></i></strong>
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
                                                <button class="btn btn-coin fw-medium" type="submit" @if($availableForBid < $minBid) disabled @endif>
                                                    <i class="fas fa-gavel" aria-hidden="true"></i> Ставка
                                                </button>
                                            </div>
                                            <div class="market-auction-note small text-muted">GC резервируются до завершения.</div>
                                        </form>
                                        @if($availableForBid < $minBid)
                                            <div class="small text-danger mt-2">Недостаточно GC для минимальной ставки.</div>
                                        @endif
                                    @endif
                                @else
                                    <button class="btn btn-sm btn-outline-secondary align-self-end" disabled>
                                        Аукцион завершён
                                    </button>
                                @endif
                            @else
                                <x-gc-purchase-footer :price="$marketPrice" :original-price="$priceQuote['base_price']">
                                    @if ($good->number > 0 && $marketPrice <= $user->balance())
                                        <form method="POST" action="{{ url('/insider/market/'.$good->id.'/buy') }}" class="d-inline" data-confirm="Купить «{{ $good->name }}» за {{ $marketPrice }} GC? Заказ появится в разделе «Мои покупки».">@csrf<input type="hidden" name="purchase_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}"><input type="hidden" name="expected_price" value="{{ $marketPrice }}"><button type="submit" class="btn btn-coin btn-sm" aria-label="Купить {{ $good->name }} за {{ $marketPrice }} GC">Купить</button></form>
                                    @else
                                        <button class="btn btn-outline-secondary btn-sm" disabled>{{ $good->number > 0 ? 'Нужно ещё '.($marketPrice - $user->balance()).' GC' : 'Нет в наличии' }}</button>
                                    @endif
                                </x-gc-purchase-footer>
                            @endif
                        @endif
                    </div>
                </article>
            </div>
        @endforeach
    </div>
@else
    <div class="gc-empty-state">
        <div class="gc-empty-icon"><i class="fas fa-store" aria-hidden="true"></i></div>
        <h5>{{ $emptyTitle ?? ($isArchive ? 'Архив пуст' : 'Нет доступных товаров') }}</h5>
        <p class="mx-auto mb-0">{{ $emptyText ?? ($isArchive ? 'Снятые с продажи товары появятся здесь.' : 'Новые товары появятся в магазине позже.') }}</p>
    </div>
@endif
