<article class="mr-inline" id="inline-{{ $proposal['id'] }}-{{ substr(hash('sha256',$before.$after),0,8) }}" data-proposal-inline="{{ $proposal['id'] }}" data-review-scope="{{ $proposal['payload']['scope']??'local' }}">
    <h4 class="h6">{{ $proposal['title'] }}</h4>
    @if($operation['op']==='image')
    <div class="mr-image-pair"><figure><figcaption>Исходная иллюстрация</figcaption><button type="button" class="mr-image-preview" data-image-preview="{{ $operation['old'] }}" aria-label="Увеличить иллюстрацию"><img src="{{ $operation['old'] }}" alt="Исходная иллюстрация"></button></figure><figure><figcaption>Предлагаемый вариант</figcaption><button type="button" class="mr-image-preview" data-image-preview="/material-resources/{{ $operation['resource_id'] }}" aria-label="Увеличить иллюстрацию"><img src="/material-resources/{{ $operation['resource_id'] }}" alt="Предлагаемая иллюстрация"></button></figure></div>
    @else<div class="markdown">{!! \App\MaterialReview\Diff::html($before,$after) !!}</div>@endif
    <details><summary>Почему стоит изменить</summary><p>{{ $proposal['reason'] }}</p></details>
    @include('material-review.proposal-actions')
</article>
<div data-review-original="{{ $proposal['payload']['scope']??'local' }}" hidden>{!! parsedown_math($before) !!}</div>
