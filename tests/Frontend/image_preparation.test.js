import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import { buildImagePrompt } from '../../assets/image-preparation.js';

test('image instructions describe a starting still with common identity without rendering dialogue as text', () => {
  const prompt = buildImagePrompt({ idea: 'Milo découvre un bouton.', scene: 'Nox dit « Attention ». Milo tend sa patte.', ratio: '16:9', style: 'Animation 3D', characters: 'Milo roux en bleu.', subjects: ['Nox', ''] });
  assert.match(prompt, /paysage 16:9/);
  assert.match(prompt, /Milo roux en bleu/);
  assert.match(prompt, /Nox dit/);
  assert.match(prompt, /AVANT l’action/);
  assert.match(prompt, /ne les écris pas dans l’image/);
  assert.match(prompt, /Animation 3D/);
});

test('missing scenes and oversized instructions fail explicitly without silently truncating inputs', () => {
  assert.throws(() => buildImagePrompt({ scene: ' ' }), /Décris la scène/);
  assert.throws(() => buildImagePrompt({ scene: '🌙'.repeat(3001) }), /3 000 caractères/);
  assert.match(buildImagePrompt({ idea: 'Unique', scene: 'Unique', ratio: 'auto' }), /identique à la référence/);
  assert.equal(buildImagePrompt({ idea: 'Unique', scene: 'Unique' }).includes('Contexte de l’histoire'), false);
});

function setup(clipboard = {}) {
  const source = readFileSync(new URL('../../assets/controllers/image_preparation_controller.js', import.meta.url), 'utf8')
    .replace("import { Controller } from '@hotwired/stimulus';", '').replace("import { buildImagePrompt } from '../image-preparation.js';", '').replace('export default class', 'class ImagePreparation');
  const Controller = runInNewContext(`${source}\nImagePreparation`, { Controller: class {}, buildImagePrompt, navigator: { clipboard }, Event: class {} });
  const controller = new Controller();
  const scene = { value: 'Milo regarde le bouton.' };
  const selected = { value: 'image', focus() {}, scrollIntoView() {}, dispatchEvent() {} };
  const continuity = { value: 'independent' };
  const row = { querySelector: () => scene };
  const rows = [row];
  const fields = { '[data-creation-target="source"]': selected, '[name$="[continuity]"]': continuity, '[data-creation-target="ratio"]': { value: '9:16' }, '[name="ai_idea[prompt]"]': { value: 'Une histoire de chats.' } };
  const root = { querySelector: selector => fields[selector] || null, querySelectorAll: selector => selector === '[data-shot-editor-row]' ? rows : [], addEventListener() {}, removeEventListener() {} };
  controller.element = { closest: selector => selector === '[data-shot-editor-row]' ? row : root };
  controller.styleValue = ''; controller.charactersValue = ''; controller.ideaValue = ''; controller.ratioValue = '9:16'; controller.modeValue = 'image'; controller.continuityValue = 'independent';
  const events = [];
  controller.promptTarget = { value: '', focus() {}, select() { this.selected = true; }, dispatchEvent: event => events.push(event) };
  controller.previewTarget = { value: '', focus() {} }; controller.proposalTarget = { hidden: true };
  controller.statusTarget = {}; controller.noticeTarget = {}; controller.proposeTarget = {}; controller.chooseModeTarget = {}; controller.toolsTarget = { hidden: true };
  controller.connect();
  return { controller, scene, selected, continuity, rows, events, root, fields };
}

test('initial proposal notifies autosave and an edited prompt needs explicit replacement', () => {
  const { controller, scene, events } = setup();
  controller.propose();
  assert.match(controller.promptTarget.value, /Milo regarde/);
  assert.equal(events.length, 1);
  controller.promptTarget.value = 'Mon cadrage personnel';
  scene.value = 'Milo appuie sur le bouton.';
  controller.propose();
  assert.equal(controller.promptTarget.value, 'Mon cadrage personnel');
  assert.equal(controller.proposalTarget.hidden, false);
  controller.replace();
  assert.match(controller.promptTarget.value, /Milo appuie/);
  assert.equal(controller.proposalTarget.hidden, true);
});

test('a changed scene cannot apply a stale image proposal over human instructions', () => {
  const { controller, scene } = setup();
  controller.promptTarget.value = 'Conserver le costume';
  controller.propose();
  scene.value = 'Autre action';
  controller.replace();
  assert.equal(controller.promptTarget.value, 'Conserver le costume');
  assert.match(controller.statusTarget.textContent, /scène a changé/);
});

test('identity mode and inherited linked frames explain why no starting image should be prepared', () => {
  const { controller, selected, continuity, rows } = setup();
  selected.value = 'subject'; controller.refresh(); controller.propose();
  assert.equal(controller.proposeTarget.disabled, true);
  assert.equal(controller.promptTarget.value, '');
  assert.match(controller.noticeTarget.textContent, /références d’identité/);
  selected.value = 'image'; continuity.value = 'linked'; rows.unshift({}); controller.refresh();
  assert.equal(controller.proposeTarget.disabled, true);
  assert.match(controller.noticeTarget.textContent, /clip précédent/);
});

test('copy only sends the chosen text to the clipboard and provides a manual fallback', async () => {
  const copies = [];
  const { controller } = setup({ writeText: async value => copies.push(value) });
  controller.promptTarget.value = 'Mon image privée';
  await controller.copy();
  assert.deepEqual(copies, ['Mon image privée']);
  const fallback = setup().controller;
  fallback.promptTarget.value = 'À copier'; await fallback.copy();
  assert.equal(fallback.promptTarget.selected, true);
  assert.match(fallback.statusTarget.textContent, /Ctrl\+C/);
});

test('legacy story forms without the common selectors still use their saved visual context', () => {
  const { controller, fields } = setup();
  for (const field of Object.keys(fields)) delete fields[field];
  controller.ideaValue = 'Une série privée'; controller.charactersValue = 'Milo roux'; controller.ratioValue = '1:1';
  controller.refresh();
  assert.equal(controller.chooseModeTarget.hidden, true);
  controller.propose();
  assert.match(controller.promptTarget.value, /carré 1:1/);
  assert.match(controller.promptTarget.value, /Une série privée/);
  assert.match(controller.promptTarget.value, /Milo roux/);
  controller.disconnect();
});
