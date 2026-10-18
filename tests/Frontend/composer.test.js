import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

test('composer reveals scheduling fields and refreshes networks through the native form', () => {
  const source = readFileSync(new URL('../../assets/controllers/composer_controller.js', import.meta.url), 'utf8')
    .replace("import { Controller } from '@hotwired/stimulus';", '')
    .replace('export default class', 'class ComposerController');
  const Composer = runInNewContext(`${source}\nComposerController;`, { Controller: class {} });
  const controller = new Composer();
  let mode = 'immediate', submitted;
  const date = { type: 'text' };
  controller.element = { classList: { add() {} }, querySelectorAll: () => [date], querySelector: () => ({ value: mode }), requestSubmit: button => { submitted = button; } };
  controller.dateTarget = { hidden: false };
  controller.refreshTarget = { name: 'intent', value: 'refresh' };
  controller.connect();
  assert.equal(date.type, 'datetime-local');
  assert.equal(controller.dateTarget.hidden, true);
  mode = 'scheduled'; controller.timing();
  assert.equal(controller.dateTarget.hidden, false);
  controller.networks();
  assert.equal(submitted, controller.refreshTarget);
});
