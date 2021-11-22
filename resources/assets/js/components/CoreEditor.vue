<template>
  <div id="editor">
    <h2>Выберите версию</h2>
    <ul class="select" v-if="ready">
      <li v-for="(version, index) in coreVersions" :key="index"
         :class="versionSelected == index?'selected option':'option'" @click="versionSelected = index">{{version}}</li>
      <li @click="allocVersion" class="addVer">+</li>
      <li @click="save" class="option selected">Сохранить данную версию</li>
      <li v-if="savedState == 'success'" class="ico-li"><i class="ico success ion ion-checkmark"></i></li>
      <li v-if="savedState == 'pending'" class="ico-li"><i class="ico pending ion ion-clock"></i></li>
      <li v-if="savedState == 'error'"  class="ico-li"><i class="ico fail"> &times; </i></li>
    </ul>
    <div style="display: flex; border: 1px solid #ccc; padding: 10px;">
      <div>
        <div class="btn btn-danger" @click="removeVersion">&times;</div> <span style="padding:10px;">Удалить версию</span>
      </div>
    </div>
    <div class="graph" v-if="ready && system">
      <div class="nodes" v-if="ready">

        <input type="text" v-model="nodeFilter" placeholder="Фильтр">
        <div ref="nedit" class="nodelist">
          <div v-for="(node, index) in nodes" :class="isSelected(node.localId)?'selected node':'node'" :key="index">
            <div class="f" @click="select(node.localId)">
              {{node.title}}
            </div>
            <div>
              <div class="btn btn-danger" @click="removeNode(node.localId)">&times;</div>
              <!-- <div class="btn btn-success" v-if="!haveParent(node.id)" @click="addParent(node)">Р</div> -->
              <div class="btn btn-primary" v-show="!isSelected(node.localId)" @click="addChild(node)">→</div>
            </div>
          </div>
        </div>

        <div class="fieldset">
          <input type="text" placeholder="Имя нового элемента" ref="nname">
          <div class="btn-add" @click="addNode">+</div>
        </div>
      </div>
      <div  class="node_edit" v-if="ready && selected">
        <input type="text" v-model="selected.title" placeholder="Новое имя">
        <div class="split">
          <div class="wrap">
            <h4>Родители</h4>
            <div class="parents">
              <div class="node" v-for="(node, index) in parents" :key="index">
                <div class="f" @click="select(node.localId)">
                  {{node.source.title}}
                </div>
                <div class="fieldset">
                  <div class="btn btn-danger float-right" @click="removeParent(node.source.localId)">&times;</div>
                </div>

              </div>
            </div>
          </div>
          <div class="wrap">
            <h4>Дети</h4>
            <div class="children">

              <div class="node" v-for="(node, index) in children" :key="index">
                <div class="f" @click="select(node.localId)">
                  {{node.target.title}}
                </div>
                <div class="fieldset">
                  <div class="btn btn-danger float-right" @click="removeChild(node.target.localId)">&times;
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <h3 style="padding: 10px; border-left: 1px solid #ccc; border-left: 1px solid #ccc;border-right: 1px solid #ccc;">Справка</h3>
        <div style="display: flex; border: 1px solid #ccc; border-top: none; padding: 10px;">
      <div>
        <div class="btn btn-primary">→</div> <span style="padding:10px"> - Добавить ребенка</span>
      </div>
      <div>
        <div class="btn btn-danger">&times;</div> <span style="padding:10px;"> - Удалить</span>
      </div>
    </div>

  </div>
</template>

<script>
  import axios from 'axios';
  import _ from 'lodash';
  import NodeSystem from '../classes/NodeSystem';

  import qstr from "querystring";
import { setTimeout } from 'timers';

  export default {

    mounted() {
      axios.get('/insider/core/network').then(async (res) => {
        this.coreVersions = res.data;
        for (var i = 0; i < res.data.length; i++) {
          await axios.get(`/insider/core/network/1?version=${res.data[i]}`).then((data) => {
            var s = new NodeSystem();
            s.fromObject(data.data);
            this.systems.push(s);
          });
        }
        this.ready = true;

      }).catch((e) => {
        console.error(e)
      })

    },
    computed: {
      selected() {
        return this.system.current;
      },
      nodes() {
        var nodes = this.system.nodes;
        var reg = new RegExp(
          this.nodeFilter.replace(/[^sStTnNvVfFrR0]/g, (e) => `\\${e}`),
          'gi'
        );      

        return this.system.nodes.filter((e) => e.title.match(reg));
      },
      children() {
        return this.selected.children();
      },
      parents() {

        return this.selected.parents();
      },
      system()
      {
        return this.systems[this.versionSelected];
      }
    },
    methods: {
      select(id)
      {
        this.system.setCurrent(id);
      },
      removeChild(id) {
        return this.selected.removeChild(id);
      },
      removeParent(id) {
        return this.selected.removeParent(id);
      },
      removeNode(id) {
        this.system.removeNode(id);
      },
      addParent(node) {
        return this.selected.addParent(node);
      },
      addChild(node) {
        return this.selected.addChild(node);
      },
      isSelected(id)
      {
        return this.selected.localId == id;
      },
      removeVersion() 
      {
        if (confirm("Вы правда хотите данную эту версию?"))
        {
          this.coreVersions.splice(this.versionSelected, 1);
          this.systems.splice(this.systems.indexOf(this.system), 1);
          this.versionSelected = 0;
        }
      },
      allocId()
      {
        var max;
        
        max =  _.maxBy(this.system.nodes, 'localId');
        if (max === undefined) max = {localId: 0};
        console.log(max.localId+1);
        return max.localId+1;

      },
      allocVersion()
      {
        var m = _.max(this.coreVersions)+1 || 0;

        this.coreVersions.push(m);
        var s = new NodeSystem();
        s.fromObject({"nodes": [], "edges": []});
        this.systems.push(s);
      },
      // haveParent(id)
      // {
      //   return this.system.current.haveParent(id) || this.system.find(id).haveParent(this.system.current.id);
      // },
      // haveChild(id)
      // {
      //   return !this.system.find(id).canBeChild(this.system.current.id);
      // },
      addNode() {
        var title = this.$refs.nname.value;

        this.system.addNode(title);
        if (this.selected == undefined)
        {
          this.select(this.system.nodes[0].localId);
        }
        this.$refs.nname.value = "";
        setTimeout(() => {this.$refs.nedit.scrollTop =  this.$refs.nedit.scrollHeight+123}, 100);
      },
      save()
      {
        var jsond = this.system.toObject(); 
        var fdata = qstr.stringify({version: this.coreVersions[this.versionSelected], data: JSON.stringify(jsond)});
        console.log(fdata);
        console.log(jsond);
        this.savedState = "pending";
        axios.post('/insider/core/import', fdata).then(async (res) => {
          console.log(res.data);
          // document.write(res.data);
          this.savedState = "success";
          setTimeout(() => {this.savedState = "nothing"}, 3000);
        }).catch((e) => {
          console.error(e.data);
          this.savedState = "error";
          setTimeout(() => {this.savedState = "nothing"}, 3000);
        })
      }
    },
    data() {
      return {
        coreVersions: [],
        systems: [],
        savedState: "nothing",
        versionSelected: 2,
        selectedNode: 0,
        nodeFilter: "",
        lastNodeId: 0,
        ready: false,
        newTitle: "",
      }
    },
    name: 'core-editor'
  }
</script>


<style scoped>
  .graph {
    border-bottom: 1px solid #CCC;
    display: flex;
    max-height: 400px;
    border-left: 1px solid #ccc;
    border-right: 1px solid #ccc;
  }

  .nodes {
    border-right: 1px solid #ccc;
  }

  .split {
    display: flex;

  }

  .addVer
  {

    margin-right: 10px;
    margin-bottom: 10px;
    border-radius: 5px;
    list-style: none;
    cursor: pointer;
    padding: 10px 20px;
    flex-direction: column;
    transition: .1s all;
    color: #2c78c9;
    border: 2px dashed #2c78c9;
  }
  .ico-li
  {
    list-style: none;
    padding: 10px;
  }
  .ico.success
  {
    color: green;
  }
  .ico.pending
  {
    color: #2c78c9;
  }
  .ico.fail
  {
    color: crimson;
  }
  .addVer:hover
  {
    transform: scale(1.1);
  }
  .f {
    flex: 1;
    padding: 10px 20px;
  }

  .split>* {
    flex: 1;
  }

  .parents .node,
  .children .node {
    border: none !important;
  }

  .ico
  {
    font-size: 30px;
  }

  .nodelist {
    overflow: auto;
    flex: 1;
  }

  .nodes {
    flex: 1;
    display: flex;
    flex-direction: column;

  }

  .parents {
    border-top: 2px solid #2c78c9;
  }

  .children {
    border-top: 2px solid green;
  }

  .node {
    align-items: stretch;
    display: flex;
    justify-content: space-between;
  }

  .node_edit {
    flex: 1;

    overflow: auto;
  }

  h4 {
    padding: 4px !important;
  }

  input[type=text] {
    padding: 10px 20px;
    box-sizing: border-box;
    width: 100%;
    border: none;
    border-bottom: 1px solid #ccc;
  }

  .node {
    cursor: pointer;
  }

  .node:hover {
    background: #eee;
  }

  .node.selected {
    background: #2c78c9;
    color: #fff;
  }

  .select {
    margin: 0 !important;
    padding-left: 30px !important;
    padding-bottom: 20px;
    flex-wrap: wrap;
    display: flex;
  }

  h2 {
    padding: 30px;
  }

  .option {
    margin-right: 10px;
    margin-bottom: 10px;
    border-radius: 5px;
    list-style: none;
    cursor: pointer;
    padding: 10px 20px;
    flex-direction: column;

    color: #2c78c9;
    border: 1px solid #2c78c9;
  }

  .option.selected {
    color: #fff;
    background: #2c78c9;
    border-color: #2c78c9 !important;
  }

  .fieldset {
    display: flex;

  }

  .fieldset input {

    border: none;
    border-top: 1px solid #ccc;
    flex: 1;
  }

  .btn-add {
    background: #2c78c9;
    color: #fff;
    font-size: 30px;
    cursor: pointer;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 0 15px;
    text-align: center;

  }
</style>