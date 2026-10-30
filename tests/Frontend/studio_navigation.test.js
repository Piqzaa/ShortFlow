import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

function setup() {
  const listeners = new Map();
  const browser = {
    location: { href: 'http://localhost/studio', origin: 'http://localhost', pathname: '/studio', search: '' },
    addEventListener: (name, callback) => listeners.set(name, callback),
    removeEventListener: (name, callback) => { if (listeners.get(name) === callback) listeners.delete(name); },
  };
  const source = readFileSync(new URL('../../assets/controllers/studio_navigation_controller.js', import.meta.url), 'utf8')
    .replace("import { Controller } from '@hotwired/stimulus';", '')
    .replace('export default class', 'class StudioNavigationController');
  const Navigation = runInNewContext(`${source}\nStudioNavigationController;`, { Controller: class {}, window: browser, URL });
  const controller = new Navigation();
  controller.statusTarget = { hidden: true, textContent: '' };
  return { controller, listeners, browser };
}

test('old episode fragment links open the containing page without redirect loops', () => {
  const { controller, browser } = setup();
  const redirects = [];
  browser.location.href = 'http://localhost/studio/series/9?page=2&occurrencePage=3#episode-123';
  browser.location.hash = '#episode-123';
  browser.location.replace = url => { redirects.push(url); browser.location.href = url; };
  controller.element = { querySelector: selector => selector === '[data-series-page]' ? {} : null };
  controller.findEpisode();
  assert.equal(redirects.length, 1);
  const destination = new URL(redirects[0]);
  assert.equal(destination.origin, 'http://localhost');
  assert.equal(destination.pathname, '/studio/series/9');
  assert.equal(destination.searchParams.get('episode'), '123');
  assert.equal(destination.searchParams.get('occurrencePage'), '3');
  controller.findEpisode();
  assert.equal(redirects.length, 1);
});

test('an existing target or a page without series pagination keeps native anchors', () => {
  const { controller, browser } = setup();
  browser.location.hash = '#episode-123';
  browser.location.replace = () => assert.fail('No redirect for an existing target or another screen.');
  controller.element = { querySelector: selector => ['[data-series-page]', '#episode-123'].includes(selector) ? {} : null };
  controller.findEpisode();
  controller.element = { querySelector: () => null };
  controller.findEpisode();
});

test('an old fragment cannot redirect away from a rejected human submission', () => {
  const { controller, browser } = setup();
  browser.location.href = 'http://localhost/studio/series/9#episode-1';
  browser.location.hash = '#episode-1';
  browser.location.replace = () => assert.fail('A rejected form must remain visible even with an older fragment.');
  controller.element = { querySelector: selector => ['[data-series-page]', '.legacy-episode-correction'].includes(selector) ? {} : null };
  controller.findEpisode();
});

test('a rejected recurrence form also blocks old-fragment redirects', () => {
  const { controller, browser } = setup();
  browser.location.href = 'http://localhost/studio/series/9#episode-25';
  browser.location.hash = '#episode-25';
  browser.location.replace = () => assert.fail('Submitted recurrence values must remain on the response.');
  controller.element = { querySelector: selector => ['[data-series-page]', '[data-series-navigation-blocked="true"]'].includes(selector) ? {} : null };
  controller.findEpisode();
});

test('modified clicks, external links and local anchors keep their native behavior', () => {
  const { controller } = setup();
  const click = (href, extra = {}) => ({
    button: 0, target: { closest: () => ({ href, target: '', hasAttribute: () => false }) }, ...extra,
  });
  for (const event of [
    click('http://localhost/studio/publications', { ctrlKey: true }),
    click('https://example.test/'),
    click('http://localhost/studio#contenu'),
    click('http://localhost/studio/publications', { defaultPrevented: true }),
  ]) {
    controller.navigate(event);
    assert.equal(controller.statusTarget.hidden, true);
  }
  controller.navigate(click('http://localhost/studio/bibliotheque#library-selection'));
  assert.equal(controller.statusTarget.hidden, false);
  assert.match(controller.statusTarget.textContent, /Chargement/);
});

test('an upload that already handles submit keeps its own progress feedback', () => {
  const { controller } = setup();
  controller.submit({ defaultPrevented: true });
  assert.equal(controller.statusTarget.hidden, true);
});

test('history restoration synchronizes checked videos with the counter and preparation button', () => {
  const listeners = new Map();
  const source = readFileSync(new URL('../../assets/controllers/selection_controller.js', import.meta.url), 'utf8')
    .replace("import { Controller } from '@hotwired/stimulus';", '')
    .replace('export default class', 'class SelectionController');
  const Selection = runInNewContext(`${source}\nSelectionController;`, {
    Controller: class {},
    window: { addEventListener: (name, callback) => listeners.set(name, callback), removeEventListener: name => listeners.delete(name) },
  });
  const controller = new Selection();
  controller.itemTargets = [{ checked: false }, { checked: false }];
  controller.countTarget = {};
  controller.submitTarget = {};
  controller.submitTargets = [controller.submitTarget, {}];
  controller.allTarget = {};
  controller.connect();
  assert.equal(controller.submitTarget.disabled, true);
  controller.itemTargets.forEach(input => { input.checked = true; });
  listeners.get('pageshow')();
  assert.equal(controller.countTarget.textContent, '2 vidéos sélectionnées');
  assert.equal(controller.submitTarget.disabled, false);
  assert.equal(controller.submitTargets[1].disabled, false);
  assert.equal(controller.allTarget.checked, true);
  controller.disconnect();
  assert.equal(listeners.size, 0);
});

test('repeated submit is blocked without disabling submitted fields; pageshow restores the form', () => {
  const { controller, listeners } = setup();
  const attributes = new Map();
  const form = { dataset: {}, setAttribute: (key, value) => attributes.set(key, value), removeAttribute: key => attributes.delete(key) };
  const classes = new Set();
  const buttonAttributes = new Map();
  const button = {
    disabled: false,
    classList: { add: name => classes.add(name), remove: name => classes.delete(name) },
    setAttribute: (key, value) => buttonAttributes.set(key, value), removeAttribute: key => buttonAttributes.delete(key),
  };
  controller.element = { querySelectorAll: selector => selector === '[data-studio-submitting]' ? (form.dataset.studioSubmitting ? [form] : []) : (classes.has('is-pending') ? [button] : []) };
  controller.connect();
  let prevented = 0;
  const event = { target: form, submitter: button, preventDefault: () => { prevented++; } };
  controller.submit(event);
  assert.equal(prevented, 0);
  assert.equal(button.disabled, false);
  assert.equal(attributes.get('aria-busy'), 'true');
  controller.submit(event);
  assert.equal(prevented, 1);
  listeners.get('pageshow')();
  assert.equal(controller.statusTarget.hidden, true);
  assert.equal(form.dataset.studioSubmitting, undefined);
  assert.equal(buttonAttributes.has('aria-disabled'), false);
  controller.submit(event);
  assert.equal(prevented, 1);
  controller.disconnect();
  assert.equal(listeners.size, 0);
});
