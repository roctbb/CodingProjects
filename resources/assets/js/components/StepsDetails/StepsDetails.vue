<template>
    <div>
        <gk-steps-breadcrumb 
            :insider="insider"
            :course-name="course.name"
            :step-lesson-name="step.lesson.name"
            :step-name="step.name"
            :course-id="course.id"
            >         
        </gk-steps-breadcrumb>
        <gk-steps-tabs>
            
        </gk-steps-tabs> 
    </div>
</template>

<script>
    import StepsBreadcrumb from './StepsBreadcrumb';
    import StepsTabs from './StepsTabs';

    export default {
        name: 'gk-steps-details',

        components: {
            'gk-steps-breadcrumb': StepsBreadcrumb,
        },

        props: {
            step: Object,
            course: Object,
            insider: Boolean
        }
    }
</script>

<style>
    
</style>