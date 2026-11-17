@php($participantScope = \App\Support\CourseScope::for($course))
@if($participantScope->params())<input type="hidden" name="subgroup" value="{{ $participantScope->key }}">@endif
