import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import { writingProgress } from '../../assets/writing-progress.js';

function setup(fetch) {
  const source = readFileSync(new URL('../../assets/controllers/publication_writer_controller.js', import.meta.url), 'utf8').replace("import { Controller } from '@hotwired/stimulus';", '').replace("import { writingProgress } from '../writing-progress.js';", '').replace('export default class', 'class Writer');
  let reloads = 0;
  const listeners = {};
  const Writer = runInNewContext(`${source}\nWriter`, { Controller: class {}, AbortController, fetch, setTimeout, clearTimeout, setInterval, clearInterval, writingProgress, location: { reload() { reloads++; } } });
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

test('waiting and editorial processing have distinct labels and recover by GET only', async () => {
  let status = 'queued';
  const calls = [];
  const state = setup(async (url, options) => {
    calls.push({ url, method: options.method || 'GET' });
    return { ok: true, json: async () => ({ status }) };
  });
  const writer = state.writer;
  writer.hasProgressTarget = true; writer.progressTarget = {};
  await writer.poll();
  assert.match(writer.statusTarget.textContent, /attente/);
  assert.equal(writer.progressTarget.textContent, 'Étape 1 sur 2');
  status = 'editorial';
  await writer.poll();
  assert.match(writer.statusTarget.textContent, /titres, légendes et hashtags/);
  assert.equal(writer.progressTarget.textContent, 'Étape 2 sur 2');
  assert.deepEqual(calls.map(call => call.method), ['GET', 'GET']);
  writer.disconnect();
});

test('a tracking error exposes recovery without enabling a duplicate request', async () => {
  const state = setup(async () => { throw new TypeError('offline'); });
  const writer = state.writer;
  writer.hasGenerateTarget = true; writer.generateTarget = { disabled: true };
  writer.hasRetryTarget = true; writer.retryTarget = { hidden: true };
  await writer.poll();
  assert.match(writer.statusTarget.textContent, /peut continuer/);
  assert.equal(writer.retryTarget.hidden, false);
  assert.equal(writer.generateTarget.disabled, true);
  writer.disconnect();
});

test('a confirmed failure enables explicit resubmission only when the writer is available', async () => {
  for (const available of [true, false]) {
    const state = setup(async () => ({ ok: true, json: async () => ({ status: 'failed', error: 'Délai dépassé' }) }));
    const writer = state.writer;
    writer.hasGenerateTarget = true; writer.generateTarget = { disabled: true };
    writer.availableValue = available;
    await writer.poll();
    assert.equal(writer.generateTarget.disabled, !available);
    assert.equal(writer.statusTarget.textContent, 'Délai dépassé');
    assert.equal(state.reloads(), 0);
    writer.disconnect();
  }
});

test('repeated recovery clicks cannot create concurrent polling requests', async () => {
  let finish, calls = 0;
  const state = setup(() => { calls++; return new Promise(resolve => { finish = resolve; }); });
  const pending = state.writer.poll();
  await state.writer.poll();
  assert.equal(calls, 1);
  finish({ ok: true, json: async () => ({ status: 'stale' }) });
  await pending;
  state.writer.disconnect();
});
