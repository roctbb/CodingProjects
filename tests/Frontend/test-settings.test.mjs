import {test} from 'node:test';
import assert from 'node:assert/strict';
import {enableGroups,addGroup,removeGroup,testIssues} from '../../resources/assets/js/code-checking/test-settings-model.js';

test('group scoring assigns existing tests and keeps file descriptors and overrides',()=>{
    const blob={type:'blob',blob_id:'original',bytes:2000000};
    const config={tests:[{input:blob,output:'ok',time_limit:7,comparison:'float'}]};
    enableGroups(config,20);
    assert.equal(config.tests[0].input,blob);assert.equal(config.tests[0].time_limit,7);
    assert.equal(config.tests[0].group,config.groups[0].id);assert.deepEqual(testIssues(config,20),[]);
    const extra=addGroup(config);config.groups[0].points=10;extra.points=10;
    assert.ok(testIssues(config,20).some(issue=>issue.includes('хотя бы один тест')));
    config.tests.push({group:extra.id,input:'x',output:'y'});
    assert.deepEqual(testIssues(config,20),[]);
    assert.ok(testIssues(config,30).some(issue=>issue.includes('30 XP')));
    removeGroup(config,extra.id);assert.equal(config.tests[1].group,undefined);
    assert.ok(testIssues(config,20).some(issue=>issue.includes('каждому тесту')));
});

test('group identities survive switching rules and empty sets fail validation',()=>{
    const config={tests:[{input:'',output:''}]};enableGroups(config,10);
    const original=config.groups[0].id;addGroup(config);removeGroup(config,'group_1');
    const next=addGroup(config);config.tests[0].group=next.id;
    config.scoring='proportional';enableGroups(config,10);
    assert.equal(config.groups[0].id,original);assert.equal(config.tests[0].group,next.id);
    assert.ok(testIssues({scoring:'proportional',tests:[]},10).length);
});
