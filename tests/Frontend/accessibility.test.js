import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

test('native validation and returned errors reveal nested disclosures across forms; disconnect removes capture', () => {
  const source = readFileSync(new URL('../../assets/controllers/accessibility_controller.js', import.meta.url), 'utf8')
    .replace("import { Controller } from '@hotwired/stimulus';", '')
    .replace('export default class', 'class Accessibility');
  const Accessibility = runInNewContext(`${source}\nAccessibility`, { Controller: class {} });
  const controller = new Accessibility();
  let listener;
  const root = {
    addEventListener(name, callback, capture) {
      assert.equal(name, 'invalid'); assert.equal(capture, true); listener = callback;
    },
    removeEventListener(name, callback, capture) {
      assert.equal(name, 'invalid'); assert.equal(callback, listener); assert.equal(capture, true); listener = null;
    },
    querySelectorAll: () => [serverError],
  };
  const outer = { tagName: 'DETAILS', open: false, parentElement: root };
  const inner = { tagName: 'DETAILS', open: false, parentElement: outer };
  const serverError = { parentElement: inner };
  controller.element = root;
  controller.connect();
  assert.equal(inner.open, true); assert.equal(outer.open, true);
  inner.open = outer.open = false;
  listener({ target: { parentElement: inner } });
  assert.equal(inner.open, true); assert.equal(outer.open, true);
  // Opening errors must never unlock sections hidden by business rules.
  const hidden = { tagName: 'DIV', hidden: true, parentElement: root };
  listener({ target: { parentElement: hidden } });
  assert.equal(hidden.hidden, true);
  controller.disconnect();
  assert.equal(listener, null);
});
