import { test } from 'node:test';
import assert from 'node:assert/strict';
import { proposePlan } from '../../assets/preparation-plan.js';

test('preparation proposes native plans without silently rounding the target', () => {
  assert.equal(proposePlan(37, [3,4,5,6,7,8,9,10,11,12,13,14,15]).parts.reduce((a,b) => a+b), 37);
  assert.deepEqual(proposePlan(30, [5,10,15]).parts, [15,15]);
  assert.deepEqual(proposePlan(32, [5,10]), {parts:null,nearby:[30,35]});
  assert.equal(proposePlan(60, [5]).parts, null);
  assert.equal(proposePlan(61, [5,10]).parts, null);
});
test('every proposed plan respects the target, native durations and eight-source limit', () => {
  for (const allowed of [[5], [4,6,8], [5,10], [5,10,15], [1,2,3,4,5,6,7,8,9,10,11,12,13,14,15]]) for (let target=1; target<=60; target++) {
    const {parts} = proposePlan(target, allowed);
    if (!parts) continue;
    assert.equal(parts.reduce((a,b) => a+b), target);
    assert.ok(parts.length <= 8);
    assert.ok(parts.every(s => allowed.includes(s)));
  }
});
