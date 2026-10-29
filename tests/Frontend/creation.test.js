import { GuidedModelPicker } from '../../assets/guided-model-picker.js';
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

function creation() {
  const source = readFileSync(new URL('../../assets/controllers/creation_controller.js', import.meta.url), 'utf8')
    .replace("import { Controller } from '@hotwired/stimulus';", '').replace("import { GuidedModelPicker } from '../guided-model-picker.js';", '').replace('export default class', 'class CreationController');
  const Creation = runInNewContext(`${source}\nCreationController;`, { Controller: class {}, GuidedModelPicker, Intl, Event: class {} });
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

test('favorite stars follow every resolution without changing the selected model', () => {
  const controller = creation();
  controller.modelTarget.options = [
    {value:'kling',textContent:'Kling · 1080p'},
    {value:'kling-4k',textContent:'Kling · 4K'},
    {value:'wan',textContent:'Wan · 720p · à connecter'},
  ];
  const card = {dataset:{variants:JSON.stringify(['kling','kling-4k'])}};
  controller.markFavoriteOptions(card, true);
  controller.markFavoriteOptions(card, true);
  assert.equal(controller.modelTarget.options[0].textContent, '★ Kling · 1080p');
  assert.equal(controller.modelTarget.options[1].textContent, '★ Kling · 4K');
  assert.equal(controller.modelTarget.options[2].textContent, 'Wan · 720p · à connecter');
  assert.equal(controller.modelTarget.value, 'kling');
  controller.markFavoriteOptions(card, false);
  assert.equal(controller.modelTarget.options[0].textContent, 'Kling · 1080p');
  assert.equal(controller.modelTarget.options[1].textContent, 'Kling · 4K');
});

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

test('tiered prices use the exact duration quote and block missing prices', () => {
  const controller = creation();
  controller.modelsValue.kling.pricesCents = { 5: 45, 8: 90, 10: 99 };
  controller.durationTarget.value = '10'; controller.estimate();
  assert.equal(controller.costTarget.textContent, 'Estimation : 0,99 $ US');
  controller.modelsValue.kling.pricesCents = { 5: 45 }; controller.estimate();
  assert.match(controller.costTarget.textContent, /doit être vérifié/);
});

test('native thirty second models remain searchable and preserve their duration', () => {
  const controller = creation();
  controller.modelsValue.long.seconds = [4, 5, 10, 15, 20, 30];
  controller.modelsValue.long.rateMicros = 473000;
  controller.modelTarget.value = 'long'; controller.durationTarget.value = '30';
  controller.changeModel(); controller.minimumTarget.value = '30'; controller.filter();
  assert.equal(controller.durationTarget.value, '30');
  assert.equal(controller.costTarget.textContent, 'Estimation : 14,19 $ US');
  assert.deepEqual(controller.cardTargets.filter(c => !c.hidden).map(c => c.dataset.modelId), ['long']);
});

test('resolution filter and price sort compare the same duration with exact tiered prices', () => {
  const controller = creation();
  controller.hasQualityTarget = true; controller.hasSortTarget = true; controller.hasGridTarget = true;
  controller.qualityTarget = {value:'1080p'}; controller.sortTarget = {value:'price-asc'}; controller.comparisonTarget = {value:'10'};
  controller.modelsValue.kling.quality = '1080p'; controller.modelsValue.wan.quality = '4k'; controller.modelsValue.long.quality = '1080p';
  controller.modelsValue.long.pricesCents = {5:20,10:65};
  const ordered = [];
  controller.gridTarget = {append: card => ordered.push(card.dataset.modelId)};
  controller.cardTargets.forEach(card => { card.note = {}; card.querySelector = () => card.note; });
  controller.filter();
  assert.deepEqual(ordered, ['long','kling','wan']);
  assert.equal(controller.cardTargets.find(card => card.dataset.modelId === 'wan').hidden, true);
  assert.match(controller.cardTargets.find(card => card.dataset.modelId === 'long').note.textContent, /10 s : 0,65/);
});

test('missing access explains the chosen provider and never substitutes another access mode', () => {
  const controller = creation();
  controller.hasConnectionTarget = true; controller.connectionTarget = {};
  controller.connectionMessageTarget = {}; controller.connectionButtonTarget = {};
  controller.providersValue = {fal:'Fal',replicate:'Replicate'};
  controller.modelTarget.value = 'wan'; controller.estimate();
  assert.equal(controller.connectionTarget.hidden, false);
  assert.match(controller.connectionMessageTarget.textContent, /clé Replicate/);
  assert.equal(controller.connectionButtonTarget.textContent, 'Connecter Replicate');
  controller.modelTarget.value = 'kling'; controller.estimate();
  assert.equal(controller.connectionTarget.hidden, true);
  controller.connectedValue['fal-mcp'] = true; controller.connectedValue['fal-api'] = false;
  controller.hasAccessTarget = true; controller.accessTarget = {value:'api'};
  controller.estimate();
  assert.equal(controller.connectionTarget.hidden, false);
  controller.accessTarget.value = 'mcp'; controller.estimate();
  assert.equal(controller.connectionTarget.hidden, true);
});

test('economy badges follow the filtered results and the comparable duration', () => {
  const controller = creation();
  controller.hasComparisonTarget = true; controller.comparisonTarget = {value:'5'};
  controller.cardTargets.forEach(card => { card.badge = {}; card.querySelector = () => card.badge; });
  controller.filter();
  assert.equal(controller.cardTargets[0].badge.hidden, false);
  assert.equal(controller.cardTargets[1].badge.hidden, true);
  controller.providerTarget.value = 'replicate'; controller.filter();
  assert.equal(controller.cardTargets[0].badge.hidden, true);
  assert.equal(controller.cardTargets[1].badge.hidden, false);
  controller.comparisonTarget.value = '6'; controller.filter();
  assert.equal(controller.cardTargets[1].badge.hidden, true);
});

test('grouped cards retain every resolution and favorites combine with the quality filter', () => {
  const controller = creation();
  controller.modelsValue['kling-4k'] = {...controller.modelsValue.kling,quality:'4k',rateMicros:240000};
  const card = controller.cardTargets[0];
  card.dataset.variants = JSON.stringify(['kling','kling-4k']); card.dataset.favorite = 'true';
  controller.hasQualityTarget = true; controller.qualityTarget = {value:'4k'};
  controller.hasOnlyFavoritesTarget = true; controller.onlyFavoritesTarget = {checked:true};
  controller.filter();
  assert.equal(card.hidden, false);
  assert.equal(card.dataset.modelId, 'kling-4k');
  assert.equal(controller.cardTargets[1].hidden, true);
  assert.equal(controller.modelTarget.value, 'kling');
  card.dataset.favorite = 'false'; controller.filter();
  assert.equal(card.hidden, true);
});


test('guided picker keeps configuration IDs, constrains resolutions and clears incompatible durations', () => {
  const c = creation();
  c.hasPickerTarget = true;
  c.modelsValue['kling-4k'] = {...c.modelsValue.kling,quality:'4k'};
  c.cardTargets[0].dataset = {groupId:'kling',modelId:'kling',variants:JSON.stringify(['kling','kling-4k'])};
  c.cardTargets[1].dataset.groupId = 'wan';
  c.cardTargets[2].dataset.groupId = 'long';
  c.groupTarget = {value:''};
  c.resolutionTarget = {value:'',options:['','kling','kling-4k','wan','long'].map(value=>({value}))};
  c.modelTarget.value = 'kling-4k'; c.syncPicker();
  assert.equal(c.groupTarget.value, 'kling');
  assert.equal(c.resolutionTarget.value, 'kling-4k');
  assert.equal(c.resolutionTarget.options.find(o=>o.value==='wan').disabled, true);
  c.resolutionTarget.value = 'wan'; c.selectResolution();
  assert.equal(c.modelTarget.value, 'kling-4k');
  c.modelTarget.dispatchEvent = () => c.changeModel();
  c.groupTarget.value = 'wan'; c.durationTarget.value = '15'; c.selectGroup();
  assert.equal(c.modelTarget.value, 'wan');
  assert.equal(c.durationTarget.value, '');
  assert.equal(c.resolutionTarget.value, 'wan');
});

test('searching guided models preserves the current selection even when no names match', () => {
  const c = creation();
  c.pickerSearchTarget = {value:'introuvable'};
  c.pickerCountTarget = {};
  c.groupTarget = {options:[{value:''},{value:'kling',textContent:'★ Kling · Fal',selected:true},{value:'wan',textContent:'Wan · Replicate',selected:false}]};
  let stopped = 0, prevented = 0;
  c.searchPicker({stopPropagation(){stopped++;}});
  c.preventPickerSubmit({preventDefault(){prevented++;}});
  assert.equal(stopped, 1); assert.equal(prevented, 1);
  assert.equal(c.groupTarget.options[1].hidden, undefined);
  assert.equal(c.groupTarget.options[2].hidden, undefined);
  assert.match(c.pickerCountTarget.textContent, /sélection est conservée/);
  assert.equal(c.modelTarget.value, 'kling');
});

test('model search exposes clickable results and picking one changes the real configuration', () => {
  const c = creation();
  c.hasPickerResultsTarget = true; c.pickerResultsTarget = {};
  c.pickerSearchTarget = {value:'wan'}; c.pickerCountTarget = {};
  c.groupTarget = {options:[{value:'kling',textContent:'★ Kling · Fal'},{value:'wan',textContent:'Wan · Replicate'}]};
  c.pickerResultTargets = ['kling','wan'].map(groupId=>({dataset:{groupId}}));
  c.cardTargets[1].dataset.groupId = 'wan';
  c.resolutionTarget = {focus(){this.focused=true;}};
  c.searchPicker();
  assert.equal(c.pickerResultsTarget.hidden, false);
  assert.deepEqual(c.pickerResultTargets.map(b=>b.hidden), [true,false]);
  assert.match(c.pickerCountTarget.textContent, /clique sur un résultat/);
  c.modelTarget.dispatchEvent = () => {};
  c.pickResult({currentTarget:c.pickerResultTargets[1]});
  assert.equal(c.modelTarget.value, 'wan');
  assert.equal(c.pickerSearchTarget.value, '');
  assert.equal(c.pickerResultsTarget.hidden, true);
  assert.equal(c.resolutionTarget.focused, true);
});

test('changing source filters models and requires an explicit compatible model choice', () => {
  const c = creation();
  c.modelsValue.image = {...c.modelsValue.kling,image:true,ratios:['auto']};
  c.cardTargets.push({dataset:{modelId:'image',groupId:'image'},classList:{toggle(){}}});
  c.hasSourceTarget = true; c.sourceTarget = {value:'image'}; c.referencePanelTarget = {};
  c.ratioTarget.options.push({value:'auto'});
  c.modelTarget.dispatchEvent = () => c.changeModel();
  c.changeSource();
  assert.equal(c.modelTarget.value, '');
  c.modelTarget.value = 'image'; c.changeModel();
  assert.equal(c.modelTarget.value, 'image');
  assert.equal(c.ratioTarget.value, 'auto');
  assert.equal(c.referencePanelTarget.hidden, false);
  assert.deepEqual(c.cardTargets.filter(card=>!card.hidden).map(card=>card.dataset.modelId), ['image']);
  c.sourceTarget.value = 'text'; c.changeSource();
  assert.equal(c.modelTarget.value, '');
  assert.equal(c.referencePanelTarget.hidden, true);
  assert.equal(c.cardTargets.at(-1).hidden, true);
});

test('switching from end frames to identity references releases the obsolete end-frame filter', () => {
  const c = creation();
  c.modelsValue.subject = { ...c.modelsValue.kling, image: true, subjectReference: true, subjectLimit: 7 };
  c.cardTargets.push({ dataset: { modelId: 'subject', groupId: 'subject' }, classList: { toggle() {} } });
  c.hasSourceTarget = true; c.sourceTarget = { value: 'subject' }; c.referencePanelTarget = {};
  c.hasUseEndTarget = true; c.useEndTarget = { checked: true };
  c.endReferenceTarget = { value: 'saved-last-frame.jpg' };
  c.modelTarget.dispatchEvent = () => c.changeModel();
  c.changeSource();
  assert.equal(c.useEndTarget.checked, false);
  assert.equal(c.endReferenceTarget.value, 'saved-last-frame.jpg');
  assert.equal(c.modelTarget.value, '');
  assert.equal(c.cardTargets.at(-1).hidden, false);
});

test('two pending image uploads keep preparation disabled until both settle', () => {
  const c = creation(), button = {};
  c.hasEndReferenceTarget = true;
  c.referenceTarget = {value:'',dispatchEvent(){}}; c.endReferenceTarget = {value:'',dispatchEvent(){}};
  c.sourceTarget = {value:'image'};
  c.changeSource = () => {};
  c.element = {querySelectorAll:()=>[button]};
  c.referenceChanged({detail:{slot:'start',pending:true}});
  c.referenceChanged({detail:{slot:'end',pending:true}});
  c.referenceChanged({detail:{slot:'start',reference:'first.jpg',pending:false}});
  assert.equal(button.disabled, true);
  c.referenceChanged({detail:{slot:'end',reference:'last.jpg',pending:false}});
  assert.equal(button.disabled, false);
  assert.equal(c.referenceTarget.value, 'first.jpg');
  assert.equal(c.endReferenceTarget.value, 'last.jpg');
});

test('framing previews honor the selected ratio and fit without marking image loads as edits', () => {
  const c = creation(); let edits = 0;
  c.hasStartPreviewTarget = true; c.hasImageFitTarget = true; c.hasFramingNoticeTarget = true;
  c.startPreviewTarget = {naturalWidth:400,naturalHeight:600,hidden:false,style:{}};
  c.endPreviewTarget = {naturalWidth:640,naturalHeight:360,hidden:false,style:{}};
  c.startFrameTarget = {style:{}}; c.endFrameTarget = {style:{}}; c.framingNoticeTarget = {};
  c.referenceTarget = {dispatchEvent(){edits++;}};
  c.imageFitTarget = {value:'contain'}; c.ratioTarget.value = '16:9'; c.frameChanged();
  assert.equal(c.startFrameTarget.style.aspectRatio, '16 / 9');
  assert.equal(c.endFrameTarget.style.aspectRatio, '16 / 9');
  assert.equal(c.startPreviewTarget.style.objectFit, 'contain');
  assert.equal(edits, 0);
  c.ratioTarget.value = 'auto'; c.frameChanged({type:'change'});
  assert.equal(c.endFrameTarget.style.aspectRatio, '400 / 600');
  assert.equal(edits, 1);
  assert.match(c.framingNoticeTarget.textContent, /Format d’origine/);
});

test('choosing a transition enables the ending frame; unchecking requires an explicit replacement', () => {
  const c = creation();
  c.modelsValue.image = {...c.modelsValue.kling,image:true,endImageField:'tail_image_url'};
  c.modelsValue.transition = {...c.modelsValue.image,requiresEnd:true};
  c.hasSourceTarget = true; c.sourceTarget = {value:'image'}; c.referencePanelTarget = {};
  c.hasUseEndTarget = true; c.useEndTarget = {checked:false};
  c.hasEndPanelTarget = true; c.endPanelTarget = {};
  c.modelTarget.value = 'transition'; c.modelTarget.dispatchEvent = () => c.changeModel();
  c.changeModel();
  assert.equal(c.useEndTarget.checked, true);
  assert.equal(c.endPanelTarget.hidden, false);
  c.useEndTarget.checked = false; c.changeEnd();
  assert.equal(c.useEndTarget.checked, false);
  assert.equal(c.modelTarget.value, '');
  assert.equal(c.endPanelTarget.hidden, true);
});

test('subject mode hides the whole ending option and preserves its stored image for later', () => {
  const c = creation();
  c.modelsValue.subject = {...c.modelsValue.kling, image:true, subjectReference:true};
  c.modelsValue.image = {...c.modelsValue.kling, image:true, endImageField:'end_image_url'};
  Object.assign(c, {hasSourceTarget:true,sourceTarget:{value:'subject'},hasUseEndTarget:true,useEndTarget:{checked:true},hasEndOptionTarget:true,endOptionTarget:{},hasReferencePanelTarget:true,referencePanelTarget:{querySelector:()=>null},hasEndPanelTarget:true,endPanelTarget:{},hasImageSettingsTarget:true,imageSettingsTarget:{},endReferenceTarget:{value:'stored-end.jpg'}});
  c.modelTarget.value='subject'; c.modelTarget.dispatchEvent=()=>{};
  c.changeSource(); c.changeModel();
  assert.equal(c.referencePanelTarget.hidden,false);
  assert.equal(c.endOptionTarget.hidden,true);
  assert.equal(c.endPanelTarget.hidden,true);
  assert.equal(c.useEndTarget.checked,false);
  assert.equal(c.useEndTarget.disabled,true);
  assert.equal(c.imageSettingsTarget.hidden,true);
  assert.equal(c.endReferenceTarget.value,'stored-end.jpg');
  c.sourceTarget.value='image'; c.modelTarget.value='image';
  c.changeSource(); c.changeModel();
  assert.equal(c.endOptionTarget.hidden,false);
  assert.equal(c.useEndTarget.disabled,false);
  c.useEndTarget.checked=true; c.changeModel();
  assert.equal(c.endPanelTarget.hidden,false);
  c.sourceTarget.value='text'; c.changeSource(); c.changeModel();
  assert.equal(c.endOptionTarget.hidden,true);
  assert.equal(c.endReferenceTarget.value,'stored-end.jpg');
});

test('image fees are included in the estimate and price ordering', () => {
  const c = creation();
  assert.equal(c.price({seconds:[5],rateMicros:70000,fixedMicros:2000},5),36);
  assert.equal(c.price({seconds:[5],rateMicros:250000,fixedMicros:10000},5),126);
});


test('recommendations follow image/end-frame capabilities and keep the chosen account mode', () => {
  const c = creation();
  c.modelsValue.image = {...c.modelsValue.kling, name:'Kling Image', image:true, endImageField:'tail_image_url'};
  c.modelsValue.frames = {...c.modelsValue.kling, name:'Pika Frames', image:true, requiresEnd:true, endImageField:'image_urls'};
  const button = {dataset:{textModel:'kling', imageModel:'image', framesModel:'frames'},attrs:{},setAttribute(k,v){this.attrs[k]=v;},querySelector(selector){return selector === '[data-recommendation-access]' ? (this.access ||= {}) : (this.label ||= {});}};
  Object.assign(c,{hasRecommendationsTarget:true,recommendationsTarget:{hidden:true},recommendationTargets:[button],selectedModelTarget:{},hasSourceTarget:true,sourceTarget:{value:'text'},hasUseEndTarget:true,useEndTarget:{checked:false},accessTarget:{value:'mcp'}});
  c.updateRecommendations(); assert.equal(button.dataset.modelId, 'kling'); assert.equal(button.attrs['aria-pressed'], 'true');
  c.sourceTarget.value='image'; c.useEndTarget.checked=true; c.modelTarget.value='frames';
  c.updateRecommendations(); assert.equal(button.dataset.modelId, 'frames'); assert.equal(button.label.textContent, 'Pika Frames');
  assert.equal(button.attrs['aria-pressed'], 'true'); assert.equal(c.accessTarget.value, 'mcp');
  assert.match(c.selectedModelTarget.textContent, /Pika Frames/);
  c.useEndTarget.checked=false; c.updateRecommendations(); assert.equal(button.dataset.modelId, 'image');
});
