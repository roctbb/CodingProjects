<template>
    <div>
        <li class="nav-item" style="max-width: 45px;">
                <a href="{{url('/insider/courses/'.$course->id.'/steps/'.$step->id.'/edit')}}"
                 class="nav-link btn btn-success"
                 style="padding: 8px 9px; height: 40px; margin: 0 0; margin-left: 5px; width: 40px;;"><i
                 class="icon ion-android-create"></i></a>
             </li>
             <li class="nav-item" style="max-width: 45px;">
                <button type="button" class="nav-link btn btn-success"
                data-toggle="modal" data-target="#exampleModal"
                style="padding: 8px 9px;height: 40px; margin: 0 0; margin-left: 5px; width: 40px;;">
                <i class="icon ion-android-add-circle"></i>
            </button>
        </li>
        <li class="nav-item" style="max-width: 45px;">
            <a href="{{url('/insider/courses/'.$course->id.'/perform/'.$step->id)}}"
             class="nav-link btn btn-success"
             style="padding: 8px 9px;height: 40px; margin: 0 0; margin-left: 5px; width: 40px;"><i
             class="icon ion-android-desktop"></i></a>
        </li>
        <li class="nav-item" style="max-width: 45px;">

            <a class="nav-link btn btn-success"
            style="padding: 8px 9px;height: 40px; margin: 0 0; margin-left: 5px; width: 40px;"
            href="{{url('/insider/courses/'.$course->id.'/steps/'.$step->id.'/lower')}}"><i
            class="ion-arrow-up-c"></i></a>
        </li>
        <li class="nav-item" style="max-width: 45px;">
            <a class="nav-link btn btn-success"
            style="padding: 8px 9px;height: 40px; margin: 0 0; margin-left: 5px; width: 40px;"
            href="{{url('/insider/courses/'.$course->id.'/steps/'.$step->id.'/upper')}}"><i
            class="ion-arrow-down-c"></i></a>
        </li>
        <li class="nav-item" style="max-width: 45px;">
            <a class="nav-link btn btn-danger"
            style="padding: 8px 9px;height: 40px; margin: 0 0; margin-left: 5px; width: 40px;"
            href="{{url('/insider/courses/'.$course->id.'/steps/'.$step->id.'/delete')}}"
            onclick="return confirm('Вы уверены?')"><i
            class="ion-close-round"></i></a>
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