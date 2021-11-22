<template>
    <div class="row">
        <div class="col">
            <ul class="nav nav-pills nav-fill" id="pills-tab" role="tablist">
                <div v-if="tasks.length != 0 && !zeroTheory && !quizer">
                    <li class="nav-item">
                        <a class="nav-link active" data-toggle="pill" id="theory-tab" href="#theory" role="tab" aria-controls="theory" aria-expanded="true">0. Теория</a>
                    </li>
                </div>
                <div v-if="!quizer && (!oneTasker || !zeroTheory)">
                    <div v-for="(task, key) in task">
                        <li class="nav-item">
                            <a class="nav-link task-pill" data-toggle="pill" :id="`tasks-tab${task.id}`" :href="`#task${task.id}`">{{key+1}}
                                {{task.name}}
                                <sup v-if="task.is_star">*</sup>
                                <!-- TODO -->
                                <div v-if="insider">
                                    <div v-if="task.isSubmitted">
                                        <sup v-if="task.isFailed"><img title="Не выполнено" src="{{ url('images/icons/icons8-cancel-48.png') }}" style="height: 20px;" /></sup>
                                        <sup v-else-if="task.isOnCheck"><img title="Ожидает проверки" src="{{ url('images/icons/icons8-historical-48.png') }}" style="height: 20px;" /></sup>
                                        <sup v-else-if="task.isFullDone"><img title="Выполнено" src="{{ url('images/icons/icons8-checkmark-48.png') }}" style="height: 20px;" /></sup>
                                        <sup v-else><img title="Требует доработки" src="{{ url('images/icons/icons8-error-48.png') }}" style="height: 20px;" /></sup>
                                    </div>
                                </div>
                            </a>
                        </li>
                    </div>
                </div>
                <div v-if="teacher">
                </div>
            </ul>
        </div>
    </div>
</template>

<script>
    export default {
        name: 'gk-steps-tabs',
        props: {
            task: Object,
            zeroTheory: Boolean,
            quizer: Boolean,
            oneTasker: Boolean,
            insider: Boolean,
            teacher: Boolean
        }
    }
</script>

<style>
    
</style>