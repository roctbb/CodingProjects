@extends('layouts.left-menu')
@section('title', 'Уведомления — '.$course->name)
@section('content')
<div class="container-fluid px-0">
    <x-gc-course-header :course="$course" active="notifications" />
    <div class="gc-course-section-heading"><div><h2 class="mb-1">Уведомления курса</h2><p class="gc-help-text mb-0">В чаты — при установке и изменении сроков. Напоминания — за сутки до каждого срока и при его наступлении.</p></div></div>
    @if(session('status'))<div class="alert alert-info">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <div class="row g-3 mb-3">
        <section class="col-12 col-lg-6"><div class="gc-card p-3 h-100">
            <h3 class="h5 mb-3">Куда отправлять</h3>
            <form method="POST" action="{{ url('/insider/courses/'.$course->id.'/notifications') }}">@csrf
                <label class="form-check mb-3"><input class="form-check-input" type="checkbox" name="teachers_enabled" value="1" @checked($settings?->teachers_enabled ?? true)><span class="form-check-label">В личный Telegram преподавателей курса</span></label>
                <label class="form-check mb-3"><input class="form-check-input" type="checkbox" name="chat_enabled" value="1" @checked($settings?->chat_enabled ?? true)><span class="form-check-label">В подключённые чаты курса</span></label>
                <p class="gc-help-text">Преподавателям нужно подключить Telegram в личных настройках. Ученики включают личные напоминания сами на вкладке «Дедлайны».</p>
                <button class="btn btn-primary btn-sm">Сохранить</button>
            </form>
        </div></section>
        <section class="col-12 col-lg-6"><div class="gc-card p-3 h-100">
            <h3 class="h5 mb-3"><i class="fab fa-telegram" aria-hidden="true"></i> Чаты курса</h3>
            <p class="gc-help-text">Добавьте бота в группу, затем подтвердите её здесь. Для отдельной темы отправьте команду подключения внутри этой темы.</p>
            @foreach($chats as $chat)
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <div class="min-width-0"><strong class="text-break">{{ $chat->title }}</strong><small class="d-block gc-help-text">{{ ['active' => 'Подключён', 'pending' => 'Ждёт подтверждения', 'disabled' => 'Отключён'][$chat->status] ?? $chat->status }}@if($chat->thread_id) · Тема {{ $chat->thread_id }}@endif</small></div>
                    @if($chat->status !== 'disabled')<form method="POST" action="{{ url('/insider/courses/'.$course->id.'/notifications/chats/'.$chat->id) }}">@csrf
                        @if($chat->status === 'pending')<button name="action" value="confirm" class="btn btn-primary btn-sm">Подтвердить</button>@endif
                        <button name="action" value="unlink" class="btn btn-outline-secondary btn-sm">Отключить</button>
                    </form>@endif
                </div>
            @endforeach
            <form method="POST" action="{{ url('/insider/courses/'.$course->id.'/notifications/link') }}">@csrf<button class="btn btn-outline-secondary btn-sm"><i class="fas fa-plus" aria-hidden="true"></i> Подключить чат</button></form>
            @if($link = session('telegram_course_link'))
                <div class="gc-help-note mt-3"><a class="btn btn-primary btn-sm mb-2" href="{{ $link['url'] }}" target="_blank" rel="noopener">Выбрать группу в Telegram</a><p class="small mb-1">Или отправьте команду в нужной теме. Ссылка одноразовая, действует 30 минут. Затем обновите эту страницу.</p><code class="d-block text-break">{{ $link['command'] }}</code></div>
            @endif
        </div></section>
    </div>
    <section class="gc-card p-3">
        <h3 class="h5 mb-2">Доставка напоминаний</h3>
        <p class="gc-help-text">Планировщик ставит сообщения в очередь в нужную минуту. Время получения зависит от очереди и Telegram. После сетевого обрыва результат может быть неизвестен — повтор запускается вручную, чтобы избежать дублей.</p>
        @php($statuses = ['pending'=>'Ожидает', 'queued'=>'В очереди', 'sending'=>'Отправляется', 'sent'=>'Доставлено в Telegram', 'retry'=>'Повторная попытка', 'failed'=>'Ошибка', 'uncertain'=>'Результат неизвестен', 'cancelled'=>'Отменено'])
        @forelse($deliveries as $delivery)
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 py-2">
                <div><strong>{{ $statuses[$delivery->status] ?? $delivery->status }}</strong><small class="gc-help-text d-block">{{ \Carbon\Carbon::parse($delivery->created_at, 'UTC')->setTimezone(config('app.timezone'))->format('d.m H:i') }} · {{ ['teacher'=>'Преподаватель', 'student'=>'Ученик', 'chat'=>'Чат курса'][$delivery->recipient_type] }} · Попыток: {{ $delivery->attempts }}@if($delivery->last_error) · {{ $delivery->last_error }}@endif</small></div>
                @if(in_array($delivery->status, ['failed','uncertain']))<form method="POST" action="{{ url('/insider/courses/'.$course->id.'/notifications/deliveries/'.$delivery->id.'/retry') }}">@csrf<button class="btn btn-outline-secondary btn-sm" data-confirm="Повторить отправку? При неизвестном результате предыдущее сообщение могло быть доставлено.">Повторить</button></form>@endif
            </div>
        @empty<p class="gc-help-text mb-0">Здесь появится история отправки. Прошедшие сроки после подключения не рассылаются.</p>@endforelse
        <div class="mt-3">{{ $deliveries->links() }}</div>
    </section>
</div>
@endsection
