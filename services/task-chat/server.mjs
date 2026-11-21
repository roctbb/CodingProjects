import http from 'node:http';
import {setTimeout as delay} from 'node:timers/promises';
import pg from 'pg';
import {Server as SocketIO} from 'socket.io';
import {WebSocketServer} from 'ws';

const rooms = new Map();
const sockets = new WebSocketServer({noServer:true, maxPayload:256, perMessageDeflate:false});
const authBase = process.env.TASK_CHAT_AUTH_URL || 'http://nginx:8050';
let listening = false;
const server = http.createServer((req, res) => {
    res.writeHead(req.url === '/health' && listening ? 200 : 503).end();
});
function validOrigin(req) {
    try {
        const origin = new URL(req.headers.origin);
        return ['https:', 'http:'].includes(origin.protocol) && (process.env.TASK_CHAT_ORIGIN
            ? origin.origin === process.env.TASK_CHAT_ORIGIN : origin.host === req.headers.host);
    } catch { return false; }
}

async function authorized(req, course, task, student) {
    if (!listening || !req.headers.cookie || !validOrigin(req)
        || ![course, task, student].every(id => /^[1-9][0-9]{0,17}$/.test(id))) return false;
    const origin = new URL(req.headers.origin);
    const response = await fetch(new URL('/insider/courses/'+course+'/tasks/'+task+'/chat/'+student+'?socket_auth=1', authBase), {
        headers:{Cookie:req.headers.cookie, Accept:'application/json', 'X-Forwarded-Proto':origin.protocol.slice(0, -1)},
        redirect:'manual', signal:AbortSignal.timeout(5000),
    });
    await response.body?.cancel();
    return response.status === 204 && listening;
}
server.on('upgrade', async (req, connection, head) => {
    if (req.url.startsWith('/task-chat/socket/io/')) return; // Engine.IO owns this upgrade.
    connection.on('error', () => connection.destroy());
    const route = /^\/task-chat\/socket\/(\d+)\/(\d+)\/(\d+)$/.exec(req.url);
    const reject = () => connection.end('HTTP/1.1 403 Forbidden\r\nConnection: close\r\n\r\n');
    if (!route || !listening || !req.headers.cookie) return reject();
    try {
        const [, course, task, student] = route;
        if (!await authorized(req, course, task, student) || connection.destroyed) return reject();
        const room = [course, task, student].join('/');
        sockets.handleUpgrade(req, connection, head, ws => {
            if (!rooms.has(room)) rooms.set(room, new Set());
            rooms.get(room).add(ws);
            ws.alive = true;
            ws.on('pong', () => { ws.alive = true; });
            ws.on('error', () => ws.terminate());
            ws.on('message', () => ws.close(1008)); // Subscribers only receive invalidations.
            const expiry = setTimeout(() => ws.close(1000), 300_000);
            ws.on('close', () => {
                clearTimeout(expiry);
                const members = rooms.get(room);
                members.delete(ws);
                if (!members.size) rooms.delete(room);
            });
            ws.send('ready');
        });
    } catch { reject(); }
});
setInterval(() => {
    for (const ws of sockets.clients) {
        if (!ws.alive) ws.terminate();
        else { ws.alive = false; ws.ping(); }
    }
}, 25000).unref();

const io = new SocketIO(server, {
    path:'/task-chat/socket/io', transports:['websocket'], serveClient:false, destroyUpgrade:false, maxHttpBufferSize:4096,
    allowRequest:(req, done)=>done(null, listening && validOrigin(req)),
});
io.use(async (socket, next) => {
    const {course, task, student} = socket.handshake.auth;
    try {
        if (await authorized(socket.request,course,task,student)) {
            socket.data.room = [course,task,student].join('/');
            return next();
        }
    } catch { /* fail closed when Laravel is unavailable */ }
    next(new Error('Access denied'));
});
io.on('connection', socket => {
    socket.join(socket.data.room);
    socket.emit('ready');
    const expiry=setTimeout(()=>socket.conn.close(),300000);
    socket.once('disconnect',()=>clearTimeout(expiry));
});

server.listen(Number(process.env.TASK_CHAT_PORT || 8080), process.env.TASK_CHAT_HOST || '0.0.0.0');
while (true) {
    const db = new pg.Client({connectionTimeoutMillis:5000, keepAlive:true, application_name:'geekclass-task-chat'});
    const ended = new Promise(resolve => { db.on('error', resolve); db.once('end', resolve); });
    db.on('notification', ({payload}) => {
        io.to(payload).emit('changed');
        for (const ws of rooms.get(payload) || []) {
            if (ws.readyState === ws.OPEN) ws.send('changed');
        }
    });
    try {
        await db.connect();
        await db.query('LISTEN task_chat_changed');
        listening = true;
        await ended;
    } catch (error) { console.error('Task chat listener:', error.code || error.name); }
    finally {
        listening = false;
        for (const session of io.sockets.sockets.values()) session.conn.close();
        for (const ws of sockets.clients) ws.terminate();
        await db.end().catch(() => {});
    }
    await delay(1000);
}
