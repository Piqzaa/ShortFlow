import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

function setup() {
  const source = readFileSync(new URL('../../assets/controllers/shot_editor_controller.js', import.meta.url), 'utf8').replace("import { Controller } from '@hotwired/stimulus';", '').replace('export default class', 'class Editor');
  const Editor = runInNewContext(`${source}\nEditor`, { Controller: class {} });
  const editor = new Editor();
  const prompt = { value: 'Milo opens a box.', focus() { this.focused = true; } };
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

test('focusing a card targets the prompt and edits preserve the stored French summary', () => {
  const { editor, row, prompt, description } = setup();
  editor.update();
  editor.focusRow(row);
  assert.equal(prompt.focused, true);
  assert.equal(description.focused, undefined);
  prompt.value = 'Milo closes the box.';
  editor.update();
  assert.equal(description.value, 'Milo ouvre une boîte.');
  assert.equal(prompt.value, 'Milo closes the box.');
});

test('an old scene without summary keeps its prompt and does not invent a summary', () => {
  const { editor, description, prompt, references } = setup();
  description.value = '';
  editor.update();
  assert.equal(description.value, '');
  assert.equal(prompt.value, 'Milo opens a box.');
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

test('refreshing cards preserves their archived context without adding it to the edited prompt', () => {
  const { editor, row, prompt } = setup();
  const query = row.querySelector.bind(row);
  const identity = { value: 'A white cat.' }, source = { value: 'a'.repeat(64) };
  row.querySelector = selector => selector.includes('translatedIdentity') ? identity : selector.includes('translationSource') ? source : query(selector);
  prompt.value = 'A blue cat walks across the room.';
  editor.update();
  assert.equal(prompt.value, 'A blue cat walks across the room.');
  assert.equal(identity.value, 'A white cat.');
  assert.equal(source.value, 'a'.repeat(64));
});