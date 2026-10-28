import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

function setup() {
  const source = readFileSync(new URL('../../assets/controllers/shot_editor_controller.js', import.meta.url), 'utf8').replace("import { Controller } from '@hotwired/stimulus';", '').replace('export default class', 'class Editor');
  const Editor = runInNewContext(`${source}\nEditor`, { Controller: class {} });
  const editor = new Editor();
  const prompt = { value: 'Milo opens a box.' };
  const description = { value: 'Milo ouvre une boîte.', focus() { this.focused = true; } };
  const status = { textContent: '' }, review = { hidden: true }, references = { textContent: '' };
  const row = { dataset: {}, querySelector(selector) {
    if (selector === 'textarea') return prompt;
    if (selector === 'select') return { value: '5' };
    if (selector.includes('sceneDescription')) return description;
    if (selector === '[data-shot-summary-status]') return status;
    if (selector === '[data-shot-summary-review]') return review;
    if (selector === '[data-shot-reference-summary]') return references;
    if (selector === '[data-shot-heading]') return { textContent: '' };
    return null;
  }, querySelectorAll() { return []; } };
  editor.rows = () => [row];
  editor.element = { querySelector() { return null; } };
  editor.modelValue = { seconds: [5], rateMicros: 1000 };
  editor.addTarget = {};
  editor.summaryTarget = { classList: { toggle() {} } };
  return { editor, row, prompt, description, status, review, references };
}

test('reviewing a summary preserves keyboard focus and becomes stale after a prompt or summary edit', () => {
  const { editor, row, prompt, description, status, review } = setup();
  editor.update();
  editor.review({ currentTarget: { closest: () => row } });
  assert.equal(description.focused, true);
  assert.equal(review.hidden, true);
  prompt.value = 'Milo closes the box.';
  editor.update();
  assert.match(status.textContent, /à revérifier/);
  assert.equal(review.hidden, false);
  editor.review({ currentTarget: { closest: () => row } });
  description.value = 'Milo ferme la boîte.';
  editor.update();
  assert.match(status.textContent, /à revérifier/);
  assert.equal(prompt.value, 'Milo closes the box.');
});

test('an old scene without summary keeps its prompt and does not invent a summary', () => {
  const { editor, description, prompt, status, review, references } = setup();
  description.value = '';
  editor.update();
  assert.match(status.textContent, /à compléter/);
  assert.equal(description.value, '');
  assert.equal(prompt.value, 'Milo opens a box.');
  assert.equal(review.hidden, true);
  assert.match(references.textContent, /aucune image envoyée/);
});

test('invalid fields reveal every enclosing detail before native focus', () => {
  const { editor } = setup();
  const outer = { tagName: 'DETAILS', open: false, parentElement: editor.element };
  const inner = { tagName: 'DETAILS', open: false, parentElement: outer };
  editor.reveal({ parentElement: inner });
  assert.equal(inner.open, true);
  assert.equal(outer.open, true);
});
