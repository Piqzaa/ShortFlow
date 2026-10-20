import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {runInNewContext} from 'node:vm';

function watcher(fetchResult) {
  const timers = new Map(), events = new Map(), calls = [];
  let timerId = 0, reloads = 0;
  const document = {hidden:false, addEventListener:(name, fn)=>events.set(name,fn),removeEventListener:name=>events.delete(name)};
  const source = readFileSync(new URL('../../assets/controllers/generation_watch_controller.js', import.meta.url), 'utf8')
    .replace("import {Controller} from '@hotwired/stimulus';", '').replace('export default class','class Watcher');
  const Watcher = runInNewContext(`${source}\nWatcher`, {
    Controller:class {}, document, AbortController,
    window:{location:{reload:()=>reloads++}},
    setTimeout:(fn,ms)=>{timers.set(++timerId,{fn,ms});return timerId;},clearTimeout:id=>timers.delete(id),
    fetch:async(url, options)=>{calls.push({url,options});return fetchResult(options);},
  });
  const c = new Watcher();
  Object.assign(c, {urlValue:'/private/state',statusValue:'processing',revisionValue:'123.456',messageTarget:{}});
  return {c,document,timers,events,calls,reloads:()=>reloads};
}

test('status polling only reads local state and reloads when the persisted result changes', async () => {
  let status = 'processing';
  const x = watcher(()=>({ok:true,json:async()=>({status,updatedAt:'123.456'})}));
  x.c.connect(); await x.c.poll();
  assert.equal(x.calls.length, 1);
  assert.equal(x.calls[0].options.method, 'GET');
  assert.equal(x.calls[0].options.redirect, 'error');
  assert.equal(x.calls[0].options.cache, 'no-store');
  assert.equal(x.reloads(), 0);
  status = 'available'; await x.c.poll(); assert.equal(x.reloads(), 1);
  x.c.disconnect();
});

test('polling pauses while hidden, stops after repeated failures and leaves a manual recovery message', async () => {
  const x = watcher(()=>{throw new Error('network');});
  x.c.connect(); x.document.hidden = true; await x.c.poll();
  assert.equal(x.calls.length, 0);
  x.document.hidden = false;
  for (let i=0;i<3;i++) await x.c.poll();
  assert.match(x.c.messageTarget.textContent, /Actualiser le suivi/);
  const before = x.timers.size; x.c.schedule(); assert.equal(x.timers.size, before);
  x.c.disconnect(); assert.equal(x.events.size, 0);
});

test('prepared ideas never poll and leaving cancels a pending state request', async () => {
  const x = watcher(options=>new Promise((resolve,reject)=>options.signal.addEventListener('abort',()=>reject(new Error('aborted')))));
  x.c.statusValue = 'prepared'; x.c.connect(); assert.equal(x.timers.size, 0);
  x.c.statusValue = 'processing'; const pending = x.c.poll();
  x.c.disconnect(); await pending;
  assert.equal(x.calls[0].options.signal.aborted, true);
  assert.equal(x.reloads(), 0); assert.equal(x.c.failures, 0);
});
