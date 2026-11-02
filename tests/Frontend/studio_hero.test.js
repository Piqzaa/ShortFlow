import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

function setup(loadMedia = async names => Object.fromEntries(names.map(name => [name, {}]))) {
  const listeners = new Map(), ticks = new Set(), draws = [], observers = [];
  const classes = () => {
    const values = new Set();
    return { add: (...names) => names.forEach(name => values.add(name)), remove: (...names) => names.forEach(name => values.delete(name)),
      contains: name => values.has(name), toggle: (name, active) => active ? values.add(name) : values.delete(name) };
  };
  const document = { hidden: false, addEventListener: (name, fn) => listeners.set(name, fn), removeEventListener: name => listeners.delete(name) };
  const composer = { HERO_MEDIA: ['first', 'second'], loadMedia, drawFilm: (_context, _images, seconds) => draws.push(seconds) };
  const source = readFileSync(new URL('../../assets/controllers/studio_controller.js', import.meta.url), 'utf8')
    .replace(/^import .*;\r?$/gm, '')
    .replace('export default class', 'class StudioController')
    .replaceAll("import('../screen-composer.js')", 'Promise.resolve(composer)');
  const Studio = runInNewContext(`${source}\nStudioController;`, { Controller: class {}, composer, document,
    IntersectionObserver: class { constructor(fn) { this.callback = fn; observers.push(this); } observe() {} disconnect() { this.disconnected = true; } } });
  const controller = new Studio();
  controller.hasHeroTarget = true;
  controller.heroTarget = { getContext: () => ({}), classList: classes() };
  controller.fallbackTarget = { classList: classes() };
  controller.generation = 1;
  controller.gsap = { ticker: { add: fn => ticks.add(fn), remove: fn => ticks.delete(fn) } };
  return { controller, document, listeners, ticks, draws, observers };
}

test('mobile hero draws without video autoplay and stops drawing offscreen or in a hidden tab', async () => {
  const state = setup();
  const { controller, document, listeners, ticks, draws, observers } = state;
  await controller.setupMobileHero(controller.gsap, 1);
  assert.deepEqual(draws, [0]);
  observers[0].callback([{ isIntersecting: true }]);
  assert.equal(ticks.size, 1);
  const tick = [...ticks][0];
  tick(0, 16); tick(0, 16); tick(0, 16);
  assert.equal(draws.length, 2, 'Canvas drawing is capped below the animation ticker frequency.');
  document.hidden = true; listeners.get('visibilitychange')();
  assert.equal(ticks.size, 0);
  document.hidden = false; listeners.get('visibilitychange')();
  assert.equal(ticks.size, 1);
  observers[0].callback([{ isIntersecting: false }]);
  assert.equal(ticks.size, 0);
  controller.clearMobileHero();
  assert.equal(listeners.size, 0);
  assert.equal(observers[0].disconnected, true);
  assert.equal(controller.heroTarget.classList.contains('is-ready'), false);
  assert.equal(controller.fallbackTarget.classList.contains('has-hero-motion'), false);
});

test('late image loads cannot restore animation after pause or preference changes', async () => {
  let resolve;
  const { controller, ticks, observers } = setup(() => new Promise(done => { resolve = done; }));
  const loading = controller.setupMobileHero(controller.gsap, 1);
  await Promise.resolve();
  controller.generation++;
  resolve({ first: {}, second: {} });
  await loading;
  assert.equal(controller.heroTarget.classList.contains('is-ready'), false);
  assert.equal(ticks.size, 0);
  assert.equal(observers.length, 0);
});

test('missing imagery preserves the static phone instead of showing an empty film', async () => {
  const { controller, ticks, draws } = setup(async () => ({ first: {}, second: null }));
  await controller.setupMobileHero(controller.gsap, 1);
  assert.equal(controller.heroTarget.classList.contains('is-ready'), false);
  assert.equal(draws.length, 0);
  assert.equal(ticks.size, 0);
});
