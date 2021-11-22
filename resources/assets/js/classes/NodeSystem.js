import Node from './Node';
import Connection from './Connection';

export default class NodeSystem
{
  constructor()
  {
    this.nodes = [];
    this.current = null;
    this.versionBinding = 0;
    this.deletedNodes = [];
    this.lastLocalId = 1;
  }

  setCurrent(id)
  {
    this.current = this.nodes.find((e) => e.localId == id);
  }

  find(id)
  {
    return this.nodes.find((e) => e.localId == id)
  }
  findIndex(id)
  {
    return this.nodes.findIndex((e) => e.localId == id)
  }
  addNode(title)
  {
    this.nodes.push(new Node(title, -1, this.lastLocalId));
    this.lastLocalId++;
  }

  removeNode(id)
  {
    this.deletedNodes.push(id);

    this.nodes.forEach((e) => {
      e.removeChild(id);
      e.removeParent(id);
    })
    this.nodes.splice(this.findIndex(id), 1);
  }

  toObject()
  {
    var obj = {
      nodes: [],
      edges: [],
      deletedNodes: this.deletedNodes
    };


    this.nodes.forEach((e) => {
      obj.nodes.push({
        title: e.title,
        localId: e.localId,
        id: e.id,
        nodeType: "exists",
        level: 6,
        root: false,
        cluster: 0
      });
      e.children().forEach((i, d) => {
        obj.edges.push({
          id: i.id,
          source: e.localId,
          target: i.target.localId,
          type: "partOf"

        });
        i.target.parents().splice( i.target.parents().findIndex((h) => h.source.localId == e.localId), 1);
      });
    })
    return obj;
  }

  fromObject(obj)
  {
    console.log(obj.nodes.length);
    console.log(obj.edges.length);
    this.nodes = [];
    var connections = [];

    
    obj.nodes.forEach((e) => {
      this.nodes.push(new Node(e.title, e.id, this.lastLocalId));
      this.lastLocalId++;
    });

    obj.edges.forEach((e) => {
      var parent = this.nodes.find((i) => e.from_id == i.id);
      var child = this.nodes.find((i) => e.to_id == i.id);
      connections.push(new Connection(child, parent, e.id));
    });
    
    connections.forEach((e) => {
      e.source.addParent(e.target);
      e.target.addChild(e.source, true);
    })


    this.current = this.nodes[0];

  }

}