import {io} from 'socket.io-client';

// The socket carries invalidations. The existing authenticated history endpoint supplies the content.
export function watchTaskChat(root, panel, student, refresh) {
    let socket, retry, fallback, attempts = 0, visible = false, suspended = false;
    const active = () => visible && !suspended && !document.hidden;
    function stop() {
        clearTimeout(retry);
        clearInterval(fallback);
        fallback = null;
        const previous = socket;
        socket = null;
        previous?.close();
    }
    function catchUpWhileOffline() {
        if (!fallback) fallback = setInterval(refresh, 10000);
    }
    function connect() {
        if (!active() || socket) return;
        catchUpWhileOffline();
        if (!root.dataset.chatSocket || !window.WebSocket) return;
        try {
            const url = new URL(root.dataset.chatSocket, location.href);
            const origin = url.origin.replace(/^ws/, 'http');
            const channel = io(origin, {path:url.pathname.replace(/\/$/, '')+'/io', transports:['websocket'],
                forceNew:true, reconnection:false, withCredentials:true,
                auth:{course:root.dataset.courseId, task:root.dataset.taskId, student}});
            socket = channel;
            channel.on('ready', () => {
                if (socket !== channel) return;
                clearInterval(fallback); fallback=null; attempts=0;
                refresh();
            });
            channel.on('changed', () => { if (socket === channel) refresh(); });
            const reconnect = () => {
                if (socket !== channel) return;
                socket=null;
                channel.close();
                catchUpWhileOffline();
                retry=setTimeout(connect,Math.min(15000,1000*2**attempts++));
            };
            channel.on('disconnect',reconnect);
            channel.on('connect_error',reconnect);
        } catch { /* Invalid deployment URL: keep the authenticated HTTP fallback. */ }
    }
    function sync() { if (active()) connect(); else stop(); }
    const observer = new IntersectionObserver(([entry]) => { visible = entry.isIntersecting; sync(); });
    observer.observe(panel);
    const hide = () => { suspended = true; stop(); };
    const show = () => { suspended = false; sync(); };
    document.addEventListener('visibilitychange', sync);
    window.addEventListener('pagehide', hide);
    window.addEventListener('pageshow', show);
    return () => {
        stop();
        observer.disconnect();
        document.removeEventListener('visibilitychange', sync);
        window.removeEventListener('pagehide', hide);
        window.removeEventListener('pageshow', show);
    };
}
