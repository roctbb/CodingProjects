@extends('layouts.empty')

@section('title')
    Обратная связь
@endsection

@section('head')
    <script>
        function toggleBlock(id, force, groupClass) {
            const block = document.getElementById(id);

            if (force === undefined) {
                if (!block) {
                    return;
                }
                block.classList.toggle('d-none');
                return;
            }

            var hide = document.getElementsByClassName(groupClass);
            for (var i = 0; i < hide.length; i++) {
                hide[i].classList.add('d-none');
            }

            var show = document.getElementsByClassName(id);
            for (var i = 0; i < show.length; i++) {
                show[i].classList.remove('d-none');
            }
        }
    </script>
@endsection

@section('content')
    <div class="container py-4 cp-feedback-page">
        <div class="text-center mb-4">
            <img class="mb-3" src="{{ url('images/icons/icons8-idea-64.png') }}" alt="logo" width="64" height="64">
            <h2 class="h3 mb-0">Обратная связь</h2>
            <p class="text-muted">Заполняя форму ниже, вы помогаете нам стать лучше!</p>
        </div>

        @if (count($queries))
            <p>Спасибо, что помогаете нам стать лучше. Если что-то неясно по занятию — нажмите на соответствующий пункт.</p>

            <form method="post">
                {{ csrf_field() }}

                @foreach ($queries as $query)
                    <div class="card mb-3">
                        <div class="card-body">
                            <h5 class="card-title mb-3">
                                {{ $query->course->name }}
                                <small class="text-muted">— {{ $query->created_at->format('d.m.Y') }}</small>
                            </h5>

                            <div class="form-check mb-3">
                                <input class="form-check-input mt-1" name="{{ $query->id }}_missed" type="checkbox"
                                       onclick="toggleBlock('{{ $query->id }}_block')" value="yes">
                                <label class="form-check-label">Меня не было…</label>
                            </div>

                            <div id="{{ $query->id }}_block">
                                <p><strong>Как прошло занятие?</strong></p>

                                <div class="mb-3">
                                    <div class="btn-group w-100" role="group">
                                        <label class="btn btn-outline-primary flex-fill"
                                               onclick="toggleBlock('{{ $query->id }}_5', true, '{{ $query->id }}_problems');">
                                            <input type="radio" name="{{ $query->id }}_mark" autocomplete="off" value="5"> 🤩
                                        </label>
                                        <label class="btn btn-outline-primary flex-fill active"
                                               onclick="toggleBlock('{{ $query->id }}_4', true, '{{ $query->id }}_problems');">
                                            <input type="radio" name="{{ $query->id }}_mark" autocomplete="off" value="4" checked> 🙂
                                        </label>
                                        <label class="btn btn-outline-primary flex-fill"
                                               onclick="toggleBlock('{{ $query->id }}_3', true, '{{ $query->id }}_problems');">
                                            <input type="radio" name="{{ $query->id }}_mark" autocomplete="off" value="3"> 🤨
                                        </label>
                                        <label class="btn btn-outline-primary flex-fill"
                                               onclick="toggleBlock('{{ $query->id }}_2', true, '{{ $query->id }}_problems');">
                                            <input type="radio" name="{{ $query->id }}_mark" autocomplete="off" value="2"> 😔️
                                        </label>
                                        <label class="btn btn-outline-primary flex-fill"
                                               onclick="toggleBlock('{{ $query->id }}_1', true, '{{ $query->id }}_problems');">
                                            <input type="radio" name="{{ $query->id }}_mark" autocomplete="off" value="1"> 😡
                                        </label>
                                    </div>
                                </div>

                                @if ($errors->has($query->id.'_mark'))
                                    <span class="invalid-feedback d-block">
                                        <strong>{{ $errors->first($query->id.'_mark') }}</strong>
                                    </span>
                                @endif

                                <div class="mb-3">
                                    <div class="d-none {{ $query->id }}_problems {{ $query->id }}_1 {{ $query->id }}_2 {{ $query->id }}_3">
                                        <div class="form-check">
                                            <input class="form-check-input" name="{{ $query->id }}_unclear" type="checkbox" value="yes">
                                            <label class="form-check-label">Я не понял, что мы проходили.</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" name="{{ $query->id }}_late" type="checkbox" value="yes">
                                            <label class="form-check-label">Занятие началось с опозданием.</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" name="{{ $query->id }}_unprepaired" type="checkbox" value="yes">
                                            <label class="form-check-label">Преподаватель не проверил задания / не выложил обещанный материал.</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" name="{{ $query->id }}_notanswering" type="checkbox" value="yes">
                                            <label class="form-check-label">Не удается связаться с преподавателем в чате.</label>
                                        </div>
                                    </div>

                                    <div class="d-none {{ $query->id }}_problems {{ $query->id }}_1 {{ $query->id }}_2">
                                        <div class="form-check">
                                            <input class="form-check-input" name="{{ $query->id }}_need_contact" type="checkbox" value="yes" @if (old($query->id.'_need_contact')) checked @endif>
                                            <label class="form-check-label text-danger">Свяжитесь со мной!</label>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <label for="comment{{$query->id}}" class="form-label">Комментарий</label>
                                    <textarea id="comment{{$query->id}}" class="form-control" name="{{ $query->id }}_comment" rows="3"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach

                <button type="submit" class="btn btn-primary btn-lg w-100">Готово</button>
            </form>
        @else
            <div class="alert alert-warning">
                Ссылка уже использована или еще не создана.
            </div>
        @endif
    </div>
@endsection
