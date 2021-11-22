export default class Connection
{
  constructor(source, target, id = -1)
  {
    this.id = id;
    this.source = source;
    this.target = target;
  }
}