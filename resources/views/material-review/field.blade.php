@php
    $path=$entity.':'.rawurlencode((string)$entityId).':'.$field;
    $change=$validation['changes'][$path]??null;
    $isNotebook=$field==='theory' && !empty($nodeFields['is_notebook']);
    $canEdit=$draft && $draft['state']!=='published';
    $pending=$isNotebook?null:\App\MaterialReview\Rendering::pending((string)($value??''),$documents,$draft,$entity,$entityId,$field);
@endphp
<section class="mr-field" id="{{ $prefix??'' }}field-{{ $entity }}-{{ $entityId }}-{{ $field }}">
    <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
        <h3 class="h6 mb-0">{{ $label }}</h3>
        @if($canEdit)<button class="btn btn-sm btn-outline-secondary" type="button" @if($isNotebook) data-edit-book @else data-edit-field @endif data-entity="{{ $entity }}" data-id="{{ $entityId }}" data-field="{{ $field }}" data-value="{{ $value ?? '' }}" data-expected="{{ \App\MaterialReview\Documents::encode($nodeFields[$field]??null) }}">{{ $isNotebook?'Редактировать ячейки':'Редактировать' }}</button>@endif
    </div>
    @if($isNotebook)
        @if($change)<details><summary>Исходная тетрадка</summary><div class="notebook-container" data-notebook-content="{{ $change['before'] }}"></div></details>@endif
        <div class="notebook-container" data-notebook-content="{{ $value }}"></div>
    @else
        <div class="markdown">{!! \App\MaterialReview\Rendering::versioned($pending ?? ($change ? \App\MaterialReview\Diff::html(is_scalar($change['before'])?(string)$change['before']:'',(string)($value??'')) : parsedown_math((string)($value??''))),$documents) !!}</div>
        @if($pending!==null && $change)<details><summary>Все изменения поля относительно основы</summary>{!! \App\MaterialReview\Diff::html((string)$change['before'],(string)$value) !!}</details>@endif
    @endif
    @if($isNotebook)
    @foreach($draft['proposals']??[] as $proposal)
        @if($proposal['decision']!=='pending') @continue @endif
        @foreach($proposal['payload']['operations']??[] as $operation)
            @if(($operation['entity']??'')!==$entity || (string)($operation['id']??'')!==(string)$entityId || ($operation['field']??'')!==$field) @continue @endif
            @php
                $proposed=null; $error=null;
                try {
                    $preview=app(\App\MaterialReview\Plan::class)->apply($documents,[$operation]);
                    $pn=app(\App\MaterialReview\Plan::class)->node($preview,$entity,$entityId); $proposed=$pn['fields'][$field];
                } catch(\App\MaterialReview\Problem $e) { $error=$e->getMessage(); }
            @endphp
            <article class="mr-inline" id="inline-{{ $proposal['id'] }}-{{ $loop->index }}" data-proposal-inline="{{ $proposal['id'] }}" data-review-scope="{{ $proposal['payload']['scope']??'local' }}">
                <h4 class="h6">{{ $proposal['title'] }}</h4>
                @if($error)<p class="alert alert-warning">{{ $error }}</p>
                @elseif(($operation['op']??'')==='image')
                    <div class="mr-image-pair">
                        <figure><figcaption>Исходная иллюстрация</figcaption><button type="button" class="mr-image-preview" data-image-preview="{{ $operation['old'] }}" aria-label="Увеличить иллюстрацию"><img src="{{ $operation['old'] }}" alt="Исходная иллюстрация"></button></figure>
                        <figure><figcaption>Предлагаемый вариант</figcaption><button type="button" class="mr-image-preview" data-image-preview="/material-resources/{{ $operation['resource_id'] }}" aria-label="Увеличить иллюстрацию"><img src="/material-resources/{{ $operation['resource_id'] }}" alt="Предлагаемая иллюстрация"></button></figure>
                    </div>
                @elseif($isNotebook)<div class="notebook-container" data-notebook-content="{{ $proposed }}"></div>
                @else<div class="markdown">{!! \App\MaterialReview\Diff::html((string)$value,(string)$proposed) !!}</div>@endif
                <details><summary>Почему стоит изменить</summary><p>{{ $proposal['reason'] }}</p></details>
                @include('material-review.proposal-actions',['proposal'=>$proposal,'disabled'=>(bool)$error])
            </article>
        @endforeach
    @endforeach
    @endif
</section>
