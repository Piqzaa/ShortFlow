import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

function saver() {
  const requests = [], timers = new Map();
  let text = 'Initial', timer = 0;
  const source = readFileSync(new URL('../../assets/controllers/idea_save_controller.js', import.meta.url), 'utf8')
    .replace("import { Controller } from '@hotwired/stimulus';", '').replace('export default class', 'class Saver');
  const Saver = runInNewContext(`${source}\nSaver`, {
    Controller: class {}, URLSearchParams,
    crypto: { randomUUID: () => 'a'.repeat(32) },
    FormData: class { *[Symbol.iterator]() { yield ['ai_idea[prompt]', text]; yield ['ai_idea[_token]', 'csrf']; } },
    setTimeout: fn => { timers.set(++timer, fn); return timer; }, clearTimeout: id => timers.delete(id),
    fetch: (url, options) => new Promise(resolve => requests.push({ url, options, resolve })),
  });
  const c = new Saver();
  Object.assign(c, { element: {}, statusTarget: {}, retryTarget: {}, urlValue: '/studio/creation/idee', revisionValue: 'revision' });
  c.connect();
  return { c, requests, timers, edit: value => { text = value; }, ok: index => requests[index].resolve({ ok: true, json: async () => ({ saved: true }) }) };
}

test('autosave preserves edits on departure, ignores late replies and never posts a generation', async () => {
  const x = saver();
  x.edit('B'); x.c.change({ type: 'input' });
  assert.equal(x.timers.size, 1);
  const oldSave = x.c.flush();
  x.edit('C'); const latestSave = x.c.flush();
  assert.equal(x.requests[1].options.keepalive, true);
  assert.equal(x.requests[1].options.body.get('sequence'), '2');
  assert.equal(x.requests[1].options.body.get('ai_idea[prompt]'), 'C');
  assert.equal(x.requests[1].url, '/studio/creation/idee');
  x.ok(1); await latestSave;
  x.ok(0); await oldSave;
  assert.equal(x.c.statusTarget.hidden, true);
  assert.equal(x.c.statusTarget.textContent, '');
  assert.match(x.c.saved, /prompt%5D=C/);
  x.c.submit(); x.edit('D'); await x.c.flush();
  assert.equal(x.requests.length, 2);
  assert.equal(x.timers.size, 0);
});

test('reverting while a save is pending still saves the reverted idea; errors allow retry', async () => {
  const x = saver();
  x.edit('B'); const first = x.c.flush();
  x.edit('Initial'); const reverted = x.c.flush();
  assert.equal(x.requests.length, 2);
  x.ok(0); await first;
  x.requests[1].resolve({ ok: false }); await reverted;
  assert.equal(x.c.retryTarget.hidden, false);
  assert.match(x.c.statusTarget.textContent, /impossible/);
  assert.equal(x.c.statusTarget.hidden, false);
  const retry = x.c.flush(); x.ok(2); await retry;
  assert.equal(x.c.retryTarget.hidden, true);
});
