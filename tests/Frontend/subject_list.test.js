import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

function setup(limit, mode = 'image', count = 0) {
  const source = readFileSync(new URL('../../assets/controllers/subject_list_controller.js', import.meta.url), 'utf8').replace("import { Controller } from '@hotwired/stimulus';", '').replace('export default class', 'class Subjects');
  const Subjects = runInNewContext(`${source}\nSubjects`, { Controller: class {}, Event: class {} });
  const controller = new Subjects();
  controller.namespaceValue = 'ai_series';
  const model = { value: 'kling-3-image', dataset: { subjectLimit: String(limit), referenceMode: mode } };
  const fields = {};
  controller.form = { querySelector: selector => selector.includes('target="model"') ? { selectedOptions: [model] } : fields[selector], dispatchEvent() {} };
  const rows = Array.from({ length: count }, () => ({}));
  controller.element = {};
  controller.listTarget = { querySelectorAll: () => rows };
  controller.addTarget = { focus() {} }; controller.noticeTarget = {};
  return { controller, model, rows, fields };
}

test('series and studio identity limits reserve the main Vidu image and count Kling elements separately', () => {
  const { controller, model } = setup(7, 'subject', 6);
  controller.update();
  assert.equal(controller.addTarget.disabled, true);
  assert.match(controller.noticeTarget.textContent, /une image principale.*6 autre/);
  model.dataset.referenceMode = 'image'; model.dataset.subjectLimit = '2';
  controller.update();
  assert.match(controller.noticeTarget.textContent, /Retire les références incompatibles/);
});

test('changing to an unsupported model preserves existing subjects and lets the user remove them', () => {
  const { controller, model, rows } = setup(2, 'image', 1);
  controller.update(); assert.equal(controller.addTarget.disabled, false);
  model.dataset.subjectLimit = '0'; controller.update();
  assert.equal(controller.element.hidden, false);
  assert.equal(controller.addTarget.disabled, true);
  assert.match(controller.noticeTarget.textContent, /ne reçoit pas de sujet séparé/);
  controller.remove({ currentTarget: { closest: () => ({ remove: () => rows.splice(0, 1) }) } });
  assert.equal(controller.element.hidden, true);
});

test('a new series primary upload uses a form-only reference until it is stored', () => {
  const { controller, fields } = setup(7, 'subject');
  fields['[name="ai_series[image]"]'] = { files: [{}] };
  assert.equal(controller.primaryReference(), 'primary');
  fields['[name="ai_series[removeImage]"]'] = { checked: true };
  assert.equal(controller.primaryReference(), '');
});
