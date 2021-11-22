import Vue from 'vue';
import Votes from './components/IDE.vue';

var app = new Vue({
  el: '#root',
  components: {'ide-app': Votes}
})