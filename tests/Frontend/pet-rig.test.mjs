import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {plantedFoot, rigPose} from '../../resources/assets/js/components/pet-rig-model.js';

const names = ['learning_bot','brr_brr_patapim','tralalelo_tralala','tung_tung_tung_sahur'];
const rigs = names.map(name => ({name,...JSON.parse(readFileSync(new URL(`../../public/images/screen-pets/${name}-rig-v1.json`,import.meta.url)))}));

test('all supporting paws remain fixed in world coordinates, including acceleration and both directions', () => {
    for (const {name,rig} of rigs) {
        for (const stride of [rig.stride*.7,rig.stride,rig.stride*1.3]) {
            for (const direction of [-1,1]) {
                const anchors = [];
                for (let i=0;i<1200;i++) {
                    const t=i/1199,phase=3*t*t*(3-2*t);
                    const pose=rigPose(rig,{direction,phase,state:'walk',stride});
                    pose.legs.forEach((leg,index)=>{
                        const world=direction*phase*stride+leg.foot.x;
                        if(leg.foot.planted) {
                            anchors[index] ??= world;
                            assert.ok(Math.abs(world-anchors[index])<1e-7,name);
                            assert.equal(leg.foot.y,rig.ankle,name);
                        } else anchors[index]=undefined;
                    });
                }
            }
        }
    }
});

test('walking joints retain their lengths and turn joints never extend beyond their physical length', () => {
    for(const {name,rig} of rigs) {
        let maxDrop=0;
        for(const direction of [-1,1]) {
            for(const state of ['walk','prepare','settle','turn']) {
                for(let i=0;i<=400;i++) {
                    const p=i/400;
                    const pose=rigPose(rig,{direction,state,phase:p,progress:p,stride:rig.stride*1.3});
                    maxDrop=Math.max(maxDrop,pose.bodyY);
                    for(const leg of pose.legs) {
                        const upper=Math.hypot(leg.knee.x-leg.hip.x,leg.knee.y-leg.hip.y);
                        const lower=Math.hypot(leg.knee.x-leg.foot.x,leg.knee.y-leg.foot.y);
                        assert.ok(upper<=leg.upper+1e-7,`${name} ${state} upper ${p}: ${upper}`);
                        assert.ok(lower<=leg.lower+1e-7,`${name} ${state} lower ${p}: ${lower}`);
                        if(state==='walk') {
                            assert.ok(Math.abs(upper-leg.upper)<1e-7,name);
                            assert.ok(Math.abs(lower-leg.lower)<1e-7,name);
                        }
                    }
                }
            }
        }
        assert.ok(maxDrop<24,`${name} excessive crouch: ${maxDrop}`);
    }
});

test('turns keep planted paws fixed, finish in a neutral stance and use native body views', () => {
    for(const {name,rig,frames} of rigs) {
        for(const direction of [-1,1]) {
            const anchors=[];
            for(let i=0;i<=1500;i++) {
                const pose=rigPose(rig,{direction,state:'turn',progress:i/1500});
                pose.legs.forEach((leg,index)=>{
                    if(leg.foot.planted) {
                        anchors[index] ??= leg.foot.x;
                        assert.ok(Math.abs(leg.foot.x-anchors[index])<1e-7,name);
                    } else anchors[index]=undefined;
                });
                assert.ok(pose.bodyLayers.every(layer=>frames[layer.frame]),name);
                assert.equal(pose.bodyLayers.length,1,`${name}: turn must not dissolve two heads`);
                assert.equal(pose.bodyLayers[0].opacity,1,name);
            }
            const end=rigPose(rig,{direction,state:'turn',progress:1});
            const neutral=rigPose(rig,{direction:-direction});
            end.legs.forEach((leg,index)=>{
                assert.equal(leg.foot.x,neutral.legs[index].foot.x,name);
                assert.equal(leg.foot.y,rig.ankle,name);
                assert.ok(leg.foot.planted,name);
            });
        }
        assert.notEqual(rig.body.right,rig.body.left,name);
        assert.equal(rig.turnBody.length,12,name);
    }
});

test('every foot lifts and the initial gait phase has all feet grounded', () => {
    for(const {name,rig} of rigs) {
        for(const leg of rig.legs) {
            assert.ok(plantedFoot(leg.phase,rig.stride,rig.duty,rig.lift).planted,name);
            assert.ok(Array.from({length:200},(_,i)=>plantedFoot(i/200+leg.phase,rig.stride,rig.duty,rig.lift)).some(p=>p.y<-rig.lift*.95),name);
        }
    }
});
