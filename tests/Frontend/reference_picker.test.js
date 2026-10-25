import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

function picker(fetch) {
  const source = readFileSync(new URL('../../assets/controllers/reference_picker_controller.js', import.meta.url), 'utf8').replace("import { Controller } from '@hotwired/stimulus';", '').replace('export default class', 'class Picker');
  const revoked = [];
  const FakeURL = class extends URL {};
  FakeURL.createObjectURL = () => 'blob:selection';
  FakeURL.revokeObjectURL = url => revoked.push(url);
  const Picker = runInNewContext(`${source}\nPicker`, { Controller: class {}, URL: FakeURL, location: new URL('http://localhost/studio'), AbortController, FormData: class { set() {} }, Event: class {}, fetch, setTimeout, clearTimeout });
  const controller = new Picker();
  controller.uploadValue = '/studio/creation/images?slot=plan';
  controller.kindValue = 'frame';
  controller.imageValue = '';
  controller.valueTarget = { value: '', dispatchEvent() {} };
  controller.fileTarget = { files: [{ type: 'image/png', size: 100 }], value: 'selected' };
  controller.previewTarget = { style: {}, getAttribute() { return this.src; }, removeAttribute() { this.src = ''; } };
  controller.frameTarget = { style: {} }; controller.removeTarget = {}; controller.statusTarget = {};
  const form = { querySelector() { return null; }, addEventListener() {}, removeEventListener() {} };
  controller.element = { dataset: {}, closest: selector => selector === 'form' ? form : null };
  controller.connect();
  return { controller, revoked };
}

test('removal aborts an upload and a late response cannot restore its image', async () => {
  let finish;
  const { controller, revoked } = picker(() => new Promise(resolve => { finish = resolve; }));
  const pending = controller.select();
  assert.equal(controller.element.dataset.planReferenceBusy, 'true');
  controller.remove();
  assert.equal(controller.request.signal.aborted, true);
  finish({ ok: true, json: async () => ({ reference: 'a'.repeat(48) + '.jpg', url: '/studio/creation/images/' + 'a'.repeat(48) + '.jpg' }) });
  await pending;
  assert.equal(controller.valueTarget.value, '');
  assert.equal(controller.element.dataset.planReferenceBusy, 'false');
  assert.deepEqual(revoked, ['blob:selection']);
});

test('restoring the same saved image after removal rebuilds its private preview', () => {
  const { controller } = picker(() => assert.fail('No upload expected'));
  const name = 'b'.repeat(48) + '.jpg';
  controller.valueTarget.value = name; controller.render();
  const original = controller.previewTarget.src;
  controller.remove(); controller.valueTarget.value = name; controller.render();
  assert.equal(controller.previewTarget.src, original);
  assert.equal(controller.frameTarget.hidden, false);
});

test('complementary subject views stay whole and are offered only to Kling elements', () => {
  const { controller } = picker(() => assert.fail('No upload expected'));
  controller.kindValue = 'view';
  controller.valueTarget.value = 'd'.repeat(48) + '.jpg';
  controller.allowRemoveValue = false;
  const model = { value: 'kling-3-image', selectedOptions: [{ dataset: { subjectLimit: '2', referenceMode: 'image' } }] };
  controller.form.querySelector = selector => selector.includes('target="model"') ? model : null;
  controller.render();
  assert.equal(controller.fileTarget.disabled, false);
  assert.equal(controller.previewTarget.style.objectFit, 'contain');
  assert.equal(controller.removeTarget.hidden, true);
  model.value = 'wan-image'; controller.render();
  assert.equal(controller.fileTarget.disabled, true);
  assert.equal(controller.frameTarget.hidden, false);
  assert.match(controller.statusTarget.textContent, /ne convient pas/);
});

test('an invalid replacement clears pending state and preserves the earlier saved reference', async () => {
  let finish;
  const { controller } = picker(() => new Promise(resolve => { finish = resolve; }));
  const name = 'c'.repeat(48) + '.jpg';
  controller.valueTarget.value = name;
  const pending = controller.select();
  controller.fileTarget.files = [{ type: 'text/plain', size: 10 }];
  await controller.select();
  assert.equal(controller.element.dataset.planReferenceBusy, 'false');
  assert.equal(controller.valueTarget.value, name);
  assert.match(controller.statusTarget.textContent, /JPG/);
  finish({ ok: true, json: async () => ({}) }); await pending;
  assert.equal(controller.valueTarget.value, name);
});
