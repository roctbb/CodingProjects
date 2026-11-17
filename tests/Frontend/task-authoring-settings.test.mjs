import test from 'node:test';
import assert from 'node:assert/strict';
import {submissionFields,hasFloatComparison,checkerPayload} from '../../resources/assets/js/code-checking/authoring-settings.js';

test('submission settings follow the selected ways of sending work',()=>{
    assert.deepEqual(submissionFields({language:'text',submission_modes:['text']}),{languages:false,extensions:false,entrypoint:false,limits:false});
    assert.deepEqual(submissionFields({language:'python',submission_modes:['code']}),{languages:true,extensions:false,entrypoint:false,limits:false});
    assert.deepEqual(submissionFields({language:'text',submission_modes:['files']}),{languages:false,extensions:true,entrypoint:false,limits:true});
    assert.deepEqual(submissionFields({language:'python',submission_modes:['repository']}),{languages:true,extensions:false,entrypoint:true,limits:true});
});

test('floating point settings appear for the default comparison or an individual test override',()=>{
    assert.equal(hasFloatComparison({comparison:'exact',tests:[{comparison:'tokens'}]}),false);
    assert.equal(hasFloatComparison({comparison:'float'}),true);
    assert.equal(hasFloatComparison({comparison:'tokens',tests:[{comparison:'float_binary64'}]}),true);
});

test('non-test payloads omit unused test settings while preserving edits when the checker is switched back',()=>{
    const config={check_type:'gpt',test_config:{tests:[{input:'1',output:'2'}]},private_instructions:'Rubric'};
    assert.deepEqual(checkerPayload(config),{check_type:'gpt',private_instructions:'Rubric'});
    assert.equal(config.test_config.tests[0].output,'2');
    config.check_type='tests';
    assert.equal(checkerPayload(config).test_config.tests[0].output,'2');
});

test('hidden settings do not block saving another mode and remain available when revealed again',()=>{
    const config={check_type:'manual',language:'text',submission_modes:['text'],gpt_rate_limit:0,max_files:0,entrypoint:'../invalid',allowed_extensions:['bad extension']};
    assert.deepEqual(checkerPayload(config),{check_type:'manual',language:'text',submission_modes:['text']});
    assert.equal(config.gpt_rate_limit,0);
    const tests={check_type:'tests',language:'python',submission_modes:['code'],test_config:{comparison:'exact',absolute_tolerance:'invalid',tests:[]}};
    assert.equal(checkerPayload(tests).test_config.absolute_tolerance,undefined);
    assert.equal(tests.test_config.absolute_tolerance,'invalid');
});
