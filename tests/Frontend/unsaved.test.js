import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

test('leaving warns only about unsaved edits; submitting and disconnecting clear the guard', () => {
  const listeners = new Map();
  const source = readFileSync(new URL('../../assets/controllers/unsaved_controller.js', import.meta.url), 'utf8')
    .replace("import { Controller } from '@hotwired/stimulus';", '')
    .replace('export default class', 'class UnsavedController');
  const Unsaved = runInNewContext(`${source}\nUnsavedController;`, {
    Controller: class {},
    window: {
      addEventListener: (name, listener) => listeners.set(name, listener),
      removeEventListener: (name, listener) => { if (listeners.get(name) === listener) listeners.delete(name); },
    },
  });
  const controller = new Unsaved();
  controller.connect();
  let warnings = 0;
  const event = { preventDefault: () => warnings++, returnValue: undefined };
  listeners.get('beforeunload')(event);
  assert.equal(warnings, 0);
  controller.change();
  listeners.get('beforeunload')(event);
  assert.equal(warnings, 1);
  assert.equal(event.returnValue, '');
  controller.submit();
  listeners.get('beforeunload')(event);
  assert.equal(warnings, 1);
  controller.disconnect();
  assert.equal(listeners.size, 0);
});
