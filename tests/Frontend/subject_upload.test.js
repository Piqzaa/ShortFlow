import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

test('subject previews preserve whole images and remove only the chosen new reference', () => {
  const source = readFileSync(new URL('../../assets/controllers/subject_upload_controller.js', import.meta.url), 'utf8').replace("import { Controller } from '@hotwired/stimulus';", '').replace('export default class', 'class Upload');
  const released = [];
  const node = () => ({ children: [], append(...children) { this.children.push(...children); }, replaceChildren() { this.children = []; }, addEventListener(event, callback) { this[event] = callback; } });
  const Upload = runInNewContext(`${source}\nUpload`, { Controller: class {}, document: { createElement: node }, URL: { createObjectURL: file => `blob:${file.name}`, revokeObjectURL: url => released.push(url) }, Event: class {}, DataTransfer: class { constructor() { this.files = []; this.items = { add: file => this.files.push(file) }; } } });
  const controller = new Upload();
  controller.previewTarget = node(); controller.statusTarget = {};
  controller.inputTarget = { files: ['one', 'two'].map(name => ({ name, type: 'image/png', size: 100 })), dispatchEvent() { controller.render(); } };
  controller.connect();
  assert.equal(controller.previewTarget.children.length, 2);
  assert.match(controller.statusTarget.textContent, /Images entières/);
  controller.previewTarget.children[0].children[1].click();
  assert.deepEqual(Array.from(controller.inputTarget.files, file => file.name), ['two']);
  assert.equal(controller.previewTarget.children.length, 1);
  assert.deepEqual(released, ['blob:one', 'blob:two']);
  controller.disconnect();
  assert.deepEqual(released, ['blob:one', 'blob:two', 'blob:two']);
});
