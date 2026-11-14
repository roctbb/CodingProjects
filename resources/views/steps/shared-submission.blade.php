<!doctype html>
<html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Решение · Coding Projects</title>
<link rel="stylesheet" href="{{ asset('build/css/app.css') }}"></head><body><main class="container py-4"><a href="/">Coding Projects</a><h1 class="h3 mt-4">Опубликованное решение</h1>
<p>Автор разрешил просмотр отправленных материалов.</p>
@if($text)<pre class="gc-execution-output">{{ $text }}</pre>@endif
@foreach($files as $file)<section class="mb-4"><h2 class="h5">{{ $file['name'] }}</h2><a href="{{ $file['url'] }}">Скачать оригинал</a>
@if(isset($file['text']))<pre class="gc-execution-output">{{ $file['text'] }}</pre>@endif</section>@endforeach
</main></body></html>
