import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

test('composer reveals scheduling fields and refreshes networks through the native form', async () => {
  const source = readFileSync(new URL('../../assets/controllers/composer_controller.js', import.meta.url), 'utf8')
    .replace("import { Controller } from '@hotwired/stimulus';", '')
    .replace('export default class', 'class ComposerController');
  const Composer = runInNewContext(`${source}\nComposerController;`, { Controller: class {}, setTimeout, clearTimeout });
  const controller = new Composer();
  let mode = 'immediate', submitted;
  const date = { type: 'text' };
  controller.element = { isConnected: true, classList: { add() {} }, querySelectorAll: () => [date], querySelector: () => ({ value: mode }), requestSubmit: button => { submitted = button; } };
  controller.dateTarget = { hidden: false };
  controller.refreshTarget = { name: 'intent', value: 'refresh' };
  controller.connect();
  assert.equal(date.type, 'datetime-local');
  assert.equal(controller.dateTarget.hidden, true);
  mode = 'scheduled'; controller.timing();
  assert.equal(controller.dateTarget.hidden, false);
  controller.networks();
  await new Promise(resolve => setTimeout(resolve, 0));
  assert.equal(submitted, controller.refreshTarget);
});


test('network refresh clears the unsaved guard after the original change has bubbled', async () => {
  const composerSource = readFileSync(new URL('../../assets/controllers/composer_controller.js', import.meta.url), 'utf8')
    .replace("import { Controller } from '@hotwired/stimulus';", '').replace('export default class', 'class ComposerController');
  const unsavedSource = readFileSync(new URL('../../assets/controllers/unsaved_controller.js', import.meta.url), 'utf8')
    .replace("import { Controller } from '@hotwired/stimulus';", '').replace('export default class', 'class UnsavedController');
  const listeners = new Map();
  const Unsaved = runInNewContext(`${unsavedSource}\nUnsavedController;`, { Controller: class {}, window: { addEventListener: (name, listener) => listeners.set(name, listener), removeEventListener() {} } });
  const Composer = runInNewContext(`${composerSource}\nComposerController;`, { Controller: class {}, setTimeout, clearTimeout });
  const unsaved = new Unsaved(); unsaved.connect();
  const composer = new Composer();
  let submissions = 0, warnings = 0;
  composer.refreshTarget = {};
  composer.element = { isConnected: true, requestSubmit: () => { submissions++; unsaved.submit(); } };
  // A nested change action runs before the same event reaches the form's dirty listener.
  unsaved.change();
  composer.networks(); composer.networks();
  // Native event dispatch can run a microtask checkpoint between nested listeners.
  await Promise.resolve();
  unsaved.change();
  assert.equal(submissions, 0);
  await new Promise(resolve => setTimeout(resolve, 0));
  listeners.get('beforeunload')({ preventDefault: () => warnings++ });
  assert.equal(submissions, 1);
  assert.equal(warnings, 0);
  unsaved.change();
  listeners.get('beforeunload')({ preventDefault: () => warnings++ });
  assert.equal(warnings, 1);
});
