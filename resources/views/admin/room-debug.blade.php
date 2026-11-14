@extends('layouts.left-menu')

@section('title', 'Room Debug')


@section('content')
    <div class="room-tool" id="roomTool">
        <div class="room-tool__header gc-page-header mb-0">
            <div class="room-tool__title">
                <div class="gc-meta-label mb-1">Admin debug</div>
                <h1>Редактор комнаты ученика</h1>
                <p>Собираем комнату слоями и правим координаты слотов прямо поверх картинки.</p>
            </div>
            <div class="text-muted small text-end">
                Canvas {{ $roomSystem['canvas']['width'] ?? 1024 }}x{{ $roomSystem['canvas']['height'] ?? 1024 }}<br>
                Pipeline: magenta → alpha → layers
            </div>
        </div>

        <section class="room-panel">
            <div class="room-panel__body">
                <div class="room-toolbar">
                    <div class="room-field">
                        <label for="roomSelect">Комната</label>
                        <select id="roomSelect"></select>
                    </div>
                    <div class="room-field">
                        <label for="seasonSelect">Сезон</label>
                        <select id="seasonSelect"></select>
                    </div>
                    <div class="room-field">
                        <label for="weatherSelect">Погода</label>
                        <select id="weatherSelect"></select>
                    </div>
                    <div class="room-field">
                        <label for="posterSelect">Плакат</label>
                        <select id="posterSelect"></select>
                    </div>
                    <div class="room-field">
                        <label for="genderSelect">Пол</label>
                        <select id="genderSelect">
                            <option value="boy">Мальчик</option>
                            <option value="girl">Девочка</option>
                        </select>
                    </div>
                    <div class="room-field">
                        <label for="classSelect">Класс</label>
                        <select id="classSelect">
                            @for($grade = 5; $grade <= 11; $grade++)
                                <option value="class_{{ str_pad((string) $grade, 2, '0', STR_PAD_LEFT) }}">{{ $grade }} класс</option>
                            @endfor
                            <option value="student">Выпускник</option>
                            <option value="teacher">Преподаватель</option>
                        </select>
                    </div>
                    <div class="room-field">
                        <label for="safeSelect">Сейф</label>
                        <select id="safeSelect"></select>
                    </div>
                    <div class="room-field">
                        <label for="itemSelect">Предмет на столе</label>
                        <select id="itemSelect"></select>
                    </div>
                    <div class="room-field">
                        <label for="petSelect">Питомец</label>
                        <select id="petSelect"></select>
                    </div>
                </div>

                @if(!empty($roomSystem['readiness']['cards']))
                    <div class="room-readiness-grid" aria-label="Готовность системы комнат">
                        @foreach($roomSystem['readiness']['cards'] as $readinessCard)
                            <div class="room-readiness-card room-readiness-card--{{ ($readinessCard['status'] ?? 'warn') === 'ok' ? 'ok' : 'warn' }}">
                                <span>{{ $readinessCard['label'] ?? '' }}</span>
                                <strong>{{ $readinessCard['value'] ?? '' }}</strong>
                                <small>{{ $readinessCard['hint'] ?? '' }}</small>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="room-canvas-shell">
                    <div class="room-canvas" id="roomCanvas"></div>
                </div>

                <div class="room-stage-grid" id="roomStageGrid"></div>

                @php
                    $assetSections = [
                        ['title' => 'Питомцы', 'items' => $roomSystem['pets'] ?? []],
                        ['title' => 'Предметы стола', 'items' => $roomSystem['items'] ?? []],
                        ['title' => 'Сейф / монеты', 'items' => $roomSystem['safes'] ?? []],
                        ['title' => 'Плакаты', 'items' => $roomSystem['posters'] ?? []],
                        ['title' => 'Погода', 'items' => $roomSystem['weather'] ?? []],
                    ];
                @endphp

                <div class="room-asset-library" aria-label="Библиотека слоев комнаты">
                    @foreach($assetSections as $assetSection)
                        <section class="room-asset-section">
                            <div class="room-asset-section__header">
                                <strong>{{ $assetSection['title'] }}</strong>
                                <span>{{ count($assetSection['items']) }}</span>
                            </div>
                            <div class="room-asset-grid">
                                @foreach($assetSection['items'] as $asset)
                                    <div class="room-asset-card" title="{{ $asset['name'] ?? $asset['key'] ?? '' }}">
                                        <img src="{{ $asset['src'] ?? '' }}" alt="">
                                        <span>{{ $asset['name'] ?? $asset['key'] ?? 'asset' }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endforeach
                </div>

                <div class="room-qa-grid"></div>
            </div>
        </section>

        <aside class="room-panel room-inspector">
            <div class="room-panel__body">
                <h2>Слои предпросмотра</h2>
                <div class="room-layer-presets" aria-label="Режимы предпросмотра">
                    <button class="room-layer-preset is-active" type="button" data-room-layer-preset="full">Полная</button>
                    <button class="room-layer-preset" type="button" data-room-layer-preset="posterWeather">Окно/плакат</button>
                    <button class="room-layer-preset" type="button" data-room-layer-preset="character">Персонаж</button>
                    <button class="room-layer-preset" type="button" data-room-layer-preset="cosmetics">Косметика</button>
                    <button class="room-layer-preset" type="button" data-room-layer-preset="slots">Только слоты</button>
                </div>
                <div class="room-layer-toggles" aria-label="Слои предпросмотра комнаты">
                    @foreach([
                        'weather' => 'Погода',
                        'poster' => 'Плакат',
                        'room' => 'Комната',
                        'safe' => 'Сейф',
                        'desk' => 'Стол',
                        'character' => 'Персонаж',
                        'pet' => 'Питомец',
                        'slots' => 'Рамки слотов',
                    ] as $layerKey => $layerLabel)
                        <label class="room-layer-toggle">
                            <input type="checkbox" data-room-layer-toggle="{{ $layerKey }}" checked>
                            <span>{{ $layerLabel }}</span>
                        </label>
                    @endforeach
                </div>

                <h2>Текущая комната</h2>
                <div class="room-current-card" id="roomCurrentInfo" aria-live="polite"></div>

                <h2>Погода в окне</h2>
                <div class="room-inspector__grid mb-3">
                    <div class="room-field">
                        <label for="weatherX">X картинки</label>
                        <input id="weatherX" type="number" min="-2048" max="2048">
                    </div>
                    <div class="room-field">
                        <label for="weatherY">Y картинки</label>
                        <input id="weatherY" type="number" min="-2048" max="2048">
                    </div>
                    <div class="room-field">
                        <label for="weatherW">W картинки</label>
                        <input id="weatherW" type="number" min="50" max="3072">
                    </div>
                    <div class="room-field">
                        <label for="weatherH">H картинки</label>
                        <input id="weatherH" type="number" min="50" max="3072">
                    </div>
                </div>

                <h2>Слот</h2>
                <div class="room-slot-list" id="slotList"></div>

                <hr>

                <div class="room-field mb-2">
                    <label for="activeSlotLabel">Активный слот</label>
                    <input id="activeSlotLabel" disabled>
                </div>

                <div class="room-inspector__grid">
                    <div class="room-field">
                        <label for="slotX">X</label>
                        <input id="slotX" type="number" min="0" max="1024">
                    </div>
                    <div class="room-field">
                        <label for="slotY">Y</label>
                        <input id="slotY" type="number" min="0" max="1024">
                    </div>
                    <div class="room-field">
                        <label for="slotW">W</label>
                        <input id="slotW" type="number" min="1" max="1024">
                    </div>
                    <div class="room-field">
                        <label for="slotH">H</label>
                        <input id="slotH" type="number" min="1" max="1024">
                    </div>
                </div>

                <div class="room-actions">
                    <button class="room-action room-action--primary" type="button" id="saveLayoutBtn">
                        <i class="fas fa-save me-1"></i> Сохранить layout
                    </button>
                    <button class="room-action" type="button" id="resetLayoutBtn">
                        <i class="fas fa-undo me-1"></i> Сбросить
                    </button>
                    <button class="room-action" type="button" id="restoreDraftBtn" hidden>
                        <i class="fas fa-history me-1"></i> Восстановить черновик
                    </button>
                </div>

                <div class="room-status" id="roomStatus"></div>
            </div>
        </aside>
    </div>

    <script>
        window.roomDebugData = @json($roomSystem);
        window.roomDebugSaveUrl = @json($roomEditorSaveUrl);
    </script>
    <script>
        (function () {
            const data = window.roomDebugData;
            const saveUrl = window.roomDebugSaveUrl;
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const canvasSize = Number(data.canvas?.width || 1024);
            const canvas = document.getElementById('roomCanvas');
            const roomSelect = document.getElementById('roomSelect');
            const seasonSelect = document.getElementById('seasonSelect');
            const weatherSelect = document.getElementById('weatherSelect');
            const posterSelect = document.getElementById('posterSelect');
            const genderSelect = document.getElementById('genderSelect');
            const classSelect = document.getElementById('classSelect');
            const safeSelect = document.getElementById('safeSelect');
            const itemSelect = document.getElementById('itemSelect');
            const petSelect = document.getElementById('petSelect');
            const slotList = document.getElementById('slotList');
            const stageGrid = document.getElementById('roomStageGrid');
            const currentInfo = document.getElementById('roomCurrentInfo');
            const status = document.getElementById('roomStatus');
            const saveLayoutBtn = document.getElementById('saveLayoutBtn');
            const restoreDraftBtn = document.getElementById('restoreDraftBtn');
            const draftStorageKey = `room-debug-layout-draft:${data.assetBasePath || 'room-system'}`;
            const inputs = {
                label: document.getElementById('activeSlotLabel'),
                x: document.getElementById('slotX'),
                y: document.getElementById('slotY'),
                width: document.getElementById('slotW'),
                height: document.getElementById('slotH'),
            };
            const weatherInputs = {
                x: document.getElementById('weatherX'),
                y: document.getElementById('weatherY'),
                width: document.getElementById('weatherW'),
                height: document.getElementById('weatherH'),
            };
            const layerToggleInputs = Array.from(document.querySelectorAll('[data-room-layer-toggle]'));
            let rooms = JSON.parse(JSON.stringify(data.rooms || []));
            let activeRoomKey = rooms[0]?.key || null;
            let activeSlotKey = 'character';
            let dragState = null;
            let layoutDirty = false;
            let layoutSaveInFlight = false;
            let dirtyRoomKeys = new Set();
            const layerVisibility = {
                weather: true,
                poster: true,
                room: true,
                safe: true,
                desk: true,
                character: true,
                pet: true,
                slots: true,
            };
            const layerPresets = {
                full: {
                    weather: true,
                    poster: true,
                    room: true,
                    safe: true,
                    desk: true,
                    character: true,
                    pet: true,
                    slots: true,
                },
                posterWeather: {
                    weather: true,
                    poster: true,
                    room: true,
                    safe: false,
                    desk: false,
                    character: false,
                    pet: false,
                    slots: true,
                },
                character: {
                    weather: false,
                    poster: false,
                    room: true,
                    safe: false,
                    desk: false,
                    character: true,
                    pet: false,
                    slots: true,
                },
                cosmetics: {
                    weather: false,
                    poster: false,
                    room: true,
                    safe: true,
                    desk: true,
                    character: false,
                    pet: true,
                    slots: true,
                },
                slots: {
                    weather: false,
                    poster: false,
                    room: true,
                    safe: false,
                    desk: false,
                    character: false,
                    pet: false,
                    slots: true,
                },
            };

            const byKey = (items) => Object.fromEntries((items || []).map((item) => [item.key, item]));
            const weatherByKey = byKey(data.weather);
            const weatherBySeasonAndState = (data.weather || []).reduce((index, item) => {
                const season = item.season || '';
                const state = item.weather || item.state || '';
                if (!season || !state) return index;
                index[season] = index[season] || {};
                index[season][state] = item;
                return index;
            }, {});
            const posterByKey = byKey(data.posters);
            const safeByKey = byKey(data.safes);
            const itemByKey = byKey(data.items);
            const petByKey = byKey(data.pets);
            const hiddenEditorSlots = new Set(data.hiddenEditorSlots || []);
            const slotOverlayOrder = {
                _weather: 9,
                window_weather: 10,
                poster_wall: 20,
                desk_center: 30,
                safe_under_desk: 40,
                shelf_trophy_1: 50,
                shelf_trophy_2: 51,
                shelf_trophy_3: 52,
                character: 60,
                pet_right: 70,
            };

            const pct = (value) => `${(100 * Number(value || 0) / canvasSize).toFixed(4)}%`;
            const currentRoom = () => rooms.find((room) => room.key === activeRoomKey) || rooms[0];
            const currentSlot = () => activeSlotKey === '_weather'
                ? weatherPlacementSlot(currentRoom())
                : currentRoom()?.slots.find((slot) => slot.key === activeSlotKey);
            const visibleRoomSlots = (room) => (room?.slots || []).filter((slot) => !hiddenEditorSlots.has(slot.key));
            function fillSelect(select, items, labelFor) {
                select.innerHTML = '';
                (items || []).forEach((item) => {
                    const option = document.createElement('option');
                    option.value = item.key;
                    option.textContent = labelFor ? labelFor(item) : (item.name || item.key);
                    select.appendChild(option);
                });
            }

            function withEmptyOption(items, label) {
                return [{key: '', name: label || 'Пусто'}].concat(items || []);
            }

            function init() {
                fillSelect(roomSelect, rooms, (room) => `${room.name} · ${room.rankFrom}-${room.rankTo}`);
                fillSelect(seasonSelect, data.seasons);
                fillSelect(weatherSelect, data.weatherStates);
                seasonSelect.value = data.defaultSeasonKey || (data.seasons || [])[0]?.key || '';
                weatherSelect.value = data.defaultWeatherStateKey || (data.weatherStates || [])[0]?.key || '';
                fillSelect(posterSelect, data.posters);
                fillSelect(safeSelect, data.safes);
                fillSelect(itemSelect, withEmptyOption(data.items));
                fillSelect(petSelect, withEmptyOption(data.pets));
                itemSelect.value = (data.items || [])[0]?.key || '';
                petSelect.value = (data.pets || [])[0]?.key || '';
                renderStageGrid();
                bind();
                render();
            }

            function bind() {
                [roomSelect, seasonSelect, weatherSelect, posterSelect, genderSelect, classSelect, safeSelect, itemSelect, petSelect].forEach((select) => {
                    select.addEventListener('change', () => {
                        if (select === roomSelect) activeRoomKey = select.value;
                        render();
                    });
                });

	                Object.entries(inputs).forEach(([key, input]) => {
	                    if (key === 'label') return;
	                    input.addEventListener('input', () => {
	                        const slot = currentSlot();
	                        if (!slot) return;
	                        slot[key] = Number(input.value || 0);
	                        normalizeSlot(slot);
                            markCurrentRoomDirty();
	                        render();
	                    });
	                });

	                Object.entries(weatherInputs).forEach(([key, input]) => {
	                    input.addEventListener('input', () => {
	                        const room = currentRoom();
	                        if (!room) return;
	                        const placement = weatherPlacementSlot(room);
	                        placement[key] = Number(input.value || 0);
	                        normalizeWeatherPlacement(placement);
	                        activeSlotKey = '_weather';
                            markCurrentRoomDirty();
	                        render();
	                    });
	                });

                layerToggleInputs.forEach((input) => {
                    layerVisibility[input.dataset.roomLayerToggle] = input.checked;
                    input.addEventListener('change', () => {
                        layerVisibility[input.dataset.roomLayerToggle] = input.checked;
                        document.querySelectorAll('[data-room-layer-preset]').forEach((button) => button.classList.remove('is-active'));
                        render();
                    });
                });

                document.querySelectorAll('[data-room-layer-preset]').forEach((button) => {
                    button.addEventListener('click', () => {
                        setLayerVisibility(layerPresets[button.dataset.roomLayerPreset] || layerPresets.full, button.dataset.roomLayerPreset);
                    });
                });

                document.getElementById('resetLayoutBtn').addEventListener('click', () => {
                    rooms = JSON.parse(JSON.stringify(data.rooms || []));
                    dirtyRoomKeys.clear();
                    clearLayoutDraft();
                    setLayoutDirty(false);
                    render();
                    setStatus('Изменения сброшены до загруженного состояния.');
                });

                restoreDraftBtn.addEventListener('click', restoreLayoutDraft);
                saveLayoutBtn.addEventListener('click', saveLayout);
                window.addEventListener('pointermove', onPointerMove);
                window.addEventListener('pointerup', () => dragState = null);
                document.addEventListener('keydown', onKeyboardNudge);
                window.addEventListener('beforeunload', (event) => {
                    if (!layoutDirty) return;
                    event.preventDefault();
                    event.returnValue = '';
                });
                updateDraftButton();
            }

            function renderStageGrid() {
                stageGrid.innerHTML = '';
                rooms.forEach((room) => {
                    const visibleSlotCount = visibleRoomSlots(room).length + 1;
                    const card = document.createElement('button');
                    card.type = 'button';
                    card.className = 'room-stage-card';
                    card.dataset.roomStageCard = room.key;
                    card.innerHTML = `
                        <img src="${room.src}" alt="">
                        <span>
                            <strong>${room.visualProgression?.level || ''}. ${room.name}</strong>
                            <span>${room.rankFrom}-${room.rankTo}</span>
                            <span>${room.xpFrom}+ XP · сейф ${room.visualProgression?.safeOpen ? 'открыт' : 'проверить'}</span>
                            <span>${room.visualProgression?.summary || ''}</span>
                            <span class="room-stage-card__badges">
                                <span class="room-stage-card__badge">${visibleSlotCount} слотов</span>
                            </span>
                        </span>
                    `;
                    card.addEventListener('click', () => {
                        activeRoomKey = room.key;
                        roomSelect.value = room.key;
                        render();
                    });
                    stageGrid.appendChild(card);
                });
                updateStageGridState();
            }

            function updateStageGridState() {
                stageGrid.querySelectorAll('[data-room-stage-card]').forEach((card) => {
                    card.classList.toggle('is-active', card.dataset.roomStageCard === activeRoomKey);
                });
            }

            function setLayerVisibility(nextVisibility, presetKey = null) {
                Object.keys(layerVisibility).forEach((key) => {
                    if (Object.prototype.hasOwnProperty.call(nextVisibility, key)) {
                        layerVisibility[key] = Boolean(nextVisibility[key]);
                    }
                });

                layerToggleInputs.forEach((input) => {
                    input.checked = Boolean(layerVisibility[input.dataset.roomLayerToggle]);
                });

                document.querySelectorAll('[data-room-layer-preset]').forEach((button) => {
                    button.classList.toggle('is-active', Boolean(presetKey) && button.dataset.roomLayerPreset === presetKey);
                });

                render();
            }

            function render() {
                const room = currentRoom();
                if (!room) return;
                roomSelect.value = room.key;
                updateStageGridState();
                canvas.innerHTML = '';

                const weather = selectedWeather();
                const poster = posterByKey[posterSelect.value] || (data.posters || [])[0];
                const safe = safeByKey[safeSelect.value] || (data.safes || [])[0];
                const deskItem = itemSelect.value ? itemByKey[itemSelect.value] : null;
                const pet = petSelect.value ? petByKey[petSelect.value] : null;
                const gender = genderSelect.value;
                const classKey = classSelect.value;
                const character = data.characters?.[gender]?.[classKey];
                if (layerVisibility.weather && weather) addWeather(weather);
                if (layerVisibility.poster && poster) addSlotImage(poster.src, slotByKey(room, 'poster_wall'), 'room-layer--poster');
                if (layerVisibility.room) addFullImage(room.src);
                if (layerVisibility.safe && safe) addSlotImage(safe.src, slotByKey(room, 'safe_under_desk'));
                if (layerVisibility.desk && deskItem) addSlotImage(deskItem.src, slotByKey(room, 'desk_center'));
                if (layerVisibility.character && character) addSlotImage(character.src, slotByKey(room, 'character'), 'room-layer--character', {
	                    fit: 'cover',
                    position: 'center bottom',
                    scale: character.scale || 1,
                });
                if (layerVisibility.pet && pet) addSlotImage(pet.src, slotByKey(room, 'pet_right'));

	                if (layerVisibility.slots) {
	                    addSlotBox(weatherPlacementSlot(room));
	                    visibleRoomSlots(room).forEach(addSlotBox);
	                }
                renderSlotList();
                renderInspector();
                renderWeatherInspector();
                renderRoomInfo(room);
            }

            function renderRoomInfo(room) {
                if (!currentInfo) return;
                const fileLabel = (src) => String(src || '')
                    .split('?')[0]
                    .split('/')
                    .filter(Boolean)
                    .slice(-2)
                    .join('/');
                const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;',
                }[char]));

                currentInfo.innerHTML = `
                    <strong>${escapeHtml(room.visualProgression?.level || '?')}. ${escapeHtml(room.name || room.key)}</strong>
                    <p>${escapeHtml(room.visualProgression?.summary || '')}</p>
                    <dl>
                        <dt>Ранг</dt>
                        <dd>${escapeHtml(room.rankFrom || '')} - ${escapeHtml(room.rankTo || '')}</dd>
                        <dt>XP</dt>
                        <dd>${escapeHtml(room.xpFrom || 0)}+</dd>
                        <dt>Сейф</dt>
                        <dd>${room.visualProgression?.safeOpen ? 'открыт' : 'проверить'}</dd>
                        <dt>Комната</dt>
                        <dd title="${escapeHtml(fileLabel(room.src))}">${escapeHtml(fileLabel(room.src))}</dd>
                    </dl>
                `;
            }

            function addFullImage(src) {
                const img = document.createElement('img');
                img.className = 'room-layer';
                img.src = src;
                img.alt = '';
                canvas.appendChild(img);
            }

            function addWeather(weather) {
                const room = currentRoom();
	                const placement = weatherPlacementSlot(room);
	                const frame = document.createElement('div');
	                const img = document.createElement('img');
	                frame.className = 'room-layer room-weather-frame';
	                Object.assign(frame.style, slotStyle(placement));
	                img.src = weather.src;
	                img.alt = '';
	                frame.appendChild(img);
	                canvas.appendChild(frame);
	            }

            function addSlotImage(src, slot, extraClass, options = {}) {
                if (!slot) return;
                const img = document.createElement('img');
                img.className = `room-layer room-layer--slot ${extraClass || ''}`;
                img.src = src;
                img.alt = '';
                Object.assign(img.style, slotStyle(slot));
                if (options.fit) img.style.objectFit = options.fit;
                if (options.position) img.style.objectPosition = options.position;
                if (options.scale) {
                    img.style.transform = `scale(${options.scale})`;
                    img.style.transformOrigin = 'center bottom';
                }
                canvas.appendChild(img);
            }

            function addSlotBox(slot) {
                const box = document.createElement('div');
	                box.className = `room-slot ${slot.key === '_weather' ? 'room-slot--weather-placement' : ''} ${slot.key === activeSlotKey ? 'is-active' : ''}`;
                box.dataset.slot = slot.key;
                box.style.setProperty('--slot-color', slot.color || '#38bdf8');
                Object.assign(box.style, slotStyle(slot));
                box.style.zIndex = slot.key === activeSlotKey ? 200 : (slotOverlayOrder[slot.key] || 100);
                box.innerHTML = `<span class="room-slot__label">${slot.label}</span><i class="room-slot__handle"></i>`;
                box.addEventListener('pointerdown', (event) => startDrag(event, slot.key, event.target.classList.contains('room-slot__handle') ? 'resize' : 'move'));
                canvas.appendChild(box);
            }

	            function renderSlotList() {
	                const room = currentRoom();
	                slotList.innerHTML = '';
	                [weatherPlacementSlot(room)].concat(visibleRoomSlots(room)).forEach((slot) => {
	                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = `room-slot-button ${slot.key === activeSlotKey ? 'is-active' : ''}`;
                    button.style.setProperty('--slot-color', slot.color || '#38bdf8');
                    button.innerHTML = `<span><i></i> ${slot.label}</span><small>${slot.x}, ${slot.y}, ${slot.width}x${slot.height}</small>`;
                    button.addEventListener('click', () => {
                        activeSlotKey = slot.key;
                        render();
                    });
                    slotList.appendChild(button);
                });
            }

	            function renderInspector() {
	                const slot = currentSlot();
	                if (!slot) return;
	                inputs.label.value = slot.label;
	                inputs.x.min = slot.key === '_weather' ? -2048 : 0;
	                inputs.y.min = slot.key === '_weather' ? -2048 : 0;
	                inputs.x.max = slot.key === '_weather' ? 2048 : canvasSize;
	                inputs.y.max = slot.key === '_weather' ? 2048 : canvasSize;
	                inputs.width.max = slot.key === '_weather' ? 3072 : canvasSize;
	                inputs.height.max = slot.key === '_weather' ? 3072 : canvasSize;
	                inputs.x.value = Math.round(slot.x);
                inputs.y.value = Math.round(slot.y);
                inputs.width.value = Math.round(slot.width);
                inputs.height.value = Math.round(slot.height);
            }

	            function renderWeatherInspector() {
	                const placement = weatherPlacementSlot(currentRoom());
	                weatherInputs.x.value = Math.round(placement.x || 0);
	                weatherInputs.y.value = Math.round(placement.y || 0);
	                weatherInputs.width.value = Math.round(placement.width || 1024);
	                weatherInputs.height.value = Math.round(placement.height || 1024);
	            }

            function slotByKey(room, key) {
                return room.slots.find((slot) => slot.key === key);
            }

            function slotStyle(slot) {
                return {
                    left: pct(slot.x),
                    top: pct(slot.y),
                    width: pct(slot.width),
                    height: pct(slot.height),
                };
            }

	            function selectedWeather() {
                return weatherBySeasonAndState[seasonSelect.value]?.[weatherSelect.value]
                    || weatherByKey[`${seasonSelect.value}_${weatherSelect.value}`]
                    || (data.weather || [])[0];
            }

            function startDrag(event, slotKey, mode) {
                event.preventDefault();
                activeSlotKey = slotKey;
                const slot = currentSlot();
                const rect = canvas.getBoundingClientRect();
                dragState = {
                    mode,
                    slot,
                    startX: event.clientX,
                    startY: event.clientY,
                    rect,
                    original: {...slot},
                };
                render();
            }

            function onPointerMove(event) {
                if (!dragState) return;
                const dx = (event.clientX - dragState.startX) / dragState.rect.width * canvasSize;
                const dy = (event.clientY - dragState.startY) / dragState.rect.height * canvasSize;
                const slot = dragState.slot;

                if (dragState.mode === 'resize') {
                    slot.width = dragState.original.width + dx;
                    slot.height = dragState.original.height + dy;
                } else {
                    slot.x = dragState.original.x + dx;
                    slot.y = dragState.original.y + dy;
                }

	                normalizeSlot(slot);
                    markCurrentRoomDirty();
	                render();
	            }

            function onKeyboardNudge(event) {
                const deltaByKey = {
                    ArrowLeft: [-1, 0],
                    ArrowRight: [1, 0],
                    ArrowUp: [0, -1],
                    ArrowDown: [0, 1],
                };
                const delta = deltaByKey[event.key];
                if (!delta || isTypingTarget(event.target)) return;

                const slot = currentSlot();
                if (!slot) return;

                event.preventDefault();
                nudgeActiveSlot(slot, delta[0], delta[1], event.shiftKey ? 10 : 1, event.altKey);
                markCurrentRoomDirty();
                render();
            }

            function nudgeActiveSlot(slot, dx, dy, step, resize = false) {
                if (resize) {
                    slot.width += dx * step;
                    slot.height += dy * step;
                } else {
                    slot.x += dx * step;
                    slot.y += dy * step;
                }

                normalizeSlot(slot);
            }

            function isTypingTarget(target) {
                if (!target) return false;
                const tagName = String(target.tagName || '').toUpperCase();
                return ['INPUT', 'SELECT', 'TEXTAREA', 'BUTTON'].includes(tagName) || Boolean(target.isContentEditable);
            }

	            function normalizeSlot(slot) {
	                if (slot.key === '_weather') {
	                    normalizeWeatherPlacement(slot);
	                    return;
	                }

	                slot.x = Math.max(0, Math.min(canvasSize - 1, Math.round(slot.x)));
                slot.y = Math.max(0, Math.min(canvasSize - 1, Math.round(slot.y)));
                slot.width = Math.max(1, Math.min(canvasSize - slot.x, Math.round(slot.width)));
                slot.height = Math.max(1, Math.min(canvasSize - slot.y, Math.round(slot.height)));
            }

	            function normalizeWeatherPlacement(placement) {
	                placement.x = Math.max(-2048, Math.min(2048, Math.round(Number.isFinite(Number(placement.x)) ? Number(placement.x) : 0)));
	                placement.y = Math.max(-2048, Math.min(2048, Math.round(Number.isFinite(Number(placement.y)) ? Number(placement.y) : 0)));
	                placement.width = Math.max(50, Math.min(3072, Math.round(Number.isFinite(Number(placement.width)) ? Number(placement.width) : Number(placement.scale || 1024))));
	                placement.height = Math.max(50, Math.min(3072, Math.round(Number.isFinite(Number(placement.height)) ? Number(placement.height) : Number(placement.scale || placement.width || 1024))));
	                placement.scale = Math.max(50, Math.min(3072, Math.round(Math.max(placement.width, placement.height))));
	                placement.cropX = Math.max(-2048, Math.min(2048, Math.round(Number.isFinite(Number(placement.cropX)) ? Number(placement.cropX) : 0)));
	                placement.cropY = Math.max(-2048, Math.min(2048, Math.round(Number.isFinite(Number(placement.cropY)) ? Number(placement.cropY) : 0)));
	            }

	            function weatherPlacementSlot(room) {
	                const windowSlot = slotByKey(room, 'window_weather') || {x: 0, y: 0, width: canvasSize, height: canvasSize};
	                room.weatherPlacement = room.weatherPlacement || {};
	                const placement = room.weatherPlacement;
	                const hasExplicitBox = Number.isFinite(Number(placement.x))
	                    && Number.isFinite(Number(placement.y))
	                    && Number.isFinite(Number(placement.width))
	                    && Number.isFinite(Number(placement.height));
	                if (!hasExplicitBox) {
	                    const scale = Number(placement.scale || 1024);
	                    placement.x = Number(windowSlot.x || 0) - Number(placement.cropX || 0);
	                    placement.y = Number(windowSlot.y || 0) - Number(placement.cropY || 0);
	                    placement.width = scale;
	                    placement.height = scale;
	                }

	                placement.key = '_weather';
	                placement.label = 'Картинка погоды';
	                placement.color = '#06b6d4';
	                normalizeWeatherPlacement(placement);

	                return placement;
            }

            function saveLayout() {
                if (layoutSaveInFlight) return;
                const room = currentRoom();
                const slots = {};
                room.slots.forEach((slot) => {
                    slots[slot.key] = {
                        x: Math.round(slot.x),
                        y: Math.round(slot.y),
                        width: Math.round(slot.width),
                        height: Math.round(slot.height),
                    };
                });

                setLayoutSaving(true);
                setStatus('Сохраняю...');
                fetch(saveUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        room: room.key,
                        slots,
	                        weather: weatherPlacementSlot(room),
                    }),
                })
                    .then((response) => response.json().then((payload) => ({response, payload})))
                    .then(({response, payload}) => {
                        if (!response.ok || !payload.ok) throw new Error(payload.message || 'Не удалось сохранить layout.');
                        room.slots = room.slots.map((slot) => ({...slot, ...(payload.slots[slot.key] || {})}));
                        room.weatherPlacement = payload.weather || room.weatherPlacement;
                        markRoomClean(room.key);
                        setStatus(layoutDirty
                            ? `Сохранено: ${payload.savedAt}. Остались несохраненные изменения: ${dirtyRoomKeys.size}.`
                            : `Сохранено: ${payload.savedAt}`);
                        render();
                    })
                    .catch((error) => setStatus(error.message || 'Ошибка сохранения.'))
                    .finally(() => setLayoutSaving(false));
            }

            function setLayoutSaving(isSaving) {
                layoutSaveInFlight = Boolean(isSaving);
                saveLayoutBtn.disabled = layoutSaveInFlight;
            }

            function markCurrentRoomDirty() {
                const room = currentRoom();
                if (room?.key) dirtyRoomKeys.add(room.key);
                setLayoutDirty(true);
            }

            function markRoomClean(roomKey) {
                dirtyRoomKeys.delete(roomKey);
                setLayoutDirty(dirtyRoomKeys.size > 0);
            }

            function setLayoutDirty(isDirty) {
                pruneDirtyRoomKeys();
                layoutDirty = Boolean(isDirty) || dirtyRoomKeys.size > 0;
                saveLayoutBtn.classList.toggle('room-action--dirty', layoutDirty);
                if (layoutDirty) {
                    persistLayoutDraft();
                    setStatus('Есть несохраненные изменения layout.');
                } else {
                    clearLayoutDraft();
                }
            }

            function pruneDirtyRoomKeys() {
                const knownRoomKeys = new Set(rooms.map((room) => room.key));
                dirtyRoomKeys = new Set([...dirtyRoomKeys].filter((roomKey) => knownRoomKeys.has(roomKey)));
            }

            function persistLayoutDraft() {
                pruneDirtyRoomKeys();
                const dirtyRooms = rooms.filter((room) => dirtyRoomKeys.has(room.key));
                if (dirtyRooms.length === 0) {
                    clearLayoutDraft();
                    return;
                }

                try {
                    window.localStorage.setItem(draftStorageKey, JSON.stringify({
                        savedAt: new Date().toISOString(),
                        activeRoomKey,
                        rooms: dirtyRooms.map((room) => ({
                            key: room.key,
                            weatherPlacement: room.weatherPlacement || null,
                            slots: (room.slots || []).map((slot) => ({
                                key: slot.key,
                                x: Math.round(slot.x),
                                y: Math.round(slot.y),
                                width: Math.round(slot.width),
                                height: Math.round(slot.height),
                            })),
                        })),
                    }));
                } catch (error) {
                    return;
                }

                updateDraftButton();
            }

            function readLayoutDraft({dropStale = false} = {}) {
                try {
                    const draft = JSON.parse(window.localStorage.getItem(draftStorageKey) || 'null');
                    if (!draft || !Array.isArray(draft.rooms)) return null;

                    const knownRoomKeys = new Set(rooms.map((room) => room.key));
                    const hasKnownRooms = draft.rooms.some((room) => knownRoomKeys.has(room.key));
                    if (!hasKnownRooms) {
                        if (dropStale) {
                            window.localStorage.removeItem(draftStorageKey);
                        }

                        return null;
                    }

                    return draft;
                } catch (error) {
                    return null;
                }
            }

            function clearLayoutDraft() {
                try {
                    window.localStorage.removeItem(draftStorageKey);
                } catch (error) {
                    return;
                }

                updateDraftButton();
            }

            function updateDraftButton() {
                if (!restoreDraftBtn) return;
                restoreDraftBtn.hidden = !readLayoutDraft({dropStale: true});
            }

            function restoreLayoutDraft() {
                const draft = readLayoutDraft({dropStale: true});
                if (!draft) {
                    setStatus('Локальный черновик не найден.');
                    updateDraftButton();
                    return;
                }

                const draftRooms = Object.fromEntries(draft.rooms.map((room) => [room.key, room]));
                const restoredRoomKeys = [];
                rooms.forEach((room) => {
                    const draftRoom = draftRooms[room.key];
                    if (!draftRoom) return;

                    restoredRoomKeys.push(room.key);
                    const draftSlots = Object.fromEntries((draftRoom.slots || []).map((slot) => [slot.key, slot]));
                    room.slots = room.slots.map((slot) => ({...slot, ...(draftSlots[slot.key] || {})}));
                    if (draftRoom.weatherPlacement) {
                        room.weatherPlacement = {...draftRoom.weatherPlacement};
                    }
                });

                dirtyRoomKeys = new Set(restoredRoomKeys);
                if (dirtyRoomKeys.size === 0) {
                    clearLayoutDraft();
                    setLayoutDirty(false);
                    setStatus('Локальный черновик устарел: подходящих комнат не найдено.');
                    return;
                }

                if (rooms.some((room) => room.key === draft.activeRoomKey)) {
                    activeRoomKey = draft.activeRoomKey;
                    roomSelect.value = activeRoomKey;
                }

                setLayoutDirty(true);
                render();
                setStatus('Локальный черновик layout восстановлен. Проверьте и сохраните.');
            }

            function setStatus(message) {
                status.textContent = message;
            }

            init();
        })();
    </script>
@endsection
