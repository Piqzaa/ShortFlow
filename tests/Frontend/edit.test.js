import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import { remapCaptions, layoutClips, timelineDuration, canPlace } from '../../assets/edit-timing.js';

test('free video placement preserves gaps and rejects collisions while allowing group movement', () => {
  const clips=[{key:'a',id:1,start:0,end:2,at:0},{key:'b',id:2,start:0,end:1,at:3}];
  assert.equal(timelineDuration(clips),4);assert.equal(canPlace(clips,['a'],.5),true);assert.equal(canPlace(clips,['a'],1.5),false);
  assert.equal(canPlace(clips,['a','b'],1),true);assert.equal(canPlace(clips,['a'],-.1),false);
  assert.equal(layoutClips([{id:1,start:0,end:2,rate:2},{id:2,start:0,end:1}])[1].at,1);
});

test('caption remapping follows a source through a gap and a speed change', () => {
  const rows=remapCaptions([{start:.5,end:1.5,text:'Paroles'}],[{id:1,start:0,end:2}],[{id:1,start:0,end:2,at:1,rate:2}]);
  assert.deepEqual(rows,[{start:1.25,end:1.75,text:'Paroles'}]);
});

test('a video gap advances the project clock and audio before returning to the next clip', () => {
  let frame;const c=editor({performance:{now:()=>0},requestAnimationFrame:callback=>{frame=callback;return 1;},cancelAnimationFrame(){}});
  c.stageTarget={pause(){}};c.canvasTarget={classList:{add(){}}};c.playheadTarget={value:0,max:3};c.paint=()=>{};c.currentTarget={};c.hasCursorTarget=false;
  const heard=[];c.paintText=()=>{};c.syncMusic=time=>heard.push(time);let resumed;c.jump=(time,play)=>{resumed={time,play};};
  c.showGap(.5,1.5,true);frame(500);assert.equal(c.playheadTarget.value,1);assert.equal(heard[0],1);frame(1100);assert.deepEqual(resumed,{time:1.5,play:true});
});

function audioRow(values, duration = 10) {
  const fields = Object.fromEntries(Object.entries({text:'Son',...values}).map(([key,value])=>[`[data-audio-${key}]`,{value}]));
  return {dataset:{audioDuration:String(duration),audioUrl:'/private/audio'},querySelector:key=>fields[key],fields};
}

test('audio moves independently and trimming its left edge advances the source offset', () => {
  const c=editor(),row=audioRow({id:'a',kind:'video',source:1,start:2,end:4,offset:1,volume:70,loop:0},4);
  c.layerRow=()=>row;c.playheadTarget={max:8};c.audioChange=()=>{};
  c.shiftAudio('a','move',2,4,2,1);assert.equal(c.readAudio(row).start,4);assert.equal(c.readAudio(row).offset,1);
  c.shiftAudio('a','start',2,4,.5,1);assert.equal(c.readAudio(row).offset,1.5);assert.equal(c.readAudio(row).start,2.5);
  c.shiftAudio('a','end',2,4,9,1);assert.equal(c.readAudio(row).end,5);
  assert.equal(c.clipTargets[0].nodes['[data-edit-start]'].value,1);
});

test('splitting audio preserves source continuity and handles a repeating musical file', () => {
  const c=editor(),row=audioRow({id:'a',kind:'music',source:0,start:1,end:5,offset:1.5,volume:70,loop:1},2);
  c.selectedLayer={kind:'audio',key:'a'};c.layerRow=()=>row;c.layerRows=()=>[row];c.playheadTarget={value:3};c.audioChange=()=>{};c.remember=()=>{};
  let added;c.appendAudio=values=>{added=values;};c.splitAudio();
  assert.equal(c.readAudio(row).end,3);assert.equal(added.start,3);assert.equal(added.offset,1.5);assert.equal(added.volume,70);
});

test('trimming repeated music wraps its source offset instead of exceeding the file', () => {
  const c=editor(),row=audioRow({id:'a',kind:'music',source:0,start:1,end:9,offset:1.5,volume:20,loop:1},2);
  c.layerRow=()=>row;c.playheadTarget={max:10};c.audioChange=()=>{};
  c.shiftAudio('a','start',1,9,3,1.5);assert.equal(c.readAudio(row).start,4);assert.equal(c.readAudio(row).offset,.5);
});

test('audio preview respects fragment timing, volume, trimming and pause', () => {
  const c=editor(),row=audioRow({id:'a',kind:'video',source:1,start:1,end:3,offset:.5,volume:40,loop:0},4);
  const player={duration:4,currentTime:0,paused:true,pause(){this.paused=true;},play(){this.paused=false;return Promise.resolve();}};
  c.audioPlayers=new Map([['a',player]]);c.layerRows=()=>[row];c.playing=true;
  c.syncAudio(.5);assert.equal(player.paused,true);
  c.syncAudio(2);assert.equal(player.currentTime,1.5);assert.equal(player.volume,.4);assert.equal(player.paused,false);
  c.syncAudio(3);assert.equal(player.paused,true);c.syncAudio(2);c.stop();assert.equal(player.paused,true);
});

function editor(extra = {}) {
  const source = readFileSync(new URL('../../assets/controllers/edit_controller.js', import.meta.url), 'utf8').replace("import { Controller } from '@hotwired/stimulus';", '').replace("import { remapCaptions, layoutClips, timelineDuration, canPlace } from '../edit-timing.js';", '').replace('export default class', 'class Editor');
  const Editor = runInNewContext(`${source}\nEditor;`, {Controller:class {},remapCaptions,layoutClips,timelineDuration,canPlace,AbortController,setTimeout,clearTimeout,...extra});
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
  const c=editor(); c.captionStyleTarget={value:'clean'}; c.captionTextTarget={classList:{toggle(){}}};c.hookTarget={};c.introTarget={checked:true};
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
  let jumped=false;c.selectRow=()=>{jumped=true;};c.paint=()=>{};c.showClipPanel=()=>{};
  c.choose({currentTarget:{dataset:{clipId:'part-b'}}});assert.equal(c.editingRow(),second);assert.equal(jumped,false);
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


test('short accelerated fragments retain their exact timeline duration',()=>{
  const c=editor(),row=c.clipTargets[0];row.nodes['[data-edit-start]'].value=0;row.nodes['[data-edit-end]'].value=.1;row.nodes['[data-edit-rate]']={value:4};
  assert.equal(c.clipLength(row),.025);
});


test('playback crosses an untouched cut in the same source without seeking or pausing',()=>{
 const c=editor(),[first,second]=c.clipTargets;
 first.dataset={id:'1',key:'a'};second.dataset={id:'1',key:'b'};
 first.nodes['[data-edit-start]'].value=0;first.nodes['[data-edit-end]'].value=1;second.nodes['[data-edit-start]'].value=1;second.nodes['[data-edit-end]'].value=2;
 first.nodes['[data-edit-at]']={value:0};second.nodes['[data-edit-at]']={value:1};
 c.selectedRow=first;c.selectedClipKey='a';c.playing=true;c.paint=()=>{};c.playheadTarget={max:2};let sought=false;c.jump=()=>{sought=true;};c.stageEnd();
 assert.equal(c.selectedRow,second);assert.equal(c.selectedClipKey,'a');assert.equal(sought,false);assert.equal(c.playing,true);
});

test('the split control names its target and stays disabled outside that target',()=>{
 const c=editor(),row=layerRow({id:'title',text:'Mon titre',start:1,end:2},'text');
 c.hasSplitButtonTarget=true;c.hasSelectionLabelTarget=true;c.splitButtonTarget={};c.selectionLabelTarget={};c.selectedLayer={kind:'text',key:'title'};c.layerRow=()=>row;
 c.clipTargets=[];c.timelineTarget={querySelectorAll:()=>[]};c.element={querySelectorAll:()=>[],querySelector:()=>null};c.playheadTarget={value:1.5};
 c.updateSelectionUI();assert.equal(c.splitButtonTarget.textContent,'✂ Couper le texte');assert.equal(c.splitButtonTarget.disabled,false);assert.equal(c.selectionLabelTarget.textContent,'Texte · Mon titre');
 c.playheadTarget.value=2.5;c.updateSelectionUI();assert.equal(c.splitButtonTarget.disabled,true);
});


test('choosing a text in the timeline does not activate its transform frame',()=>{
 const c=editor(),row=layerRow({id:'t',text:'Titre',start:0,end:2},'text');c.transformLayer={kind:'text',key:'old'};c.layerRows=()=>[row];c.playheadTarget={value:1};c.element={querySelectorAll:()=>[],querySelector:()=>({})};c.panel=()=>{};c.drawCaptionTrack=()=>{};
 c.selectLayer('text','t');assert.equal(c.transformLayer,null);assert.equal(c.selectedLayer.key,'t');
});


test('saved personal styles preserve booleans and normalize legacy bold font names',()=>{
 const c=editor(),fields={'[data-type-preset]':{value:'mine'},'[data-type-font]':{},'[data-type-bold]':{},'[data-type-box]':{}},container={querySelector:key=>fields[key]};
 c.stylesValue={mine:{style:{font:'sans-bold',bold:false,box:true}}};c.typographyChange=()=>{};
 c.applyTypePreset({currentTarget:{closest:()=>container}});
 assert.equal(fields['[data-type-font]'].value,'sans');assert.equal(fields['[data-type-bold]'].value,'1');assert.equal(fields['[data-type-box]'].value,'1');
});

test('Original restores colour corrections and a new filter uses full intensity',()=>{
 const c=editor(),fields=Object.fromEntries(['filter','intensity','brightness','contrast','saturation'].map(key=>[`[data-look-${key}]`,{value:0}])),row={querySelector:key=>fields[key]};
 c.lookChange=()=>{};c.updateSelectionUI=()=>{};c.applyFilter({currentTarget:{closest:()=>row,dataset:{filterPreset:'none'}}});
 for(const key of ['brightness','contrast','saturation'])assert.equal(fields[`[data-look-${key}]`].value,1);
 assert.equal(fields['[data-look-intensity]'].value,100);
});

test('subtitle visibility toggles independently from its appearance',()=>{
 const c=editor();c.captionStyleTarget={value:'social'};c.paint=()=>{};c.scheduleSave=()=>{};
 c.toggleCaptions({currentTarget:{checked:false}});assert.equal(c.captionStyleTarget.value,'off');
 c.toggleCaptions({currentTarget:{checked:true}});assert.equal(c.captionStyleTarget.value,'clean');
});
