<template>
    <div>
        <li class="nav-item" style="max-width: 45px;">
                <a href="{{url('/insider/courses/'.$course->id.'/steps/'.$step->id.'/edit')}}"
                 class="nav-link btn btn-success"
                 style="padding: 8px 9px; height: 40px; margin: 0 0; margin-left: 5px; width: 40px;;"><i
                 class="icon fa-solid fa-pen-to-square"></i></a>
             </li>
             <li class="nav-item" style="max-width: 45px;">
                <button type="button" class="nav-link btn btn-success"
                data-toggle="modal" data-target="#exampleModal"
                style="padding: 8px 9px;height: 40px; margin: 0 0; margin-left: 5px; width: 40px;;">
                <i class="icon fa-solid fa-circle-plus"></i>
            </button>
        </li>
        <li class="nav-item" style="max-width: 45px;">
            <a href="{{url('/insider/courses/'.$course->id.'/perform/'.$step->id)}}"
             class="nav-link btn btn-success"
             style="padding: 8px 9px;height: 40px; margin: 0 0; margin-left: 5px; width: 40px;"><i
             class="icon fa-solid fa-desktop"></i></a>
        </li>
        <li class="nav-item" style="max-width: 45px;">

            <a class="nav-link btn btn-success"
            style="padding: 8px 9px;height: 40px; margin: 0 0; margin-left: 5px; width: 40px;"
            href="#"
            data-action-url="{{url('/insider/courses/'.$course->id.'/steps/'.$step->id.'/lower')}}"
            data-action-method="POST"><i
            class="fa-solid fa-arrow-up"></i></a>
        </li>
        <li class="nav-item" style="max-width: 45px;">
            <a class="nav-link btn btn-success"
            style="padding: 8px 9px;height: 40px; margin: 0 0; margin-left: 5px; width: 40px;"
            href="#"
            data-action-url="{{url('/insider/courses/'.$course->id.'/steps/'.$step->id.'/upper')}}"
            data-action-method="POST"><i
            class="fa-solid fa-arrow-down"></i></a>
        </li>
        <li class="nav-item" style="max-width: 45px;">
            <a class="nav-link btn btn-danger"
            style="padding: 8px 9px;height: 40px; margin: 0 0; margin-left: 5px; width: 40px;"
            href="#"
            data-action-url="{{url('/insider/courses/'.$course->id.'/steps/'.$step->id.'/delete')}}"
            data-action-method="DELETE"
            data-action-confirm="Вы уверены?"><i
            class="fa-solid fa-xmark"></i></a>
        </li>
    </div>
</template>

<script>
    export default {
        name: 'gk-steps-controls'
    }
</script>

<style>
    

</style>
