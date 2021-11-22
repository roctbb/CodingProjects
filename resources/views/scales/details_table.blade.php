@extends('layouts.left-menu')

@section('title')
    GeekClass
@endsection

@section('content')
    <div class="row" style="margin-top: 15px;">
        <div class="col">
            <h2>Шкала {{ $scale->name }}</h2>
        </div>
        <div class="col">
            <a class="float-right btn btn-success btn-sm" href="{{url('/insider/scales/'.$scale->id.'/edit/')}}"><i
                        class="icon ion-edit"></i>&nbsp;Изменить</a>

            <a class="float-right btn btn-success btn-sm" style="margin-right: 5px;"  href="{{url('/insider/scales/'.$scale->id.'/results/add')}}"><i
                        class="icon ion-plus"></i>&nbsp;Добавить результат</a>
        </div>
    </div>
    <div class="row" style="margin-top: 15px;">
        <div class="col-12">
            <p>{{$scale->description}}</p>

            <table class="table table-stripped">
                <tr>
                    <th>Уровень</th>
                    <th>Описание</th>
                    <th>Примеры задач</th>
                </tr>
                <tr>
                    <th>4.0</th>
                    <td>
                        <ul>
                            @foreach($scale->results->where('level', 8) as $result)
                                <li>{{ $result->name }}</li>
                            @endforeach
                        </ul>
                    </td>
                    <td>
                        <ul>
                            @foreach($scale->results->where('level', 8) as $result)
                                @foreach($result->tasks->where('is_demo', true) as $task)
                                    <li>{{ $task->name }}</li>
                                @endforeach
                            @endforeach
                        </ul>
                    </td>
                </tr>

                <tr>
                    <td>3.5</td>
                    <td>В дополнение к выполненным заданиям уровня 3.0, частичный успех в выполнении заданий уровня 4.0.</td>
                    <td></td>
                </tr>

                <tr>
                    <th>3.0</th>
                    <td>
                        <ul>
                            @foreach($scale->results->where('level', 6) as $result)
                                <li>{{ $result->name }}</li>
                            @endforeach
                        </ul>
                    </td>
                    <td>
                        <ul>
                            @foreach($scale->results->where('level', 6) as $result)
                                @foreach($result->tasks->where('is_demo', true) as $task)
                                    <li>{{ $task->name }}</li>
                                @endforeach
                            @endforeach
                        </ul>
                    </td>
                </tr>

                <tr>
                    <td>2.5</td>
                    <td>
                        <small>Отсутствуют ошибки и неточности в выполнении заданий уровня 2.0, частичный успех или выполнение с помощью преподавателя задач уровня 3.0.</small>
                    </td>
                    <td></td>
                </tr>

                <tr>
                    <th>2.0</th>
                    <td>
                        <ul>
                            @foreach($scale->results->where('level', 4) as $result)
                                <li>{{ $result->name }}</li>
                            @endforeach
                        </ul>
                    </td>
                    <td>
                        <ul>
                            @foreach($scale->results->where('level', 4) as $result)
                                @foreach($result->tasks->where('is_demo', true) as $task)
                                    <li>{{ $task->name }}</li>
                                @endforeach
                            @endforeach
                        </ul>
                    </td>
                </tr>

                <tr>
                    <td>1.5</td>
                    <td>
                        <small>Частичный успех в выполнении задач уровня 2.0, ошибки и неточности в заданиях 3.0.</small>
                    </td>
                    <td></td>
                </tr>

                <tr>
                    <td>1.0</td>
                    <td>При наличии помощи, частичный успех в выполнении задач уровня 2.0 или 3.0.</td>
                    <td></td>
                </tr>

                <tr>
                    <td>0.5</td>
                    <td>
                        <small>При наличии помощи, частичный успех в выполнении задач уровня 2.0, но не в 3.0.</small>
                    </td>
                    <td></td>
                </tr>

                <tr>
                    <td>0.0</td>
                    <td>При наличии помощи не достигает результатов.</td>
                    <td></td>
                </tr>

            </table>
        </div>
    </div>



@endsection
