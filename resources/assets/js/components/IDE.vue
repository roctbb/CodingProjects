<template>
  <div class="ide">
    <nav>
      <a :href="'/insider/games/'+game" @click="run">
        Вернутся в GeekClass
      </a>
      <div class="right">
        <p class="title">Проект: {{gameData.title}}</p>
        <a :href="`/insider/games/${game}/edit`" class="btn secondary" @click="run">Настройки</a>

        <a href="javascript:void(0)" class="btn" @click="run">Запустить</a>
      </div>
    </nav>
    <main>
      <div id="editor" ref="editor"></div>
      <div id="output">
        <iframe :src="'/insider/games/'+game+'/frame'" frameborder="0" sandbox="allow-same-origin allow-scripts"
          style="margin-bottom: -7px;" ref="frame"></iframe>
      </div>
    </main>
  </div>
</template>

<script>
  import axios from 'axios';
  import * as monaco from 'monaco-editor';


  self.MonacoEnvironment = {
    getWorkerUrl: function (moduleId, label) {
      if (label === 'json') {
        return '/json.worker.js';
      }
      if (label === 'css') {
        return '/css.worker.js';
      }
      if (label === 'html') {
        return '/html.worker.js';
      }
      if (label === 'typescript' || label === 'javascript') {
        return '/typescript.worker.js';
      }
      return '/editor.worker.js';
    }
  }
  
  export default {
    name: 'x-ide',
    mounted() {
      var _this = this;
      axios.get('/api/games/' + this.game + '/info').then((res) => {
        _this.gameData = res.data;
        console.log(res.data);
        _this.editor = monaco.editor.create(_this.$refs.editor, {
          value: _this.gameData.code,
          language: 'javascript',
          automaticLayout: true,
          wordWrap: true,
          theme: 'vs-dark',
          folding: true
        });
      }).catch((err) => {
        document.write('Unable to run editor due to following error: ' + err)
      })


      
    },
    data() 
    {
      return {
        gameData: {},
        editor: {}
      }
    },
    props: {
      game: Number
    },
    methods: {
      run() {

        axios.post(`/api/games/${this.game}/update`, {
          code: this.editor.getValue(),
        }).then(() => {
          
          this.$refs.frame.contentWindow.location.reload();
        })
      }
    }
  }
</script>


<style scoped>
  .ide {
    display: flex;
    flex-direction: column;

    height: 100%;
  }
  .title
  {
    color: #ccc !important;
  }

  #editor {
    flex: 1;
  }

  #output {
    flex: 1;
  }

  #output iframe {
    width: 100%;
    height: 100%;
  }
  .right
  {
    display: flex;

    align-items: center;

  }
  .right > *
  {
    margin-left: 10px;
  }
  nav {
    color: #fff;
    padding: 10px;
    align-items: center;
    display: flex;
    justify-content: space-between;
    background: #1e1e1e;
    /* border-bottom: 2px solid rgba(0,0,0,0.1); */
  }

  main {
    display: flex;

    flex: 1;
  }

  a {
    color: #ccc;
    text-decoration: none;
  }

  .btn {
    padding: 10px 20px;
    background: #16c2cc;
    border-radius: 5px;
    display: block;
    color: #1e1e1e;
  }

  .btn.secondary
  {
    background: rgba(255,255,255,0.1);
    color: rgba(255,255,255,0.5);
  }
</style>