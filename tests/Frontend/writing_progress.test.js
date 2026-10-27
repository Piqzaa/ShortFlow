import { test } from 'node:test';
import assert from 'node:assert/strict';
import { writingProgress } from '../../assets/writing-progress.js';

const timing = { createdAt: '2026-10-27T12:00:00Z', startedAt: '2026-10-27T12:11:00Z', observedAt: '2026-10-27T12:11:30Z' };

test('waiting is distinct from execution and has no completion percentage', () => {
  const state = writingProgress({ ...timing, status: 'queued', startedAt: null });
  assert.equal(state.stepLabel, 'Étape 1 sur 3');
  assert.match(state.detail, /Attente : 11 min 30 s/);
  assert.match(state.detail, /pas encore démarré/);
  assert.doesNotMatch(state.detail + state.label, /%|restantes|terminé dans/);
});

test('execution starts at the worker start rather than the creation time', () => {
  const state = writingProgress({ ...timing, status: 'shots' }, 5);
  assert.equal(state.stepLabel, 'Étape 3 sur 3');
  assert.match(state.detail, /^Traitement : 35 s/);
  assert.match(state.detail, /Attente avant démarrage : 11 min\./);
});

test('editorial waiting and writing use two workflow stages', () => {
  assert.equal(writingProgress({ ...timing, status: 'queued', kind: 'editorial' }).stepLabel, 'Étape 1 sur 2');
  const state = writingProgress({ ...timing, status: 'editorial' });
  assert.equal(state.stepLabel, 'Étape 2 sur 2');
  assert.match(state.label, /titres, légendes et hashtags/);
});

test('legacy or malformed timing does not invent an elapsed execution duration', () => {
  for (const extra of [{}, { observedAt: 'bad', startedAt: 'bad' }]) {
    const state = writingProgress({ status: 'story', createdAt: timing.createdAt, ...extra });
    assert.match(state.detail, /durée dépend/);
    assert.doesNotMatch(state.detail, /NaN|Infinity|11 min/);
  }
});

test('clock adjustments and invalid local increments cannot produce negative durations', () => {
  const job = { ...timing, status: 'story', observedAt: '2026-10-27T11:00:00Z' };
  for (const delta of [-5, NaN, Infinity]) {
    const state = writingProgress(job, delta);
    assert.match(state.detail, /^Traitement : 0 s/);
    assert.doesNotMatch(state.detail, /NaN|Infinity|-[0-9]/);
  }
});

test('terminal and unrecognized states are not presented as waiting', () => {
  for (const status of ['ready', 'failed', 'stale', 'unexpected', 'toString', 'constructor']) assert.equal(writingProgress({ status }), null);
  assert.equal(writingProgress(null), null);
});
