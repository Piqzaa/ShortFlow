import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

function creation() {
  const source = readFileSync(new URL('../../assets/controllers/creation_controller.js', import.meta.url), 'utf8')
    .replace("import { Controller } from '@hotwired/stimulus';", '').replace('export default class', 'class CreationController');
  const Creation = runInNewContext(`${source}\nCreationController;`, { Controller: class {}, Intl });
  const controller = new Creation();
  controller.modelsValue = { kling: { rate: 7, audio: false }, wan: { rate: 10, audio: true } };
  controller.modelTarget = { value: 'kling' };
  controller.durationTarget = { value: '5' };
  controller.costTarget = {}; controller.descriptionTarget = {};
  return controller;
}

test('changing model or duration updates the indicative price without submitting the idea', () => {
  const controller = creation();
  controller.connect();
  assert.equal(controller.costTarget.textContent, 'Estimation : 0,35 $ US');
  controller.durationTarget.value = '10'; controller.estimate();
  assert.equal(controller.costTarget.textContent, 'Estimation : 0,70 $ US');
  controller.modelTarget.value = 'wan'; controller.estimate();
  assert.equal(controller.costTarget.textContent, 'Estimation : 1,00 $ US');
  assert.match(controller.descriptionTarget.textContent, /Audio généré/);
});

test('missing model and unsupported duration never show a fabricated estimate', () => {
  const controller = creation();
  controller.modelTarget.value = ''; controller.connect();
  assert.match(controller.costTarget.textContent, /Choisis un modèle/);
  controller.modelTarget.value = 'kling'; controller.durationTarget.value = '9'; controller.estimate();
  assert.match(controller.costTarget.textContent, /Choisis un modèle/);
});
