import { test } from 'node:test';
import assert from 'node:assert/strict';
import { fileURLToPath } from 'node:url';
import { build } from 'vite';

test('lazy preview chunks cannot import the application bootstrap under another URL', async () => {
  const result = await build({ configFile: fileURLToPath(new URL('../../vite.config.js', import.meta.url)),
    logLevel: 'silent', build: { write: false } });
  const chunks = (Array.isArray(result) ? result : [result]).flatMap(output => output.output)
    .filter(output => output.type === 'chunk');
  const entry = chunks.find(chunk => chunk.isEntry);
  assert.ok(entry, 'The build must contain its application entry.');
  assert.ok(chunks.some(chunk => chunk.isDynamicEntry), 'Check an actual split build, not a single bundle.');
  for (const chunk of chunks.filter(chunk => !chunk.isEntry)) {
    assert.ok(![...chunk.imports, ...chunk.dynamicImports].includes(entry.fileName),
      `${chunk.fileName} must not execute ${entry.fileName} a second time.`);
  }
});
