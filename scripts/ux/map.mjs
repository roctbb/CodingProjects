import { readFileSync, writeFileSync, existsSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const file = (name) => path.join(root, 'docs/ux', name);
const map = JSON.parse(readFileSync(file('system-map.json'), 'utf8'));
const routes = JSON.parse(execFileSync('php', ['artisan', 'route:list', '--json'], { cwd: root, encoding: 'utf8' }));
const nodes = new Map(map.nodes.map((node) => [node.id, node]));
const errors = [];
const types = new Set(['screen', 'action', 'api', 'download', 'redirect', 'callback', 'media', 'maintenance']);
const escape = (value) => String(value ?? '').replaceAll('|', '\\|').replaceAll('\n', ' ');
const id = (value) => value.replaceAll('-', '_');
if (nodes.size !== map.nodes.length) errors.push('Duplicate node IDs');
for (const node of map.nodes) {
  if (!map.domains.some((domain) => domain.id === node.domain)) errors.push(`${node.id}: unknown domain`);
  for (const source of node.sources) if (!existsSync(path.join(root, source))) errors.push(`${node.id}: missing ${source}`);
  if (node.parent && !nodes.has(node.parent)) errors.push(`${node.id}: unknown parent`);
}
for (const edge of map.edges) {
  if (!nodes.has(edge.from) || !nodes.has(edge.to)) errors.push(`Unknown edge: ${edge.from} → ${edge.to}`);
  if (!edge.label || !edge.source || !existsSync(path.join(root, edge.source))) errors.push(`Missing edge evidence: ${edge.from} → ${edge.to}`);
}
const designNodes = new Set([...nodes.keys(), ...(map.proposedNodes || []).map((node) => node.id)]);
for (const edge of map.proposedEdges || []) {
  if (!designNodes.has(edge.from) || !designNodes.has(edge.to)) errors.push(`Unknown proposed edge: ${edge.from} → ${edge.to}`);
  if (!existsSync(path.join(root, edge.source))) errors.push(`Missing design evidence: ${edge.source}`);
}
const snapshot = routes.map((route) => {
  const action = route.action.replace('App\\Http\\Controllers\\', '');
  const key = action === 'Closure' ? `Closure:${route.uri}` : action;
  const binding = map.routeBindings[key];
  if (!binding || !nodes.has(binding.node) || !types.has(binding.kind)) errors.push(`Unmapped route: ${route.method} ${route.uri} (${key})`);
  return { method: route.method, uri: route.uri, name: route.name, action, middleware: route.middleware, ...binding };
});
for (const node of map.nodes.filter((node) => node.kind === 'page')) {
  if (!snapshot.some((route) => route.node === node.id && route.kind === 'screen')) errors.push(`${node.id}: page without a screen route`);
}
if (errors.length) {
  console.error(errors.join('\n'));
  process.exit(1);
}

const routeTable = snapshot.map((route) => `| ${escape(route.method)} | \`/${route.uri === '/' ? '' : route.uri}\` | ${route.kind} | [${nodes.get(route.node).title}](screens.md#${route.node}) | ${escape(route.middleware.join(', '))} |`).join('\n');
const screenBlocks = map.nodes.map((node) => {
  const ownRoutes = snapshot.filter((route) => route.node === node.id);
  const transitions = map.edges.filter((edge) => edge.from === node.id && edge.kind !== 'shell');
  return `<a id="${node.id}"></a>\n\n## ${node.title}\n\n` +
    `Тип: ${node.kind}. Роли сценария: ${node.roles.map((role) => map.roles[role]).join(', ')}.\n\n${node.goal}\n\n` +
    (node.guard ? `Условия: ${node.guard}\n\n` : '') + (node.note ? `${node.note}\n\n` : '') +
    `Проверка: ${node.review.note}\n\n` +
    (node.checkStates?.length ? `Состояния для макета и проверки: ${node.checkStates.join('; ')}.\n\n` : '') +
    (node.design ? `Шаблон экрана: ${node.design.layout}. Статус: ${node.design.stage}.\n\n` : '') +
    (ownRoutes.length ? `Маршруты:\n\n${ownRoutes.map((route) => `- \`${route.method} /${route.uri === '/' ? '' : route.uri}\` — ${route.kind}`).join('\n')}\n\n` : '') +
    (transitions.length ? `Переходы:\n\n${transitions.map((edge) => `- ${edge.label} → [${nodes.get(edge.to).title}](#${edge.to}) (${edge.kind})`).join('\n')}\n\n` : '') +
    `Исходники:\n\n${node.sources.map((source) => `- [${source}](../../${source})`).join('\n') || '- Внешняя граница системы.'}\n`;
}).join('\n');

function graph(domain) {
  const own = map.nodes.filter((node) => node.domain === domain.id);
  const links = map.edges.filter((edge) => nodes.get(edge.from).domain === domain.id && edge.kind !== 'shell');
  const linked = new Set([...own.map((node) => node.id), ...links.map((edge) => edge.to)]);
  return 'flowchart TB\n' + [...linked].map((key) => {
    const node = nodes.get(key);
    const label = node.title.replaceAll('"', "'");
    return `  ${id(key)}${node.kind === 'dialog' ? '(["' : '["'}${label}${node.kind === 'dialog' ? '"])' : '"]'}`;
  }).join('\n') + '\n' + links.map((edge) => `  ${id(edge.from)} ${edge.kind === 'close' ? '-.' : '-'}->|"${edge.label.replaceAll('"', "'")}"| ${id(edge.to)}`).join('\n') + '\n';
}
const outputs = new Map([
  ['routes.json', JSON.stringify(snapshot, null, 2) + '\n'],
  ['routes.md', '# Покрытие маршрутов UX-картой\n\nГенерируется `npm run ux:build`. GET-маршрут может изменять данные, отдавать файл или перенаправлять; он не считается экраном автоматически. Таблица — инвентаризация текущего кода, не аудит безопасности.\n\n| Метод | URL | Тип | Владелец в UX | Middleware |\n| --- | --- | --- | --- | --- |\n' + routeTable + '\n'],
  ['screens.md', '# Экраны, состояния и переходы\n\nГенерируется из `system-map.json`. «Изучен код» не означает «визуально проверен». Роли описывают пользовательский сценарий; точные middleware перечислены в `routes.md`, дополнительные проверки — в контроллерах и policy.\n\n' + screenBlocks],
]);
for (const domain of map.domains) outputs.set(`${domain.id}.mmd`, graph(domain));
const counts = { routes: snapshot.length, pages: map.nodes.filter((node) => node.kind === 'page').length, nodes: map.nodes.length, edges: map.edges.length };
outputs.set('coverage.json', JSON.stringify({ ...counts, unmappedRoutes: 0, missingPageRoutes: 0, missingSources: 0 }, null, 2) + '\n');
const visualData = { ...map, routeBindings: undefined, routes: snapshot };
outputs.set('graph.html', readFileSync(file('graph.template.html'), 'utf8').replace('/* UX_MAP_DATA */', JSON.stringify(visualData).replaceAll('<', '\\u003c')));
if (process.argv.includes('--write')) {
  for (const [name, content] of outputs) writeFileSync(file(name), content);
} else {
  for (const [name, content] of outputs) {
    if (!existsSync(file(name)) || readFileSync(file(name), 'utf8') !== content) errors.push(`${name} is stale; run npm run ux:build`);
  }
}
if (errors.length) {
  console.error(errors.join('\n'));
  process.exit(1);
}
console.log(`UX map: ${counts.pages} pages, ${counts.nodes} nodes, ${counts.edges} transitions, ${counts.routes}/${counts.routes} routes mapped.`);
