import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

function setup(fetch) {
  const source = readFileSync(new URL('../../assets/controllers/story_writer_controller.js', import.meta.url), 'utf8').replace("import { Controller } from '@hotwired/stimulus';", '').replace('export default class', 'class Writer');
  const Writer = runInNewContext(`${source}\nWriter`, { Controller: class {}, AbortController, URLSearchParams, fetch, setTimeout, clearTimeout, setInterval, clearInterval });
  const writer = new Writer();
  writer.urlValue = '/scenario'; writer.tokenValue = 'csrf';
  writer.payload = () => ({ idea: 'Milo chat blanc', model: 'kling', target: '40' });
  writer.fingerprint = () => 'original';
  writer.generateTarget = { disabled: false, textContent: '' };
  writer.statusTarget = { textContent: '' };
  writer.previewTarget = { hidden: true };
  return writer;
}

test('a text engine error leaves the current scenes untouched and permits retry', async () => {
  const writer = setup(async () => ({ ok: false, json: async () => ({ error: 'Moteur indisponible' }) }));
  await writer.propose();
  assert.equal(writer.statusTarget.textContent, 'Moteur indisponible');
  assert.equal(writer.generateTarget.disabled, false);
  assert.equal(writer.proposal, null);
});

test('a delayed proposal cannot replace scenes edited during the request', async () => {
  let respond;
  const writer = setup(() => new Promise(resolve => { respond = resolve; }));
  const pending = writer.propose();
  writer.fingerprint = () => 'edited';
  respond({ ok: true, json: async () => ({ title: 'Old story', shots: [] }) });
  await pending;
  assert.match(writer.statusTarget.textContent, /a changé pendant/);
  assert.equal(writer.proposal, null);
});

test('an earlier preview cannot be applied after the model, tone or scenes changed', () => {
  const writer = setup(async () => {});
  writer.proposal = { shots: [] };
  writer.requestFingerprint = 'earlier';
  writer.apply();
  assert.match(writer.statusTarget.textContent, /nouvelle proposition/);
});

test('a queued request returns while the worker runs and disconnecting stops only the browser polling', async () => {
  let calls = 0;
  const writer = setup(async () => { calls++; return { ok: true, json: async () => ({id:'job',url:'/status',status:'queued',fingerprint:'original'}) }; });
  await writer.propose();
  assert.equal(calls, 1);
  assert.equal(writer.pending, true);
  assert.equal(writer.generateTarget.disabled, true);
  assert.match(writer.statusTarget.textContent, /En attente/);
  writer.disconnect();
  assert.equal(writer.pending, false);
  assert.equal(calls, 1);
});

test('returning to the page retrieves the ready proposal without applying scenes', async () => {
  const result = {title:'Milo et le robot',shots:[]};
  const writer = setup(async () => {});
  writer.latestValue = '/recent'; writer.active = true;
  writer.request = async () => ({status:'ready',fingerprint:'original',result});
  let rendered;
  writer.render = data => { rendered = data; };
  await writer.restore();
  assert.equal(rendered, result);
  assert.equal(writer.generateTarget.disabled, false);
});

test('a fresh page without an earlier request does not enter an endless loading state', async () => {
  const writer = setup(async () => {});
  writer.latestValue = '/recent'; writer.active = true;
  writer.request = async () => ({status:'none'});
  await writer.restore();
  assert.equal(writer.generateTarget.disabled, false);
  assert.equal(writer.statusTarget.textContent, '');
});

test('returning after applying or changing scenes ignores an older completed proposal', async () => {
  const writer = setup(async () => {});
  writer.latestValue = '/recent'; writer.active = true;
  writer.request = async () => ({status:'ready',fingerprint:'previous preparation',result:{}});
  await writer.restore();
  assert.equal(writer.statusTarget.textContent, '');
  assert.equal(writer.generateTarget.disabled, false);
});

test('the progress indicator follows the real worker stages and a failure allows another request', async () => {
  const writer = setup(async () => {});
  writer.active = true; writer.hasProgressTarget = true; writer.progressTarget = {};
  await writer.watch({status:'story',url:'/status'});
  assert.equal(writer.progressTarget.value, 2);
  assert.match(writer.statusTarget.textContent, /invente l’histoire/);
  await writer.watch({status:'shots',url:'/status'});
  assert.equal(writer.progressTarget.value, 3);
  writer.disconnect();
  writer.failed(new Error('Rédacteur indisponible'));
  assert.equal(writer.generateTarget.disabled, false);
});
