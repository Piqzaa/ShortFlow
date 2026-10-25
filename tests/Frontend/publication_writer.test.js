import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

function setup(fetch) {
  const source = readFileSync(new URL('../../assets/controllers/publication_writer_controller.js', import.meta.url), 'utf8').replace("import { Controller } from '@hotwired/stimulus';", '').replace('export default class', 'class Writer');
  let reloads = 0;
  const listeners = {};
  const Writer = runInNewContext(`${source}\nWriter`, { Controller: class {}, AbortController, fetch, setTimeout, clearTimeout, location: { reload() { reloads++; } } });
  const writer = new Writer();
  writer.element = { closest: () => ({ addEventListener(name,callback) { listeners[name] = callback; }, removeEventListener(name) { delete listeners[name]; } }) };
  writer.statusTarget = {}; writer.urlValue = '';
  writer.connect(); writer.urlValue = '/private-status';
  return { writer, listeners, reloads: () => reloads };
}

test('a ready proposal never reloads over edits made while the local writer runs', async () => {
  let finish;
  const state = setup(() => new Promise(resolve => { finish = resolve; }));
  const pending = state.writer.poll();
  state.listeners.input();
  finish({ ok:true, json:async () => ({status:'ready'}) });
  await pending;
  assert.equal(state.reloads(), 0);
  assert.match(state.writer.statusTarget.textContent, /Enregistre le brouillon/);
  state.writer.disconnect();
});

test('a clean form refreshes to display the preview without applying it', async () => {
  const state = setup(async () => ({ok:true,json:async () => ({status:'ready'})}));
  await state.writer.poll();
  assert.equal(state.reloads(), 1);
  state.writer.disconnect();
});

test('disconnect aborts polling and a late result cannot reload the page', async () => {
  let finish;
  const state = setup(() => new Promise(resolve => { finish = resolve; }));
  const pending = state.writer.poll();
  state.writer.disconnect();
  finish({ok:true,json:async () => ({status:'ready'})});
  await pending;
  assert.equal(state.writer.request.signal.aborted, true);
  assert.equal(state.reloads(), 0);
});
