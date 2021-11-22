@extends('layouts.fluid')

@section('title')
    @if (\Request::is('insider/*'))
        GeekClass: "{{$course->name}}" - "{{$step->name}}"
    @else
        GeekClass: "{{$step->name}}"
    @endif
@endsection


@section('tabs')

@endsection

@section('content')
    <div class="row" style="min-height: 100%; position: absolute; width: 100%; background-color: #fafafa;">
        @include('steps/partials/nav')

        <main role="main" class="col-sm-8 ml-sm-auto col-md-9 col-xl-10 pt-3" style="background-color: white; padding-bottom: 40px;">
            @include('steps/partials/breadcrumb_widget')
            @include('steps/partials/tabs')


            <div class="tab-content" id="pills-tabContent" style="padding: 15px;">

                @include('steps/partials/notes')
                @include('steps/partials/quizer')
                @include('steps/partials/content')

            </div>
            <p class="markdown">
                @if (\Request::is('insider/*'))
                    @if ($step->previousStep() != null)
                        <a href="{{url('/insider/courses/'.$course->id.'/steps/'.$step->previousStep()->id)}}"
                           class="btn btn-success btn-sm">Назад</a>
                    @endif
                    @if ($step->nextStep() != null)
                        <a href="{{url('/insider/courses/'.$course->id.'/steps/'.$step->nextStep()->id)}}"
                           class="btn btn-success btn-sm float-right">Вперед</a>
                    @endif
                @endif
                @if (\Request::is('open/*'))
                    @if ($step->previousStep() != null)
                        <a href="{{url('/open/steps/'.$step->previousStep()->id)}}"
                           class="btn btn-success btn-sm">Назад</a>
                    @endif
                    @if ($step->nextStep() != null)
                        <a href="{{url('/open/steps/'.$step->nextStep()->id)}}"
                           class="btn btn-success btn-sm float-right">Вперед</a>
                    @endif
                @endif
            </p>

        </main>


    </div>


    @include('steps/partials/modal')
    <script>
        $('blockquote').addClass('bd-callout bd-callout-info')

        var simplemde_task = new EasyMDE({
            spellChecker: false,
            element: document.getElementById("text")
        });
        var simplemde_solution = new EasyMDE({
            spellChecker: false,
            element: document.getElementById("solution")
        });

        $('table').addClass('table table-striped');
    </script>
    @if (\Request::is('insider/*'))
        <script>
            var thtml = `
    <div class="row" style="margin-top: 15px; margin-bottom: 15px;">
      <div class="col">
         <div class="card">
            <div class="card-header">
               Дата сдачи: __DATE_PLACEHOLDER__
               <div class="float-right">
                  <span class="badge badge-secondary">Решение еще не проверено</span>
               </div>
            </div>
            <div class="card-body" style="padding-top: 0; padding-bottom: 0; padding: 20px;">
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
                console.log(e.target.querySelector("#sbtn"))
                e.target.querySelector("#sbtn").classList.remove("btn-success");
                e.target.querySelector("#sbtn").classList.add("btn-disabled");
                e.target.querySelector("#sbtn").disabled = "true";
                e.target.querySelector("#sbtn").innerHTML = "Подождите ...";

                axios.post(`/insider/courses/{{$course->id}}/tasks/${taskId}/solution`, `text=` + encodeURIComponent(text))
                    .then((res) => {

                        e.target.querySelector("#sbtn").classList.add("btn-success");
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
