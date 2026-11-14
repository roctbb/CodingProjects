import {io} from 'socket.io-client';
export function subscribe(subscription, update, terminal, disconnected, renew, connect = io) {
    if (!subscription) return () => {};
    let sequence = -1, finished = false, closed = false, renewing = false;
    const socket = connect(subscription.url + subscription.namespace, {transports:['websocket'], auth:{token:subscription.token}, reconnection:true});
    socket.on('execution_progress', event => {
        if (closed || event.execution_id !== subscription.execution_id || !Number.isInteger(event.sequence) || event.sequence < sequence) return;
        sequence = event.sequence; update(event);
        if (['completed','error'].includes(event.phase) && !finished) { finished = true; terminal(); }
    });
    socket.on('disconnect', () => {if (!closed && !finished) disconnected();});
    socket.on('connect_error', async () => {
        disconnected();
        const expiry = typeof subscription.expires_at === 'number' ? subscription.expires_at * 1000 : Date.parse(subscription.expires_at);
        if (renewing || closed || !renew || expiry > Date.now()) return;
        renewing = true;
        try { const next = await renew(); if (!closed) {subscription = next; socket.auth = {token:next.token}; socket.connect();} }
        catch { /* A visible reconnect action remains available. */ }
        finally {renewing = false;}
    });
    return () => {closed = true; socket.disconnect();};
}
