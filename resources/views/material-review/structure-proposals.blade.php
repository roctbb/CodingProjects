@foreach($draft['proposals']??[] as $proposal)
    @if($proposal['decision']!=='pending'||($proposal['payload']['scope']??'local')==='local'||in_array($proposal['kind'],['question','plan','unsupported'])) @continue @endif
    @php
        $proposedDocuments=null; $previewError=null;
        try {
            $proposedDocuments=isset($proposal['payload']['targets'])?\App\MaterialReview\Documents::merge($proposal['payload']['against']??$draft['base_documents'],$proposal['payload']['targets'],$documents):app(\App\MaterialReview\Plan::class)->apply($documents,$proposal['payload']['operations']??[]);
            $previewChanges=app(\App\MaterialReview\Plan::class)->validate($documents,$proposedDocuments);
        } catch(\App\MaterialReview\Problem $e) { $previewError=$e->getMessage(); }
    @endphp
    <details class="gc-card mr-structural-proposal" data-proposal-inline="{{ $proposal['id'] }}" data-review-scope="{{ $proposal['payload']['scope'] }}">
        <summary>{{ $proposal['title'] }}</summary><p class="gc-help-text mt-2">{{ $proposal['reason'] }}</p>
        @if($previewError)<p class="alert alert-warning">{{ $previewError }}</p>
        @else
            <div class="mr-compare">@foreach(['Сейчас'=>$documents,'Предлагается'=>$proposedDocuments] as $heading=>$list)<div><h3 class="h6">{{ $heading }}</h3>@foreach($list as $doc)<ol>@foreach($doc['steps'] as $step)<li>{{ $step['fields']['name'] }} @if($step['archived'])<span class="text-danger">исключена</span>@elseif(is_string($step['id'])&&str_starts_with($step['id'],'new:'))<span class="text-success">добавлена</span>@endif<span class="gc-help-text d-block">{{ count($step['tasks']) }} задач</span></li>@endforeach</ol>@endforeach</div>@endforeach</div>
            <details><summary>Прочитать предлагаемый результат целиком</summary>@include('material-review.document',['documents'=>$proposedDocuments,'draft'=>null,'validation'=>['changes'=>$previewChanges],'prefix'=>'proposal-'.$proposal['id'].'-'])</details>
        @endif
        @include('material-review.proposal-actions',['disabled'=>(bool)$previewError])
    </details>
@endforeach
