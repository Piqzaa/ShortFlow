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

test('the empty studio shows one model hint and an import refusal always becomes visible', () => {
  const {controller}=setup(0);
  controller.selectedModel=null;
  controller.hasCountInfoTarget=true; controller.countInfoTarget={};
  controller.hasPlanCountTarget=true; controller.planCountTarget={value:'',options:[]};
  controller.hasCountPanelTarget=true; controller.countPanelTarget={};
  controller.countStatusTarget={}; controller.hasCountApplyTarget=true; controller.countApplyTarget={};
  controller.refresh();
  assert.equal(controller.noticeTarget.hidden,false);
  assert.equal(controller.noticeTarget.textContent,'Choisis un modèle pour préparer tes plans.');
  assert.equal(controller.singleTarget.hidden,true);
  assert.equal(controller.singleActionTarget.hidden,true);
  assert.equal(controller.countInfoTarget.hidden,true);
  assert.equal(controller.countApplyTarget.hidden,true);
  controller.noticeTarget.hidden=true;
  controller.element.querySelector=()=>({dataset:{planReferenceBusy:'true'}});
  let prevented=false;
  controller.guardPending({preventDefault(){prevented=true;}});
  assert.equal(prevented,true);
  assert.equal(controller.noticeTarget.hidden,false);
  assert.match(controller.noticeTarget.textContent,/fin de l’import/);
});

test('pending single conversion keeps the original cards until the server accepts them', () => {
  const {controller,rows} = setup();
  controller.refresh();
  assert.equal(controller.singleActionTarget.hidden, true);
  controller.continuityTarget.value = 'single';
  controller.refresh();
  assert.equal(controller.singleActionTarget.hidden, false);
  assert.match(controller.singleStatusTarget.textContent, /Passage au plan unique à terminer/);
  assert.match(controller.continuityHelpTarget.textContent, /cartes restent conservées/);
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

test('choosing a frame bridge explains its image requirement without changing a text or subject source', () => {
  for (const mode of ['text','subject']) {
    const {controller}=setup();
    const source={value:mode};
    controller.selectedModel={name:'Chosen model',seconds:[5,10,15],image:mode==='subject',subjectReference:mode==='subject'};
    controller.element.querySelector=selector=>selector.includes('data-plan-reference-busy')?null:selector.includes('data-creation-target="source"')?source:{value:''};
    controller.continuityTarget.value='linked';
    controller.change({target:{matches:selector=>selector==='[data-preparation-target="continuity"]'}});
    assert.equal(source.value,mode);
    assert.equal(controller.continuityHelpTarget.hidden,false);
    assert.match(controller.continuityHelpTarget.textContent,/exige un modèle image et un premier cadre/);
    assert.match(controller.noticeTarget.textContent,/modèle image compatible/);
  }
});

test('choosing single submits only the existing safe conversion intent with all three cards intact', () => {
  const {controller,rows}=setup();
  let submitted;
  controller.singleButtonTarget.form={requestSubmit(button){submitted=button;}};
  controller.continuityTarget.value='single';
  controller.change({target:{matches(selector){return selector==='[data-preparation-target="continuity"]';}}});
  assert.equal(submitted,controller.singleButtonTarget);
  assert.equal(rows.length,3);
  assert.equal(rows[2].querySelector('textarea').value,'Scene 3.');
  submitted=null;
  controller.selectedModel={name:'Kling 2.5',seconds:[5,10]};
  controller.change({target:{matches(selector){return selector==='[data-preparation-target="continuity"]';}}});
  assert.equal(submitted,null);
  assert.match(controller.singleStatusTarget.textContent,/5 ou 10 s/);
});

test('a duration correction retries conversion, while active image imports and completed single cards never submit', () => {
  const {controller}=setup();
  let count=0;
  controller.singleButtonTarget.form={requestSubmit(){count++;}};
  controller.continuityTarget.value='single';
  controller.totalTarget.value='15';
  controller.duration();
  assert.equal(count,1);
  controller.element.querySelector=selector=>selector.includes('data-plan-reference-busy')?{}:{value:''};
  controller.duration();
  assert.equal(count,1);
  assert.match(controller.noticeTarget.textContent,/fin de l’import/);
  const ready=setup(1).controller;
  ready.singleButtonTarget.form={requestSubmit(){count++;}};
  ready.continuityTarget.value='single'; ready.totalTarget.value='5';
  ready.duration();
  assert.equal(count,1);
  assert.equal(ready.singleButtonTarget.hidden,true);
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
