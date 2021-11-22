import Vue from 'vue';
import Votes from './components/Votes.vue';

var app = new Vue({
  el: '#root',
  components: {'gk-votes': Votes}
})