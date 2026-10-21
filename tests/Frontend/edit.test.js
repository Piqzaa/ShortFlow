import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import { remapCaptions } from '../../assets/edit-timing.js';

function editor(extra = {}) {
  const source = readFileSync(new URL('../../assets/controllers/edit_controller.js', import.meta.url), 'utf8').replace("import { Controller } from '@hotwired/stimulus';", '').replace("import { remapCaptions } from '../edit-timing.js';", '').replace('export default class', 'class Editor');
  const Editor = runInNewContext(`${source}\nEditor;`, {Controller:class {},remapCaptions,AbortController,setTimeout,clearTimeout,...extra});
  const controller = new Editor();
  controller.summaryTarget = {}; controller.noticeTarget = {}; controller.previewTarget = {}; controller.hasPreviewTarget = true;
  controller.formatTarget = {value:'9:16'};
  const row = (start, end) => {
    const nodes = {'[data-edit-start]':{value:start},'[data-edit-end]':{value:end,max:10},'[data-edit-omit]':{checked:false},'[data-edit-position]':{value:1},'[data-edit-number]':{},'[data-action="edit#up"]':{setAttribute(){},focus(){}},'[data-action="edit#down"]':{setAttribute(){},focus(){}},video:{pause(){this.paused=true;},style:{setProperty(){}}}};
    const element = {querySelector:selector=>nodes[selector]||null, nodes};
    nodes.video.closest = ()=>element;
    return element;
  };
  controller.clipTargets = [row(1, 4),row(0, 5)];
  return controller;
}

test('omitted clips are excluded from preview duration and editing stops playback', () => {
  const c = editor(); c.playing = true;
  c.clipTargets[1].nodes['[data-edit-omit]'].checked = true;
  c.change();
  assert.equal(c.playing, false); assert.equal(c.active().length,1);
  assert.match(c.summaryTarget.textContent,/1 clip · 3 s/);
  assert.match(c.noticeTarget.textContent,/enregistrer/);
});

test('preview advances only at the selected cut and stops after the final clip', () => {
  const c = editor(); c.playing = true;
  let next = null; c.playRow = row=>{next=row;};
  const player=c.clipTargets[0].nodes.video; player.currentTime=2;
  c.limit({currentTarget:player}); assert.equal(next,null);
  player.currentTime=4; c.limit({currentTarget:player}); assert.equal(next,c.clipTargets[1]);
  const last=c.clipTargets[1].nodes.video; last.currentTime=5;
  c.limit({currentTarget:last}); assert.equal(c.playing,false);
});

test('preview refuses invalid cuts and unavailable source media', () => {
  const c = editor(); c.playing=false;
  c.clipTargets[0].nodes['[data-edit-end]'].value=0;
  c.preview(); assert.equal(c.playing,false); assert.match(c.noticeTarget.textContent,/Vérifie/);
});

test('reordering keeps cut values attached to their clip and refreshes positions', () => {
  const c = editor(); const first=c.clipTargets[0]; const second=c.clipTargets[1];
  c.listTarget={insertBefore(node,before){c.clipTargets.splice(c.clipTargets.indexOf(node),1);c.clipTargets.splice(c.clipTargets.indexOf(before),0,node);}};
  c.move(first,1);
  assert.equal(c.clipTargets[0],second); assert.equal(first.nodes['[data-edit-start]'].value,1);
  assert.equal(first.nodes['[data-edit-position]'].value,2);
  assert.equal(second.nodes['[data-edit-position]'].value,1);
});

function watcher(fetch) {
  const source = readFileSync(new URL('../../assets/controllers/edit_watch_controller.js', import.meta.url), 'utf8').replace("import { Controller } from '@hotwired/stimulus';", '').replace('export default class', 'class Watcher');
  const state = {document:{hidden:false},reloads:0};
  const Watcher = runInNewContext(`${source}\nWatcher;`, {Controller:class {},fetch,AbortController,document:state.document,setTimeout:()=>1,clearTimeout(){},window:{location:{reload(){state.reloads++;}}}});
  const controller = new Watcher(); controller.urlValue='/studio/montage/1/etat'; controller.statusTarget={textContent:'Export en attente'}; controller.errors=0;
  return {controller,state};
}

test('export watcher reads only local status, pauses hidden and reloads on completion', async () => {
  let calls=0;
  const {controller:c,state}=watcher(async (url,options)=>{calls++;assert.equal(url,'/studio/montage/1/etat');assert.equal(options.cache,'no-store');assert.equal(options.body,undefined);return {ok:true,headers:{get:()=> 'application/json'},json:async()=>({status:'ready',label:'Export terminé'})};});
  state.document.hidden=true; await c.poll(); assert.equal(calls,0);
  state.document.hidden=false; await c.poll(); assert.equal(calls,1); assert.equal(state.reloads,1);
});

test('repeated export status failures leave a manual refresh and stop polling', async () => {
  const {controller:c,state}=watcher(async()=>({ok:false}));
  await c.poll(); await c.poll(); await c.poll();
  assert.equal(c.stopped,true); assert.match(c.statusTarget.textContent,/Actualise/); assert.equal(state.reloads,0);
});


test('global playhead maps into the proper source cut without changing cuts', () => {
  const c=editor(); let selected;
  c.selectRow=(row,time,play)=>{selected={row,time,play};};
  c.jump(4,true);
  assert.equal(selected.row,c.clipTargets[1]);assert.equal(selected.time,1);assert.equal(selected.play,true);
  assert.equal(c.bounds(c.clipTargets[0]).start,1);
  c.clipTargets[0].nodes['[data-edit-omit]'].checked=true;
  assert.equal(c.offset(c.clipTargets[1]),0);
});

test('cut changes retime captions while style changes keep their current timing', () => {
  const c=editor();let changes=0;c.hasCaptionRowsTarget=true;c.retimeCaptions=()=>changes++;c.refresh=()=>{};
  c.change(null,true);assert.equal(changes,1);
  c.change({target:{closest:()=>null}});assert.equal(changes,1);
});

for(const fixture of JSON.parse(readFileSync(new URL('../Fixtures/caption-timing.json',import.meta.url),'utf8'))) {
  test(fixture.name,()=>assert.deepEqual(remapCaptions(fixture.rows,fixture.before,fixture.after),fixture.expected));
}

test('caption preview uses the correct timed block and hides the opening after 2.5 seconds', () => {
  const c=editor(); c.captionStyleTarget={value:'clean'}; c.captionTextTarget={};c.hookTarget={};c.introTarget={checked:true};
  c.formTarget={querySelector:()=>({value:'Un titre'})};
  c.captionRowsTarget={querySelectorAll:()=>[{querySelector:selector=>({'[data-caption-start]':{value:0},'[data-caption-end]':{value:2},'[data-caption-text]':{value:'<b>Texte privé</b>'}}[selector])}]};
  c.paintText(1);assert.equal(c.captionTextTarget.textContent,'<b>Texte privé</b>');assert.equal(c.captionTextTarget.hidden,false);assert.equal(c.hookTarget.hidden,false);
  c.paintText(3);assert.equal(c.captionTextTarget.hidden,true);assert.equal(c.hookTarget.hidden,true);
});

test('dropping a clip forward moves it after its target and keeps its cut values', () => {
  const c=editor();const [first,second]=c.clipTargets;first.dataset={id:'1'};second.dataset={id:'2'};second.nextSibling=null;
  c.draggedId='1';c.listTarget={insertBefore(row,before){c.clipTargets.splice(c.clipTargets.indexOf(row),1);if(before===null)c.clipTargets.push(row);else c.clipTargets.splice(c.clipTargets.indexOf(before),0,row);}};
  c.drop({preventDefault(){},target:{closest:()=>({dataset:{clipId:'2'}})}});
  assert.equal(c.clipTargets[0],second);assert.equal(c.bounds(first).start,1);
});


test('trim slider cannot move the start beyond the selected end', () => {
  const c=editor();const row=c.clipTargets[0];
  c.trim({currentTarget:{closest:()=>row,value:'9',max:'10',dataset:{trim:'start'}},target:{closest:()=>row}});
  assert.equal(Number(row.nodes['[data-edit-start]'].value),3.9);
  assert.equal(c.bounds(row).end,4);
});

test('split fragments sharing one source remain independently selectable and reorderable', () => {
  const c=editor();const [first,second]=c.clipTargets;
  first.dataset={id:'42',key:'part-a'};second.dataset={id:'42',key:'part-b'};second.nextSibling=null;
  let selected;c.selectRow=row=>{selected=row;};c.showClipPanel=()=>{};
  c.choose({currentTarget:{dataset:{clipId:'part-b'}}});assert.equal(selected,second);
  c.draggedId='part-a';c.listTarget={insertBefore(row,before){c.clipTargets.splice(c.clipTargets.indexOf(row),1);if(before===null)c.clipTargets.push(row);else c.clipTargets.splice(c.clipTargets.indexOf(before),0,row);}};
  c.drop({preventDefault(){},target:{closest:()=>({dataset:{clipId:'part-b'}})}});
  assert.equal(c.clipTargets[0],second);assert.equal(c.bounds(first).start,1);
});

test('music preview follows global time, offset and repetition, and pauses with the montage', () => {
  const c=editor(); c.hasMusicTarget=true;c.playing=true;
  c.musicVolumeTarget={value:20};c.musicOffsetTarget={value:1};c.musicLoopTarget={checked:true};
  c.musicTarget={duration:3,currentTime:0,paused:true,play(){this.paused=false;return Promise.resolve();},pause(){this.paused=true;}};
  c.syncMusic(4);assert.equal(c.musicTarget.currentTime,2);assert.equal(c.musicTarget.volume,0.2);assert.equal(c.musicTarget.paused,false);
  c.musicLoopTarget.checked=false;c.syncMusic(4);assert.equal(c.musicTarget.paused,true);
  c.musicLoopTarget.checked=true;c.syncMusic(0.5);assert.equal(c.musicTarget.currentTime,1.5);
  c.stop();assert.equal(c.musicTarget.paused,true);
});

test('direct timeline resizing clamps both edges inside the source and preserves a minimum fragment', () => {
  const c=editor();const row=c.clipTargets[0];c.change=()=>{};c.selectRow=()=>{};
  c.resizeClip(row,'start',20);assert.equal(c.bounds(row).start,3.9);
  c.resizeClip(row,'start',-10);assert.equal(c.bounds(row).start,0);
  c.resizeClip(row,'end',30);assert.equal(c.bounds(row).end,10);
  c.resizeClip(row,'end',-1);assert.equal(c.bounds(row).end,0.1);
});

test('removing a selected fragment keeps at least one source and never deletes the media file', () => {
  const c=editor();c.selectedRow=c.clipTargets[0];c.removeSelected();
  assert.equal(c.active().length,1);
  c.selectedRow=c.clipTargets[1];c.removeSelected();assert.equal(c.active().length,1);
  assert.match(c.noticeTarget.textContent,/au moins un clip/);
});

test('background preservation saves a draft, updates revision and refuses validation or login redirects', async () => {
  const requests=[];let response={ok:true,url:'http://localhost/studio/montage/1',headers:{get:()=> 'application/json'},json:async()=>({revision:4,status:'draft'})};
  const c=editor({FormData:class{constructor(){this.values=new Map();}set(k,v){this.values.set(k,v);}},URL,fetch:async(url,options)=>{requests.push(options);return response;}});
  c.formTarget={action:response.url};c.element={inert:false};c.hasRevisionTarget=true;c.revisionTarget={value:3};
  assert.equal(await c.persist(),true);assert.equal(requests[0].body.values.get('intent'),'save');assert.equal(c.revisionTarget.value,'4');assert.equal(c.element.inert,false);
  response={...response,ok:false,json:async()=>({message:'Le montage a changé'})};assert.equal(await c.persist(),false);assert.match(c.noticeTarget.textContent,/changé/);
  response={...response,ok:true,url:'http://localhost/connexion'};assert.equal(await c.persist(),false);
});

test('music upload only continues after the draft was preserved', async () => {
  const c=editor();let submitted=0;const form={dataset:{},requestSubmit(){submitted++;}};
  c.persist=async()=>false;await c.saveBeforeMusic({currentTarget:form,preventDefault(){}});assert.equal(submitted,0);
  c.persist=async()=>true;await c.saveBeforeMusic({currentTarget:form,preventDefault(){}});assert.equal(submitted,1);assert.equal(form.dataset.saved,'yes');
});


test('inline transcription applies results without navigation or resetting the playhead', async()=>{
  let applied=null,painted=0;const c=editor({document:{hidden:false},fetch:async()=>({ok:true,headers:{get:()=> 'application/json'},json:async()=>({status:'draft',revision:7,captions:[{start:0,end:1,text:'Corrigé'}],captionStyle:'clean',issue:null})})});
  c.transcribing=true;c.playing=true;c.stateUrlValue='/state';c.playheadTarget={value:'2.4'};c.revisionTarget={value:'5'};c.captionStyleTarget={value:'off'};
  c.writeCaptions=rows=>applied=rows;c.recipe=()=>[];c.setTranscribing=active=>c.transcribing=active;c.paint=()=>painted++;
  await c.pollCaptions();assert.equal(c.transcribing,false);assert.equal(c.playheadTarget.value,'2.4');assert.equal(c.revisionTarget.value,'7');assert.equal(applied[0].text,'Corrigé');assert.equal(painted,1);assert.equal(c.playing,true);
});

test('temporary transcription follow-up failure keeps existing captions and continues to wait',async()=>{
  let replaced=0;const c=editor({document:{hidden:false},setTimeout:()=>1,clearTimeout(){},fetch:async()=>{throw new Error('offline');}});
  c.transcribing=true;c.writeCaptions=()=>replaced++;await c.pollCaptions();assert.equal(replaced,0);assert.equal(c.transcribing,true);assert.match(c.noticeTarget.textContent,/conservés/);
});


test('autosave stays serial and preserves playback while later edits wait for their revision',async()=>{
  const pending=[];let requests=0;
  const c=editor({FormData:class{set(){}},URL,fetch:async()=>{requests++;return new Promise(resolve=>pending.push(resolve));}});
  c.element={inert:false};c.formTarget={action:'http://localhost/studio/montage/1'};c.hasRevisionTarget=true;c.revisionTarget={value:'1'};c.dirty=true;c.changeSerial=1;c.playing=true;
  const response=revision=>({ok:true,url:c.formTarget.action,headers:{get:()=> 'application/json'},json:async()=>({status:'draft',revision})});
  const first=c.persist({quiet:true});c.changeSerial=2;const second=c.persist({quiet:true});assert.equal(requests,1);
  pending.shift()(response(2));await first;await Promise.resolve();assert.equal(requests,2);pending.shift()(response(3));await second;
  assert.equal(c.revisionTarget.value,'3');assert.equal(c.dirty,false);assert.equal(c.playing,true);
});


function layerRow(values,kind='caption') {
  const inputs=Object.fromEntries(Object.entries(values).map(([name,value])=>[`[data-${kind}-${name}]`,{value:String(value)}]));
  return {querySelector:selector=>inputs[selector],inputs};
}

test('caption movement and trimming cannot overlap neighboring subtitles',()=>{
  const c=editor();const rows=[layerRow({start:0,end:1}),layerRow({start:2,end:3}),layerRow({start:4,end:5})];
  c.layerRows=()=>rows;c.playheadTarget={max:6};c.textChange=()=>{};
  c.shiftLayer('caption','1','move',2,3,-10);assert.equal(rows[1].inputs['[data-caption-start]'].value,1);assert.equal(rows[1].inputs['[data-caption-end]'].value,2);
  c.shiftLayer('caption','1','end',2,3,10);assert.equal(rows[1].inputs['[data-caption-end]'].value,4);
});

test('free text timing moves independently from the video fragments',()=>{
  const c=editor();const row=layerRow({id:'t',start:1,end:3},'text');c.layerRows=()=>[row];c.playheadTarget={max:6};c.textChange=()=>{};
  c.shiftLayer('text','t','move',1,3,2);assert.equal(row.inputs['[data-text-start]'].value,3);assert.equal(row.inputs['[data-text-end]'].value,5);assert.equal(c.clipTargets[0].nodes['[data-edit-start]'].value,1);
});

test('splitting a subtitle distributes words and preserves its individual position',()=>{
  const c=editor();const row=layerRow({start:0,end:3});c.layerRows=()=>[row];c.selectedLayer={kind:'caption',key:'0'};c.playheadTarget={value:1.5};c.readCaptions=()=>[{start:0,end:3,text:'Un texte bien corrigé',x:.5,y:.15}];let result;
  c.writeCaptions=rows=>result=rows;c.textChange=()=>{};c.splitLayer();assert.equal(result[0].text,'Un texte');assert.equal(result[1].text,'bien corrigé');assert.equal(result[0].end,1.5);assert.equal(result[1].y,.15);
});

test('moving all captions resets per-block overrides without changing their timing',()=>{
  const c=editor();const row=layerRow({start:1,end:2,x:.2,y:.3});c.layerRows=()=>[row];c.captionScopeTarget={value:'all'};c.captionXTarget={};c.captionYTarget={};c.playheadTarget={value:1};c.paintText=()=>{};c.scheduleSave=()=>{};
  c.placeLayer({kind:'caption',key:'0'},.5,.15);assert.equal(c.captionYTarget.value,.15);assert.equal(row.inputs['[data-caption-y]'].value,'');assert.equal(row.inputs['[data-caption-start]'].value,'1');
});

test('selecting a text block preserves an existing cursor inside that block',()=>{
  const c=editor();const row=layerRow({id:'t',start:1,end:3},'text');c.layerRows=()=>[row];c.playheadTarget={value:2};c.element={querySelector:()=>({})};c.panel=()=>{};c.drawCaptionTrack=()=>{};let jumped=false;c.jump=()=>jumped=true;
  c.selectLayer('text','t');assert.equal(jumped,false);assert.equal(c.playheadTarget.value,2);
});
