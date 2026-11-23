import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {runInNewContext} from 'node:vm';

function upload() {
  const revoked = [], listeners = new Map(), requests = [];
  const window = {
    URL:{createObjectURL:file => `blob:${file.name}`, revokeObjectURL:url => revoked.push(url)},
    addEventListener:(name, fn) => listeners.set(name, fn), removeEventListener:name => listeners.delete(name),
    XMLHttpRequest:true, FormData:true,
  };
  class Request {
    constructor() { this.upload = {}; requests.push(this); }
    open() {} setRequestHeader() {}
    send(data) { this.sent = data; }
    abort() { this.aborted = true; }
  }
  class Data {
    constructor(form) { this.file = form.file.disabled ? null : form.file.files[0]; }
  }
  const source = readFileSync(new URL('../../assets/controllers/upload_controller.js', import.meta.url), 'utf8')
    .replace("import { Controller } from '@hotwired/stimulus';", '').replace('export default class', 'class Upload');
  const Upload = runInNewContext(`${source}\nUpload`, {Controller:class {}, window, Intl, Event:class {}, XMLHttpRequest:Request, FormData:Data});
  const controller = new Upload();
  controller.fileTarget = {files:[], value:'', focus(){}, dispatchEvent(){}};
  controller.playerTarget = {src:'', pause(){}, load(){}, removeAttribute(){this.src = '';}};
  for (const name of ['button','progressPanel','progress','status','error','preview','fileName','previewInfo','previewError']) controller[`${name}Target`] = {};
  controller.zoneTarget = {classList:{add(){},remove(){}},contains:()=>false};
  controller.element = {action:'/studio/videos/importer',file:controller.fileTarget};
  return {controller, revoked, listeners, requests};
}

test('local preview never submits, releases replaced URLs, and works after history restoration', () => {
  const {controller:c, revoked, listeners, requests} = upload();
  c.connect();
  c.fileTarget.files = [{name:'<ma-video>.mp4',size:1024}]; c.selected();
  assert.equal(c.playerTarget.src, 'blob:<ma-video>.mp4');
  assert.equal(c.fileNameTarget.textContent, '<ma-video>.mp4');
  assert.equal(c.previewTarget.hidden, false);
  Object.assign(c.playerTarget, {duration:65.2,videoWidth:1080,videoHeight:1920}); c.metadata();
  assert.match(c.previewInfoTarget.textContent, /1:05 · 1080 × 1920/);
  assert.equal(c.playerTarget.currentTime, 0.1);
  c.fileTarget.files = [{name:'autre.webm',size:500}]; c.selected();
  assert.deepEqual(revoked, ['blob:<ma-video>.mp4']);
  listeners.get('pagehide')(); assert.equal(c.previewTarget.hidden, true);
  listeners.get('pageshow')(); assert.equal(c.playerTarget.src, 'blob:autre.webm');
  assert.equal(requests.length, 0);
  c.disconnect(); assert.equal(listeners.size, 0);
  assert.equal(c.previewUrl, null);
});

test('invalid local files clear the previous preview; decode errors do not reject a valid file', () => {
  const {controller:c} = upload();
  for (const file of [{name:'photo.jpg',size:10},{name:'gros.mp4',size:104857601}]) {
    c.fileTarget.files = [file]; c.selected();
    assert.equal(c.previewTarget.hidden, true);
    assert.equal(c.errorTarget.hidden, false);
  }
  c.fileTarget.files = [{name:'video.mp4',size:100}]; c.selected(); c.previewFailed();
  assert.equal(c.previewErrorTarget.hidden, false);
  assert.equal(c.fileIssue(c.fileTarget.files[0]), null);
});

test('drop accepts one file and upload captures it before disabling input, without duplicate requests', () => {
  const {controller:c, requests} = upload();
  const file = {name:'video.mp4',size:100};
  let changes = 0, prevented = 0;
  c.fileTarget.dispatchEvent = () => { changes++; c.selected(); };
  c.drop({preventDefault(){prevented++;},dataTransfer:{files:[file,file]}});
  assert.equal(changes, 0);
  c.drop({preventDefault(){prevented++;},dataTransfer:{files:[file]}});
  assert.equal(changes, 1); assert.equal(prevented, 2);
  c.submit({preventDefault(){}}); c.submit({preventDefault(){}});
  assert.equal(requests.length, 1);
  assert.equal(requests[0].sent.file, file);
  assert.equal(c.fileTarget.disabled, true);
  requests[0].onerror();
  assert.equal(c.fileTarget.disabled, false);
  assert.equal(c.buttonTarget.disabled, false);
});


test('long video preview explains the three minute limit and never sends the file',()=>{
  const {controller:c,requests}=upload();c.fileTarget.files=[{name:'long.mp4',size:500}];c.selected();
  Object.assign(c.playerTarget,{duration:181,videoWidth:1080,videoHeight:1920});c.metadata();
  assert.equal(c.errorTarget.hidden,false);assert.match(c.errorTarget.textContent,/3 minutes/);c.submit({preventDefault(){}});assert.equal(requests.length,0);
});

test('server import refusal stays visible and keeps the selected file and preview',()=>{
  const {controller:c,requests}=upload();const file={name:'long.mp4',size:500};c.fileTarget.files=[file];c.selected();c.submit({preventDefault(){}});
  const request=requests[0];request.status=422;request.getResponseHeader=()=> 'application/json';request.responseText=JSON.stringify({message:'Cette vidéo dépasse 3 minutes.'});request.onload();
  assert.equal(c.errorTarget.hidden,false);assert.match(c.errorTarget.textContent,/3 minutes/);assert.equal(c.fileTarget.disabled,false);assert.equal(c.fileTarget.files[0],file);assert.equal(c.previewTarget.hidden,false);
});

function batchUpload(respond) {
  const results = [];
  const requests = [];
  const source = readFileSync(new URL('../../assets/controllers/upload_controller.js', import.meta.url), 'utf8')
    .replace("import { Controller } from '@hotwired/stimulus';", '').replace('export default class', 'class Upload');
  const Upload = runInNewContext(`${source}\nUpload`, {
    AbortController,
    Controller: class {},
    FormData: class extends Map { constructor() { super(); } },
    document: { createElement: () => ({}) },
    fetch: async (url, options) => {
      requests.push({url, file: options.body.get('video_upload[file]').name});
      return respond(requests.length);
    },
  });
  const controller = new Upload();
  Object.assign(controller, {
    element: {action: '/studio/bibliotheque/importer'},
    fileTarget: {name: 'video_upload[file]'}, buttonTarget: {}, progressPanelTarget: {},
    errorTarget: {}, progressTarget: {}, statusTarget: {}, selected() {},
    batchResultsTarget: {replaceChildren() {}, append: item => results.push(item.textContent)},
  });
  return {controller, results, requests};
}

test('batch sends files independently and reports a rejection between successful files', async () => {
  const {controller, results, requests} = batchUpload(index => index === 2
    ? {status: 422, json: async () => ({message: 'Vidéo illisible.'})} : {status: 201});
  const files = ['un.mp4', 'deux.webm', 'trois.mp4'].map(name => ({name, size: 20}));
  await controller.submitBatch(files);
  assert.deepEqual(requests.map(request => request.file), files.map(file => file.name));
  assert.deepEqual(results, ['un.mp4 — Importée', 'deux.webm — Vidéo illisible.', 'trois.mp4 — Importée']);
  assert.equal(controller.statusTarget.textContent, '2 vidéos importées sur 3.');
  assert.equal(controller.fileTarget.value, '');
});

test('batch stops on an uncertain response and identifies files that were never sent', async () => {
  const {controller, results, requests} = batchUpload(() => { throw new Error('network down'); });
  await controller.submitBatch([{name: 'un.mp4', size: 20}, {name: 'deux.mp4', size: 20}]);
  assert.equal(requests.length, 1);
  assert.match(results[0], /État incertain/);
  assert.match(results[1], /avant ce fichier/);
  assert.equal(controller.buttonTarget.disabled, false);
});
