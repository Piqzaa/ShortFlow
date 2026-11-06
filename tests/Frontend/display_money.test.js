import {test} from 'node:test';
import assert from 'node:assert/strict';
import {displayMoney} from '../../assets/display-money.js';

test('EUR uses the inverse ECB rate and converts the USD total before rounding',()=>{
  const rate={date:'2026-11-06',usdPerEur:1.1165};
  assert.equal(displayMoney(41,2,rate),'0,37 €');
  assert.equal(displayMoney(62,2,rate),'0,56 €');
  assert.equal(displayMoney(82,2,rate),'0,73 €');
  assert.equal(displayMoney(123,2,rate),'1,10 €');
  assert.equal(displayMoney(.1,2,rate),'< 0,01 €');
  assert.equal(displayMoney(.1,3,rate),'< 0,001 €');
  assert.equal(displayMoney(0,2,rate),'0,00 €');
});

test('unavailable conversion preserves dollar amounts rather than implying parity',()=>{
  assert.equal(displayMoney(41,2,null),'0,41 $ US');
  assert.throws(()=>displayMoney(-1),/invalide/);
  assert.throws(()=>displayMoney(NaN),/invalide/);
});
