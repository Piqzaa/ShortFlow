import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import { proposePlan } from '../../assets/preparation-plan.js';

function setup(count = 3) {
  const source = readFileSync(new URL('../../assets/controllers/preparation_controller.js', import.meta.url), 'utf8')
    .replace("import { Controller } from '@hotwired/stimulus';", '')
    .replace("import { proposePlan } from '../preparation-plan.js';", '')
    .replace('export default class', 'class Preparation');
  const Preparation = runInNewContext(`${source}\nPreparation`, { Controller: class {}, proposePlan, Event });
  const controller = new Preparation();
  const rows = Array.from({ length: count }, (_, i) => {
    const duration = { value: '5', options: [{ value: '5' }] }, prompt = { value: `Scene ${i + 1}.` };
    return { querySelector(selector) { return selector === 'select' ? duration : selector === 'textarea' ? prompt : null; } };
  });
  Object.defineProperty(controller, 'editor', { value: { rows: () => rows, update() {} } });
  controller.selectedModel = { name: 'Kling 3', seconds: Array.from({length:13},(_,i)=>i+3) };
  Object.defineProperty(controller, 'model', { get() { return this.selectedModel; } });
  controller.element = { querySelector: selector => selector.includes('data-plan-reference-busy') ? null : ({ value: '' }) };
  controller.continuityPanelTarget = {};
  controller.singleTarget = {};
  controller.fixContinuityTarget = {};
  controller.continuityTarget = { value: 'independent', querySelector: () => ({}) };
  controller.continuityHelpTarget = {};
  controller.hasSingleActionTarget = true;
  controller.singleActionTarget = {};
  controller.singleStatusTarget = {};
  controller.singleButtonTarget = { focus() { this.focused = true; } };
  controller.useTotalTarget = {};
  controller.hasRecapTarget = true;
  controller.recapTarget = {};
  controller.totalTarget = { value: '15', dispatchEvent(event) { controller.duration(); this.changed = event.type; } };
  controller.totalTarget.options = Array.from({length:60},(_,i)=>({value:String(i+1),get selected(){return controller.totalTarget.value === this.value;}}));
  controller.noticeTarget = {};
  return { controller, rows };
}

test('choosing single exposes conversion and native duration choices without changing scenes', () => {
  const {controller,rows} = setup();
  controller.refresh();
  assert.equal(controller.singleActionTarget.hidden, true);
  controller.continuityTarget.value = 'single';
  controller.refresh();
  assert.equal(controller.singleActionTarget.hidden, false);
  assert.match(controller.singleStatusTarget.textContent, /3 plan\(s\) actuellement/);
  assert.match(controller.continuityHelpTarget.textContent, /pas encore réunies/);
  assert.equal(controller.totalTarget.options.find(o=>o.value==='16').disabled, true);
  assert.equal(controller.totalTarget.options.find(o=>o.value==='15').disabled, false);
  assert.equal(controller.singleButtonTarget.disabled, false);
  assert.equal(controller.recapTarget.disabled, true);
  assert.equal(rows.reduce((sum,row)=>sum+Number(row.querySelector('select').value),0),15);
  controller.continuityTarget.value = 'independent';
  controller.refresh();
  assert.equal(controller.totalTarget.options.find(o=>o.value==='60').disabled,false);
  assert.equal(controller.recapTarget.disabled,false);
});

test('switching to a shorter model preserves the old duration and scenes, blocks conversion and explains the limit', () => {
  const {controller,rows} = setup();
  controller.continuityTarget.value = 'single';
  controller.selectedModel = {name:'Kling 2.5',seconds:[5,10]};
  controller.refresh();
  assert.equal(controller.totalTarget.value,'15');
  assert.match(controller.totalTarget.options.find(o=>o.value==='15').textContent,/à ajuster/);
  assert.equal(controller.totalTarget.options.find(o=>o.value==='12').disabled,true);
  assert.equal(controller.totalTarget.options.find(o=>o.value==='10').disabled,false);
  assert.equal(controller.singleButtonTarget.disabled,true);
  assert.match(controller.singleStatusTarget.textContent,/5 ou 10 s/);
  assert.match(controller.noticeTarget.textContent,/maximum 10 s/);
  assert.equal(rows.length,3);
  assert.equal(rows[2].querySelector('textarea').value,'Scene 3.');
});

test('using the supported total fixes the desired duration without rewriting multiple plans', () => {
  const {controller,rows} = setup();
  controller.continuityTarget.value='single';
  controller.totalTarget.value='10';
  controller.refresh();
  assert.equal(controller.singleButtonTarget.disabled,true);
  assert.equal(controller.useTotalTarget.hidden,false);
  controller.usePlanTotal({preventDefault(){}});
  assert.equal(controller.totalTarget.value,'15');
  assert.equal(controller.totalTarget.changed,'change');
  assert.equal(controller.singleButtonTarget.disabled,false);
  assert.equal(controller.singleButtonTarget.focused,true);
  assert.equal(rows.length,3);
  assert.equal(rows[0].querySelector('select').value,'5');
});

test('explicit final duration changes update the existing single clip without losing its text', () => {
  const {controller,rows}=setup(1);
  controller.continuityTarget.value='single';
  controller.totalTarget.value='10';
  controller.duration();
  assert.equal(rows[0].querySelector('select').value,'10');
  assert.equal(rows[0].querySelector('textarea').value,'Scene 1.');
  assert.equal(controller.recapTarget.disabled,false);
  controller.totalTarget.value='30';
  controller.duration();
  assert.equal(rows[0].querySelector('select').value,'10');
  assert.equal(controller.singleButtonTarget.disabled,true);
});

test('count guidance keeps the precise loss warning when a duration change cannot remove written scenes', () => {
  const {controller}=setup();
  controller.hasPlanCountTarget=true;
  controller.planCountTarget={value:'2',options:[]};
  controller.countStatusTarget={};
  controller.hasCountApplyTarget=true;
  controller.countApplyTarget={};
  controller.refresh();
  assert.match(controller.countStatusTarget.textContent,/plans déjà renseignés/);
  assert.equal(controller.countApplyTarget.disabled,true);
  controller.duration();
  assert.match(controller.noticeTarget.textContent,/plan déjà renseigné/);
  controller.refresh();
  assert.match(controller.countStatusTarget.textContent,/plans déjà renseignés/);
  assert.equal(controller.prepareStory(),false);
});

test('a contain framing choice alone prevents deleting a plan', () => {
  const {controller,rows}=setup();
  controller.hasPlanCountTarget=true;
  controller.planCountTarget={value:'2',options:[]};
  controller.countStatusTarget={};
  for (const row of rows) row.querySelector('textarea').value='';
  const original=rows[2].querySelector;
  rows[2].querySelector=selector=>selector.includes('[referenceFit]')?{value:'contain'}:original(selector);
  assert.equal(controller.redistribute(),false);
  assert.match(controller.noticeTarget.textContent,/plan déjà renseigné/);
});
