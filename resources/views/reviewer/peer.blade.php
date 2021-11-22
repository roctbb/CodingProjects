<html>
<head>
    <style>
        img {
            max-width: 40%;
        }

    </style>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-beta/css/bootstrap.min.css"
          integrity="sha384-/Y6pD6FV/Vv2HJnA6t+vslU6fwYXjCFtcEpHbNJ0lyAFsXTsjBbfaDjzALeQsN6M" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

</head>
<body>
<table class="table">
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
<div style="page-break-after: always;"></div>

@foreach($students as $id => $student)
    <h3 style="font-weight: 300;">{{$student->name}} - протокол peer review</h3>

    <h6>Условие задачи и правила оценивания:</h6>
    @parsedown($task->text)

    <h6>Решения:</h6>
    <p><small>Для каждого решения оставьте оценку по каждому из критериев, прокомментировав вашу оценку.</small></p>
    <table class="table">
        <tr>
            <th>ID</th>
            <th>Решение</th>
        </tr>
        @foreach($student->works as $solution)
            <tr>
                <th>{{$ids[$solution->user->id]}}</th>
                <td>{!! nl2br($solution->text) !!}}</td>
            </tr>
            <tr>
                <td colspan="2" style="border: 2px dotted gray;">
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
                <td style="border: 2px dotted green;">

                </td>
            </tr>
        @endforeach
    </table>
    <div style="page-break-after: always;"></div>
@endforeach


</body>
</html>
