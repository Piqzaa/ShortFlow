import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

function creation() {
  const source = readFileSync(new URL('../../assets/controllers/creation_controller.js', import.meta.url), 'utf8')
    .replace("import { Controller } from '@hotwired/stimulus';", '').replace('export default class', 'class CreationController');
  const Creation = runInNewContext(`${source}\nCreationController;`, { Controller: class {}, Intl });
  const controller = new Creation();
  controller.modelsValue = { kling: { name: 'Kling', maker: 'Kling', rateMicros: 70000, audio: false, seconds: [5, 10], ratios: ['9:16','16:9','1:1'], provider: 'fal', quality: 'Pro' }, wan: { name: 'Wan', maker: 'Alibaba', rateMicros: 100000, audio: true, seconds: [5, 10], ratios: ['9:16','16:9'], provider: 'replicate', quality: '720p' }, long: { name: 'Kling 3', maker: 'Kling', rateMicros: 168000, audio: true, seconds: [3,5,10,15], ratios: ['9:16','16:9'], provider: 'fal', quality: 'Pro' } };
  controller.connectedValue = { fal: true, replicate: false };
  controller.connectTarget = {}; controller.filtersTarget = {}; controller.chooseTargets = [];
  controller.searchTarget = { value: '' }; controller.providerTarget = { value: '' }; controller.audioTarget = { value: '' }; controller.minimumTarget = { value: '' }; controller.onlyConnectedTarget = { checked: false };
  controller.cardTargets = Object.keys(controller.modelsValue).map(id => ({ dataset: { modelId: id } }));
  controller.countTarget = {}; controller.emptyTarget = {};
  controller.ratioTarget = { value: '9:16', options: ['','9:16','16:9','1:1'].map(value => ({value})) };
  controller.modelTarget = { value: 'kling' };
  controller.durationTarget = { value: '5', options: ['','3','5','10','15'].map(value => ({value})) };
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
  assert.match(controller.costTarget.textContent, /durée compatible/);
});


test('changing to a restrictive model clears unsupported settings instead of replacing them silently', () => {
  const controller = creation();
  controller.modelTarget.value = 'wan'; controller.durationTarget.value = '15'; controller.ratioTarget.value = '1:1';
  controller.changeModel();
  assert.equal(controller.durationTarget.value, ''); assert.equal(controller.ratioTarget.value, '');
  assert.equal(controller.durationTarget.options.find(o => o.value === '15').disabled, true);
  assert.equal(controller.connectTarget.hidden, false);
  controller.modelTarget.value = 'long'; controller.durationTarget.value = '15'; controller.changeModel();
  assert.equal(controller.costTarget.textContent, 'Estimation : 2,52 $ US');
  controller.durationTarget.value = '3'; controller.estimate();
  assert.equal(controller.costTarget.textContent, 'Estimation : 0,51 $ US');
  assert.equal(controller.connectTarget.hidden, true);
});

test('catalog filters combine search, provider, audio, duration and connected providers', () => {
  const controller = creation(); controller.connect();
  assert.equal(controller.cardTargets.filter(c => !c.hidden).length, 3);
  controller.searchTarget.value = 'alibaba'; controller.filter();
  assert.equal(controller.cardTargets.filter(c => !c.hidden).length, 1);
  controller.onlyConnectedTarget.checked = true; controller.filter();
  assert.equal(controller.emptyTarget.hidden, false);
  controller.onlyConnectedTarget.checked = false; controller.searchTarget.value = ''; controller.minimumTarget.value = '15'; controller.audioTarget.value = 'yes'; controller.filter();
  assert.deepEqual(controller.cardTargets.filter(c => !c.hidden).map(c => c.dataset.modelId), ['long']);
  controller.providerTarget.value = 'replicate'; controller.filter();
  assert.equal(controller.emptyTarget.hidden, false);
});
