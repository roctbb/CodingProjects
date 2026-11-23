@props(['items', 'allUrl' => null, 'cardUrl' => null, 'showCourse' => false, 'embedded' => false, 'limit' => 3, 'personal' => true])

@if($items->isNotEmpty())
    @php
        $previewItems = $items->take($limit);
        $rowTag = $cardUrl ? 'span' : 'a';
    @endphp
    <x-gc-panel title="Ближайшие сроки" :embedded="$embedded" :href="$cardUrl" {{ $attributes->class(['gc-deadline-preview-link' => $cardUrl]) }}>
        <x-slot:actions>
            @if($cardUrl)
                <i class="fas fa-chevron-right text-muted" aria-hidden="true"></i>
            @elseif($allUrl)
                <a class="gc-quiet-link small" href="{{ $allUrl }}">Все сроки</a>
            @endif
        </x-slot:actions>
        <ul class="list-unstyled gc-rows mb-0">
            @foreach($previewItems as $deadline)
                @php $penaltyPercent = (float) round(100 - (float) $deadline->penalty * 100, 2); @endphp
                <li>
                    <{{ $rowTag }} class="gc-deadline-row" @unless($cardUrl) href="{{ url('/insider/courses/'.$deadline->course_id.'/steps/'.$deadline->task->step_id.'#task'.$deadline->task_id) }}" @endunless>
                        <span class="gc-deadline-row__schedule">
                            <time class="gc-deadline-row__date @if($personal && $deadline->is_overdue) text-danger @endif" datetime="{{ $deadline->expiration->format('Y-m-d') }}" title="{{ $deadline->expiration->format('d.m.Y') }}">{{ $deadline->expiration->format('d.m') }}</time>
                            <span class="gc-deadline-row__penalty" title="После {{ $deadline->expiration->format('d.m') }} — {{ $penaltyPercent ? 'штраф '.$penaltyPercent.'%' : 'без штрафа' }}"><span class="visually-hidden">Штраф после срока: </span>{{ $penaltyPercent ? '−'.$penaltyPercent : '0' }}%</span>
                        </span>
                        <span class="min-width-0">
                            <strong>{{ $deadline->task->name }}</strong>
                            <small>{{ $showCourse ? $deadline->course->name : $deadline->task->step->lesson->name }}
                                @if($personal && ($deadline->is_overdue || $deadline->is_today || $deadline->expiration->isTomorrow() || $deadline->is_soon))
                                <span class="gc-deadline-row__state @if($deadline->is_overdue) text-danger @elseif($deadline->is_today) text-primary @endif">
                                    · @if($deadline->is_overdue)Просрочено
                                    @elseif($deadline->is_today)Сегодня
                                    @elseif($deadline->expiration->isTomorrow())Завтра
                                    @elseif($deadline->is_soon)Скоро
                                    @endif
                                </span>
                                @endif
                            </small>
                        </span>
                    </{{ $rowTag }}>
                </li>
            @endforeach
        </ul>
    </x-gc-panel>
@endif
