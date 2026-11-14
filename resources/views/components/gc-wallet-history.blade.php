@props(['transactions'])
<div class="gc-rows">
    @forelse($transactions as $transaction)
        <div class="gc-row gc-row--split">
            <div class="min-width-0"><div>{{ $transaction->displayComment() }}</div><time class="gc-meta-label">{{ optional($transaction->created_at)->format('d.m.Y H:i') ?: 'Дата не указана' }}</time></div>
            <span class="gc-amount {{ $transaction->price > 0 ? 'text-primary' : '' }}">{{ $transaction->price > 0 ? '+' : '' }}{{ $transaction->price }} GC</span>
        </div>
    @empty
        <p class="text-muted mb-0">Операций пока нет. Начисления и покупки появятся здесь.</p>
    @endforelse
</div>
