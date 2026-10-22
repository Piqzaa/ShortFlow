import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

test('changing a series model only retains supported duration and ratio and explains image requirements', () => {
    const source = readFileSync(new URL('../../assets/controllers/series_settings_controller.js', import.meta.url), 'utf8')
        .replace("import { Controller } from '@hotwired/stimulus';", '').replace('export default class', 'class SeriesSettings');
    const Settings = runInNewContext(`${source}\nSeriesSettings`, { Controller: class {} });
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
    assert.equal(controller.imagesTarget.hidden, true);
    assert.equal(imageInput.disabled, true);
});
