@php($executable=!in_array($proposal['kind'],['question','plan','unsupported']) && (!empty($proposal['payload']['operations']) || isset($proposal['payload']['targets'])))
<div class="d-flex flex-wrap gap-2 mt-2">
    @if($executable && $proposal['decision']!=='accepted')<button class="btn btn-sm btn-success" data-decision="accepted" data-proposal="{{ $proposal['id'] }}" @disabled($disabled??false) aria-label="Принять: {{ $proposal['title'] }}">✓ Принять</button>@endif
    @if($proposal['decision']!=='rejected')<button class="btn btn-sm btn-outline-danger" data-decision="rejected" data-proposal="{{ $proposal['id'] }}">✕ Отклонить</button>@endif
    @if($proposal['decision']!=='deferred')<button class="btn btn-sm btn-outline-secondary" data-decision="deferred" data-proposal="{{ $proposal['id'] }}">Отложить</button>@endif
    @if($proposal['decision']!=='pending')<button class="btn btn-sm btn-outline-secondary" data-decision="pending" data-proposal="{{ $proposal['id'] }}">Отменить решение</button>@endif
    @if($executable && count($proposal['payload']['operations']??[])>0 && collect($proposal['payload']['operations'])->every(fn($o)=>in_array($o['op'],['fragment','replace','notebook_cell'],true)))<button class="btn btn-sm btn-outline-secondary" data-edit-proposal="{{ $proposal['id'] }}">Изменить вариант</button>@endif
</div>
