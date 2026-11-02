import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

function setup() {
  let resolve, reject, calls = 0;
  const observers = [];
  const pending = new Promise((done, fail) => { resolve = done; reject = fail; });
  const source = readFileSync(new URL('../../assets/controllers/studio_controller.js', import.meta.url), 'utf8')
    .replace(/^import .*;\r?$/gm, '')
    .replace('export default class', 'class StudioController')
    .replace("import('gsap')", 'imports.gsap()')
    .replace("import('gsap/ScrollTrigger')", 'imports.scroll()');
  const Studio = runInNewContext(`${source}\nStudioController;`, { Controller: class {},
    imports: { gsap: () => { calls++; return pending; }, scroll: async () => ({}) },
    navigator: {}, IntersectionObserver: class {
      constructor(callback) { this.callback = callback; observers.push(this); }
      observe() {}
      disconnect() { this.disconnected = true; }
    }, matchMedia: () => ({ addEventListener() {} }) });
  const controller = new Studio();
  controller.element = { dataset: {} };
  controller.connected = true;
  controller.generation = 7;
  return { controller, resolve, reject, observers, calls: () => calls };
}

test('reconnecting keeps a fresh generation and preserves the user pause choice', () => {
  const { controller } = setup();
  controller.restart = () => { controller.generation++; };
  controller.connect();
  const first = controller.generation;
  controller.generation++; // Disconnect invalidates the pending generation.
  controller.paused = true;
  controller.connect();
  assert.ok(controller.generation > first);
  assert.equal(controller.paused, true);
});

test('repeated observer notifications cannot initialize one generation twice', async () => {
  const { controller, resolve, calls } = setup();
  const first = controller.load(7);
  const duplicate = controller.load(7);
  assert.equal(calls(), 1);
  controller.generation = 8;
  resolve({ gsap: {} });
  await Promise.all([first, duplicate]);
});

test('stale observers and disconnected controllers cannot start another load', async () => {
  const { controller, calls } = setup();
  controller.element.dataset.studioState = 'static';
  await controller.load(6);
  controller.connected = false;
  await controller.load(7);
  assert.equal(calls(), 0);
  assert.equal(controller.element.dataset.studioState, 'static');
});

test('failure of an old import cannot clear a newer active generation', async () => {
  const { controller, reject } = setup();
  controller.cleanup = () => assert.fail('A stale failure must not clean up the current animation.');
  const loading = controller.load(7);
  controller.generation = 8;
  controller.element.dataset.studioState = 'mobile';
  reject(new Error('Old import failed'));
  await loading;
  assert.equal(controller.element.dataset.studioState, 'mobile');
});

test('late observer deliveries cannot disconnect the new visibility watcher', () => {
  const { controller, observers } = setup();
  controller.motion = { matches: false };
  controller.cleanup = () => { controller.generation++; };
  const loads = [];
  controller.load = generation => loads.push(generation);
  controller.restart();
  controller.restart();
  observers[0].callback([{ isIntersecting: true }]);
  assert.equal(observers[1].disconnected, undefined);
  assert.deepEqual(loads, []);
  observers[1].callback([{ isIntersecting: true }]);
  assert.equal(observers[1].disconnected, true);
  assert.deepEqual(loads, [controller.generation]);
});
