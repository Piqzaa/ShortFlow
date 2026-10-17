import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

test('opening never autoplays; closing and disconnecting pause the source', () => {
  const source = readFileSync(new URL('../../assets/controllers/publication_preview_controller.js', import.meta.url), 'utf8')
    .replace("import { Controller } from '@hotwired/stimulus';", '')
    .replace('export default class', 'class PublicationPreviewController');
  const Preview = runInNewContext(`${source}\nPublicationPreviewController;`, { Controller: class {} });
  const controller = new Preview();
  let pauses = 0;
  let loads = 0;
  const video = { preload: 'none', load: () => { loads++; }, pause: () => { pauses++; }, play: () => { throw new Error('Unexpected autoplay'); } };
  controller.element = { open: true, querySelector: () => video };
  controller.toggle();
  assert.equal(pauses, 0);
  assert.equal(loads, 1);
  assert.equal(video.preload, 'metadata');
  controller.toggle();
  assert.equal(loads, 1);
  controller.element.open = false;
  controller.toggle();
  assert.equal(pauses, 1);
  controller.disconnect();
  assert.equal(pauses, 2);
  controller.element.querySelector = () => null;
  controller.disconnect();
});
