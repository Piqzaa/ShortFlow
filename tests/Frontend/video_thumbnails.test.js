import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {runInNewContext} from 'node:vm';

test('only nearby covers load, at most two at a time; captured frames and page exit release streams', () => {
  let observe, observerStopped = false;
  const pageEvents = new Map();
  const source = readFileSync(new URL('../../assets/controllers/video_thumbnails_controller.js', import.meta.url), 'utf8')
    .replace("import { Controller } from '@hotwired/stimulus';", '').replace('export default class', 'class Thumbnails');
  class Observer {
    constructor(fn){observe = fn;} observe() {} unobserve() {} disconnect(){observerStopped = true;}
  }
  const Thumbnails = runInNewContext(`${source}\nThumbnails`, {
    Controller:class {}, IntersectionObserver:Observer,
    window:{IntersectionObserver:Observer,addEventListener:(n,fn)=>pageEvents.set(n,fn),removeEventListener:n=>pageEvents.delete(n)},
    setTimeout:()=>1,clearTimeout(){},
    document:{createElement:()=>({getContext:()=>({drawImage(){}}),toDataURL:()=> 'data:image/jpeg;base64,synthetic'})},
  });
  const c = new Thumbnails();
  c.videoTargets = Array.from({length:5}, (_,i) => ({
    dataset:{previewSrc:`/private/${i}`},poster:'',readyState:2,videoWidth:1920,videoHeight:1080,
    events:new Map(),addEventListener(n,fn){this.events.set(n,fn);},removeEventListener(n){this.events.delete(n);},
    removeAttribute(){delete this.src;},pause(){},load(){},
  }));
  c.connect(); assert.equal(c.active.size, 0);
  observe(c.videoTargets.slice(0,4).map(target => ({target,isIntersecting:true})));
  assert.equal(c.active.size, 2); assert.equal(c.queue.length, 2);
  assert.equal(c.videoTargets[4].src, undefined);
  const first = c.videoTargets[0]; first.events.get('loadeddata')();
  assert.match(first.poster, /^data:image\/jpeg/); assert.equal(first.src, undefined);
  assert.equal(c.active.size, 2); assert.equal(c.videoTargets[2].src, '/private/2');
  pageEvents.get('pagehide')();
  assert.equal(c.active.size, 0); assert.equal(c.queue.length, 0); assert.equal(observerStopped, true);
  assert.equal(c.videoTargets.every(video => !video.src), true);
  c.disconnect(); assert.equal(pageEvents.size, 0);
});
