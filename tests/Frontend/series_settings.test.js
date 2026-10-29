import { GuidedModelPicker } from '../../assets/guided-model-picker.js';
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

test('changing a series model only retains supported duration and ratio and explains image requirements', () => {
    const source = readFileSync(new URL('../../assets/controllers/series_settings_controller.js', import.meta.url), 'utf8')
        .replace("import { Controller } from '@hotwired/stimulus';", '').replace("import { GuidedModelPicker } from '../guided-model-picker.js';", '').replace('export default class', 'class SeriesSettings');
    const Settings = runInNewContext(`${source}\nSeriesSettings`, { Controller: class {}, GuidedModelPicker });
    const controller = new Settings();
    controller.modelsValue = { image: { seconds: [6, 8], ratios: ['16:9'], image: true, requiresEnd: true }, text: { seconds: [5], ratios: ['9:16'], image: false } };
    controller.modelTarget = { value: 'image' };
    controller.secondsTarget = { value: '5', options: ['5', '6', '8'].map(value => ({ value })) };
    controller.ratioTarget = { value: '9:16', options: ['9:16', '16:9'].map(value => ({ value })) };
    controller.hintTarget = {};
    const imageInput = { disabled: false };
    controller.hasImagesTarget = true;
    controller.imagesTarget = { hidden: false, querySelectorAll: () => [imageInput] };
    controller.connect();
    assert.equal(controller.secondsTarget.value, '5'); // Preserve an invalid submitted value so the error remains understandable.
    controller.change();
    assert.equal(controller.secondsTarget.value, '6');
    assert.equal(controller.ratioTarget.value, '16:9');
    assert.equal(controller.secondsTarget.options[0].disabled, true);
    assert.match(controller.hintTarget.textContent, /Image de fin obligatoire/);
    assert.equal(controller.imagesTarget.hidden, false);
    controller.modelTarget.value = 'text';
    controller.change();
    assert.equal(controller.secondsTarget.value, '5');
    assert.equal(controller.secondsTarget.options[0].disabled, false);
    assert.match(controller.hintTarget.textContent, /n’utilise pas d’image/);
    assert.equal(controller.imagesTarget.hidden, false);
    assert.equal(imageInput.disabled, true);
});

test('requesting an image reference clears an incompatible text model without silently choosing a paid model', () => {
    const source = readFileSync(new URL('../../assets/controllers/series_settings_controller.js', import.meta.url), 'utf8').replace("import { Controller } from '@hotwired/stimulus';", '').replace("import { GuidedModelPicker } from '../guided-model-picker.js';", '').replace('export default class', 'class SeriesSettings');
    const Settings = runInNewContext(`${source}\nSeriesSettings`, { Controller: class {}, GuidedModelPicker });
    const controller = new Settings();
    controller.modelsValue = { text: {image:false}, image: {image:true} };
    controller.sourceTarget = {value:'image'};
    controller.modelTarget = {value:'text',options:[{value:''},{value:'text'},{value:'image'}],focus() {}};
    controller.hintTarget = {};
    controller.changeSource();
    assert.equal(controller.modelTarget.value, '');
    assert.equal(controller.modelTarget.options[1].disabled, true);
    assert.equal(controller.modelTarget.options[2].disabled, false);
    assert.match(controller.hintTarget.textContent, /explicitement/);
});

test('series exposes the same subject and frame capabilities and refuses unsupported legacy subject uploads', () => {
    const source = readFileSync(new URL('../../assets/controllers/series_settings_controller.js', import.meta.url), 'utf8').replace("import { Controller } from '@hotwired/stimulus';", '').replace("import { GuidedModelPicker } from '../guided-model-picker.js';", '').replace('export default class', 'class SeriesSettings');
    const Settings = runInNewContext(`${source}\nSeriesSettings`, { Controller: class {}, GuidedModelPicker });
    const controller = new Settings();
    const options = ['kling', 'wan'].map(value => ({value,dataset:{}}));
    controller.modelTarget = {value:'kling',options};
    controller.modelsValue = {kling:{image:true,end:true,subjectLimit:2,seconds:[5],ratios:['9:16']},wan:{image:true,seconds:[5],ratios:['9:16']}};
    controller.secondsTarget = {value:'5',options:[{value:'5'}]}; controller.ratioTarget = {value:'9:16',options:[{value:'9:16'}]}; controller.hintTarget = {};
    const subjectUpload = {name:'ai_series[subjectImages][]'}, firstFrame = {name:'ai_series[image]'};
    controller.hasImagesTarget = true; controller.imagesTarget = {querySelectorAll:()=>[subjectUpload,firstFrame]};
    controller.connect();
    assert.equal(options[0].dataset.subjectLimit, '2');
    assert.equal(options[0].dataset.referenceMode, 'image');
    assert.equal(options[0].dataset.acceptsEnd, 'true');
    assert.equal(subjectUpload.disabled, false);
    controller.modelTarget.value = 'wan'; controller.change();
    assert.equal(subjectUpload.disabled, true);
    assert.equal(firstFrame.disabled, false);
});

test('a pending private reference upload keeps the series form unsaved and blocks submission', () => {
    const source = readFileSync(new URL('../../assets/controllers/series_settings_controller.js', import.meta.url), 'utf8').replace("import { Controller } from '@hotwired/stimulus';", '').replace("import { GuidedModelPicker } from '../guided-model-picker.js';", '').replace('export default class', 'class SeriesSettings');
    const Settings = runInNewContext(`${source}\nSeriesSettings`, { Controller: class {}, GuidedModelPicker });
    const controller = new Settings();
    controller.element = {querySelector:()=>({})}; controller.hintTarget = {focus(){this.focused=true;}};
    let prevented = false, stopped = false;
    controller.guardPending({preventDefault(){prevented=true;},stopImmediatePropagation(){stopped=true;}});
    assert.equal(prevented, true); assert.equal(stopped, true); assert.equal(controller.hintTarget.focused, true);
    assert.match(controller.hintTarget.textContent, /fin de l’import/);
});
