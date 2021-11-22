@extends('layouts.empty')

@section('head')
    <script>
        function toggleBlock(id, force, b) {

            if (force == undefined) {
                var block = document.getElementById(id);

                if (block.style.display == "none") {
                    block.style.display = "block";
                } else {
                    block.style.display = "none";
                }
            } else {
                var blocks = document.getElementsByClassName(b);
                Array.from(blocks).forEach(function (item) {
                    item.style.display = "none";
                });
                blocks = document.getElementsByClassName(id);
                Array.from(blocks).forEach(function (item) {
                    item.style.display = "block";
                });
            }

        }
    </script>
    <style>
        .btn-secondary {
            color: #fff;
            background-color: white;
            border-color: white;
            font-size: 40px;
            width: 20%;

            /* background-color: #868e96; */
            /* border-color: #868e96; */
        }

        .btn-secondary.active {
            background-color: white;
        }

        body {
            display: -ms-flexbox;
            display: flex;
            -ms-flex-align: center;
            align-items: center;
            padding-top: 0px;
            background: white;
            padding-bottom: 20px;
        }

        .form-check-input {
            margin-left: 0px;
        }
    </style>
@endsection

@section('content')
    <img style="height: 64px;" src="{{ url('images/icons/icons8-idea-64.png') }}">&nbsp;
    <h2 class="h3 mb-3 font-weight-normal">Обратная связь!</h2>

    @if (count($queries))

        <p>Заполняя форму ниже, вы помогаете нам стать лучше! А еще мы пришлем небольшой бонус...</p>

        <form method="post">
            {{ csrf_field() }}

            @foreach ($queries as $query)

                <div class="card">
                    <div class="card-body">
                        <h5>{{ $query->course->name }}<br><small>{{ $query->created_at->format('d.m.Y') }}</small>
                        </h5>

                        <div class="form-check">
                            <input style="margin-left: 0px;" class="form-check-input" name="{{ $query->id }}_missed" type="checkbox" onclick="toggleBlock('{{ $query->id }}_block')" value="yes">
                            <label class="form-check-label">
                                Меня не было...
                            </label>
                        </div>

                        <div id="{{ $query->id }}_block">

                            <p><strong>Как прошло занятие?</strong></p>

                            <div class="form-group">

                                <div class="btn-group btn-group-toggle" data-toggle="buttons" style="width: 100%;">
                                    <label class="btn btn-secondary" onclick="toggleBlock('{{ $query->id }}_5', true, '{{ $query->id }}_problems');">
                                        <input type="radio" name="{{ $query->id }}_mark" id="option1" autocomplete="off" value="5"> 🤩
                                    </label>
                                    <label class="btn btn-secondary active" onclick="toggleBlock('{{ $query->id }}_4', true, '{{ $query->id }}_problems');">
                                        <input type="radio" name="{{ $query->id }}_mark" id="option2" autocomplete="off" value="4" checked> 🙂
                                    </label>
                                    <label class="btn btn-secondary" onclick="toggleBlock('{{ $query->id }}_3', true, '{{ $query->id }}_problems');">
                                        <input type="radio" name="{{ $query->id }}_mark" id="option3" autocomplete="off" value="3"> 🤨
                                    </label>
                                    <label class="btn btn-secondary" onclick="toggleBlock('{{ $query->id }}_2', true, '{{ $query->id }}_problems');">
                                        <input type="radio" name="{{ $query->id }}_mark" id="option4" autocomplete="off" value="2"> 😔️
                                    </label>
                                    <label class="btn btn-secondary" onclick="toggleBlock('{{ $query->id }}_1', true, '{{ $query->id }}_problems');">
                                        <input type="radio" name="{{ $query->id }}_mark" id="option5" autocomplete="off" value="1"> 😡
                                    </label>
                                </div>
                            <!--
                                <div class="form-check form-check-inline">
                                    <span><input class="form-check-input" type="radio" name="{{ $query->id }}_mark" value="5"><label class="form-check-label"
                                                                                                                                     style="font-weight: bold; color: #1c7430;">5</label></span>
                                </div>
                                <div class="form-check form-check-inline">
                                    <span><input class="form-check-input" type="radio" name="{{ $query->id }}_mark" value="4" checked><label class="form-check-label">4</label></span>
                                </div>
                                <div class="form-check form-check-inline">
                                    <span><input class="form-check-input" type="radio" name="{{ $query->id }}_mark" value="3"><label class="form-check-label">3</label></span>
                                </div>
                                <div class="form-check form-check-inline">
                                    <span><input class="form-check-input" onclick="toggleBlock('{{ $query->id }}_contact', true)" type="radio" name="{{ $query->id }}_mark" value="2"><label
                                                class="form-check-label"
                                                style="font-weight: bold; color: darkred;">2</label></span>
                                </div>
                                <div class="form-check form-check-inline">
                                    <span><input class="form-check-input" onclick="toggleBlock('{{ $query->id }}_contact', true)" type="radio" name="{{ $query->id }}_mark" value="1"><label
                                                class="form-check-label"
                                                style="font-weight: bold; color: red;">1</label></span>
                                </div>

-->
                                @if ($errors->has($query->id.'_mark'))
                                    <span class="help-block error-block">
                                            <strong>{{ $errors->first($query->id.'_mark') }}</strong>
                                        </span>
                                @endif

                            </div>
                            <div class="form-group">
                                <div style="display: none" class="{{ $query->id }}_problems {{ $query->id }}_1 {{ $query->id }}_2 {{ $query->id }}_3">
                                    <div class="form-check">
                                        <input class="form-check-input" name="{{ $query->id }}_unclear" type="checkbox" value="yes" style="margin-left: 0px;">
                                        <label class="form-check-label">
                                            Я не понял, что мы проходили.
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" name="{{ $query->id }}_late" type="checkbox" value="yes" style="margin-left: 0px;">
                                        <label class="form-check-label">
                                            Занятие началось с опозданием.
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" name="{{ $query->id }}_unprepaired" type="checkbox" value="yes" style="margin-left: 0px;">
                                        <label class="form-check-label">
                                            Преподаватель не проверил задания / не выложил обещанный материал.
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" name="{{ $query->id }}_notanswering" type="checkbox" value="yes" style="margin-left: 0px;">
                                        <label class="form-check-label">
                                            Не удается связаться с преподавателем в чате.
                                        </label>
                                    </div>
                                </div>
                                <div style="display: none" class="{{ $query->id }}_problems {{ $query->id }}_1 {{ $query->id }}_2">
                                    <div class="form-check">
                                        <input class="form-check-input" name="{{ $query->id }}_need_contact" type="checkbox" style="margin-left: 0px;"
                                               value="yes" @if (old($query->id.'_need_contact')) checked @endif>
                                        <label class="form-check-label">
                                            <span style="color: red;">Свяжитесь со мной!</span>
                                        </label>
                                    </div>
                                </div>
                                <strong>Комментарий:</strong>
                                <textarea class="form-control" name="{{ $query->id }}_comment"></textarea>
                            </div>

                        </div>

                    </div>
                </div>

            @endforeach
            <button style="margin-top: 10px;" class="btn btn-lg btn-primary btn-block" type="submit">Готово</button>
        </form>
    @else
        <strong>Ссылка уже использована или еще не создана.</strong>
    @endif
@endsection
