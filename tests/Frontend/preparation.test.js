import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import { proposePlan } from '../../assets/preparation-plan.js';

test('choosing single exposes the adjacent conversion without changing existing scenes or durations', () => {
  const source = readFileSync(new URL('../../assets/controllers/preparation_controller.js', import.meta.url), 'utf8')
    .replace("import { Controller } from '@hotwired/stimulus';", '')
    .replace("import { proposePlan } from '../preparation-plan.js';", '')
    .replace('export default class', 'class Preparation');
  const Preparation = runInNewContext(`${source}\nPreparation`, { Controller: class {}, proposePlan });
  const controller = new Preparation();
  const prompts = ['First scene.', 'Second scene.', 'Third scene.'];
  const rows = prompts.map(prompt => ({ querySelector(selector) { return selector === 'select' ? { value: '5', options: [{ value: '5' }] } : selector === 'textarea' ? { value: prompt } : null; } }));
  Object.defineProperty(controller, 'editor', { value: { rows: () => rows, update() {} } });
  Object.defineProperty(controller, 'model', { value: { seconds: [5,10,15] } });
  controller.element = { querySelector: () => ({ value: '' }) };
  controller.continuityPanelTarget = {};
  controller.singleTarget = {};
  controller.fixContinuityTarget = {};
  controller.continuityTarget = { value: 'independent', querySelector: () => ({}) };
  controller.continuityHelpTarget = {};
  controller.hasSingleActionTarget = true;
  controller.singleActionTarget = {};
  controller.singleStatusTarget = {};
  controller.totalTarget = { value: '15' };
  controller.noticeTarget = {};
  controller.refresh();
  assert.equal(controller.singleActionTarget.hidden, true);
  controller.continuityTarget.value = 'single';
  controller.refresh();
  assert.equal(controller.singleActionTarget.hidden, false);
  assert.match(controller.singleStatusTarget.textContent, /3 plans actuellement/);
  assert.match(controller.continuityHelpTarget.textContent, /pas encore réunies/);
  assert.deepEqual(rows.map(row => row.querySelector('textarea').value), prompts);
  assert.equal(rows.reduce((sum,row) => sum + Number(row.querySelector('select').value), 0), 15);
  controller.continuityTarget.value = 'independent';
  controller.refresh();
  assert.equal(controller.singleActionTarget.hidden, true);
});
