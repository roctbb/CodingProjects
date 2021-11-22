<template>
    <div class="upvotesComponent">
        <div :class="upvoteClasses" @click="upvote">
            <i class="icon ion-chevron-up"></i>
        </div>
        <div v-if="!showed" :class="scoreClasses" @click="show">{{score}}</div>
        <div class="green" v-if="showed" @click="show">{{upvotes + upvoted}}</div>
        <div class="red" v-if="showed" @click="show">{{-downvotes - downvoted}}</div>
        <div @click="downvote" :class="downvoteClasses">
            <i class="icon ion-chevron-down"></i>
        </div>
    </div>
</template>

<script>
    import axios from 'axios';

    export default {
        name: "gk-votes",
        props: {
            upvotes: Number,
            downvotes: Number,
            upvoted: Number,
            downvoted: Number,
            canvote: {
                type: Boolean,
                default: true
            },
            urls: Object
        },
        data: function () {
            return {
                showed: false,
            };
        },
        computed: {
            score() {
                return this.upvotes - this.downvotes + this.upvoted - this.downvoted;
            },
            upvoteClasses() {

                if (this.upvoted) {
                    return "upvote green";
                }
                return "upvote";
            },
            downvoteClasses() {
                if (this.downvoted) {
                    return "downvote red";
                }
                return "upvote";
            },
            scoreClasses() {
                if (this.score < 0) {
                    return "red";
                } else if (this.score > 0) {
                    return "green";
                }
            }
        },
        methods: {
            show() {
                this.showed = !this.showed;
            },
            upvote() {
                if (!this.canvote) return;
                this.downvoted = false;
                this.upvoted = !this.upvoted;
                axios.get(this.urls.upvote);
            },
            downvote() {
                if (!this.canvote) return;
                this.upvoted = false;
                this.downvoted = !this.downvoted;
                axios.get(this.urls.downvote);
            }
        }
    };
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>
    .green {
        color: green;
    }

    .red {
        color: crimson;
    }

    .upvotesComponent {
        text-align: center;
        display: inline-block;
        cursor: pointer;
    }
</style>