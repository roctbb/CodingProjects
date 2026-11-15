// Only completed detail responses are cached. Mutations invalidate the cache.
export function detailCache(fetchDetail, {limit = 4, ttl = 15000, now = Date.now} = {}) {
    const values = new Map();
    let pending;
    return {
        async get(key) {
            if (pending?.key === key) return pending.promise;
            pending?.controller.abort();
            const saved = values.get(key);
            if (saved && now() - saved.at < ttl) {
                values.delete(key); values.set(key, saved);
                pending = null;
                return saved.value;
            }
            const controller = new AbortController();
            const entry = {key, controller};
            entry.promise = fetchDetail(key, controller.signal).then(value => {
                if (!controller.signal.aborted && ['completed','error','awaiting_review','imported_pending'].includes(value.state)) {
                    values.set(key, {value, at:now()});
                    while (values.size > limit) values.delete(values.keys().next().value);
                }
                return value;
            }).finally(() => {if (pending === entry) pending = null;});
            pending = entry;
            return entry.promise;
        },
        clear() {values.clear();pending?.controller.abort();pending=null;},
        cancel() {pending?.controller.abort(); pending = null;},
    };
}
