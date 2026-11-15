import {test} from 'node:test';
import assert from 'node:assert/strict';
import {request} from '../../resources/assets/js/code-checking/ui.js';
import {subscribe} from '../../resources/assets/js/code-checking/progress.js';
import {detailCache} from '../../resources/assets/js/code-checking/detail-cache.js';

test('attempt switching cancels the old request, caches only terminal data and invalidates after changes', async () => {
    const calls=[];let clock=0;
    const cache=detailCache((key,signal)=>new Promise((resolve,reject)=>{
        signal.addEventListener('abort',()=>reject(new DOMException('cancelled','AbortError')));
        calls.push({key,signal,resolve});
    }),{limit:2,ttl:100,now:()=>clock});
    const first=cache.get('first');const cancelled=assert.rejects(first,{name:'AbortError'});
    const second=cache.get('second');calls[1].resolve({id:'second',state:'completed'});
    await cancelled;assert.equal((await second).id,'second');
    assert.equal((await cache.get('second')).id,'second');assert.equal(calls.length,2);
    cache.clear();const refreshed=cache.get('second');calls[2].resolve({id:'second',state:'testing'});await refreshed;
    const live=cache.get('second');calls[3].resolve({id:'second',state:'completed'});await live;
    clock=101;const expired=cache.get('second');calls[4].resolve({id:'second',state:'completed'});await expired;
    assert.equal(calls.length,5);
    const stale=cache.get('third');const invalidated=assert.rejects(stale,{name:'AbortError'});cache.clear();await invalidated;
    const updated=cache.get('third');calls[6].resolve({id:'third',state:'completed'});await updated;
    assert.equal(calls.length,7);
});

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

import {submissionGroups,submissionMode,uploadDescription,executionPhase,draftFilePayload} from '../../resources/assets/js/code-checking/composer-options.js';

test('student methods combine code/project and preserve fixed repository and restricted materials',()=>{
    assert.deepEqual(submissionGroups({submission_modes:['code','project']}),['code']);
    assert.deepEqual(submissionGroups({submission_modes:['repository']}),['repository']);
    assert.equal(submissionMode({submission_modes:['code','project']},'code',1),'code');
    assert.equal(submissionMode({submission_modes:['code','project']},'code',2),'project');
    assert.equal(submissionMode({submission_modes:['project']},'code',1),'project');
    assert.equal(submissionMode({submission_modes:['text']},'text',1),'text');
    assert.equal(submissionMode({submission_modes:['files','text','code']},'files',0),'text');
    assert.equal(uploadDescription({allowed_extensions:['ipynb']}),'Jupyter Notebook (.ipynb)');
    assert.equal(uploadDescription({allowed_extensions:['xlsx']}),'Таблица Excel (.xlsx)');
});

test('execution progress has exact test counts and no invented preparation percentage',()=>{
    assert.equal(executionPhase({phase:'preparing',completed:1,total:4}).total,null);
    assert.equal(executionPhase({phase:'compiling',completed:1,total:4}).total,null);
    assert.deepEqual(executionPhase({phase:'testing',completed:7,total:24}),{label:'Запускаю тесты · 7 из 24',completed:7,total:24});
});

test('saved originals and large legacy sources are retained without replacing their bytes',()=>{
    const files=[{index:0,path:'main.cpp',bytes:3145728},{index:1,path:'notebook.ipynb',content:'original JSON'},{index:2,path:'helper.py',content:'updated',changed:true}];
    assert.deepEqual(draftFilePayload(files,4),{keep_files:[0,1],editor_files:[{path:'helper.py',content:'updated'}]});
    assert.deepEqual(draftFilePayload([{index:0,path:'main.py',content:'starter'}],0),{keep_files:[],editor_files:[{path:'main.py',content:'starter'}]});
});
