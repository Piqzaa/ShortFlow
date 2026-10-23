import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

function setup(responses) {
    let reloads = 0, calls = 0, timers = 0, cancelled = 0;
    const document = { hidden: false };
    const source = readFileSync(new URL('../../assets/controllers/episode_watch_controller.js', import.meta.url), 'utf8')
        .replace("import { Controller } from '@hotwired/stimulus';", '').replace('export default class', 'class Watch');
    const Watch = runInNewContext(`${source}\nWatch`, { Controller: class {}, AbortController, document,
        window: { location: { reload: () => reloads++ } }, setTimeout: () => ++timers, clearTimeout: () => cancelled++,
        fetch: async () => { const value = responses[calls++]; if (value instanceof Error) throw value; return { ok: true, json: async () => value }; }
    });
    const controller = new Watch();
    controller.revisionValue = 'initial'; controller.urlValue = '/etat'; controller.messageTarget = { textContent: '' }; controller.connect();
    return { controller, document, result: () => ({ reloads, calls, timers, cancelled }) };
}

test('episode watch reloads only on a new server revision and stops after disconnect', async () => {
    const state = setup([{ revision: 'initial' }, { revision: 'changed' }]);
    await state.controller.poll(); assert.equal(state.result().reloads, 0);
    await state.controller.poll(); assert.equal(state.result().reloads, 1);
    state.controller.disconnect(); await state.controller.poll(); assert.equal(state.result().calls, 2); assert.equal(state.result().cancelled, 1);
});
test('episode watch pauses in a hidden tab and offers manual refresh after repeated failures', async () => {
    const state = setup([new Error('offline'), new Error('offline'), new Error('offline')]);
    state.document.hidden = true; await state.controller.poll(); assert.equal(state.result().calls, 0);
    state.document.hidden = false;
    await state.controller.poll(); await state.controller.poll(); await state.controller.poll();
    assert.match(state.controller.messageTarget.textContent, /Actualise cette page/);
    assert.equal(state.result().calls, 3);
});
