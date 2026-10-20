import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

function reference() {
  const changes = [], calls = [], revoked = [], timers = new Map();
  let timer = 0;
  const source = readFileSync(new URL('../../assets/controllers/image_reference_controller.js', import.meta.url), 'utf8')
    .replace("import { Controller } from '@hotwired/stimulus';", '').replace('export default class', 'class Reference');
  const Reference = runInNewContext(`${source}\nReference`, {
    Controller: class { dispatch(name, options) { changes.push(options.detail); } },
    AbortController, FormData: class {},
    URL: { createObjectURL: () => 'blob:preview', revokeObjectURL: url => revoked.push(url) },
    setTimeout: fn => { timers.set(++timer, fn); return timer; }, clearTimeout: id => timers.delete(id),
    fetch: (url, options) => new Promise(resolve => calls.push({ url, options, resolve })),
  });
  const c = new Reference();
  const preview = { hidden: true, removeAttribute() { delete this.src; } };
  Object.assign(c, { fileTarget: { files: [{ type: 'image/png', size: 1000 }], value: 'test.png' },
    previewTarget: preview, statusTarget: {}, submitTarget: {}, removeTarget: {},
    element: { action: '/studio/creation/images', requestSubmit() { c.uploadPromise = c.upload({ preventDefault() {} }); } } });
  c.connect();
  return { c, changes, calls, revoked, timers };
}

test('image selection previews locally before upload and only exposes a reference after saving', async () => {
  const x = reference(); x.c.select();
  assert.equal(x.c.previewTarget.src, 'blob:preview');
  assert.equal(x.c.previewTarget.hidden, false);
  assert.equal(x.changes.at(-1).pending, true);
  assert.equal(x.c.submitTarget.disabled, true);
  assert.equal(x.calls[0].url, '/studio/creation/images');
  assert.equal(x.calls[0].options.redirect, 'error');
  const name = 'a'.repeat(48) + '.jpg';
  x.calls[0].resolve({ ok: true, json: async () => ({ reference: name }) });
  await x.c.uploadPromise;
  assert.equal(x.changes.at(-1).reference, name);
  assert.equal(x.c.submitTarget.disabled, false);
  assert.equal(x.timers.size, 0);
  x.c.disconnect();
  assert.deepEqual(x.revoked, ['blob:preview']);
});

test('removing a pending image cannot be undone by a late upload; invalid files never upload', async () => {
  const x = reference(); x.c.select();
  x.c.remove();
  assert.equal(x.calls[0].options.signal.aborted, true);
  x.calls[0].resolve({ ok: true, json: async () => ({ reference: 'a'.repeat(48) + '.jpg' }) });
  await x.c.uploadPromise;
  assert.equal(x.changes.at(-1).reference, '');
  assert.equal(x.c.previewTarget.hidden, true);
  x.c.fileTarget.files = [{ type: 'image/svg+xml', size: 100 }]; x.c.select();
  assert.equal(x.calls.length, 1);
  assert.match(x.c.statusTarget.textContent, /JPG, PNG ou WebP/);
});
