<template>
    <div style="padding: 15px;">
        <div v-if="insider">
            <small><a :href="`/insider/courses/${courseId}`"
            style="font-weight: 300;">{{courseName}}</a> &raquo;
            <strong>{{stepLessonName}}</strong></small>
            <h2 style="font-weight: 300;">{{stepName}}</h2>
        </div>
        <div v-else>
            <small>
            <strong>{{stepLessonName}}</strong></small>
            <h2 style="font-weight: 300;">{{stepName}}</h2>
        </div>
    </div>
</template>

<script>
    export default {
        name: 'gk-steps-breadcrumb',
        props: {
            insider: Boolean,
            courseName: String,
            stepLessonName: String,
            stepName: String,
            courseId: Number
        }
    }
</script>

<style>
    
</style>