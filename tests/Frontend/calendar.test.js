import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

test('day picker preserves date and timezone; modified clicks keep the fallback link', () => {
  const source = readFileSync(new URL('../../assets/controllers/calendar_controller.js', import.meta.url), 'utf8')
    .replace("import { Controller } from '@hotwired/stimulus';", '').replace('export default class', 'class CalendarController');
  const Calendar = runInNewContext(`${source}\nCalendarController;`, { Controller: class {}, URL, clearTimeout });
  const controller = new Calendar();
  let opened = 0, prevented = 0;
  controller.dialogTarget = { showModal: () => opened++, close: () => opened-- };
  controller.hasDateTarget = true;
  controller.dateTarget = {};
  controller.labelTarget = {};
  controller.uploadTarget = { href: 'http://localhost/studio/bibliotheque/importer?next=compose&zone=America%2FToronto' };
  const event = { button: 0, currentTarget: { dataset: { date: '2026-10-20' } }, preventDefault: () => prevented++, stopPropagation() {} };
  controller.open({ ...event, ctrlKey: true });
  assert.equal(opened, 0); assert.equal(prevented, 0);
  controller.open(event);
  assert.equal(opened, 1); assert.equal(prevented, 1);
  assert.equal(controller.dateTarget.value, '2026-10-20');
  assert.equal(controller.labelTarget.textContent, 'Le 20/10/2026');
  const url = new URL(controller.uploadTarget.href);
  assert.equal(url.searchParams.get('zone'), 'America/Toronto');
  assert.equal(url.searchParams.get('date'), '2026-10-20');
  controller.close(); assert.equal(opened, 0);
});


test('calendar loads previews near the visible list and disconnects the observer', () => {
  const source = readFileSync(new URL('../../assets/controllers/calendar_controller.js', import.meta.url), 'utf8')
    .replace("import { Controller } from '@hotwired/stimulus';", '').replace('export default class', 'class CalendarController');
  let callback, options, disconnected = 0;
  const observed = [], removed = [];
  class Observer {
    constructor(fn, config) { callback = fn; options = config; }
    observe(video) { observed.push(video); }
    unobserve(video) { removed.push(video); }
    disconnect() { disconnected++; }
  }
  const Calendar = runInNewContext(`${source}\nCalendarController;`, { Controller: class {}, URL, clearTimeout, IntersectionObserver: Observer });
  const controller = new Calendar();
  controller.hasListTarget = true;
  controller.listTarget = {};
  controller.previewTargets = [{ preload: 'none' }, { preload: 'none' }];
  controller.loadPreviews();
  assert.equal(options.root, controller.listTarget);
  assert.equal(observed.length, 2);
  callback([{ target: observed[0], isIntersecting: true }, { target: observed[1], isIntersecting: false }]);
  assert.equal(observed[0].preload, 'metadata');
  assert.equal(observed[1].preload, 'none');
  assert.deepEqual(removed, [observed[0]]);
  controller.disconnect();
  assert.equal(disconnected, 1);
});


test('video search replaces stale choices, paginates and offers a retry on failure', async () => {
  const source = readFileSync(new URL('../../assets/controllers/calendar_controller.js', import.meta.url), 'utf8')
    .replace("import { Controller } from '@hotwired/stimulus';", '').replace('export default class', 'class CalendarController');
  let reply = { html: '<label>Found</label>', total: 13, nextPage: 2 }, fail = false, requested;
  const Calendar = runInNewContext(`${source}\nCalendarController;`, {
    Controller: class {}, URL, AbortController, clearTimeout,
    window: { location: { href: 'https://local.test/studio/calendrier' } },
    fetch: async url => {
      requested = url;
      if (fail) throw new Error('Offline');
      return { ok: true, json: async () => reply };
    },
  });
  const controller = new Calendar();
  controller.searchUrlValue = '/studio/calendrier/videos';
  controller.searchTarget = { value: ' Archive ' };
  controller.dialogTarget = { open: true };
  controller.listTarget = { innerHTML: 'stale', setAttribute() {}, removeAttribute() {}, insertAdjacentHTML(position, html) { this.innerHTML += html; } };
  controller.statusTarget = {};
  controller.submitTarget = {};
  controller.moreTarget = { dataset: {} };
  await controller.fetchVideos(1);
  assert.equal(requested.searchParams.get('q'), 'Archive');
  assert.equal(controller.listTarget.innerHTML, '<label>Found</label>');
  assert.equal(controller.moreTarget.dataset.page, 2);
  reply = { html: '<label>Last</label>', total: 13, nextPage: null };
  await controller.fetchVideos(2);
  assert.equal(controller.listTarget.innerHTML, '<label>Found</label><label>Last</label>');
  assert.equal(controller.moreTarget.hidden, true);
  fail = true;
  await controller.fetchVideos(1);
  assert.equal(controller.moreTarget.textContent, 'Réessayer');
  assert.equal(controller.submitTarget.disabled, true);
  assert.equal(controller.moreTarget.disabled, false);
});
