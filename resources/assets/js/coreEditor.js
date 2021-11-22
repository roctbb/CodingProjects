import Vue from 'vue';
import Votes from './components/CoreEditor.vue';
import 'babel-polyfill';


var app = new Vue({
  el: '#root',
  components: {'gk-app': Votes}
})