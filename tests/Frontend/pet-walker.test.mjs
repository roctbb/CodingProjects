import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {PetWalker} from '../../resources/assets/js/components/pet-walker.js';

class SilentWalker extends PetWalker { draw() {} }

test('each articulated pet completes both entry routes, stands, turns and stops without an idle RAF', () => {
    const keys=['document','innerWidth','innerHeight','devicePixelRatio','requestAnimationFrame','cancelAnimationFrame'];
    const before=new Map(keys.map(key=>[key,globalThis[key]]));
    try {
        for(const name of ['learning_bot','brr_brr_patapim','tralalelo_tralala','tung_tung_tung_sahur']) {
            const data=JSON.parse(readFileSync(new URL(`../../public/images/screen-pets/${name}-rig-v1.json`,import.meta.url)));
            for(const width of [320,640,3840]) for(const random of [()=>0,()=>1]) {
                let index=0,complete=0,now=0,hold=0,turn=0;
                const frames=new Map(),properties={};
                const context={setTransform(){}};
                const canvas={getContext:()=>context,remove(){}};
                const nav={getClientRects:()=>[{}],getBoundingClientRect:()=>({top:734})};
                Object.assign(globalThis,{document:{createElement:()=>canvas,querySelector:selector=>selector==='.gc-bottom-nav'?nav:null},
                    innerWidth:width,innerHeight:800,devicePixelRatio:1,
                    requestAnimationFrame:callback=>{const id=++index;frames.set(id,callback);return id;},cancelAnimationFrame:id=>frames.delete(id)});
                const host={append(){},style:{setProperty:(key,value)=>properties[key]=value}};
                const pet=new SilentWalker(host,{naturalWidth:1024},data);
                pet.startVisit(()=>complete++,random);
                assert.ok(pet.stride<=data.rig.stride+1e-8,name);
                const home=pet.x;
                for(let i=0;i<12000&&!complete;i++) {
                    const [id,callback]=frames.entries().next().value;
                    frames.delete(id);now+=1000/60;callback(now);
                    assert.ok(pet.x>=pet.left-1e-8&&pet.x<=pet.right+1e-8,name);
                    if(pet.mode==='stand'&&pet.playing){hold++;assert.equal(pet.x,width/2,name);}
                    if(pet.mode==='turn'){turn++;assert.equal(pet.x,width/2,name);}
                }
                assert.equal(complete,1,name);
                assert.equal(pet.x,home,name);
                assert.ok(hold>90,name);assert.ok(turn>40,name);
                assert.equal(pet.mode,'stand',name);
                assert.equal(pet.playing,false,name);assert.equal(frames.size,0,name);
                assert.ok(now<80000,`${name} route took ${now}ms`);
                assert.equal(properties['--screen-pet-bottom'],'66px',name);
                assert.equal(properties['--screen-pet-size'],width<480?'80px':'96px',name);
            }
        }
    } finally {
        for(const [key,value]of before){if(value===undefined)delete globalThis[key];else globalThis[key]=value;}
    }
});
