@extends('layouts.left-menu')

@section('title', 'Массовое начисление GC')

@section('content')
    @php
        $selectedRecipientIds = collect(old('recipients', []))->map(fn ($id) => (int) $id);
    @endphp

    <div class="container-xl px-0">
        <div class="gc-card gc-page-header mb-3">
            <div class="min-width-0">
                <a class="assessment-back-link" href="{{ url('/insider/market') }}"><i class="fas fa-chevron-left"></i> В магазин</a>
                <h2 class="mb-1">Массовое начисление GC</h2>
                <p class="mb-0 text-muted">Указанная сумма будет начислена каждому выбранному получателю.</p>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-12 col-lg-8">
                <div class="gc-card">
                    <form method="POST" action="{{ url('/insider/market/credit') }}">
                        @csrf

                        <div class="p-3 p-md-4">
                            <div class="mb-3">
                                <label for="amount" class="form-label">Сумма каждому получателю, GC</label>
                                <input id="amount" type="number" class="form-control rounded-3" name="amount" value="{{ old('amount') }}" min="1" max="10000" step="1" required>
                                <div class="form-text">От 1 до 10 000 GC.</div>
                                @error('amount')
                                    <span class="text-danger small d-block mt-1"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="recipients" class="form-label d-flex align-items-center justify-content-between gap-2">
                                    <span>Получатели</span>
                                    <span class="badge rounded-pill bg-body-tertiary form-selected-count" id="recipients-selected-count">{{ $selectedRecipientIds->count() }} выбрано</span>
                                </label>
                                <select id="recipients" class="form-select rounded-3" name="recipients[]" multiple data-selected-count="#recipients-selected-count" data-enhanced-multiselect data-placeholder="Выберите получателей" data-search-placeholder="Найти по имени или почте">
                                    @foreach ($recipients as $recipient)
                                        <option value="{{ $recipient->id }}" @selected($selectedRecipientIds->contains($recipient->id))>{{ $recipient->name }} — {{ $recipient->email }}</option>
                                    @endforeach
                                </select>
                                @foreach ($errors->get('recipients') as $message)
                                    <span class="text-danger small d-block mt-1"><strong>{{ $message }}</strong></span>
                                @endforeach
                                @foreach ($errors->get('recipients.*') as $messages)
                                    @foreach ($messages as $message)
                                        <span class="text-danger small d-block mt-1"><strong>{{ $message }}</strong></span>
                                    @endforeach
                                @endforeach
                            </div>

                            <div>
                                <label for="comment" class="form-label">Комментарий</label>
                                <textarea id="comment" class="form-control rounded-3" name="comment" rows="3" maxlength="255" required>{{ old('comment') }}</textarea>
                                <div class="form-text">Будет виден получателям в истории GC.</div>
                                @error('comment')
                                    <span class="text-danger small d-block mt-1"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>
                        </div>

                        <div class="gc-form-footer flex-column-reverse flex-sm-row justify-content-end gap-2">
                            <a class="btn btn-outline-secondary rounded-3" href="{{ url('/insider/market') }}">Отмена</a>
                            <button type="submit" class="btn btn-success rounded-3 px-3 fw-medium"><i class="fas fa-coins me-1"></i>Начислить</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
