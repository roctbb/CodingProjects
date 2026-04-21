<html>
<head>
    <style>
        body {
            font-family: "Trebuchet MS", "Segoe UI", sans-serif;
            color: #1f2f4d;
            margin: 16px;
        }

        h3, h6 {
            color: #1b3f78;
        }

        img {
            max-width: 40%;
        }

        .peer-summary-table th {
            background: #eaf2ff;
            color: #2a4675;
            font-weight: 700;
        }

        .peer-work-table th {
            background: #f3f7ff;
            color: #2a4675;
        }

        .peer-page-break {
            page-break-after: always;
        }

        .peer-border-gray {
            border: 2px dotted gray;
        }

        .peer-border-green {
            border: 2px dotted green;
        }

    </style>
    <link rel="stylesheet" href="{{ url('/styles/bootstrap.min.css') }}"
          integrity="sha384-/Y6pD6FV/Vv2HJnA6t+vslU6fwYXjCFtcEpHbNJ0lyAFsXTsjBbfaDjzALeQsN6M" crossorigin="anonymous">

</head>
<body>
<h3 class="cp-heading-lite">Сводка Peer Review</h3>
<table class="table table-sm table-striped peer-summary-table">
    <tr>
        <th>id</th>
        <th>Имя</th>
        <th>Reviewer 1</th>
        <th>Reviewer 2</th>
        <th>Решение</th>
    </tr>
    @foreach($students as $id => $student)
        <tr>
            <td>{{$id}}</td>
            <td>{{$student->name}}</td>
            <td>{{$student->reviewer1->name}}</td>
            <td>{{$student->reviewer2->name}}</td>
            <td>{{$student->solution}}</td>
        </tr>
    @endforeach
</table>

<hr>
<div class="peer-page-break"></div>

@foreach($students as $id => $student)
    <h3 class="cp-heading-lite">{{$student->name}} - протокол peer review</h3>

    <h6>Условие задачи и правила оценивания:</h6>
    {!! parsedown($task->text) !!}

    <h6>Решения:</h6>
    <p><small>Для каждого решения оставьте оценку по каждому из критериев, прокомментировав вашу оценку.</small></p>
    <table class="table table-bordered peer-work-table">
        <tr>
            <th>ID</th>
            <th>Решение</th>
        </tr>
        @foreach($student->works as $solution)
            <tr>
                <th>{{$ids[$solution->user->id]}}</th>
                <td>{!! nl2br($solution->text) !!}</td>
            </tr>
            <tr>
                <td colspan="2" class="peer-border-gray">
                    <p><br></p>
                    <p><br></p>
                    <p><br></p>
                    <p><br></p>
                </td>
            </tr>
            <tr>
                <td>
                    <small><strong>Итого&nbsp;очков опыта:</strong></small>
                </td>
                <td class="peer-border-green">

                </td>
            </tr>
        @endforeach
    </table>
    <div class="peer-page-break"></div>
@endforeach


</body>
</html>
