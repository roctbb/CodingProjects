import {test} from 'node:test';
import assert from 'node:assert/strict';
import {request} from '../../resources/assets/js/code-checking/ui.js';
import {subscribe} from '../../resources/assets/js/code-checking/progress.js';

test('native request preserves source and uploads multipart using PHP method override and CSRF', async () => {
    const oldFetch = globalThis.fetch, oldDocument = globalThis.document;
    const calls = [];
    globalThis.document = {querySelector:() => ({content:'csrf-token'})};
    globalThis.fetch = async (url,options) => {calls.push({url,...options});return {ok:true,json:async()=>({saved:true})};};
    try {
        await request('/draft','PUT',{code:'  print(1)\n'});
        assert.equal(calls[0].body,JSON.stringify({code:'  print(1)\n'}));
        const form = new FormData();form.append('revision','1');form.append('file',new Blob(['source']),'main.py');
        await request('/draft','PUT',form);
        assert.equal(calls[1].method,'POST');assert.equal(form.get('_method'),'PUT');
        assert.equal(calls[1].credentials,'same-origin');assert.equal(calls[1].headers['X-CSRF-TOKEN'],'csrf-token');
        assert.equal(calls[1].headers['Content-Type'],undefined);
        globalThis.fetch = async () => ({ok:false,status:409,json:async()=>({})});
        await assert.rejects(request('/draft','PUT',{}),error=>error.status===409);
    } finally {globalThis.fetch=oldFetch;globalThis.document=oldDocument;}
});

test('direct execution socket ignores other runs, restores reconnect progress and completes once', () => {
    const handlers = {}, received = [];let terminal=0,closed=0;
    const socket = {on:(name,fn)=>{handlers[name]=fn;},disconnect:()=>{closed++;}};
    const stop = subscribe({url:'https://executor.test',namespace:'/executions',execution_id:'owned',token:'scoped'},
        event=>received.push(event),()=>terminal++,()=>{},null,(url,options)=>{
            assert.equal(url,'https://executor.test/executions');assert.deepEqual(options.transports,['websocket']);assert.equal(options.auth.token,'scoped');return socket;
        });
    const send=(id,sequence,phase)=>handlers.execution_progress({execution_id:id,sequence,phase});
    send('other',9,'testing');send('owned',2,'testing');send('owned',1,'preparing');send('owned',2,'testing');
    send('owned',3,'completed');send('owned',3,'completed');
    assert.equal(received.length,4);assert.equal(terminal,1);
    stop();send('owned',4,'completed');assert.equal(received.length,4);assert.equal(closed,1);
});

import {diffLines} from '../../resources/assets/js/code-checking/diff.js';

test('line comparison preserves both attempts including blanks and repeated lines', () => {
    for (const [before,after] of [['','a'],['a\nb\na\n','a\nc\na\n'],['same','same'],[Array(600).fill('a').join('\n'),Array(600).fill('b').join('\n')]]) {
        const result=diffLines(before,after);
        assert.equal(result.filter(row=>row.type!=='added').map(row=>row.text).join('\n'),before);
        assert.equal(result.filter(row=>row.type!=='removed').map(row=>row.text).join('\n'),after);
    }
});
