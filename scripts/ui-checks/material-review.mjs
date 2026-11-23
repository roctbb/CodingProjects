import {spawn,execFile,execFileSync} from 'node:child_process';
import {mkdtempSync,mkdirSync,readFileSync,writeFileSync} from 'node:fs';
import {tmpdir} from 'node:os';
import path from 'node:path';
import net from 'node:net';
import {promisify} from 'node:util';
import {randomBytes} from 'node:crypto';
const root=process.cwd(),directory=mkdtempSync(path.join(tmpdir(),'material-review-ui-'));
const output=path.join(root,'output/playwright/material-review');mkdirSync(output,{recursive:true});
execFileSync('php',['scripts/ui-checks/prepare-material-review.php',directory],{cwd:root,stdio:'inherit'});
const sample=JSON.parse(readFileSync(path.join(directory,'material-review.json'),'utf8'));
const port=await new Promise(resolve=>{const server=net.createServer();server.listen(0,'127.0.0.1',()=>{const p=server.address().port;server.close(()=>resolve(p));});});
const url=`http://127.0.0.1:${port}`,server=spawn('php',['-S',`127.0.0.1:${port}`,'-t','public','scripts/ui-checks/router.php'],{cwd:root,env:{...process.env,UI_QA_MODE:'1',UI_QA_DIRECTORY:directory,UI_QA_URL:url,UI_QA_KEY:'base64:'+randomBytes(32).toString('base64')},stdio:['ignore','ignore','pipe']});
let log='';server.stderr.on('data',chunk=>log+=chunk);
const session=`material-review-${process.pid}`,cli=async args=>(await promisify(execFile)('npx',['--yes','--package','@playwright/cli','playwright-cli',`-s=${session}`,...args],{cwd:root,encoding:'utf8',timeout:60000,maxBuffer:8*1024*1024})).stdout;
try{
    for(let i=0;;i++){try{await fetch(url+'/login');break;}catch{if(i>40)throw new Error('Test server did not start');await new Promise(resolve=>setTimeout(resolve,100));}}
    await cli(['open',`${url}/insider/material-review?draft=${sample.draft}&actor=admin`]);
    await cli(['snapshot']);
    const result=await cli(['run-code',`async page => {
        const results=[],errors=[];page.on('pageerror',e=>errors.push(e.message));
        const check=(name,value)=>{results.push({name,passed:!!value});if(!value)throw new Error(name);};
        for(const width of [1440,390]){
            await page.setViewportSize({width,height:1000});
            await page.goto(${JSON.stringify(url)}+'/insider/material-review?draft=${sample.draft}&actor=admin');
            await page.locator('[data-material-review]').waitFor();
            check('all review levels '+width,await page.locator('[data-scope]').count()===4);
            const navigation=page.locator('[data-review-navigation]');const compact=!(await navigation.evaluate(el=>el.open));if(compact)await navigation.locator('> summary').click();
            check('notebook independent task navigation '+width,await page.getByRole('link',{name:'Все задачи урока',exact:true}).count()===1);
            if(compact)await navigation.locator('> summary').click();
            check('image comparison '+width,await page.locator('.mr-image-pair img').count()===2);
            check('no horizontal overflow '+width,await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
            await page.locator('[data-scope="lesson"]').click();
            check('lesson questions have no accept '+width,await page.locator('.mr-proposal:visible [data-decision="accepted"]').count()===0);
            check('scope filter preserves lesson context '+width,(await page.locator('.mr-material').innerText()).includes('Старое объяснение индексации.'));
            await page.locator('[data-scope="local"]').click();
            check('local changes visible '+width,await page.locator('.mr-proposal:visible').count()===2);
            await page.locator('[data-scope="all"]').click();
            const proposalPanel=page.locator('[data-review-proposals]');if(width<700&&await proposalPanel.evaluate(el=>el.open))await proposalPanel.locator('> summary').click();
            const zoomButton=page.locator('.mr-image-preview').first();await zoomButton.click();await page.locator('.mr-image-zoom').waitFor({state:'visible'});await page.keyboard.press('Escape');
            check('image zoom restores focus '+width,await zoomButton.evaluate(el=>document.activeElement===el));
            await page.evaluate(()=>window.scrollTo({top:0,behavior:'instant'}));
            for(const theme of ['light','dark']){
                await page.evaluate(async theme=>{document.documentElement.dataset.bsTheme=theme;await new Promise(requestAnimationFrame);await Promise.all(document.getAnimations().filter(animation=>animation.constructor.name==='CSSTransition').map(animation=>animation.finished.catch(()=>{})));},theme);
                check('no overflow '+theme+' '+width,await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
                const contrasts=await page.evaluate(()=>{const canvas=document.createElement('canvas');canvas.width=canvas.height=1;const ctx=canvas.getContext('2d');const rgb=color=>{ctx.fillStyle=color;ctx.fillRect(0,0,1,1);return [...ctx.getImageData(0,0,1,1).data].slice(0,3).map(v=>v/255);};const luminance=color=>rgb(color).map(v=>v<=.04045?v/12.92:((v+.055)/1.055)**2.4).reduce((sum,v,i)=>sum+v*[.2126,.7152,.0722][i],0);const background=luminance(getComputedStyle(document.querySelector('.mr-lesson')).backgroundColor);return [document.querySelector('.mr-workspace .gc-breadcrumb a'),document.querySelector('.mr-workspace [data-edit-field]')].map(el=>{const color=luminance(getComputedStyle(el).color);return (Math.max(color,background)+.05)/(Math.min(color,background)+.05);});});
                check('readable links and secondary controls '+theme+' '+width+' '+contrasts.map(r=>r.toFixed(2)).join('/'),contrasts.every(ratio=>ratio>=4.5));
                await page.screenshot({path:${JSON.stringify(output)}+'/review-'+theme+'-'+width+'.png',fullPage:true});
                if(theme==='light')await page.screenshot({path:${JSON.stringify(output)}+'/overview-'+width+'.png',fullPage:false});
            }
            await page.evaluate(()=>document.documentElement.dataset.bsTheme='light');
            await page.screenshot({path:${JSON.stringify(output)}+'/review-'+width+'.png',fullPage:true});
            await page.locator('[data-edit-field][data-entity="step"][data-id="1"][data-field="theory"]').first().click();
            const editor=page.locator('[data-review-editor]');await editor.locator('[data-editor-text]').fill('Несохранённый авторский текст '+width);
            await editor.locator('[data-editor-preview]').click();await editor.locator('[data-editor-preview-body]').getByText('Несохранённый авторский текст '+width).waitFor();
            await page.evaluate(async()=>{const root=document.querySelector('[data-material-review]');await fetch('/insider/material-review/drafts/'+root.dataset.draft+'/select',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content,Accept:'application/json'},body:JSON.stringify({version:Number(root.dataset.version)})});});
            await editor.locator('[data-editor-save]').click();await editor.locator('[data-editor-error]').filter({hasText:'Черновик изменился'}).waitFor();
            check('conflict retains author input '+width,(await editor.locator('[data-editor-text]').inputValue())==='Несохранённый авторский текст '+width);
            await page.reload();await editor.waitFor({state:'visible'});
            check('reload restores author input '+width,(await editor.locator('[data-editor-text]').inputValue())==='Несохранённый авторский текст '+width);
            await editor.locator('.btn-close').click();await page.evaluate(()=>sessionStorage.clear());
        }
        await page.setViewportSize({width:1440,height:1000});
        await page.goto(${JSON.stringify(url)}+'/insider/material-review?draft=${sample.draft}&actor=admin');
        const local=page.locator('.mr-proposal').filter({hasText:'Объяснить индексацию с нуля'});
        await Promise.all([page.waitForNavigation(),local.locator('[data-decision="accepted"]').click()]);
        check('accepted correction appears in result',(await page.locator('#field-step-1-theory').first().innerText()).includes('Индекс — номер элемента'));
        await page.locator('[data-edit-field][data-entity="task"][data-id="11"][data-field="text"]').first().click();
        await page.locator('[data-editor-text]').fill('Ручное уточнение условия для ученика.');
        await Promise.all([page.waitForNavigation(),page.locator('[data-editor-save]').click()]);
        check('manual edit is saved',(await page.locator('#field-task-11-text').first().innerText()).includes('Ручное уточнение условия'));
        const structural=page.locator('.mr-proposal').filter({hasText:'Добавить самостоятельную практику'});
        await Promise.all([page.waitForNavigation(),structural.locator('[data-decision="accepted"]').click()]);
        check('new step is readable',await page.locator('.mr-material > .mr-lesson .mr-step h2').filter({hasText:'Проверим себя'}).count()===1);
        await page.locator('[data-final-result] > summary').click();
        check('final preview excludes review controls',await page.locator('[data-final-result] [data-decision]').count()===0);
        await page.locator('[data-publication-confirm]').check();
        await Promise.all([page.waitForNavigation(),page.locator('[data-publish]').click()]);
        check('publication is a separate completed action',await page.locator('[data-action="reverse"]').count()===1);
        await Promise.all([page.waitForNavigation(),page.locator('[data-action="reverse"]').click()]);
        check('restore creates a reviewable draft',await page.locator('[data-draft]').getAttribute('data-draft')!=='${sample.draft}'&&await page.locator('[data-action="select"]').count()===1);
        await page.goto(${JSON.stringify(url)}+'/insider/material-review?draft=${sample.draft}&compare=${sample.alternative}&actor=admin');
        check('full alternatives compare',await page.getByRole('heading',{name:'Сравнение редакций',exact:true}).count()===1);
        check('comparison has unique anchors',await page.locator('.mr-workspace [id]').evaluateAll(nodes=>new Set(nodes.map(el=>el.id)).size===nodes.length));
        await page.locator('#revision-comparison').scrollIntoViewIfNeeded();await page.screenshot({path:${JSON.stringify(output)}+'/comparison-1440.png',fullPage:false});
        const denied=await page.goto(${JSON.stringify(url)}+'/insider/material-review?draft=${sample.draft}&actor=student');check('student access denied',denied.status()===403);
        return {results,errors};
    }`]);
    writeFileSync(path.join(output,'report.txt'),result);
    const match=/### Result\n([\s\S]*?)\n###/.exec(result);const report=match?JSON.parse(match[1]):null;
    if(report){writeFileSync(path.join(output,'report.json'),JSON.stringify(report,null,2));console.log(JSON.stringify(report));if(report.errors.length)throw new Error('Browser runtime errors were reported');}
    if(result.includes('### Error'))throw new Error('Browser check failed; see report.txt');
}finally{await cli(['close']).catch(()=>{});server.kill();writeFileSync(path.join(output,'server.log'),log);}
