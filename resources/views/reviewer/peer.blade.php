<html>
<head>
    <link rel="stylesheet" href="{{ asset('build/css/app.css') }}">

</head>
<body class="peer-review">
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
<div class="peer-review-page-break"></div>

@foreach($students as $id => $student)
    <h3 class="font-weight-light">{{$student->name}} - протокол peer review</h3>

    <h6>Условие задачи и правила оценивания:</h6>
    {!! parsedown($task->text) !!}

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
                <td colspan="2" class="peer-review-cell">
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
                <td class="peer-review-score-cell">

                </td>
            </tr>
        @endforeach
    </table>
    <div class="peer-review-page-break"></div>
@endforeach


</body>
</html>
