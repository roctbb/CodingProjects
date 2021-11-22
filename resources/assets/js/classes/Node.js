import { resolve } from "url";
import Connection from "./Connection";

export default class Node
{
  constructor(title, id, localId)
  {
    this.connections = [];
    this.hpCache = [];
    
    this.title = title;
    this.id = id;
    this.localId = localId;
  }



  // canBeChild(id, queue)
  // {
  //   for (var i = 0; i < this.connections.length; i++)
  //   {
  //     if (this.hasChild(id))
  //     {
  //       return(false);
  //     }   
  //   }
  //   for (var i = 0; i < this.connections.length; i++)
  //   {
  //     if (this.hasParent(id))
  //     {
  //       return(false);
  //     }
  //   }
    
  //   return(true);
    
  // }
  
  hasParent(id)
  {
    var pref = this.parents();
    for (var i = 0; i < pref.length; i++)
    {
      if (pref[i].source.localId == id)
      {
        return 1;
      }
      else 
      {
        pref[i].source.hasParent(id);
      }
    }
    return 0;
  }

  addChild(node, rerr = false)
  {
    if (this.connections.findIndex((e) => e.target.localId == node.localId) != -1) 
    {
      if (rerr) return;
      alert("Нельзя добавить дочерний элемент дважды");
      return;
    }
    if (this.hasParent(node.localId))
    {
      if (rerr) return;
      alert("Нельзя добавить элемент который является родителем");
      return;
    }

    node.addParent(this);
  }

  parents()
  {
    return this.connections.filter((e) => e.target.localId == this.localId);
  }

  children()
  {
    return this.connections.filter((e) => e.source.localId == this.localId);
  }
  
  removeChild(nodeId)
  {
    var index = this.connections.findIndex((e) => e.target.localId == nodeId);
    if (index == -1) return;

    var node = this.connections.find((e) => e.target.localId == nodeId);

    var node = node.target;
    var indexn2 = node.connections.findIndex((e) => e.source.localId == this.localId);
    this.connections.splice(index, 1);
    node.connections.splice(indexn2, 1);
  }
  
  addParent(node)
  {
    if (this.connections.findIndex((e) => e.source.localId == node.localId) != -1) 
    {
      return;
    }
    this.connections.push(new Connection(node, this));
    node.connections.push(new Connection(node, this));
  }
  
  removeParent(nodeId)
  {
    var index = this.connections.findIndex((e) => e.source.localId == nodeId);
    if (index == -1) return;
    var node = this.connections.find((e) => e.source.localId == nodeId);
    var node = node.source;
    var indexn2 = node.connections.findIndex((e) => e.target.localId == this.localId);
    console.warn(index);
    this.connections.splice(index, 1);
    node.connections.splice(indexn2, 1);
  }
}