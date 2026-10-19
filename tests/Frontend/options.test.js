import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

test('video options close outside, stay open for internal actions, and return focus on Escape', () => {
  const source = readFileSync(new URL('../../assets/controllers/options_controller.js', import.meta.url), 'utf8').replace("import { Controller } from '@hotwired/stimulus';", '').replace('export default class', 'class Options');
  const Options = runInNewContext(`${source}\nOptions`, {Controller: class {}});
  const controller = new Options();
  let focus = 0;
  controller.element = {open: true, contains: target => target === 'inside', querySelector: () => ({focus: () => focus++})};
  controller.outside({target:'inside'}); assert.equal(controller.element.open, true);
  controller.outside({target:'elsewhere'}); assert.equal(controller.element.open, false);
  controller.element.open = true; controller.escape({key:'Enter'}); assert.equal(controller.element.open, true);
  controller.escape({key:'Escape'}); assert.equal(controller.element.open, false); assert.equal(focus, 1);
});
