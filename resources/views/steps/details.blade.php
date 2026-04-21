@extends('layouts.fluid')

@section('title')
    @if (\Request::is('insider/*'))
        {{$course->name}} - {{$step->name}}
    @else
        {{$step->name}}
    @endif
@endsection

@section('tabs')
@endsection

@section('content')
    <div class="neo-step-page">
        <section class="steps-hero card border-0">
            <div class="card-body">
                <div class="steps-hero-top">
                    <div>
                        @if (\Request::is('insider/*'))
                            <a class="steps-hero-course-link" href="{{url('/insider/courses/'.$course->id)}}">{{$course->name}}</a>
                        @endif
                        @if (\Request::is('open/*'))
                            <span class="steps-hero-course-link">Открытый урок</span>
                        @endif
                        <h1 class="steps-hero-title">{{$step->lesson->name}}</h1>
                        <p class="steps-hero-subtitle">{{$step->name}}</p>
                    </div>
                    <div class="steps-hero-meta">
                        <span class="badge text-bg-secondary">Этапов: {{$step->lesson->steps->count()}}</span>
                        @if (count($tasks) > 0)
                            <span class="badge text-bg-primary">Задач: {{count($tasks)}}</span>
                        @endif
                    </div>
                </div>
            </div>
        </section>

        <div class="steps-layout neo-step-layout mt-3">
            @include('steps/partials/nav')

            <main role="main" class="steps-main">
                @include('steps/partials/breadcrumb_widget')
                @include('steps/partials/tabs')

                <div class="tab-content steps-tab-content" id="pills-tabContent">
                    @include('steps/partials/notes')
                    @include('steps/partials/quizer')
                    @include('steps/partials/content')
                </div>

                <div class="steps-bottom-nav">
                    @if (\Request::is('insider/*'))
                        @if ($step->previousStep() != null)
                            <a href="{{url('/insider/courses/'.$course->id.'/steps/'.$step->previousStep()->id)}}"
                               class="btn btn-outline-secondary steps-bottom-nav-btn">Назад</a>
                        @endif
                        @if ($step->nextStep() != null)
                            <a href="{{url('/insider/courses/'.$course->id.'/steps/'.$step->nextStep()->id)}}"
                               class="btn btn-primary steps-bottom-nav-btn steps-bottom-nav-btn--next">Вперед</a>
                        @endif
                    @endif
                    @if (\Request::is('open/*'))
                        @if ($step->previousStep() != null)
                            <a href="{{url('/open/steps/'.$step->previousStep()->id)}}"
                               class="btn btn-outline-secondary steps-bottom-nav-btn">Назад</a>
                        @endif
                        @if ($step->nextStep() != null)
                            <a href="{{url('/open/steps/'.$step->nextStep()->id)}}"
                               class="btn btn-primary steps-bottom-nav-btn steps-bottom-nav-btn--next">Вперед</a>
                        @endif
                    @endif
                </div>
            </main>
        </div>
    </div>

    @include('steps/partials/modal')
    <script>
        document.querySelectorAll('blockquote').forEach(function (node) {
            node.classList.add('bd-callout', 'bd-callout-info');
        });

        var taskEditorElement = document.getElementById('text');
        if (taskEditorElement) {
            window.simplemde_task = new EasyMDE({
                spellChecker: false,
                element: taskEditorElement
            });
        }

        var solutionEditorElement = document.getElementById('solution');
        if (solutionEditorElement) {
            window.simplemde_solution = new EasyMDE({
                spellChecker: false,
                element: solutionEditorElement
            });
        }

        document.querySelectorAll('table').forEach(function (node) {
            node.classList.add('table', 'table-striped', 'table-sm', 'align-middle');
        });
    </script>
    @if (\Request::is('insider/*'))
        <script>
            var thtml = `
    <div class="row steps-solution-row">
      <div class="col">
         <div class="card">
            <div class="card-header">
               Дата сдачи: __DATE_PLACEHOLDER__
               <div class="steps-solution-status">
                  <span class="badge text-bg-secondary">Решение еще не проверено</span>
               </div>
            </div>
            <div class="card-body steps-solution-body">
               __TEXT_PLACEHOLDER__
            </div>
         </div>
      </div>
   </div>`
            var months = [
                "January", "February", "March", "April", "May", "June", "Jule", "August", "September",
                "October", "November", "December"
            ]

            function checkTask(e, taskId) {
                e.preventDefault();
                var text = e.target.querySelector("[name=text]").value;

                axios.post(`/insider/courses/{{$course->id}}/tasks/${taskId}/solution`, `text=` + encodeURI(text))
                    .then((res) => {
                        document.getElementById("TSK_" + taskId).innerHTML = "Очков опыта: " + res.data.mark;
                        document.getElementById("TSK_COM_" + taskId).innerHTML = res.data.comment;
                    })
            }

            function sendSolution(e, taskId) {
                var date = new Date();
                e.preventDefault();
                var text = e.target.querySelector("[name=text]").value;
                if (text == "") {
                    alert("Нельзя сдать пустое решение!");
                    return;
                }
                e.target.querySelector("[name=text]").value = "";
                e.target.querySelector("#sbtn").classList.remove("btn-primary");
                e.target.querySelector("#sbtn").classList.add("btn-disabled");
                e.target.querySelector("#sbtn").disabled = "true";
                e.target.querySelector("#sbtn").innerHTML = "Подождите ...";

                axios.post(`/insider/courses/{{$course->id}}/tasks/${taskId}/solution`, `text=` + encodeURIComponent(text))
                    .then((res) => {
                        e.target.querySelector("#sbtn").classList.add("btn-primary");
                        e.target.querySelector("#sbtn").classList.remove("btn-disabled");
                        e.target.querySelector("#sbtn").removeAttribute("disabled");
                        e.target.querySelector("#sbtn").innerHTML = "Ответить";

                        var toAdd = thtml.replace("__DATE_PLACEHOLDER__",
                            `${date.getDate()}.${months[date.getMonth()]}.${date.getFullYear()} ${date.getHours()}:${date.getMinutes()}`)
                        text = text.replace(/\n/g, "<br>");
                        text = text.replace(/\s/g, "&nbsp;");
                        text = text.replace(/\t/g, "&nbsp;&nbsp;&nbsp;&nbsp;");
                        toAdd = toAdd.replace("__TEXT_PLACEHOLDER__", text);
                        document.getElementById("solutions_ajax" + taskId).innerHTML += toAdd;
                    })
            }
        </script>
    @endif
@endsection
