@if($items->count())
    <div class="gc-catalog-grid" @isset($gridId) id="{{ $gridId }}" @endisset data-market-grid>
        @foreach($items as $item)
            @php
                $type = $item['type'] ?? 'learning_avatar_item';
                $isPet = $item['slot'] === 'pet_right';
                $isLearningAvatarItem = $type === 'learning_avatar_item';
                $isProfileCosmetic = in_array($type, ['custom_title', 'avatar_frame', 'custom_avatar_frame'], true);
                $canBuy = (!$isLearningAvatarItem || !$item['owned']) && $item['cost'] <= $user->balance();
                $previewSrc = !empty($item['preview_src']) ? asset($item['preview_src']) : null;
                $avatarPreviewSrc = $user->imageUrl();
                $frameConfig = $type === 'custom_avatar_frame'
                    ? ($item['config'] ?? \App\User::customAvatarFrameDefaults())
                    : null;
                $categoryKey = $isPet
                    ? 'pets'
                    : ($isLearningAvatarItem ? 'room_items' : ($type === 'custom_title' ? 'titles' : ($type === 'room_character' ? 'services' : 'frames')));
                $categoryLabel = $isPet
                    ? 'Питомец'
                    : ($isLearningAvatarItem ? 'Предмет комнаты' : ($type === 'custom_title' ? 'Звание' : ($type === 'room_character' ? 'Услуга' : 'Рамка профиля')));
                $categoryClass = 'gc-soft-badge';
            @endphp
            <div class="col" data-market-good data-market-discounted="{{ ($item['discount_percent'] ?? 0) > 0 ? 'true' : 'false' }}" data-market-available="{{ $canBuy ? 'true' : 'false' }}" data-market-good-category="{{ $categoryKey }}" data-market-good-text="{{ $item['name'] }} {{ $item['description'] }} {{ implode(' ', $item['ability_descriptions']) }} {{ $categoryLabel }}" @if($type === 'custom_avatar_frame') data-avatar-frame-editor data-frame-balance="{{ $user->balance() }}" @endif>
                <article class="gc-card gc-catalog-card gc-catalog-card--{{ $categoryKey }} {{ ($item['discount_percent'] ?? 0) > 0 ? 'gc-catalog-card--promotion' : '' }} h-100 d-flex flex-column">
                    <div class="gc-catalog-media market-digital-preview">
                        <div class="market-digital-preview__stage">
                            @if ($previewSrc)
                                <img src="{{ $previewSrc }}" loading="lazy" alt="" class="market-digital-preview__asset {{ $isPet ? 'market-digital-preview__asset--pet' : 'market-digital-preview__asset--item' }}">
                            @elseif ($type === 'avatar_frame')
                                <span class="gc-avatar-frame gc-avatar-frame--{{ $item['frame_key'] }} gc-avatar-frame--size-xl market-digital-preview__frame" aria-hidden="true">
                                    <img src="{{ $avatarPreviewSrc }}" alt="" class="avatar xl">
                                    <span class="gc-avatar-frame__avatar-effect" aria-hidden="true"></span>
                                    <span class="gc-avatar-frame__effect" aria-hidden="true"></span>
                                </span>
                            @elseif ($type === 'custom_avatar_frame')
                                <span class="gc-avatar-frame gc-avatar-frame--custom gc-avatar-frame--size-xl market-digital-preview__frame" data-avatar-frame-preview aria-hidden="true">
                                    <img src="{{ $avatarPreviewSrc }}" alt="" class="avatar xl">
                                    <span class="gc-avatar-frame__avatar-effect" aria-hidden="true"></span>
                                    <span class="gc-avatar-frame__effect" aria-hidden="true"></span>
                                </span>
                            @elseif($type === 'custom_title')
                                <span class="gc-catalog-title-preview" data-market-title-preview>{{ $user->activeCustomTitle() ?: 'Ваше звание' }}</span>
                            @else
                                <span class="gc-catalog-preview-symbol" aria-hidden="true">
                                    <i class="{{ ($item['icon'] ?? '') ?: 'fas fa-laptop-code' }}"></i>
                                </span>
                            @endif
                            <span class="badge rounded-pill {{ $categoryClass }} fw-medium market-digital-preview__badge">
                                {{ $categoryLabel }}
                            </span>
                        </div>
                    </div>

                    <div class="p-3 d-flex flex-column flex-grow-1 gc-catalog-card__body">
                        <div class="gc-catalog-card__header">
                            <div class="gc-catalog-card__identity min-width-0">
                                @if (($item['active'] ?? false) || ($isLearningAvatarItem && $item['owned']))
                                <div class="gc-catalog-card__meta">
                                    @if ($item['active'] ?? false)
                                        <span class="gc-soft-badge">Активно</span>
                                    @elseif ($isLearningAvatarItem && $item['owned'])
                                        <span class="badge rounded-pill  gc-soft-badge fw-medium">Уже куплено</span>
                                    @endif
                                </div>
                                @endif

                                <h6 class="gc-catalog-title lh-sm mb-0">{{ $item['name'] }}</h6>
                                <x-gc-promotion-badge :percent="$item['discount_percent'] ?? 0" :name="$item['promotion_name'] ?? null" />
                                <x-gc-expandable-text :text="$type === 'custom_avatar_frame' ? 'Выберите цвета, форму и эффекты. Статичная или живая рамка на '.$user->customAvatarFrameDurationDays().' дней.' : $item['description']" class="gc-catalog-description text-muted small" />
                                @if($isLearningAvatarItem)<p class="gc-meta-label mb-0">Остаётся в коллекции навсегда</p>@endif
                                @if(!empty($item['active_until']))
                                    <p class="text-muted small lh-sm mb-0">Активно до {{ $item['active_until']->format('d.m.Y') }}</p>
                                @endif
                            </div>
                        </div>

                        @if (!empty($item['ability_descriptions']))
                            <div class="gc-catalog-benefits">
                                <div class="market-auction-leaders__title">
                                    <span>Способности</span>
                                    <span>{{ count($item['ability_descriptions']) }}</span>
                                </div>
                                <ul class="list-unstyled mb-0 d-grid gap-2 small">
                                    @foreach($item['ability_descriptions'] as $abilityDescription)
                                        <li class="d-flex gap-2">
                                            <i class="fas fa-bolt gc-currency-icon mt-1" aria-hidden="true"></i>
                                            <span>{{ $abilityDescription }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="flex-grow-1"></div>

                        @if ($type === 'custom_title')
                            <form method="POST" action="{{ url('/insider/profile/'.$user->id.'/custom-title') }}" class="d-grid gap-2">
                                {{ csrf_field() }}
                                <input type="hidden" name="expected_cost" value="{{ $type === 'custom_avatar_frame' ? (!empty($frameConfig['animated']) ? $item['animated_cost'] : $item['static_cost']) : $item['cost'] }}" @if($type === 'custom_avatar_frame') data-frame-expected-cost @endif>
                                <input type="hidden" name="return_to" value="market">
                                <input type="text"
                                       name="custom_title" data-market-title-input
                                       maxlength="32"
                                       class="form-control rounded-3"
                                       aria-label="Текст звания"
                                       value="{{ old('custom_title', $user->activeCustomTitle() ?: '') }}"
                                       placeholder="Например: Мастер циклов"
                                       @if(!$canBuy) disabled @endif>
                                <x-gc-purchase-footer :price="$item['cost']" :original-price="$item['base_cost'] ?? null">
                                    <button class="btn btn-coin btn-sm" type="submit" data-confirm="Купить кастомное звание на {{ $user->customTitleDurationDays() }} дней за {{ $item['cost'] }} GC?" @if(!$canBuy) disabled @endif>{{ $canBuy ? 'Купить' : 'Нужно ещё '.max(0, $item['cost'] - $user->balance()).' GC' }}</button>
                                </x-gc-purchase-footer>
                            </form>
                        @elseif ($type === 'avatar_frame')
                            <form method="POST" action="{{ url('/insider/profile/'.$user->id.'/avatar-frame') }}">
                                {{ csrf_field() }}
                                <input type="hidden" name="expected_cost" value="{{ $type === 'custom_avatar_frame' ? (!empty($frameConfig['animated']) ? $item['animated_cost'] : $item['static_cost']) : $item['cost'] }}" @if($type === 'custom_avatar_frame') data-frame-expected-cost @endif>
                                <input type="hidden" name="return_to" value="market">
                                <input type="hidden" name="avatar_frame" value="{{ $item['frame_key'] }}">
                                <x-gc-purchase-footer :price="$item['cost']" :original-price="$item['base_cost'] ?? null">
                                    <button class="btn btn-coin btn-sm" type="submit" data-confirm="{{ ($item['active'] ?? false) ? 'Продлить' : 'Купить' }} {{ $item['name'] }} за {{ $item['cost'] }} GC?" @if(!$canBuy) disabled @endif>{{ $canBuy ? (($item['active'] ?? false) ? 'Продлить' : 'Купить') : 'Нужно ещё '.max(0, $item['cost'] - $user->balance()).' GC' }}</button>
                                </x-gc-purchase-footer>
                            </form>
                        @elseif ($type === 'custom_avatar_frame')
                            <x-gc-purchase-footer :price="$item['cost']" :original-price="$item['base_cost'] ?? null" :from="true">
                                <button class="btn btn-outline-secondary btn-sm" type="button" data-market-frame-modal-open>Настроить</button>
                            </x-gc-purchase-footer>
                        @elseif ($type === 'room_character')
                            <x-gc-purchase-footer :price="$item['cost']" :original-price="$item['base_cost'] ?? null">
                                <a class="btn btn-primary btn-sm" href="{{ url('/insider/profile/'.$user->id.'#room-settings') }}">Настроить</a>
                            </x-gc-purchase-footer>
                        @elseif ($isLearningAvatarItem && $item['owned'])
                            <x-gc-purchase-footer>
                                <a href="{{ url('/insider/profile/'.$user->id.'#room-settings') }}" class="btn btn-outline-secondary btn-sm">Выбрать в комнате</a>
                            </x-gc-purchase-footer>
                        @elseif ($isLearningAvatarItem && $canBuy)
                            <form method="POST" action="{{ url('/insider/market/digital/'.$item['key'].'/buy') }}">
                                {{ csrf_field() }}
                                <input type="hidden" name="expected_cost" value="{{ $type === 'custom_avatar_frame' ? (!empty($frameConfig['animated']) ? $item['animated_cost'] : $item['static_cost']) : $item['cost'] }}" @if($type === 'custom_avatar_frame') data-frame-expected-cost @endif>
                                <x-gc-purchase-footer :price="$item['cost']" :original-price="$item['base_cost'] ?? null">
                                    <button class="btn btn-coin btn-sm" type="submit" data-confirm="Купить цифровой товар «{{ $item['name'] }}» за {{ $item['cost'] }} GC?">Купить</button>
                                </x-gc-purchase-footer>
                            </form>
                        @else
                            <x-gc-purchase-footer :price="$item['cost']" :original-price="$item['base_cost'] ?? null">
                                <button class="btn btn-outline-secondary btn-sm" disabled>Нужно ещё {{ max(0, $item['cost'] - $user->balance()) }} GC</button>
                            </x-gc-purchase-footer>
                        @endif
                    </div>
                </article>
                @if ($type === 'custom_avatar_frame')
                    <dialog class="gc-dialog market-digital-frame-modal" data-market-frame-modal aria-labelledby="market-frame-modal-title-{{ $item['key'] }}">
                            <div class="market-digital-frame-modal__header">
                                <div>
                                    <p class="gc-eyebrow mb-1">Рамка профиля</p>
                                    <h5 class="mb-0" id="market-frame-modal-title-{{ $item['key'] }}">Своя рамка</h5>
                                </div>
                                <button class="btn btn-sm gc-icon-button" type="button" data-market-frame-modal-close aria-label="Закрыть">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>

                            <form method="POST" action="{{ url('/insider/profile/'.$user->id.'/avatar-frame') }}" class="market-digital-frame-modal__body">
                                {{ csrf_field() }}
                                <input type="hidden" name="expected_cost" value="{{ $type === 'custom_avatar_frame' ? (!empty($frameConfig['animated']) ? $item['animated_cost'] : $item['static_cost']) : $item['cost'] }}" @if($type === 'custom_avatar_frame') data-frame-expected-cost @endif>
                                <input type="hidden" name="return_to" value="market">
                                <input type="hidden" name="avatar_frame" value="custom">
                                <input type="hidden" name="avatar_frame_config[animated]" value="0">

                                <div class="market-digital-frame-modal__preview">
                                    <span class="gc-avatar-frame gc-avatar-frame--custom gc-avatar-frame--size-xl market-digital-preview__frame" data-avatar-frame-preview aria-hidden="true">
                                        <img src="{{ $avatarPreviewSrc }}" alt="" class="avatar xl">
                                        <span class="gc-avatar-frame__avatar-effect" aria-hidden="true"></span>
                                        <span class="gc-avatar-frame__effect" aria-hidden="true"></span>
                                    </span>
                                    <div class="market-digital-frame-editor__preview-note">
                                        <strong>Предпросмотр</strong>
                                        <span data-frame-summary-type>Линия</span>
                                        <span data-frame-summary-shape>Круглая</span>
                                        <span data-frame-summary-pattern>Искры</span>
                                        <span data-frame-summary-animation>Вращение</span>
                                    </div>
                                </div>

                                <div class="market-digital-frame-modal__controls">
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <label class="form-label small" for="market-frame-type">Тип линии</label>
                                            <select id="market-frame-type" class="form-select form-select-sm rounded-3" name="avatar_frame_config[type]">
                                                @foreach(['linear' => 'Линия', 'conic' => 'Круг', 'radial' => 'Сияние'] as $value => $label)
                                                    <option value="{{ $value }}" @selected(($frameConfig['type'] ?? 'linear') === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small" for="market-frame-shape">Форма рамки</label>
                                            <select id="market-frame-shape" class="form-select form-select-sm rounded-3" name="avatar_frame_config[shape]">
                                                @foreach(['circle' => 'Круг', 'squircle' => 'Сквиркл', 'badge' => 'Жетон', 'soft' => 'Мягкая'] as $value => $label)
                                                    <option value="{{ $value }}" @selected(($frameConfig['shape'] ?? 'circle') === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small" for="market-frame-motion">Анимация рамки</label>
                                            <select id="market-frame-motion" class="form-select form-select-sm rounded-3" name="avatar_frame_config[motion]">
                                                @foreach(['spin' => 'Вращение', 'pulse' => 'Пульс', 'sweep' => 'Блик', 'still' => 'Статика'] as $value => $label)
                                                    <option value="{{ $value }}" @selected(($frameConfig['motion'] ?? 'spin') === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small" for="market-frame-avatar_effect">Эффект на аватарке</label>
                                            <select id="market-frame-avatar_effect" class="form-select form-select-sm rounded-3" name="avatar_frame_config[avatar_effect]">
                                                @foreach(['sheen' => 'Луч', 'scanner' => 'Сканер', 'spark' => 'Искра', 'none' => 'Нет'] as $value => $label)
                                                    <option value="{{ $value }}" @selected(($frameConfig['avatar_effect'] ?? 'sheen') === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small" for="market-frame-pattern">Узор рамки</label>
                                            <select id="market-frame-pattern" class="form-select form-select-sm rounded-3" name="avatar_frame_config[pattern]">
                                                @foreach(['sparkles' => 'Искры', 'pixels' => 'Пиксели', 'stripes' => 'Штрихи', 'none' => 'Без узора'] as $value => $label)
                                                    <option value="{{ $value }}" @selected(($frameConfig['pattern'] ?? 'sparkles') === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-6">
                                            <label class="market-digital-frame-editor__range">
                                                <span>Толщина <b data-frame-width-value>{{ $frameConfig['width'] ?? 6 }}px</b></span>
                                                <input type="range" class="form-range" name="avatar_frame_config[width]" value="{{ $frameConfig['width'] ?? 6 }}" min="2" max="14" data-frame-width>
                                            </label>
                                        </div>
                                        <div class="col-6">
                                            <label class="market-digital-frame-editor__range" data-frame-angle-field>
                                                <span>Угол <b data-frame-angle-value>{{ $frameConfig['angle'] ?? 135 }}°</b></span>
                                                <input type="range" class="form-range" name="avatar_frame_config[angle]" value="{{ $frameConfig['angle'] ?? 135 }}" min="0" max="359" data-frame-angle>
                                            </label>
                                        </div>
                                        <div class="col-6" data-frame-animated-option>
                                            <label class="market-digital-frame-editor__range">
                                                <span>Свечение <b data-frame-glow-value>{{ $frameConfig['glow'] ?? 28 }}%</b></span>
                                                <input type="range" class="form-range" name="avatar_frame_config[glow]" value="{{ $frameConfig['glow'] ?? 28 }}" min="0" max="52" data-frame-glow>
                                            </label>
                                        </div>
                                        <div class="col-6" data-frame-animated-option>
                                            <label class="market-digital-frame-editor__range">
                                                <span>Скорость <b data-frame-speed-value>{{ $frameConfig['speed'] ?? 100 }}%</b></span>
                                                <input type="range" class="form-range" name="avatar_frame_config[speed]" value="{{ $frameConfig['speed'] ?? 100 }}" min="60" max="180" data-frame-speed>
                                            </label>
                                        </div>
                                        <div class="col-6" data-frame-pattern-strength-field>
                                            <label class="market-digital-frame-editor__range">
                                                <span>Узор <b data-frame-pattern-opacity-value>{{ $frameConfig['pattern_opacity'] ?? 72 }}%</b></span>
                                                <input type="range" class="form-range" name="avatar_frame_config[pattern_opacity]" value="{{ $frameConfig['pattern_opacity'] ?? 72 }}" min="20" max="100" data-frame-pattern-opacity>
                                            </label>
                                        </div>
                                        <div class="col-6" data-frame-avatar-effect-strength-field data-frame-animated-option>
                                            <label class="market-digital-frame-editor__range">
                                                <span>Эффект <b data-frame-effect-opacity-value>{{ $frameConfig['effect_opacity'] ?? 70 }}%</b></span>
                                                <input type="range" class="form-range" name="avatar_frame_config[effect_opacity]" value="{{ $frameConfig['effect_opacity'] ?? 70 }}" min="20" max="100" data-frame-effect-opacity>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="d-flex gap-2">
                                        @foreach(($frameConfig['colors'] ?? ['#22d3ee', '#8b5cf6', '#f97316', '#22c55e']) as $color)
                                            <input type="color" class="form-control form-control-color rounded-3 flex-fill" name="avatar_frame_config[colors][]" value="{{ $color }}" aria-label="Цвет рамки {{ $loop->iteration }}">
                                        @endforeach
                                    </div>
                                    <label class="form-check small mb-0">
                                        <input type="checkbox" class="form-check-input" name="avatar_frame_config[animated]" value="1" @checked($frameConfig['animated'] ?? true)>
                                        Живая рамка
                                    </label>
                                    <p class="gc-help-text mb-0" data-frame-funds-note hidden></p>
                                </div>

                                <div class="market-digital-frame-modal__footer">
                                    <button class="btn btn-light gc-action-button" type="button" data-market-frame-modal-close>
                                        Отмена
                                    </button>
                                    <button class="btn btn-coin gc-action-button" type="submit"
                                            data-frame-submit
                                            data-frame-static-cost="{{ $item['static_cost'] ?? $item['cost'] }}"
                                            data-frame-animated-cost="{{ $item['animated_cost'] ?? $item['cost'] }}"
                                            data-frame-confirm-template="Купить свою {type} рамку на {{ $user->customAvatarFrameDurationDays() }} дней за {cost} GC?"
                                            data-confirm="Купить свою живую рамку на {{ $user->customAvatarFrameDurationDays() }} дней за {{ $item['animated_cost'] ?? $item['cost'] }} GC?"
                                            @if(!$canBuy) disabled @endif>
                                        Купить за <span data-frame-price>{{ $item['animated_cost'] ?? $item['cost'] }}</span> <i class="fas fa-coins"></i>
                                    </button>
                                </div>
                            </form>
                    </dialog>
                @endif
            </div>
        @endforeach
    </div>
@else
    <div class="gc-empty-state">
        <div class="gc-empty-icon"><i class="fas fa-box-open"></i></div>
        <h5>Нет цифровых товаров</h5>
        <p class="mx-auto mb-0">Питомцы и предметы комнаты появятся здесь позже.</p>
    </div>
@endif
